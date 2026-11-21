<?php
declare(strict_types=1);

/* Les comptes : création, mot de passe, liens à usage unique (invitation, confirmation, mot de passe oublié). */

const ROLES = ['eleve' => 'Élève', 'formateur' => 'Formateur', 'admin' => 'Administrateur'];

function nombre_comptes(): int
{
    return (int) valeur('SELECT COUNT(*) FROM comptes');
}

function compte_par_email(string $email): ?array
{
    return ligne('SELECT * FROM comptes WHERE email = ?', [normaliser_email($email)]);
}

function compte_par_id(int $id): ?array
{
    return ligne('SELECT * FROM comptes WHERE id = ?', [$id]);
}

function normaliser_email(string $email): string
{
    return mb_strtolower(trim($email));
}

/* ---------- vérifications : chaque fonction renvoie '' si tout va bien, sinon le message à afficher ---------- */

function verifier_prenom(string $prenom): string
{
    if ($prenom === '') return 'Écris ton prénom.';
    if (mb_strlen($prenom) > 60) return 'Ton prénom est trop long (60 caractères au plus).';
    return '';
}

function verifier_email(string $email): string
{
    if ($email === '') return 'Écris ton e-mail.';
    if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) return "Cette adresse n'est pas complète. Exemple : toi@exemple.fr";
    return '';
}

function verifier_discord(string $pseudo): string
{
    if ($pseudo === '') return '';
    if (mb_strlen($pseudo) > 40) return 'Ce pseudo est trop long (40 caractères au plus).';
    return '';
}

function verifier_mot_de_passe(string $mdp, string $email = '', string $prenom = ''): string
{
    $min = (int) reglage('mdp_min');
    if ($mdp === '') return 'Choisis un mot de passe.';
    if (mb_strlen($mdp) < $min) return "Ce mot de passe est trop court : $min caractères au moins.";
    if (strlen($mdp) > 72) return 'Ce mot de passe est trop long (72 caractères au plus).';
    $bas = mb_strtolower($mdp);
    $courants = ['password', 'password1', 'motdepasse', 'motdepasse1', '12345678', '123456789', '1234567890', '87654321', 'azertyuiop', 'azerty123', 'qwertyuiop', 'qwerty123',
        'iloveyou', 'jetaime', 'bonjour123', 'soleil123', 'marseille', '00000000', '11111111', 'abcd1234', 'trading123', 'noweiz123', 'noweizacademy', 'academy123', 'orderflow', 'nasdaq100'];
    if (in_array($bas, $courants, true)) return 'Ce mot de passe est trop courant : choisis-en un autre.';
    $local = mb_strtolower((string) strstr($email, '@', true));
    if ($email !== '' && ($bas === mb_strtolower($email) || (mb_strlen($local) >= 5 && $bas === $local))) return 'Ton mot de passe ne doit pas être ton e-mail.';
    if ($prenom !== '' && $bas === mb_strtolower($prenom)) return 'Ton mot de passe ne doit pas être ton prénom.';
    if (preg_match('/^(.)\1+$/u', $mdp)) return 'Ce mot de passe est trop simple : choisis-en un autre.';
    return '';
}

