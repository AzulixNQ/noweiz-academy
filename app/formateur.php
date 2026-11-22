<?php
declare(strict_types=1);

/* L'espace formateur : déposer des modules, des leçons (avec leur vidéo), des quiz, programmer des lives, ajouter les replays,
   créer des cohortes et suivre les élèves.

   Qui fait quoi :
   - l'administrateur gère tous les parcours, crée les cohortes et choisit l'équipe de chaque parcours ;
   - un formateur gère les parcours où l'administrateur l'a mis (table equipe) : modules, leçons, quiz, lives, élèves.

   Les vidéos arrivent par morceaux (PHP refuse les gros envois d'un coup) : le navigateur découpe le fichier,
   le serveur recolle les morceaux dans <données>/envois, vérifie que c'est bien une vidéo MP4, puis la range
   dans <données>/videos sous un nom tiré au hasard. */

/* ---------- l'équipe et les droits ---------- */

function parcours_tous(): array
{
    return lignes('SELECT * FROM parcours ORDER BY ordre');
}

function parcours_par_id(int $id): ?array
{
    return ligne('SELECT * FROM parcours WHERE id = ?', [$id]);
}

/** Les parcours qu'un compte peut gérer. */
function parcours_geres(array $compte): array
{
    if ($compte['role'] === 'admin') return parcours_tous();
    if ($compte['role'] !== 'formateur') return [];
    return lignes('SELECT p.* FROM parcours p JOIN equipe e ON e.parcours_id = p.id WHERE e.compte_id = ? ORDER BY p.ordre', [$compte['id']]);
}

function peut_gerer(array $compte, int $parcours_id): bool
{
    foreach (parcours_geres($compte) as $p) if ((int) $p['id'] === $parcours_id) return true;
    return false;
}

/** Réservé à l'administrateur et aux formateurs ; renvoie le compte. */
function exiger_formateur(): array
{
    $c = exiger_connexion();
    if (!in_array($c['role'], ['admin', 'formateur'], true)) {
        http_response_code(403);
        page_message('Accès réservé', "Cette page est réservée à l'équipe de la formation.", "Ton compte n'a pas ce droit.", '/espace', 'Revenir à mon espace');
        exit;
    }
    return $c;
}

function refuser_parcours(): void
{
    http_response_code(403);
    page_message('Accès réservé', "Ce parcours n'est pas le tien.", "L'administrateur choisit qui gère chaque parcours.", '/formateur', "Revenir à l'espace formateur");
    exit;
}

function equipe_parcours(int $parcours_id): array
{
    return lignes('SELECT c.id, c.prenom, c.email FROM equipe e JOIN comptes c ON c.id = e.compte_id WHERE e.parcours_id = ? ORDER BY c.prenom', [$parcours_id]);
}

function equipe_ajouter(int $compte_id, int $parcours_id): void
{
    if (valeur('SELECT 1 FROM equipe WHERE compte_id = ? AND parcours_id = ?', [$compte_id, $parcours_id])) return;
    requete('INSERT INTO equipe (compte_id, parcours_id, cree_le) VALUES (?, ?, ?)', [$compte_id, $parcours_id, time()]);
}

function equipe_retirer(int $compte_id, int $parcours_id): void
{
    requete('DELETE FROM equipe WHERE compte_id = ? AND parcours_id = ?', [$compte_id, $parcours_id]);
}

/* ---------- les champs ---------- */

/** Un texte reçu, nettoyé : espaces en trop retirés, retours à la ligne gardés si $lignes. */
function texte_recu(string $nom, int $max, bool $lignes = false): string
{
    $v = $_POST[$nom] ?? '';
    if (!is_string($v)) return '';
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    if (!$lignes) $v = preg_replace('/\s+/u', ' ', $v) ?? '';
    else $v = preg_replace("/\n{3,}/", "\n\n", $v) ?? '';
    $v = trim($v);
    return mb_substr($v, 0, $max);
}

/** Une date et une heure tapées dans le formulaire (heure de Paris), ou null. */
function date_recue(string $jour, string $heure = '00:00'): ?int
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour) || !preg_match('/^\d{2}:\d{2}$/', $heure)) return null;
    $d = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $jour . ' ' . $heure, new DateTimeZone('Europe/Paris'));
    return $d === false ? null : $d->getTimestamp();
}

