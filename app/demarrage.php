<?php
declare(strict_types=1);

/* Le démarrage : réglages, dossier de données, gestion des erreurs, puis les outils. */

define('RACINE', dirname(__DIR__));
date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

/** Le dossier des données (base, journal d'erreurs) : à côté du site, hors de ce que le web peut lire. */
function dossier_donnees(): string
{
    static $d = null;
    if ($d !== null) return $d;
    $candidats = [];
    $env = getenv('NA_DONNEES');
    if (is_string($env) && $env !== '') $candidats[] = $env;
    $candidats[] = dirname(RACINE) . '/' . basename(RACINE) . '-donnees';   // /home/…/academy-test-donnees
    $candidats[] = RACINE . '/donnees';                                       // secours : dans le site, fermé par .htaccess
    foreach ($candidats as $c) {
        if ((is_dir($c) || @mkdir($c, 0700, true)) && is_writable($c)) return $d = $c;
    }
    throw new RuntimeException('Aucun dossier de données accessible en écriture.');
}

function donnees_hors_site(): bool
{
    return strpos(dossier_donnees() . '/', RACINE . '/') !== 0;
}

/** Un réglage : reglage('courriel.expediteur'). config.local.php (hors du site) passe devant config.php. */
function reglage(string $cle, $defaut = null)
{
    static $c = null;
    if ($c === null) {
        $c = require __DIR__ . '/config.php';
        $local = dossier_donnees() . '/config.local.php';
        if (is_file($local)) {
            $l = require $local;
            if (is_array($l)) $c = array_replace_recursive($c, $l);
        }
    }
    $v = $c;
    foreach (explode('.', $cle) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) return $defaut;
        $v = $v[$k];
    }
    return $v;
}

/* Les erreurs ne s'affichent jamais au visiteur : elles vont dans le journal, hors du site. */
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
try { ini_set('error_log', dossier_donnees() . '/erreurs.log'); } catch (Throwable $e) { /* le journal par défaut du serveur */ }

set_error_handler(static function (int $no, string $msg, string $fichier, int $ligne): bool {
    if (!(error_reporting() & $no)) return false;
    // Un simple avertissement de version de PHP ne doit pas arrêter le site : il va dans le journal.
    if ($no === E_DEPRECATED || $no === E_USER_DEPRECATED) { error_log('Déprécié : ' . $msg . ' (' . basename($fichier) . ':' . $ligne . ')'); return true; }
    throw new ErrorException($msg, 0, $no, $fichier, $ligne);
});

set_exception_handler(static function (Throwable $e): void {
    error_log(get_class($e) . ' : ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo '<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Erreur</title><body style="margin:0;background:#0B0B0C;color:#fff;font:17px/1.6 system-ui,sans-serif">'
       . '<div style="max-width:560px;margin:18vh auto;padding:0 24px"><h1 style="font-weight:500">Le site a eu un problème.</h1>'
       . '<p style="color:#A9A9AC">Rien n\'a été enregistré. Réessaie dans un instant ; si ça continue, préviens-nous sur Discord.</p>'
       . '<p><a href="/" style="color:#C6F432">Revenir à l\'accueil</a></p></div></body></html>';
});

require __DIR__ . '/base.php';
require __DIR__ . '/http.php';
require __DIR__ . '/session.php';
require __DIR__ . '/courriel.php';
require __DIR__ . '/comptes.php';
require __DIR__ . '/vues.php';
