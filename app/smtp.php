<?php
declare(strict_types=1);

/* L'envoi par la boîte e-mail du site (SMTP), au lieu de l'envoi par l'hébergement.

   Pourquoi : un e-mail envoyé par l'hébergement (mail()) n'est pas signé (DKIM). Les boîtes OVH le jettent sans prévenir,
   Gmail et Outlook le rangent parfois dans les indésirables. Envoyé par la boîte contact@noweiz.fr elle-même, il est signé
   par OVH comme ceux qu'on écrit depuis le webmail.

   Le mot de passe de la boîte est tapé par l'administrateur sur /admin/courriel. Il est gardé chiffré dans la base ; la clé
   est un fichier à part dans le dossier de données (avec MySQL, la base et la clé ne sont même plus sur la même machine).
   Il n'apparaît jamais dans une page, un journal ou un message d'erreur. Si la boîte refuse un envoi (mot de passe changé,
   panne), l'e-mail part quand même par l'hébergement, et la page /admin/courriel affiche l'erreur. */

const SMTP_DELAI = 12;   // secondes d'attente au plus, pour se connecter comme pour chaque réponse du serveur

/* ---------- le réglage enregistré ---------- */

/** ['actif', 'utilisateur', 'mdp' (chiffré), 'le', 'test_le', 'erreur', 'erreur_le', 'pause'] ou null. */
function smtp_reglage(): ?array
{
    $v = valeur("SELECT valeur FROM reglages WHERE cle = 'smtp'");
    if (!is_string($v)) return null;
    $d = json_decode($v, true);
    return is_array($d) ? $d : null;
}

function smtp_ecrire(?array $d): void
{
    if ($d === null) {
        requete("DELETE FROM reglages WHERE cle = 'smtp'");
        return;
    }
    $j = json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (valeur("SELECT 1 FROM reglages WHERE cle = 'smtp'")) {
        requete("UPDATE reglages SET valeur = ? WHERE cle = 'smtp'", [$j]);
        return;
    }
    try {
        requete("INSERT INTO reglages (cle, valeur) VALUES ('smtp', ?)", [$j]);
    } catch (PDOException $e) {
        requete("UPDATE reglages SET valeur = ? WHERE cle = 'smtp'", [$j]);
    }
}

/** Le serveur d'envoi : celui des boîtes OVH (MX Plan), sauf si config.local.php en donne un autre. */
function smtp_serveur(): array
{
    return [
        'hote' => (string) (getenv('NA_SMTP_HOTE') ?: reglage('courriel.smtp.hote', 'smtp.mail.ovh.net')),
        'port' => (int) (getenv('NA_SMTP_PORT') ?: reglage('courriel.smtp.port', 465)),
    ];
}

/** De quoi envoyer, mot de passe déchiffré ; null si l'envoi par la boîte n'est pas branché (ou la clé perdue). */
function smtp_actif(): ?array
{
    if (reglage('courriel.transport') !== 'mail') return null;
    $r = smtp_reglage();
    if ($r === null || empty($r['actif']) || empty($r['utilisateur']) || empty($r['mdp'])) return null;
    // Le serveur ne répondait pas il y a un instant : on ne fait pas attendre chaque visiteur, on passe par l'hébergement.
    if ((int) ($r['pause'] ?? 0) > time()) return null;
    $mdp = smtp_dechiffrer((string) $r['mdp']);
    if ($mdp === null) return null;
    return smtp_serveur() + ['utilisateur' => (string) $r['utilisateur'], 'mdp' => $mdp];
}

/** Une panne d'envoi, pour l'afficher sur /admin/courriel (jamais le mot de passe : les messages viennent d'ici). */
function smtp_noter_erreur(string $erreur): void
{
    $r = smtp_reglage();
    if ($r === null) return;
    $r['erreur'] = $erreur;
    $r['erreur_le'] = time();
    if (strpos($erreur, 'pas pu joindre') !== false || strpos($erreur, 'ne répond plus') !== false) $r['pause'] = time() + 600;
    smtp_ecrire($r);
}

/** Un envoi réussi efface la dernière panne (et rien d'autre : on n'écrit pas dans la base à chaque e-mail). */
function smtp_noter_succes(): void
{
    $r = smtp_reglage();
    if ($r === null || (($r['erreur'] ?? '') === '' && (int) ($r['pause'] ?? 0) === 0)) return;
    $r['erreur'] = '';
    $r['erreur_le'] = 0;
    $r['pause'] = 0;
    smtp_ecrire($r);
}

/** Branche l'envoi par la boîte : on ne garde le mot de passe que s'il vient de marcher. */
function smtp_brancher(string $utilisateur, string $mdp): void
{
    smtp_ecrire(['actif' => true, 'utilisateur' => $utilisateur, 'mdp' => smtp_chiffrer($mdp), 'le' => time(), 'test_le' => time(), 'erreur' => '', 'erreur_le' => 0, 'pause' => 0]);
}

/* ---------- le chiffrement du mot de passe ---------- */