/* ---------- les modules ---------- */

function module_creer(int $parcours_id, string $titre, string $resume): int
{
    $n = (int) valeur('SELECT COALESCE(MAX(numero), 0) FROM modules WHERE parcours_id = ?', [$parcours_id]) + 1;
    requete('INSERT INTO modules (parcours_id, numero, titre, resume, essai, cree_le) VALUES (?, ?, ?, ?, 0, ?)', [$parcours_id, $n, $titre, $resume !== '' ? $resume : null, time()]);
    return (int) base()->lastInsertId();
}

function module_modifier(int $id, string $titre, string $resume): void
{
    requete('UPDATE modules SET titre = ?, resume = ? WHERE id = ?', [$titre, $resume !== '' ? $resume : null, $id]);
}

/** Remonte (-1) ou descend (+1) un module dans son parcours. */
function module_deplacer(int $id, int $sens): void
{
    $m = module_par_id($id);
    if ($m === null) return;
    $voisin = ligne('SELECT * FROM modules WHERE parcours_id = ? AND numero ' . ($sens < 0 ? '<' : '>') . ' ? ORDER BY numero ' . ($sens < 0 ? 'DESC' : 'ASC') . ' LIMIT 1',
        [$m['parcours_id'], $m['numero']]);
    if ($voisin === null) return;
    bloc_debut();
    requete('UPDATE modules SET numero = ? WHERE id = ?', [$voisin['numero'], $m['id']]);
    requete('UPDATE modules SET numero = ? WHERE id = ?', [$m['numero'], $voisin['id']]);
    bloc_fin();
}

function renumeroter_modules(int $parcours_id): void
{
    foreach (lignes('SELECT id FROM modules WHERE parcours_id = ? ORDER BY numero, id', [$parcours_id]) as $i => $m) {
        requete('UPDATE modules SET numero = ? WHERE id = ?', [$i + 1, $m['id']]);
    }
}

/** Efface un module, ses leçons (et leurs vidéos), son quiz et la progression qui va avec. Les lives restent, sans module. */
function module_supprimer(int $id): void
{
    $m = module_par_id($id);
    if ($m === null) return;
    $videos = array_column(lignes('SELECT video FROM lecons WHERE module_id = ? AND video IS NOT NULL', [$id]), 'video');
    bloc_debut();
    requete('DELETE FROM vues WHERE lecon_id IN (SELECT id FROM lecons WHERE module_id = ?)', [$id]);
    requete('DELETE FROM lecons WHERE module_id = ?', [$id]);
    requete('DELETE FROM questions WHERE module_id = ?', [$id]);
    requete('DELETE FROM quiz WHERE module_id = ?', [$id]);
    requete('UPDATE lives SET module_id = NULL WHERE module_id = ?', [$id]);
    requete('DELETE FROM modules WHERE id = ?', [$id]);
    bloc_fin();
    renumeroter_modules((int) $m['parcours_id']);
    foreach ($videos as $v) video_effacer_si_libre($v);
}

/* ---------- les leçons ---------- */

function lecon_creer(int $module_id, string $titre, string $texte): int
{
    $n = (int) valeur('SELECT COALESCE(MAX(numero), 0) FROM lecons WHERE module_id = ?', [$module_id]) + 1;
    requete('INSERT INTO lecons (module_id, numero, titre, texte, video, duree, cree_le) VALUES (?, ?, ?, ?, NULL, NULL, ?)', [$module_id, $n, $titre, $texte !== '' ? $texte : null, time()]);
    return (int) base()->lastInsertId();
}

function lecon_modifier(int $id, string $titre, string $texte): void
{
    requete('UPDATE lecons SET titre = ?, texte = ? WHERE id = ?', [$titre, $texte !== '' ? $texte : null, $id]);
}

function lecon_deplacer(int $id, int $sens): void
{
    $l = lecon_par_id($id);
    if ($l === null) return;
    $voisin = ligne('SELECT * FROM lecons WHERE module_id = ? AND numero ' . ($sens < 0 ? '<' : '>') . ' ? ORDER BY numero ' . ($sens < 0 ? 'DESC' : 'ASC') . ' LIMIT 1',
        [$l['module_id'], $l['numero']]);
    if ($voisin === null) return;
    bloc_debut();
    requete('UPDATE lecons SET numero = ? WHERE id = ?', [$voisin['numero'], $l['id']]);
    requete('UPDATE lecons SET numero = ? WHERE id = ?', [$l['numero'], $voisin['id']]);
    bloc_fin();
}

