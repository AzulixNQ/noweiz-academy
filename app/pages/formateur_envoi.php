<?php
declare(strict_types=1);

/* L'envoi d'une vidéo par morceaux, appelé par assets/formateur.js. Toujours en POST, avec le jeton du formulaire
   dans l'en-tête X-Jeton. Réponses en JSON.
     ?action=debut     genre (lecon|replay), id, taille, nom          → { envoi, morceau }
     ?action=morceau   e (envoi), debut (octet), corps = le morceau    → { recu }
     ?action=fin       e, duree (secondes, lue par le navigateur)      → { ok } */

$repondre = static function (array $d, int $code = 200): void {
    http_response_code($code);
    header_remove('X-Powered-By');
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
};

$compte = compte_courant();
if ($compte === null) $repondre(['erreur' => 'Ta session a expiré : reconnecte-toi, puis recommence.'], 401);
if (!in_array($compte['role'], ['admin', 'formateur'], true)) $repondre(['erreur' => 'Réservé à l\'équipe.'], 403);
if (methode() !== 'POST') $repondre(['erreur' => 'Méthode refusée.'], 405);
verifier_formulaire();

$action = (string) ($_GET['action'] ?? '');
if ($action === 'debut') {
    $r = envoi_debut($compte, champ_recu('genre', 10), (int) champ_recu('id', 12), (int) champ_recu('taille', 15), champ_recu('nom', 255));
    $repondre($r, isset($r['erreur']) ? 422 : 200);
}
if ($action === 'morceau') {
    $debut = (string) ($_GET['debut'] ?? '');
    if (!ctype_digit($debut)) $repondre(['erreur' => 'Morceau mal formé.'], 400);
    $donnees = (string) file_get_contents('php://input');
    if ($donnees === '') $repondre(['erreur' => 'Morceau vide.'], 400);
    $r = envoi_morceau($compte, (string) ($_GET['e'] ?? ''), (int) $debut, $donnees);
    $repondre($r, isset($r['erreur']) ? (isset($r['recu']) ? 409 : 422) : 200);
}
if ($action === 'fin') {
    $d = champ_recu('duree', 8);
    $r = envoi_fin($compte, (string) ($_GET['e'] ?? ''), ctype_digit($d) ? (int) $d : null);
    if (isset($r['ok'])) annoncer('video-en-ligne');
    $repondre($r, isset($r['erreur']) ? 422 : 200);
}
$repondre(['erreur' => 'Action inconnue.'], 400);
