<?php
declare(strict_types=1);

/* Une vidéo de leçon ou un replay, lu par le lecteur de la page. Lien signé (ce compte, cette connexion, quelques heures),
   accès revérifié à chaque morceau. Ouvrir l'adresse dans un onglet ou la passer à un outil de téléchargement ne marche pas :
   le navigateur dit d'où vient la demande (Sec-Fetch-Dest), et seule la balise vidéo d'une page du site est servie. */

$refus = static function (int $code): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    echo $code === 404 ? 'Vidéo introuvable.' : 'Accès refusé.';
};

$compte = compte_courant();
$genre = (string) ($_GET['g'] ?? '');
$id = (string) ($_GET['id'] ?? '');
$expire = (string) ($_GET['e'] ?? '');
$sig = (string) ($_GET['s'] ?? '');
$dest = (string) ($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '');
$site = (string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '');

if ($compte === null || !in_array($genre, ['l', 'r'], true) || !ctype_digit($id) || !ctype_digit($expire) || !preg_match('/^[a-f0-9]{40}$/', $sig)) {
    $refus(403);
    return;
}
if ((int) $expire < time() || !hash_equals(signature_video($compte, $genre, (int) $id, (int) $expire), $sig)) {
    $refus(403);
    return;
}
if (($dest !== '' && $dest !== 'video') || ($site !== '' && $site !== 'same-origin')) {
    $refus(403);
    return;
}

if ($genre === 'l') {
    $a = acces_lecon($compte, (int) $id);
    $fichier = $a !== null ? fichier_video($a['lecon']['video']) : null;
} else {
    $a = acces_replay($compte, (int) $id);
    $fichier = $a !== null ? fichier_video($a['live']['replay']) : null;
}
if ($a === null) {
    $refus(403);
    return;
}
if ($fichier === null) {
    $refus(404);
    return;
}
servir_video($fichier);
