<?php
declare(strict_types=1);

/* Réglages de Noweiz Academy. Aucun secret ici : ce fichier est public.
   Les réglages propres au serveur vont dans <dossier de données>/config.local.php, hors du site
   (même forme que ce tableau : seules les clés présentes remplacent celles d'ici). */
return [
    'nom' => 'Noweiz Academy',
    // L'adresse officielle du site : les liens des e-mails sont construits avec elle, jamais avec l'adresse reçue du visiteur.
    'url' => getenv('NA_URL') ?: 'https://academy.noweiz.com',
    // Les autres adresses acceptées (mêmes pages ; les liens des e-mails gardent l'adresse officielle ci-dessus)
    // (test.noweiz.com est renvoyée vers academy.noweiz.com par .htaccess ; elle reste acceptée ici par sécurité)
    'autres_adresses' => ['test.noweiz.com'],
    // Version de test : bandeau « Version de test », pages non référencées. Ouverture au public le 26/11/2026 à 10 h (heure de Paris).
    'test' => getenv('NA_TEST') !== false ? getenv('NA_TEST') === '1' : time() < strtotime('2026-11-26T10:00:00+01:00'),
    // Les prix des parcours : cachés pour l'instant (annoncés d'abord aux inscrits de la liste d'attente).
    'prix_visibles' => false,
    // L'éditeur du site (mentions légales) et le responsable des données (confidentialité). À remplir avant l'ouverture.
    'editeur' => [
        'nom' => '',                 // la société (ou le nom de la personne)
        'forme' => '',               // ex. SAS au capital de 1 000 €
        'adresse' => '',
        'immatriculation' => '',     // ex. RCS Paris 123 456 789
        'directeur' => '',           // le directeur de la publication
    ],
    'discord' => 'https://discord.gg/zVJuSdCjSG',   // le serveur Noweiz HUB, salon d'arrivée (l'ancien lien ouvrait #logs, un salon privé)
    // Les réseaux, affichés sur l'accueil (icône, nom, lien)
    'reseaux' => [
        ['instagram', 'Instagram', 'https://www.instagram.com/fx_nojah'],
        ['tiktok', 'TikTok', 'https://www.tiktok.com/@nojahonorderflow'],
        ['telegram', 'Telegram', 'https://t.me/+zSf9D4ee_4w5NzM0'],
        ['discord', 'Discord', 'https://discord.gg/zVJuSdCjSG'],
    ],
    'hub' => 'https://noweiz.com/',

    // La page d'accueil (liste d'attente). ouverture : vide = « 2027 » ; une date (ex. '2027-01-11T18:00:00+01:00') = compte à rebours.
    // compteur : « ouverture » = jusqu'à la date d'ouverture ; « annee » = jusqu'au début de l'année d'ouverture (la date exacte n'est pas encore fixée).
    'ouverture' => '2027-01-01T00:00:00+01:00',
    'compteur' => 'annee',
    'compteur_min' => 25,            // le compteur « X inscrits » ne s'affiche qu'à partir de ce nombre d'inscrits confirmés

    // Le dossier des données (base, journal), par rapport au dossier du site. Il doit être HORS de tout dossier lisible par le web.
    // Chez OVH, le site est dans hub/academy (le dépôt du HUB) : deux niveaux plus haut, on est à la racine de l'hébergement.
    'donnees' => '../../academy-donnees',

    // La base. Vide : un fichier SQLite dans le dossier de données. Plus tard, MySQL :
    // 'base' => ['dsn' => 'mysql:host=...;dbname=...;charset=utf8mb4', 'utilisateur' => '...', 'mot_de_passe' => '...'] dans config.local.php.
    'base' => ['dsn' => '', 'utilisateur' => '', 'mot_de_passe' => ''],

    // Les e-mails automatiques. transport : « mail » (envoi par l'hébergement) ou « fichier » (essais : rien ne part).
    'courriel' => [
        'transport' => getenv('NA_COURRIER') ?: 'mail',
        'expediteur' => 'contact@noweiz.com',        // l'adresse affichée : on peut y répondre (chez OVH, elle renvoie vers la boîte contact@noweiz.fr)
        'retours' => 'contact@noweiz.com',   // l'enveloppe : une adresse qui existe (les serveurs refusent un expéditeur inconnu) ; les e-mails non livrés reviennent dans la boîte contact
        'nom' => 'Noweiz Academy',
    ],
    'contact' => 'contact@noweiz.com',   // l'adresse donnée sur le site et dans les e-mails

    'session_jours' => 30,          // durée d'une connexion
    'invitation_jours' => 7,        // durée d'un lien d'invitation
    'confirmation_heures' => 48,    // durée du lien « confirme ton adresse »
    'mdp_lien_minutes' => 60,       // durée du lien « mot de passe oublié »
    'mdp_min' => 8,                 // longueur minimale d'un mot de passe
];
