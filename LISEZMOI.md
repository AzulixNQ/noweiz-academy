# Noweiz Academy : les comptes (version de test)

Le début du vrai site de la formation : de vrais comptes, avec e-mail et mot de passe, dans une vraie base.
Le design est celui de la maquette validée (`assets/maquette.css` en est la copie).

## Ce qui marche
- **Créer un compte** : le tout premier compte est l'administrateur ; ensuite, un compte se crée seulement avec un lien d'invitation (valable 7 jours, une seule fois).
- **Se connecter, se déconnecter**, rester connecté 30 jours sur un appareil.
- **E-mails automatiques** : bienvenue avec le lien pour confirmer l'adresse, « mot de passe oublié », alerte quand le mot de passe ou l'adresse change.
- **Mon compte** : prénom, pseudo Discord, adresse e-mail, mot de passe, déconnecter les autres appareils, supprimer le compte.
- **Comptes et invitations** (administrateur) : inviter, annuler une invitation, changer un rôle (élève, formateur, administrateur).

## Ce qui n'existe pas encore
Les cohortes, les modules et les quiz, le paiement, le lien avec Discord, l'espace formateurs.

## Sécurité
- Les mots de passe sont brouillés (Argon2id) ; les jetons de connexion et les liens ne sont gardés que sous forme d'empreinte.
- Formulaires protégés (jeton + provenance vérifiée), essais de connexion limités, en-têtes de sécurité, HTTPS forcé.
- `app/`, `donnees/` et `outils/` ne se lisent pas depuis le web (`.htaccess`).
- Aucun secret dans ce dépôt. Les données sont dans un dossier à côté du site, hors du web.

## Fichiers
- `index.php` : toutes les pages passent par là. `app/pages/` : une page par fichier.
- `app/config.php` : les réglages (adresse du site, durées, expéditeur des e-mails).
- `app/base.php` : la base (SQLite par défaut ; MySQL plus tard, par `config.local.php` dans le dossier de données).
- `assets/` : le style, la police, le petit script.
- `outils/serveur.php` : le serveur d'essai. `outils/essais.js` : les essais automatiques (`node outils/essais.js`).

## Mise en ligne
Hébergement OVH, site « Noweiz Academy (test) », dossier `academy-test`, relié par Git à ce dépôt (branche `main`).
Après un envoi sur GitHub : console OVH > Hébergements web > Mes sites > ⋮ du site > Git > Déployer.
Contrôle après une mise en ligne : `/sante`.
