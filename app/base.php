<?php
declare(strict_types=1);

/* La base de données : SQLite par défaut (un fichier dans le dossier de données), MySQL si config.local.php le dit.
   Les dates sont des nombres (secondes depuis 1970) : ça marche pareil partout. */

function base(): PDO
{
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $dsn = (string) reglage('base.dsn', '');
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    if ($dsn === '') {
        $fichier = dossier_donnees() . '/academy.sqlite';
        $nouveau = !is_file($fichier);
        $pdo = new PDO('sqlite:' . $fichier, null, null, $options);
        if ($nouveau) @chmod($fichier, 0600);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
    } else {
        $pdo = new PDO($dsn, (string) reglage('base.utilisateur', ''), (string) reglage('base.mot_de_passe', ''), $options);
    }
    migrer($pdo);
    return $pdo;
}

function pilote(): string
{
    return (string) base()->getAttribute(PDO::ATTR_DRIVER_NAME);
}

/** Une requête préparée : requete('SELECT … WHERE id = ?', [$id]) */
function requete(string $sql, array $params = []): PDOStatement
{
    $r = base()->prepare($sql);
    $r->execute($params);
    return $r;
}

function ligne(string $sql, array $params = []): ?array
{
    $l = requete($sql, $params)->fetch();
    return $l === false ? null : $l;
}