function smtp_cle(): string
{
    $f = dossier_donnees() . '/cle-courriel';
    if (!is_file($f)) {
        $h = @fopen($f, 'x');                       // 'x' : si un autre visiteur vient de la créer, on lit la sienne
        if ($h !== false) {
            @chmod($f, 0600);
            fwrite($h, bin2hex(random_bytes(32)));
            fclose($h);
        }
    }
    $k = @hex2bin(trim((string) @file_get_contents($f)));
    if (!is_string($k) || strlen($k) !== 32) throw new RuntimeException('Clé de chiffrement des e-mails illisible.');
    return $k;
}

function smtp_chiffrer(string $clair): string
{
    $k = smtp_cle();
    if (function_exists('sodium_crypto_secretbox')) {
        $n = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 's1:' . base64_encode($n . sodium_crypto_secretbox($clair, $n, $k));
    }
    $iv = random_bytes(12);
    $tag = '';
    $c = openssl_encrypt($clair, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
    return 'o1:' . base64_encode($iv . $tag . $c);
}

function smtp_dechiffrer(string $chiffre): ?string
{
    try {
        $k = smtp_cle();
    } catch (Throwable $e) {
        return null;
    }
    $b = base64_decode(substr($chiffre, 3), true);
    if ($b === false) return null;
    if (strncmp($chiffre, 's1:', 3) === 0 && function_exists('sodium_crypto_secretbox_open')) {
        $nl = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($b) <= $nl) return null;
        $c = sodium_crypto_secretbox_open(substr($b, $nl), substr($b, 0, $nl), $k);
        return is_string($c) ? $c : null;
    }
    if (strncmp($chiffre, 'o1:', 3) === 0 && strlen($b) > 28) {
        $c = openssl_decrypt(substr($b, 28), 'aes-256-gcm', $k, OPENSSL_RAW_DATA, substr($b, 0, 12), substr($b, 12, 16));
        return is_string($c) ? $c : null;
    }
    return null;
}

/* ---------- la conversation avec le serveur ---------- */

/**
 * Envoie un message déjà prêt (en-têtes + corps). $r : hote, port, utilisateur, mdp.
 * Port 465 : connexion chiffrée d'emblée ; sinon STARTTLS (obligatoire). Si le 465 ne répond pas, on essaie le 587.
 * Renvoie true, ou false avec $erreur rempli (un message lisible, sans le mot de passe).
 */
function smtp_envoyer(array $r, string $de, string $a, string $message, ?string &$erreur = null): bool
{
    $erreur = null;
    $essais = [(int) $r['port']];
    if ((int) $r['port'] === 465 && !smtp_local((string) $r['hote'])) $essais[] = 587;
    foreach ($essais as $i => $port) {
        try {
            $s = smtp_ouvrir((string) $r['hote'], $port);
        } catch (SmtpInjoignable $e) {
            $erreur = $e->getMessage();
            if ($i < count($essais) - 1) continue;
            return false;
        }
        try {
            smtp_session($s, (string) $r['hote'], $port, (string) $r['utilisateur'], (string) $r['mdp'], $de, $a, $message);
            return true;
        } catch (RuntimeException $e) {
            $erreur = $e->getMessage();
            return false;
        } catch (Throwable $e) {
            // un avertissement de PHP pendant l'échange (coupure, chiffrement) : le détail va au journal, pas à l'écran
            error_log('Envoi par la boîte : ' . get_class($e) . ' : ' . $e->getMessage());
            $erreur = 'La connexion avec le serveur d\'envoi a échoué en cours de route.';
            return false;
        } finally {
            @fclose($s);
        }
    }
    return false;
}

final class SmtpInjoignable extends RuntimeException {}

/** Le journal d'un échange avec le serveur, pour le diagnostic de l'administration. Jamais l'identifiant ni le mot de passe. */
function smtp_journal(?string $ligne = null): array
{
    static $j = [];
    if ($ligne !== null && !empty($GLOBALS['na_smtp_journal'])) $j[] = mb_substr((string) preg_replace('/[^\x20-\x7E\x{A0}-\x{FFFF}]+/u', ' ', $ligne), 0, 300);
    return $j;
}

/** Les essais automatiques parlent à un faux serveur sur la machine même, sans chiffrement. Jamais ailleurs. */
function smtp_local(string $hote): bool
{
    return in_array($hote, ['127.0.0.1', 'localhost'], true);
}

/** @return resource */
function smtp_ouvrir(string $hote, int $port)
{
    $direct = $port === 465 && !smtp_local($hote);
    $ctx = stream_context_create(['ssl' => [
        'verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $hote, 'SNI_enabled' => true,
        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
    ]]);
    $no = 0;
    $txt = '';
    $s = @stream_socket_client(($direct ? 'ssl://' : 'tcp://') . $hote . ':' . $port, $no, $txt, SMTP_DELAI, STREAM_CLIENT_CONNECT, $ctx);
    if ($s === false) {
        $detail = trim((string) preg_replace('/[^\x20-\x7E]+/', ' ', (string) $txt));
        throw new SmtpInjoignable('Le site n\'a pas pu joindre ' . $hote . ' (port ' . $port . ')' . ($detail !== '' ? ' : ' . mb_substr($detail, 0, 120) : '') . '.');
    }
    stream_set_timeout($s, SMTP_DELAI);
    smtp_journal('connecté à ' . (string) @stream_socket_get_name($s, true) . ($direct ? ' (chiffré)' : ''));
    return $s;
}