function lecon_supprimer(int $id): void
{
    $l = lecon_par_id($id);
    if ($l === null) return;
    requete('DELETE FROM vues WHERE lecon_id = ?', [$id]);
    requete('DELETE FROM lecons WHERE id = ?', [$id]);
    foreach (lignes('SELECT id FROM lecons WHERE module_id = ? ORDER BY numero, id', [$l['module_id']]) as $i => $x) {
        requete('UPDATE lecons SET numero = ? WHERE id = ?', [$i + 1, $x['id']]);
    }
    if ($l['video'] !== null) video_effacer_si_libre($l['video']);
}

/** Retire la vidéo d'une leçon (le fichier est effacé s'il ne sert plus ailleurs). */
function lecon_sans_video(int $id): void
{
    $l = lecon_par_id($id);
    if ($l === null || $l['video'] === null) return;
    requete('UPDATE lecons SET video = NULL, duree = NULL WHERE id = ?', [$id]);
    video_effacer_si_libre($l['video']);
}

/* ---------- les questions des quiz ---------- */

function question_par_id(int $id): ?array
{
    $q = ligne('SELECT * FROM questions WHERE id = ?', [$id]);
    if ($q !== null) {
        $c = json_decode((string) $q['choix'], true);
        $q['choix'] = is_array($c) ? array_values($c) : [];
    }
    return $q;
}

/** Crée ou modifie une question. $choix : 2 à 4 réponses ; $bonne : l'index de la bonne. */
function question_enregistrer(?int $id, int $module_id, string $enonce, array $choix, int $bonne, string $explication): int
{
    $json = json_encode(array_values($choix), JSON_UNESCAPED_UNICODE);
    if ($id !== null) {
        requete('UPDATE questions SET enonce = ?, choix = ?, bonne = ?, explication = ? WHERE id = ?', [$enonce, $json, $bonne, $explication !== '' ? $explication : null, $id]);
        return $id;
    }
    $n = (int) valeur('SELECT COALESCE(MAX(numero), 0) FROM questions WHERE module_id = ?', [$module_id]) + 1;
    requete('INSERT INTO questions (module_id, numero, enonce, choix, bonne, explication) VALUES (?, ?, ?, ?, ?, ?)', [$module_id, $n, $enonce, $json, $bonne, $explication !== '' ? $explication : null]);
    return (int) base()->lastInsertId();
}

function question_supprimer(int $id): void
{
    $q = question_par_id($id);
    if ($q === null) return;
    requete('DELETE FROM questions WHERE id = ?', [$id]);
    foreach (lignes('SELECT id FROM questions WHERE module_id = ? ORDER BY numero, id', [$q['module_id']]) as $i => $x) {
        requete('UPDATE questions SET numero = ? WHERE id = ?', [$i + 1, $x['id']]);
    }
}

/* ---------- les cohortes ---------- */

function cohortes_parcours(int $parcours_id): array
{
    return lignes('SELECT c.*, (SELECT COUNT(*) FROM inscriptions i WHERE i.cohorte_id = c.id) AS nb_eleves FROM cohortes c WHERE c.parcours_id = ? ORDER BY c.debut DESC, c.id DESC', [$parcours_id]);
}

function cohorte_creer(int $parcours_id, string $nom, int $debut, int $places, string $discord): int
{
    requete('INSERT INTO cohortes (parcours_id, nom, debut, places, discord, essai, cree_le) VALUES (?, ?, ?, ?, ?, 0, ?)',
        [$parcours_id, $nom, $debut, $places, $discord !== '' ? $discord : null, time()]);
    return (int) base()->lastInsertId();
}

function cohorte_modifier(int $id, string $nom, int $debut, int $places, string $discord): void
{
    requete('UPDATE cohortes SET nom = ?, debut = ?, places = ?, discord = ? WHERE id = ?', [$nom, $debut, $places, $discord !== '' ? $discord : null, $id]);
}

function eleves_cohorte(int $cohorte_id): array
{
    return lignes('SELECT c.* FROM inscriptions i JOIN comptes c ON c.id = i.compte_id WHERE i.cohorte_id = ? ORDER BY c.prenom', [$cohorte_id]);
}

