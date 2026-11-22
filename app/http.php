<?php
declare(strict_types=1);

/* La requête et la réponse : adresse, en-têtes de sécurité, formulaires protégés, limite d'essais, annonces. */

function methode(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function chemin(): string
{
    $p = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $p = is_string($p) ? rawurldecode($p) : '/';
    $p = '/' . trim($p, '/');
    return $p;
}

function est_https(): bool
{
    return (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off')
        || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** L'adresse officielle du site, sans barre finale : https://test.noweiz.com */
function url_site(string $chemin = ''): string
{
    return rtrim((string) reglage('url'), '/') . $chemin;
}

function site_securise(): bool
{
    return strncmp(url_site(), 'https://', 8) === 0;
}

/** Le site ne répond que sous son adresse officielle : pas de lien fabriqué avec une autre adresse. */
function verifier_adresse(): void
{
    $attendu = strtolower((string) parse_url(url_site(), PHP_URL_HOST));
    $port = parse_url(url_site(), PHP_URL_PORT);
    if ($port) $attendu .= ':' . $port;
    $recu = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($recu !== $attendu) {
        http_response_code(421);
        header('Content-Type: text/plain; charset=utf-8');
        exit("Cette adresse n'est pas celle du site.");
    }
    // Le passage de http à https est fait par le serveur (.htaccess), pas ici : aucun risque de renvoi en boucle.
}

function entetes_securite(): void
{
    header_remove('X-Powered-By');
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');   // l'adresse d'une page (et le jeton d'un lien) ne part jamais vers un autre site
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: default-src 'none'; style-src 'self'; script-src 'self'; img-src 'self' data:; media-src 'self'; font-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    if (site_securise()) header('Strict-Transport-Security: max-age=15552000');
    if (reglage('test')) header('X-Robots-Tag: noindex, nofollow');
}

function rediriger(string $chemin): void
{
    header('Location: ' . $chemin, true, 303);
    exit;
}

function champ_recu(string $nom, int $max = 300): string
{
    $v = $_POST[$nom] ?? '';
    if (!is_string($v)) return '';
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    return mb_substr(trim($v), 0, $max);
}

/* ---------- les témoins (cookies) ---------- */

function nom_temoin(string $nom): string
{
    // « __Host- » : le navigateur n'accepte ce témoin que de ce site précis, en HTTPS.
    return (site_securise() ? '__Host-' : '') . 'na_' . $nom;
}

function poser_temoin(string $nom, string $valeur, int $duree): void
{
    setcookie(nom_temoin($nom), $valeur, [
        'expires' => $duree > 0 ? time() + $duree : 0,
        'path' => '/',
        'secure' => site_securise(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function lire_temoin(string $nom): string
{
    $v = $_COOKIE[nom_temoin($nom)] ?? '';
    return is_string($v) ? $v : '';
}

function effacer_temoin(string $nom): void
{
    setcookie(nom_temoin($nom), '', ['expires' => 1, 'path' => '/', 'secure' => site_securise(), 'httponly' => true, 'samesite' => 'Lax']);
    unset($_COOKIE[nom_temoin($nom)]);
}

/* ---------- les formulaires : un jeton que seul ce site peut connaître ---------- */

function jeton_formulaire(): string
{
    $s = session_courante();
    if ($s !== null) return (string) $s['csrf'];
    $j = lire_temoin('formulaire');
    if (!preg_match('/^[a-f0-9]{64}$/', $j)) {
        $j = bin2hex(random_bytes(32));
        poser_temoin('formulaire', $j, 0);
        $_COOKIE[nom_temoin('formulaire')] = $j;
    }
    return $j;
}

/** À appeler avant de traiter un formulaire : refuse ce qui ne vient pas d'une page de ce site. */
function verifier_formulaire(): void
{
    $ok = true;
    // D'où vient l'envoi : le navigateur le dit lui-même (Sec-Fetch-Site), une page ne peut pas le falsifier.
    $prov = (string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');
    $origine = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($prov !== '') {
        if (!in_array($prov, ['same-origin', 'none'], true)) $ok = false;
    }
    if ($origine !== '' && $origine !== 'null' && rtrim($origine, '/') !== url_site()) $ok = false;

    $recu = $_POST['jeton'] ?? '';
    $s = session_courante();
    $attendu = $s !== null ? (string) $s['csrf'] : lire_temoin('formulaire');
    if (!is_string($recu) || $attendu === '' || !hash_equals($attendu, $recu)) $ok = false;

    if (!$ok) {
        http_response_code(403);
        page_message('Formulaire expiré', "Ce formulaire n'est plus valable.", 'Reviens à la page précédente, recharge-la, puis recommence.');
        exit;
    }
}

/* ---------- la limite d'essais ---------- */

function cle_limite(string $genre, string $qui): string
{
    return $genre . ':' . substr(hash('sha256', sel() . '|' . $qui), 0, 40);
}

function adresse_visiteur(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? 'inconnue');
}

function essais(string $cle, int $fenetre): int
{
    return (int) valeur('SELECT COUNT(*) FROM tentatives WHERE cle = ? AND quand > ?', [$cle, time() - $fenetre]);
}

function noter_essai(string $cle): void
{
    requete('INSERT INTO tentatives (cle, quand) VALUES (?, ?)', [$cle, time()]);
    if (random_int(1, 50) === 1) requete('DELETE FROM tentatives WHERE quand < ?', [time() - 86400]);
}

function oublier_essais(string $cle): void
{
    requete('DELETE FROM tentatives WHERE cle = ?', [$cle]);
}

/** Dans combien de minutes on peut réessayer (au moins 1). */
function attente_minutes(string $cle, int $fenetre): int
{
    $premier = (int) valeur('SELECT MIN(quand) FROM tentatives WHERE cle = ? AND quand > ?', [$cle, time() - $fenetre]);
    return max(1, (int) ceil(($premier + $fenetre - time()) / 60));
}

/* ---------- les annonces : un message affiché une fois, à la page suivante ---------- */

function annoncer(string $code, string $detail = ''): void
{
    poser_temoin('annonce', $code . ($detail !== '' ? '|' . $detail : ''), 120);
}

/** [code, détail] ou null. Le message lui-même vient de la liste des annonces connues (vues.php). */
function annonce(): ?array
{
    $v = lire_temoin('annonce');
    if ($v === '') return null;
    effacer_temoin('annonce');
    $p = explode('|', $v, 2);
    return [$p[0], $p[1] ?? ''];
}
