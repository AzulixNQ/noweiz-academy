<?php
/* Le serveur d'essai, pour travailler sur son ordinateur :
     NA_URL=http://127.0.0.1:8765 NA_COURRIER=fichier NA_DONNEES=/chemin/donnees php -S 127.0.0.1:8765 outils/serveur.php
   Il imite les règles du fichier .htaccess : dossiers privés fermés, vrais fichiers servis tels quels, le reste par index.php. */
$chemin = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$racine = dirname(__DIR__);
if (preg_match('#^/(app|donnees|outils)(/|$)#', $chemin) || preg_match('#(^|/)\.#', $chemin) || preg_match('#^/(LISEZMOI|README)\.md$#', $chemin)) {
    http_response_code(403);
    echo 'Interdit';
    return true;
}
if ($chemin !== '/' && is_file($racine . $chemin)) return false;
require $racine . '/index.php';