function inscrire_cohorte(int $compte_id, int $cohorte_id): void
{
    if (valeur('SELECT 1 FROM inscriptions WHERE compte_id = ? AND cohorte_id = ?', [$compte_id, $cohorte_id])) return;
    requete('INSERT INTO inscriptions (compte_id, cohorte_id, cree_le) VALUES (?, ?, ?)', [$compte_id, $cohorte_id, time()]);
}

function desinscrire_cohorte(int $compte_id, int $cohorte_id): void
{
    requete('DELETE FROM inscriptions WHERE compte_id = ? AND cohorte_id = ?', [$compte_id, $cohorte_id]);
}

/* ---------- les lives ---------- */

function live_par_id(int $id): ?array
{
    return ligne('SELECT * FROM lives WHERE id = ?', [$id]);
}

function live_creer(int $cohorte_id, ?int $module_id, int $debut, int $duree, string $titre, string $lien): int
{
    requete('INSERT INTO lives (cohorte_id, module_id, debut, duree, titre, lien, replay, replay_duree, cree_le) VALUES (?, ?, ?, ?, ?, ?, NULL, NULL, ?)',
        [$cohorte_id, $module_id, $debut, $duree, $titre, $lien !== '' ? $lien : null, time()]);
    return (int) base()->lastInsertId();
}

function live_supprimer(int $id): void
{
    $l = live_par_id($id);
    if ($l === null) return;
    requete('DELETE FROM lives WHERE id = ?', [$id]);
    if ($l['replay'] !== null) video_effacer_si_libre($l['replay']);
}

function live_sans_replay(int $id): void
{
    $l = live_par_id($id);
    if ($l === null || $l['replay'] === null) return;
    requete('UPDATE lives SET replay = NULL, replay_duree = NULL WHERE id = ?', [$id]);
    video_effacer_si_libre($l['replay']);
}

/** Un lien Discord (ou vide) : seulement https://discord.gg/… ou https://discord.com/… */
function lien_discord_valable(string $lien): bool
{
    return $lien === '' || (bool) preg_match('#^https://(discord\.gg|discord\.com|www\.discord\.com)/[^\s<>"]+$#', $lien);
}

/* ---------- les vidéos : fichiers ---------- */

function video_effacer_si_libre(string $nom): void
{
    if (valeur('SELECT 1 FROM lecons WHERE video = ?', [$nom]) || valeur('SELECT 1 FROM lives WHERE replay = ?', [$nom])) return;
    $f = fichier_video($nom);
    if ($f !== null) @unlink($f);
}

/** La plus grosse requête que PHP accepte ici, en octets. */
function taille_envoi_max(): int
{
    $lire = static function (string $v): int {
        $v = trim($v);
        if ($v === '' || $v === '-1' || $v === '0') return PHP_INT_MAX;
        $n = (int) $v;
        switch (strtolower(substr($v, -1))) {
            case 'g': $n *= 1024;
            // continue
            case 'm': $n *= 1024;
            // continue
            case 'k': $n *= 1024;
        }
        return $n;
    };
    return min($lire((string) ini_get('post_max_size')), $lire((string) ini_get('upload_max_filesize')) ?: PHP_INT_MAX);
}

/** La taille d'un morceau : 8 Mo au plus, et toujours sous la limite de PHP. */
function taille_morceau(): int
{
    return max(256 * 1024, min(8 * 1048576, (int) floor(taille_envoi_max() * 0.8)));
}

/* ---------- les vidéos : envoi par morceaux ---------- */

function dossier_envois(): string
{
    $d = dossier_donnees() . '/envois';
    if (!is_dir($d)) @mkdir($d, 0700, true);
    return $d;
}

/** Les envois commencés il y a plus d'un jour et jamais finis sont effacés. */
function envois_menage(): void
{
    foreach (glob(dossier_envois() . '/*.json') ?: [] as $f) {
        if (filemtime($f) < time() - 86400) {
            @unlink($f);
            @unlink(substr($f, 0, -5) . '.part');
        }
    }
}