function valeur(string $sql, array $params = [])
{
    $v = requete($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/* Un bloc « tout ou rien ». Avec SQLite, le bloc réserve l'écriture dès le début : deux inscriptions en même temps passent l'une après l'autre. */
function bloc_debut(): void
{
    if (pilote() === 'sqlite') base()->exec('BEGIN IMMEDIATE'); else base()->beginTransaction();
}

function bloc_fin(): void
{
    if (pilote() === 'sqlite') base()->exec('COMMIT'); else base()->commit();
}

function bloc_annuler(): void
{
    try {
        if (pilote() === 'sqlite') base()->exec('ROLLBACK');
        elseif (base()->inTransaction()) base()->rollBack();
    } catch (Throwable $e) { /* déjà annulé */ }
}

/* ---------- le schéma ---------- */

function version_schema(PDO $pdo): int
{
    try {
        $v = $pdo->query("SELECT valeur FROM reglages WHERE cle = 'schema'")->fetchColumn();
        return $v === false ? 0 : (int) $v;
    } catch (PDOException $e) {
        return 0;   // la table n'existe pas encore
    }
}

function migrer(PDO $pdo): void
{
    $pilote = (string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    $migrations = migrations($pilote);
    if (version_schema($pdo) >= count($migrations)) return;

    // Une seule mise à jour à la fois : SQLite la fait en un bloc (tout ou rien).
    $bloc = $pilote === 'sqlite';
    if ($bloc) $pdo->exec('BEGIN IMMEDIATE');
    try {
        $v = version_schema($pdo);
        foreach ($migrations as $n => $instructions) {
            if ($n < $v) continue;
            foreach ($instructions as $sql) $pdo->exec($sql);
            $pdo->prepare("UPDATE reglages SET valeur = ? WHERE cle = 'schema'")->execute([(string) ($n + 1)]);
        }
        if ($bloc) $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        if ($bloc) $pdo->exec('ROLLBACK');
        throw $e;
    }
}

function migrations(string $pilote): array
{
    $mysql = $pilote === 'mysql';
    $id = $mysql ? 'INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $ref = $mysql ? 'INT UNSIGNED' : 'INTEGER';
    $date = $mysql ? 'BIGINT' : 'INTEGER';
    $court = $mysql ? 'VARCHAR(190)' : 'TEXT';
    $fin = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci' : '';
    $ignore = $mysql ? 'INSERT IGNORE' : 'INSERT OR IGNORE';
    // un index : dans la table pour MySQL, à part pour SQLite
    $dans = static fn(string $nom, string $cols): string => $mysql ? ", KEY $nom ($cols)" : '';
    $apart = static fn(string $nom, string $table, string $cols): array => $mysql ? [] : ["CREATE INDEX IF NOT EXISTS $nom ON $table ($cols)"];

    return [
        // 1 : comptes, sessions, liens (invitation, confirmation de l'adresse, nouveau mot de passe), tentatives
        array_merge([
            "CREATE TABLE IF NOT EXISTS reglages (cle $court NOT NULL PRIMARY KEY, valeur TEXT NOT NULL)$fin",
            "$ignore INTO reglages (cle, valeur) VALUES ('schema', '0')",
            "CREATE TABLE IF NOT EXISTS comptes (
                id $id,
                email $court NOT NULL UNIQUE,
                prenom $court NOT NULL,
                discord $court NULL,
                hachage $court NOT NULL,
                role $court NOT NULL,
                confirme_le $date NULL,
                cree_le $date NOT NULL,
                vu_le $date NULL
            )$fin",
            "CREATE TABLE IF NOT EXISTS sessions (
                id $id,
                compte_id $ref NOT NULL,
                jeton $court NOT NULL UNIQUE,
                csrf $court NOT NULL,
                cree_le $date NOT NULL,
                vu_le $date NOT NULL,
                expire_le $date NOT NULL" . $dans('sessions_compte', 'compte_id') . "
            )$fin",
            "CREATE TABLE IF NOT EXISTS liens (
                id $id,
                type $court NOT NULL,
                jeton $court NOT NULL UNIQUE,
                compte_id $ref NULL,
                role $court NULL,
                note $court NULL,
                cree_par $ref NULL,
                cree_le $date NOT NULL,
                expire_le $date NOT NULL,
                utilise_le $date NULL,
                utilise_par $ref NULL" . $dans('liens_type', 'type, compte_id') . "
            )$fin",
            "CREATE TABLE IF NOT EXISTS tentatives (
                id $id,
                cle $court NOT NULL,
                quand $date NOT NULL" . $dans('tentatives_cle', 'cle, quand') . "
            )$fin",
        ], $apart('sessions_compte', 'sessions', 'compte_id'), $apart('liens_type', 'liens', 'type, compte_id'), $apart('tentatives_cle', 'tentatives', 'cle, quand')),
        // 2 : la liste d'attente (inscription confirmée par e-mail, désinscription en un clic)
        array_merge([
            "CREATE TABLE IF NOT EXISTS liste_attente (
                id $id,
                email $court NOT NULL UNIQUE,
                prenom $court NOT NULL,
                parcours $court NOT NULL,
                statut $court NOT NULL,
                jeton $court NULL,
                jeton_expire $date NULL,
                cree_le $date NOT NULL,
                accord_le $date NOT NULL,
                confirme_le $date NULL,
                desinscrit_le $date NULL" . $dans('liste_statut', 'statut') . "
            )$fin",
        ], $apart('liste_statut', 'liste_attente', 'statut')),
        // 3 : la formation (parcours, modules, leçons, quiz, cohortes, inscriptions, progression, lives)
        array_merge([
            "CREATE TABLE IF NOT EXISTS parcours (
                id $id,
                cle $court NOT NULL UNIQUE,
                nom $court NOT NULL,
                formateur $court NOT NULL,
                ordre $ref NOT NULL
            )$fin",
            "$ignore INTO parcours (cle, nom, formateur, ordre) VALUES ('of', 'Order flow', 'Nojah', 1)",
            "$ignore INTO parcours (cle, nom, formateur, ordre) VALUES ('ict', 'ICT', 'Weizix', 2)",
            "CREATE TABLE IF NOT EXISTS modules (
                id $id,
                parcours_id $ref NOT NULL,
                numero $ref NOT NULL,
                titre $court NOT NULL,
                resume TEXT NULL,
                essai $ref NOT NULL DEFAULT 0,
                cree_le $date NOT NULL" . $dans('modules_parcours', 'parcours_id, numero') . "
            )$fin",
            "CREATE TABLE IF NOT EXISTS lecons (
                id $id,
                module_id $ref NOT NULL,
                numero $ref NOT NULL,
                titre $court NOT NULL,
                texte TEXT NULL,
                video $court NULL,
                duree $ref NULL,
                cree_le $date NOT NULL" . $dans('lecons_module', 'module_id, numero') . "
            )$fin",
            "CREATE TABLE IF NOT EXISTS questions (
                id $id,
                module_id $ref NOT NULL,
                numero $ref NOT NULL,
                enonce TEXT NOT NULL,
                choix TEXT NOT NULL,
                bonne $ref NOT NULL,
                explication TEXT NULL" . $dans('questions_module', 'module_id, numero') . "
            )$fin",
            "CREATE TABLE IF NOT EXISTS cohortes (
                id $id,
                parcours_id $ref NOT NULL,
                nom $court NOT NULL,
                debut $date NOT NULL,
                places $ref NOT NULL,
                discord $court NULL,
                essai $ref NOT NULL DEFAULT 0,
                cree_le $date NOT NULL
            )$fin",
            "CREATE TABLE IF NOT EXISTS inscriptions (
                id $id,
                compte_id $ref NOT NULL,
                cohorte_id $ref NOT NULL,
                cree_le $date NOT NULL,
                UNIQUE (compte_id, cohorte_id)
            )$fin",
            "CREATE TABLE IF NOT EXISTS vues (
                id $id,
                compte_id $ref NOT NULL,
                lecon_id $ref NOT NULL,
                vue_le $date NOT NULL,
                UNIQUE (compte_id, lecon_id)
            )$fin",
            "CREATE TABLE IF NOT EXISTS quiz (
                id $id,
                compte_id $ref NOT NULL,
                module_id $ref NOT NULL,
                note $ref NOT NULL,
                total $ref NOT NULL,
                reponses TEXT NOT NULL,
                fait_le $date NOT NULL" . $dans('quiz_compte', 'compte_id, module_id') . "
            )$fin",
            "CREATE TABLE IF NOT EXISTS lives (
                id $id,
                cohorte_id $ref NOT NULL,
                module_id $ref NULL,
                debut $date NOT NULL,
                duree $ref NOT NULL,
                titre $court NOT NULL,
                lien $court NULL,
                replay $court NULL,
                replay_duree $ref NULL,
                cree_le $date NOT NULL" . $dans('lives_cohorte', 'cohorte_id, debut') . "
            )$fin",
        ], $apart('modules_parcours', 'modules', 'parcours_id, numero'), $apart('lecons_module', 'lecons', 'module_id, numero'),
           $apart('questions_module', 'questions', 'module_id, numero'), $apart('quiz_compte', 'quiz', 'compte_id, module_id'),
           $apart('lives_cohorte', 'lives', 'cohorte_id, debut')),
    ];
}

/** Un grain de sel propre à ce site, créé au premier besoin : il sert à ne pas garder d'adresse IP ni d'e-mail en clair dans les tentatives. */
function sel(): string
{
    static $s = null;
    if ($s !== null) return $s;
    $s = valeur("SELECT valeur FROM reglages WHERE cle = 'sel'");
    if ($s === null) {
        $s = bin2hex(random_bytes(32));
        try {
            requete("INSERT INTO reglages (cle, valeur) VALUES ('sel', ?)", [$s]);
        } catch (PDOException $e) {
            $s = (string) valeur("SELECT valeur FROM reglages WHERE cle = 'sel'");   // un autre visiteur vient de le créer
        }
    }
    return $s;
}
