<?php
declare(strict_types=1);

/* Le lien « confirme ton adresse » reçu par e-mail. */

$jeton = is_string($_GET['lien'] ?? null) ? $_GET['lien'] : '';
$lien = lien_valable('confirmation', $jeton);
$compte = $lien !== null ? compte_par_id((int) $lien['compte_id']) : null;

if ($compte === null) {
    $connecte = compte_courant();
    if ($connecte !== null && $connecte['confirme_le'] !== null) rediriger('/espace');
    page_message('Lien expiré', "Ce lien n'est plus valable.", "Il a déjà servi, ou il a expiré. Connecte-toi, puis renvoie l'e-mail depuis « Mon compte ».",
        $connecte !== null ? '/compte' : '/connexion', $connecte !== null ? 'Ouvrir mon compte' : 'Se connecter');
    exit;
}

requete('UPDATE comptes SET confirme_le = ? WHERE id = ?', [time(), $compte['id']]);
consommer_lien((int) $lien['id'], (int) $compte['id']);
annoncer('adresse-confirmee');
rediriger(compte_courant() !== null ? '/espace' : '/connexion');