function hacher(string $mdp): string
{
    return password_hash($mdp, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
}

/** Vérifie e-mail et mot de passe. Renvoie le compte, ou null. Prend le même temps que le compte existe ou non. */
function authentifier(string $email, string $mdp): ?array
{
    static $leurre = null;
    $c = compte_par_email($email);
    if ($c === null) {
        if ($leurre === null) $leurre = hacher('un mot de passe qui ne sert à personne');
        password_verify($mdp, $leurre);
        return null;
    }
    if (!password_verify($mdp, $c['hachage'])) return null;
    if (password_needs_rehash($c['hachage'], defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT)) {
        requete('UPDATE comptes SET hachage = ? WHERE id = ?', [hacher($mdp), $c['id']]);
    }
    return $c;
}

/**
 * Crée un compte. Sans invitation, seul le tout premier compte peut se créer : il est administrateur.
 * (À revoir avant de passer à MySQL : y réserver la table le temps de créer le premier compte.)
 * Renvoie [compte, ''] ou [null, message].
 */
function creer_compte(string $prenom, string $email, string $mdp, string $discord, ?array $invitation): array
{
    $email = normaliser_email($email);
    $id = 0;
    bloc_debut();
    try {
        $premier = nombre_comptes() === 0;
        if (!$premier && $invitation === null) { bloc_annuler(); return [null, 'Les inscriptions ne sont pas ouvertes.']; }
        if ($invitation !== null) {
            $invitation = ligne("SELECT * FROM liens WHERE id = ? AND type = 'invitation' AND utilise_le IS NULL AND expire_le > ?", [$invitation['id'], time()]);
            if ($invitation === null) { bloc_annuler(); return [null, "Ce lien d'invitation n'est plus valable."]; }
        }
        if (compte_par_email($email) !== null) { bloc_annuler(); return [null, 'Un compte existe déjà avec cette adresse. Connecte-toi, ou demande un nouveau mot de passe.']; }

        $role = $premier ? 'admin' : (isset(ROLES[$invitation['role']]) ? $invitation['role'] : 'eleve');
        requete(
            'INSERT INTO comptes (email, prenom, discord, hachage, role, cree_le) VALUES (?, ?, ?, ?, ?, ?)',
            [$email, $prenom, $discord !== '' ? $discord : null, hacher($mdp), $role, time()]
        );
        $id = (int) base()->lastInsertId();
        if ($invitation !== null) requete('UPDATE liens SET utilise_le = ?, utilise_par = ? WHERE id = ?', [time(), $id, $invitation['id']]);
        bloc_fin();
    } catch (Throwable $e) {
        bloc_annuler();
        if ($e instanceof PDOException && compte_par_email($email) !== null) return [null, 'Un compte existe déjà avec cette adresse. Connecte-toi, ou demande un nouveau mot de passe.'];
        throw $e;
    }
    return [compte_par_id($id), ''];
}

function changer_mot_de_passe(array $compte, string $mdp, ?int $garder_session = null): void
{
    requete('UPDATE comptes SET hachage = ? WHERE id = ?', [hacher($mdp), $compte['id']]);
    fermer_autres_sessions((int) $compte['id'], $garder_session);
    requete("DELETE FROM liens WHERE type = 'mdp' AND compte_id = ?", [$compte['id']]);
    courriel_mdp_change($compte);
}

function nombre_admins(): int
{
    return (int) valeur("SELECT COUNT(*) FROM comptes WHERE role = 'admin'");
}

function supprimer_compte(int $id): void
{
    requete('DELETE FROM sessions WHERE compte_id = ?', [$id]);
    requete('DELETE FROM liens WHERE compte_id = ? OR (cree_par = ? AND utilise_le IS NULL)', [$id, $id]);
    requete('DELETE FROM comptes WHERE id = ?', [$id]);
}

/* ---------- les liens à usage unique ---------- */

/** Crée un lien et renvoie son jeton (lui seul ouvre le lien : la base n'en garde que l'empreinte). */
function creer_lien(string $type, int $duree, ?int $compte_id = null, ?string $role = null, ?string $note = null, ?int $cree_par = null): string
{
    $jeton = bin2hex(random_bytes(24));
    requete(
        'INSERT INTO liens (type, jeton, compte_id, role, note, cree_par, cree_le, expire_le) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$type, empreinte($jeton), $compte_id, $role, $note, $cree_par, time(), time() + $duree]
    );
    return $jeton;
}

/** Le lien encore valable pour ce jeton, ou null. */
function lien_valable(string $type, string $jeton): ?array
{
    if (!preg_match('/^[a-f0-9]{48}$/', $jeton)) return null;
    return ligne('SELECT * FROM liens WHERE type = ? AND jeton = ? AND utilise_le IS NULL AND expire_le > ?', [$type, empreinte($jeton), time()]);
}

function consommer_lien(int $id, ?int $par = null): void
{
    requete('UPDATE liens SET utilise_le = ?, utilise_par = ? WHERE id = ?', [time(), $par, $id]);
}

/** Envoie l'e-mail « confirme ton adresse » (bienvenue à la création). Un seul lien valable à la fois. */
function envoyer_confirmation(array $compte, bool $bienvenue = false): bool
{
    requete("DELETE FROM liens WHERE type = 'confirmation' AND compte_id = ?", [$compte['id']]);
    $jeton = creer_lien('confirmation', (int) reglage('confirmation_heures') * 3600, (int) $compte['id']);
    $lien = url_site('/confirmer?lien=' . $jeton);
    return $bienvenue ? courriel_bienvenue($compte, $lien) : courriel_confirmation($compte, $lien);
}

function envoyer_lien_mdp(array $compte): bool
{
    requete("DELETE FROM liens WHERE type = 'mdp' AND compte_id = ?", [$compte['id']]);
    $jeton = creer_lien('mdp', (int) reglage('mdp_lien_minutes') * 60, (int) $compte['id']);
    return courriel_mdp_oublie($compte, url_site('/nouveau-mot-de-passe?lien=' . $jeton));
}