/** La cible d'un envoi, si le compte a le droit d'y mettre une vidéo : ['genre' => 'lecon'|'replay', 'ligne' => …]. */
function cible_envoi(array $compte, string $genre, int $id): ?array
{
    if ($genre === 'lecon') {
        $l = lecon_par_id($id);
        return $l !== null && peut_gerer($compte, (int) $l['parcours_id']) ? ['genre' => $genre, 'ligne' => $l] : null;
    }
    if ($genre === 'replay') {
        $l = live_par_id($id);
        if ($l === null) return null;
        $c = cohorte_par_id((int) $l['cohorte_id']);
        return $c !== null && peut_gerer($compte, (int) $c['parcours_id']) ? ['genre' => $genre, 'ligne' => $l] : null;
    }
    return null;
}

/** Commence un envoi. Renvoie le jeton de l'envoi, ou un message d'erreur (['erreur' => …]). */
function envoi_debut(array $compte, string $genre, int $id, int $taille, string $nom): array
{
    if (random_int(1, 10) === 1) envois_menage();
    if (cible_envoi($compte, $genre, $id) === null) return ['erreur' => "Tu ne peux pas ajouter de vidéo ici."];
    $max = (int) reglage('video_max_mo', 4096) * 1048576;
    if ($taille < 1024) return ['erreur' => 'Ce fichier est vide.'];
    if ($taille > $max) return ['erreur' => 'Cette vidéo est trop lourde (' . intdiv($taille, 1048576) . ' Mo, ' . intdiv($max, 1048576) . ' Mo au plus). Exporte-la en 1080p.'];
    if (!preg_match('/\.(mp4|m4v|mov)$/i', $nom)) return ['erreur' => 'Envoie une vidéo MP4 (export H.264, le format le plus courant).'];
    $libre = @disk_free_space(dossier_donnees());
    if ($libre !== false && $libre < $taille * 2) return ['erreur' => "Plus assez de place sur l'hébergement pour cette vidéo."];
    $jeton = bin2hex(random_bytes(16));
    file_put_contents(dossier_envois() . '/' . $jeton . '.json', json_encode(['compte' => (int) $compte['id'], 'genre' => $genre, 'id' => $id, 'taille' => $taille, 'debut' => time()]));
    touch(dossier_envois() . '/' . $jeton . '.part');
    return ['envoi' => $jeton, 'morceau' => taille_morceau()];
}

function envoi_lire(array $compte, string $jeton): ?array
{
    if (!preg_match('/^[a-f0-9]{32}$/', $jeton)) return null;
    $f = dossier_envois() . '/' . $jeton . '.json';
    if (!is_file($f)) return null;
    $m = json_decode((string) file_get_contents($f), true);
    if (!is_array($m) || (int) ($m['compte'] ?? 0) !== (int) $compte['id']) return null;
    return $m + ['part' => dossier_envois() . '/' . $jeton . '.part', 'json' => $f];
}

/** Ajoute un morceau à partir de l'octet $debut. Renvoie ['recu' => octets déjà là] ou ['erreur' => …]. */
function envoi_morceau(array $compte, string $jeton, int $debut, string $donnees): array
{
    $m = envoi_lire($compte, $jeton);
    if ($m === null) return ['erreur' => 'Envoi introuvable : recommence.'];
    clearstatcache(true, $m['part']);
    $deja = (int) filesize($m['part']);
    if ($debut < $deja && $debut + strlen($donnees) <= $deja) return ['recu' => $deja];       // morceau déjà reçu (renvoi après une coupure)
    if ($debut !== $deja) return ['recu' => $deja, 'erreur' => 'Morceau hors de place.'];
    if ($deja + strlen($donnees) > (int) $m['taille']) return ['erreur' => 'Le fichier est plus gros que prévu.'];
    $f = fopen($m['part'], 'ab');
    if ($f === false || !flock($f, LOCK_EX)) return ['erreur' => "Impossible d'écrire la vidéo."];
    fwrite($f, $donnees);
    fflush($f);
    flock($f, LOCK_UN);
    fclose($f);
    touch($m['json']);
    clearstatcache(true, $m['part']);
    return ['recu' => (int) filesize($m['part'])];
}

