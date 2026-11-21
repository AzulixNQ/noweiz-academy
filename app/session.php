<?php
declare(strict_types=1);

/* Les connexions. Le navigateur garde un jeton au hasard ; la base n'en garde que l'empreinte :
   même en lisant la base, personne ne peut se faire passer pour un élève. */

function empreinte(string $jeton): string
{
    return hash('sha256', $jeton);
}

/** La connexion en cours (avec le compte), ou null. */
function session_courante(): ?array
{
    static $fait = false, $s = null;
    if ($fait) return $s;
    $fait = true;

    $jeton = lire_temoin('session');
    if (!preg_match('/^[a-f0-9]{64}$/', $jeton)) return null;

    $l = ligne(
        'SELECT s.id AS session_id, s.csrf, s.vu_le AS session_vue, s.expire_le, c.* FROM sessions s JOIN comptes c ON c.id = s.compte_id WHERE s.jeton = ?',
        [empreinte($jeton)]
    );
    $maintenant = time();
    if ($l === null || (int) $l['expire_le'] < $maintenant) {
        if ($l !== null) requete('DELETE FROM sessions WHERE id = ?', [$l['session_id']]);
        effacer_temoin('session');
        return null;
    }
    // On prolonge au plus une fois par jour : pas d'écriture à chaque page.
    if ($maintenant - (int) $l['session_vue'] > 86400) {
        $duree = (int) reglage('session_jours') * 86400;
        requete('UPDATE sessions SET vu_le = ?, expire_le = ? WHERE id = ?', [$maintenant, $maintenant + $duree, $l['session_id']]);
        requete('UPDATE comptes SET vu_le = ? WHERE id = ?', [$maintenant, $l['id']]);
        poser_temoin('session', $jeton, $duree);
    }
    return $s = $l;
}

function compte_courant(): ?array
{
    return session_courante();
}

function ouvrir_session(int $compte_id): void
{
    $maintenant = time();
    $duree = (int) reglage('session_jours') * 86400;
    $jeton = bin2hex(random_bytes(32));
    requete('DELETE FROM sessions WHERE expire_le < ?', [$maintenant]);
    requete(
        'INSERT INTO sessions (compte_id, jeton, csrf, cree_le, vu_le, expire_le) VALUES (?, ?, ?, ?, ?, ?)',
        [$compte_id, empreinte($jeton), bin2hex(random_bytes(32)), $maintenant, $maintenant, $maintenant + $duree]
    );
    requete('UPDATE comptes SET vu_le = ? WHERE id = ?', [$maintenant, $compte_id]);
    poser_temoin('session', $jeton, $duree);
    effacer_temoin('formulaire');
}

function fermer_session(): void
{
    $s = session_courante();
    if ($s !== null) requete('DELETE FROM sessions WHERE id = ?', [$s['session_id']]);
    effacer_temoin('session');
}

/** Ferme les connexions du compte sur les autres appareils. Renvoie leur nombre. */
function fermer_autres_sessions(int $compte_id, ?int $sauf_session = null): int
{
    if ($sauf_session === null) return requete('DELETE FROM sessions WHERE compte_id = ?', [$compte_id])->rowCount();
    return requete('DELETE FROM sessions WHERE compte_id = ? AND id <> ?', [$compte_id, $sauf_session])->rowCount();
}

function exiger_connexion(): array
{
    $c = compte_courant();
    if ($c === null) rediriger('/connexion');
    return $c;
}

function exiger_admin(): array
{
    $c = exiger_connexion();
    if ($c['role'] !== 'admin') {
        http_response_code(403);
        page_message('Accès réservé', 'Cette page est réservée aux administrateurs.', 'Ton compte n\'a pas ce droit.', '/espace', 'Revenir à mon espace');
        exit;
    }
    return $c;
}
