<?php
declare(strict_types=1);

/* Un contrôle rapide après une mise en ligne : rien de privé ici. */

$etat = ['ok' => true, 'php' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION];
try {
    $etat['base'] = pilote();
    $etat['schema'] = version_schema(base());
    $etat['comptes'] = nombre_comptes() > 0 ? 'au moins un' : 'aucun';
} catch (Throwable $e) {
    $etat['ok'] = false;
    $etat['base'] = 'en panne';
}
$etat['donnees'] = donnees_hors_site() ? 'hors du site' : 'dans le site (dossier fermé)';
// Combien de dossiers il faut remonter depuis le site pour trouver le dossier des données (2 attendu chez OVH).
$site = explode('/', trim(RACINE, '/'));
$donnees = explode('/', trim(dirname(dossier_donnees()), '/'));
$commun = 0;
while ($commun < min(count($site), count($donnees)) && $site[$commun] === $donnees[$commun]) $commun++;
$etat['donnees_niveaux'] = count($site) - $commun;
$etat['https'] = est_https();
// « interne » voudrait dire que le site ne voit pas l'adresse des visiteurs : la limite d'essais par connexion toucherait tout le monde.
$etat['adresse_visiteur'] = filter_var(adresse_visiteur(), FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ? 'publique' : 'interne';
$etat['mots_de_passe'] = defined('PASSWORD_ARGON2ID') ? 'argon2id' : 'bcrypt';
// La taille d'un envoi accepté par PHP, en Mo : l'espace formateur découpe les vidéos en morceaux plus petits.
$etat['envoi_mo'] = intdiv(taille_envoi_max(), 1048576);
$etat['courriel'] = reglage('courriel.transport') === 'mail' ? (function_exists('mail') ? 'mail' : 'mail indisponible') : 'essai (fichier)';
// « boîte » : les e-mails partent par la boîte contact (signés) ; « hébergement » : par mail(), non signés.
try {
    $r = smtp_reglage();
    $etat['courriel_par'] = $r !== null && !empty($r['actif']) ? ((string) ($r['erreur'] ?? '') !== '' ? 'boîte (en panne : hébergement)' : 'boîte') : 'hébergement';
} catch (Throwable $e) {
    $etat['courriel_par'] = 'inconnu';
}

http_response_code($etat['ok'] ? 200 : 503);
header_remove('X-Powered-By');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
echo json_encode($etat, JSON_UNESCAPED_UNICODE);