/** Termine un envoi : vérifie la vidéo, la range et la met dans la leçon ou le live. */
function envoi_fin(array $compte, string $jeton, ?int $duree): array
{
    $m = envoi_lire($compte, $jeton);
    if ($m === null) return ['erreur' => 'Envoi introuvable : recommence.'];
    clearstatcache(true, $m['part']);
    if ((int) filesize($m['part']) !== (int) $m['taille']) return ['erreur' => 'Il manque des morceaux : recommence.'];
    // Une vidéo MP4 (ou MOV) commence par une boîte « ftyp ».
    $f = fopen($m['part'], 'rb');
    $tete = $f !== false ? (string) fread($f, 12) : '';
    if ($f !== false) fclose($f);
    if (substr($tete, 4, 4) !== 'ftyp') {
        @unlink($m['part']);
        @unlink($m['json']);
        return ['erreur' => "Ce fichier n'est pas une vidéo MP4."];
    }
    $cible = cible_envoi($compte, (string) $m['genre'], (int) $m['id']);
    if ($cible === null) return ['erreur' => "Tu ne peux plus ajouter de vidéo ici."];
    $nom = bin2hex(random_bytes(16)) . '.mp4';
    if (!@rename($m['part'], dossier_videos() . '/' . $nom)) return ['erreur' => "Impossible de ranger la vidéo."];
    @chmod(dossier_videos() . '/' . $nom, 0600);
    @unlink($m['json']);
    $duree = $duree !== null && $duree > 0 && $duree < 86400 ? $duree : null;
    $ancienne = $cible['genre'] === 'lecon' ? $cible['ligne']['video'] : $cible['ligne']['replay'];
    if ($cible['genre'] === 'lecon') requete('UPDATE lecons SET video = ?, duree = ? WHERE id = ?', [$nom, $duree, $m['id']]);
    else requete('UPDATE lives SET replay = ?, replay_duree = ? WHERE id = ?', [$nom, $duree, $m['id']]);
    if ($ancienne !== null) video_effacer_si_libre($ancienne);
    return ['ok' => true, 'duree' => $duree];
}

/* ---------- le suivi des élèves ---------- */

/**
 * Le suivi d'une cohorte : pour chaque élève, sa progression et son état.
 * État : 'termine' (tout validé), 'a_jour' (le module de la semaine est ouvert pour lui), 'en_retard' (bloqué par un quiz),
 * 'pas_commence' (rien vu), 'attente' (la cohorte n'a pas commencé).
 */
function suivi_cohorte(array $cohorte): array
{
    $cohorte['apercu'] = false;
    $mods = modules_parcours((int) $cohorte['parcours_id']);
    $sem = semaine_cohorte($cohorte, count($mods));
    $out = [];
    foreach (eleves_cohorte((int) $cohorte['id']) as $e) {
        $prog = progression($e + ['session_id' => 0], $cohorte);
        $valides = nombre_valides($prog);
        $vues = array_sum(array_column($prog, 'vues'));
        $bloque = null;
        foreach ($prog as $m) if (!$m['ouvert'] && $m['raison'] === 'quiz' && (int) $m['ouvre_le'] <= time()) { $bloque = $m; break; }
        $dernier = ligne('SELECT q.note, q.total, q.fait_le, m.numero FROM quiz q JOIN modules m ON m.id = q.module_id WHERE q.compte_id = ? AND m.parcours_id = ? ORDER BY q.fait_le DESC LIMIT 1',
            [$e['id'], $cohorte['parcours_id']]);
        if ($sem === 0) $etat = 'attente';
        elseif ($mods !== [] && $valides === count($mods)) $etat = 'termine';
        elseif ($bloque !== null) $etat = 'en_retard';
        elseif ($vues === 0 && $dernier === null) $etat = 'pas_commence';
        else $etat = 'a_jour';
        $out[] = ['compte' => $e, 'valides' => $valides, 'vues' => $vues, 'dernier_quiz' => $dernier, 'bloque' => $bloque, 'etat' => $etat];
    }
    return $out;
}