/** @param resource $s */
function smtp_session($s, string $hote, int $port, string $utilisateur, string $mdp, string $de, string $a, string $message): void
{
    $nom = (string) (parse_url(url_site(), PHP_URL_HOST) ?: 'localhost');
    smtp_attendre($s, [220], 'accueil du serveur');
    $ehlo = smtp_dire($s, 'EHLO ' . $nom, [250], 'présentation');

    if ($port !== 465 && !smtp_local($hote)) {
        if (stripos($ehlo, 'STARTTLS') === false) throw new RuntimeException('Le serveur ne propose pas de connexion chiffrée : envoi annulé.');
        smtp_dire($s, 'STARTTLS', [220], 'chiffrement');
        if (!@stream_socket_enable_crypto($s, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
            throw new RuntimeException('La connexion chiffrée avec le serveur a échoué.');
        }
        $ehlo = smtp_dire($s, 'EHLO ' . $nom, [250], 'présentation');
    }

    // L'identification. PLAIN si le serveur le propose, LOGIN sinon.
    $refus = 'La boîte a refusé l\'identifiant ou le mot de passe. Vérifie-les dans le webmail, puis retape le mot de passe.';
    if (preg_match('/^250[ -]AUTH[ =](.*)$/mi', $ehlo, $m) && preg_match('/\bPLAIN\b/i', $m[1])) {
        smtp_dire($s, 'AUTH PLAIN ' . base64_encode("\0" . $utilisateur . "\0" . $mdp), [235], 'identification', $refus);
    } else {
        smtp_dire($s, 'AUTH LOGIN', [334], 'identification', $refus);
        smtp_dire($s, base64_encode($utilisateur), [334], 'identification', $refus);
        smtp_dire($s, base64_encode($mdp), [235], 'identification', $refus);
    }

    smtp_dire($s, 'MAIL FROM:<' . $de . '>', [250], 'expéditeur');
    smtp_dire($s, 'RCPT TO:<' . $a . '>', [250, 251], 'destinataire');
    smtp_dire($s, 'DATA', [354], 'envoi');
    $m = preg_replace("/\r\n|\r|\n/", "\r\n", rtrim($message, "\r\n")) ?? '';
    $m = preg_replace('/^\./m', '..', $m) ?? '';                // une ligne qui commence par un point ne doit pas finir l'e-mail
    smtp_journal('> (le message, ' . strlen($m) . ' octets)');
    smtp_ecrire_ligne($s, $m . "\r\n.");
    smtp_attendre($s, [250], 'envoi');
    try { smtp_ecrire_ligne($s, 'QUIT'); } catch (Throwable $e) { /* déjà parti : peu importe */ }
}

/** @param resource $s */
function smtp_ecrire_ligne($s, string $ligne): void
{
    $donnees = $ligne . "\r\n";
    while ($donnees !== '') {
        $n = @fwrite($s, $donnees);
        if ($n === false || $n === 0) throw new RuntimeException('La connexion avec le serveur d\'envoi a coupé.');
        $donnees = (string) substr($donnees, $n);
    }
}

/** Envoie une commande, attend un des codes prévus ; renvoie la réponse. $si_refus : le message à donner si le code ne va pas. */
function smtp_dire($s, string $ligne, array $codes, string $etape, ?string $si_refus = null): string
{
    smtp_journal('> ' . ($etape === 'identification' ? '(identification)' : $ligne));
    smtp_ecrire_ligne($s, $ligne);
    return smtp_attendre($s, $codes, $etape, $si_refus);
}

function smtp_attendre($s, array $codes, string $etape, ?string $si_refus = null): string
{
    $tout = '';
    while (true) {
        $l = fgets($s, 2048);
        if ($l === false) {
            $meta = stream_get_meta_data($s);
            throw new RuntimeException(!empty($meta['timed_out']) ? 'Le serveur d\'envoi ne répond plus (' . $etape . ').' : 'La connexion avec le serveur d\'envoi a coupé (' . $etape . ').');
        }
        $tout .= $l;
        if (strlen($l) < 4 || $l[3] !== '-') break;          // « 250-… » : la réponse continue ; « 250 … » : c'est la dernière ligne
    }
    $code = (int) substr($tout, 0, 3);
    smtp_journal('< ' . trim($tout));
    if (!in_array($code, $codes, true)) {
        if ($si_refus !== null) throw new RuntimeException($si_refus);
        $dit = trim((string) preg_replace('/[^\x20-\x7E\x{A0}-\x{FFFF}]+/u', ' ', $tout));
        throw new RuntimeException('Refus du serveur à l\'étape « ' . $etape . ' » : ' . mb_substr($dit, 0, 160));
    }
    return $tout;
}
