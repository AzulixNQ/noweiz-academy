<?php
declare(strict_types=1);

/* Réglages de Noweiz Academy. Aucun secret ici : ce fichier est public.
   Les réglages propres au serveur vont dans <dossier de données>/config.local.php, hors du site
   (même forme que ce tableau : seules les clés présentes remplacent celles d'ici). */
return [
    'nom' => 'Noweiz Academy',
    // L'adresse officielle du site : les liens des e-mails sont construits avec elle, jamais avec l'adresse reçue du visiteur.
    'url' => getenv('NA_URL') ?: 'https://test.noweiz.com',
    // Version de test : bandeau « Version de test », pages non référencées.
    'test' => true,
    'discord' => 'https://discord.gg/4TEKKZTCdF',

    // Le dossier des données (base, journal), par rapport au dossier du site. Il doit être HORS de tout dossier lisible par le web.
    // Chez OVH, le site est dans hub/academy (le dépôt du HUB) : deux niveaux plus haut, on est à la racine de l'hébergement.
    'donnees' => '../../academy-donnees',

    // La base. Vide : un fichier SQLite dans le dossier de données. Plus tard, MySQL :
    // 'base' => ['dsn' => 'mysql:host=...;dbname=...;charset=utf8mb4', 'utilisateur' => '...', 'mot_de_passe' => '...'] dans config.local.php.
    'base' => ['dsn' => '', 'utilisateur' => '', 'mot_de_passe' => ''],

    // Les e-mails automatiques. transport : « mail » (envoi par l'hébergement) ou « fichier » (essais : rien ne part).
    'courriel' => [
        'transport' => getenv('NA_COURRIER') ?: 'mail',
        'expediteur' => 'ne-pas-repondre@noweiz.com',
        'nom' => 'Noweiz Academy',
    ],

    'session_jours' => 30,          // durée d'une connexion
    'invitation_jours' => 7,        // durée d'un lien d'invitation
    'confirmation_heures' => 48,    // durée du lien « confirme ton adresse »
    'mdp_lien_minutes' => 60,       // durée du lien « mot de passe oublié »
    'mdp_min' => 8,                 // longueur minimale d'un mot de passe
];