/** Les chiffres du quiz d'un module dans une cohorte : élèves qui l'ont tenté, moyenne des meilleurs essais, question la plus ratée. */
function stats_quiz(array $cohorte, array $module): ?array
{
    $questions = questions_module((int) $module['id']);
    if ($questions === []) return null;
    $essais = lignes('SELECT q.compte_id, q.note, q.total, q.reponses FROM quiz q JOIN inscriptions i ON i.compte_id = q.compte_id AND i.cohorte_id = ? WHERE q.module_id = ?', [$cohorte['id'], $module['id']]);
    if ($essais === []) return ['eleves' => 0, 'moyenne' => null, 'total' => count($questions), 'ratee' => null];
    $meilleur = [];
    $justes = [];
    $vus = [];
    foreach ($essais as $q) {
        $c = (int) $q['compte_id'];
        if (!isset($meilleur[$c]) || (int) $q['note'] > $meilleur[$c]) $meilleur[$c] = (int) $q['note'];
        $r = json_decode((string) $q['reponses'], true) ?: [];
        foreach ($questions as $qq) {
            $id = (int) $qq['id'];
            if (!array_key_exists((string) $id, $r) && !array_key_exists($id, $r)) continue;
            $vus[$id] = ($vus[$id] ?? 0) + 1;
            if ((int) ($r[$id] ?? $r[(string) $id]) === (int) $qq['bonne']) $justes[$id] = ($justes[$id] ?? 0) + 1;
        }
    }
    $ratee = null;
    foreach ($questions as $qq) {
        $id = (int) $qq['id'];
        if (empty($vus[$id])) continue;
        $pct = intdiv(100 * ($justes[$id] ?? 0), $vus[$id]);
        if ($ratee === null || $pct < $ratee['pct']) $ratee = ['numero' => (int) $qq['numero'], 'enonce' => $qq['enonce'], 'pct' => $pct];
    }
    return ['eleves' => count($meilleur), 'moyenne' => array_sum($meilleur) / count($meilleur), 'total' => count($questions), 'ratee' => $ratee];
}

/* ---------- l'habillage des pages formateur ---------- */

/** La zone où l'on glisse une vidéo. $genre 'lecon' ou 'replay' ; $id null pour une nouvelle leçon (elle est créée d'abord). */
function depot_video(string $genre, ?int $id, string $libelle = 'Glisse la vidéo ici'): string
{
    return '<div class="depot" data-envoi="' . e($genre) . '"' . ($id !== null ? ' data-id="' . $id . '"' : '') . '>'
        . '<input type="hidden" name="jeton-envoi" value="' . e(jeton_formulaire()) . '">'
        . '<label class="depot-zone"><input type="file" class="depot-fichier" accept="video/mp4,.mp4,.m4v,.mov"' . ($id !== null ? '' : ' name="video"') . '>'
        . '<span class="depot-titre">' . e($libelle) . '</span>'
        . '<span class="depot-aide">ou clique pour la choisir. MP4 (H.264), 1080p conseillé. Les grosses vidéos partent par morceaux : garde la page ouverte.</span></label>'
        . '<div class="depot-etat" hidden><progress class="depot-barre" max="100" value="0"></progress><p class="depot-texte" role="status" aria-live="polite"></p>'
        . '<button type="button" class="btn btn-line btn-sm depot-annuler">Annuler</button></div>'
        . '<noscript><p class="hint">Il faut JavaScript pour envoyer une vidéo.</p></noscript>'
        . '</div>';
}

/** Les onglets des parcours, si le compte en gère plusieurs. */
function onglets_parcours(array $geres, int $actif, string $page = '/formateur'): string
{
    if (count($geres) < 2) return '';
    $h = '<nav class="cohortes" aria-label="Parcours">';
    foreach ($geres as $p) {
        $h .= '<a href="' . e($page) . '?p=' . (int) $p['id'] . '"' . ((int) $p['id'] === $actif ? ' aria-current="page"' : '') . '>' . e($p['nom']) . ' · ' . e($p['formateur']) . '</a>';
    }
    return $h . '</nav>';
}

/** Le petit formulaire d'un bouton (déplacer, retirer…). */
function bouton_action(string $action, string $libelle, array $champs = [], string $classe = 'btn btn-line btn-sm', string $aria = ''): string
{
    $h = '<form method="post" class="form-bouton">' . champ_jeton() . '<input type="hidden" name="action" value="' . e($action) . '">';
    foreach ($champs as $k => $v) $h .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
    return $h . '<button type="submit" class="' . e($classe) . '"' . ($aria !== '' ? ' aria-label="' . e($aria) . '"' : '') . '>' . $libelle . '</button></form>';
}

function etat_eleve(string $etat): string
{
    return ['termine' => 'Terminé', 'a_jour' => 'À jour', 'en_retard' => 'En retard', 'pas_commence' => 'Pas commencé', 'attente' => 'Avant le départ'][$etat] ?? $etat;
}
