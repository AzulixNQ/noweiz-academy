<?php
declare(strict_types=1);

/* Noweiz Academy : toutes les pages passent par ici. Les fichiers de app/ ne sont pas lisibles depuis le web. */

require __DIR__ . '/app/demarrage.php';

verifier_adresse();

$pages = [
    '/' => 'accueil',
    '/connexion' => 'connexion',
    '/inscription' => 'inscription',
    '/deconnexion' => 'deconnexion',
    '/espace' => 'espace',
    '/compte' => 'compte',
    '/admin' => 'admin',
    '/confirmer' => 'confirmer',
    '/mot-de-passe-oublie' => 'mdp_oublie',
    '/nouveau-mot-de-passe' => 'mdp_nouveau',
    '/confidentialite' => 'confidentialite',
    '/mentions-legales' => 'mentions',
    '/liste-attente/confirmer' => 'liste_confirmer',
    '/liste-attente/desinscription' => 'liste_desinscription',
    '/admin/liste-attente' => 'admin_liste',
    '/admin/liste-attente.csv' => 'admin_liste_csv',
    '/parcours' => 'parcours',
    '/module' => 'module',
    '/quiz' => 'quiz',
    '/lives' => 'lives',
    '/classement' => 'classement',
    '/certificat' => 'certificat',
    '/video' => 'video',
    '/admin/essai' => 'admin_essai',
    '/sante' => 'sante',
];

if (!in_array(methode(), ['GET', 'HEAD', 'POST'], true)) {
    http_response_code(405);
    header('Allow: GET, HEAD, POST');
    exit;
}

$page = $pages[chemin()] ?? null;
if ($page === null) {
    http_response_code(404);
    page_message('Page introuvable', 'Cette page n\'existe pas.', 'L\'adresse est peut-être mal recopiée.', '/', "Revenir à l'accueil");
    exit;
}

require __DIR__ . '/app/pages/' . $page . '.php';
