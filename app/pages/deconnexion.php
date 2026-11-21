<?php
declare(strict_types=1);

if (methode() !== 'POST') rediriger('/');
if (compte_courant() === null) rediriger('/connexion');
verifier_formulaire();
fermer_session();
annoncer('deconnecte');
rediriger('/connexion');
