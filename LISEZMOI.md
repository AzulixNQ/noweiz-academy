# Noweiz Academy : la page d'accueil, la liste d'attente et les comptes (version de test)

Le début du vrai site de la formation : de vrais comptes, avec e-mail et mot de passe, dans une vraie base.
Le design est celui de la maquette validée (`assets/maquette.css` en est la copie).

## Ce qui marche
- **Créer un compte** : le tout premier compte est l'administrateur ; ensuite, un compte se crée seulement avec un lien d'invitation (valable 7 jours, une seule fois).
- **Se connecter, se déconnecter**, rester connecté 30 jours sur un appareil.
- **E-mails automatiques** : bienvenue avec le lien pour confirmer l'adresse, « mot de passe oublié », alerte quand le mot de passe ou l'adresse change.
- **Mon compte** : prénom, pseudo Discord, adresse e-mail, mot de passe, déconnecter les autres appareils, supprimer le compte.
- **Comptes et invitations** (administrateur) : inviter, annuler une invitation, changer un rôle (élève, formateur, administrateur).

## La page d'accueil et la liste d'attente (22/11)
- **L'accueil** (`/`) est la page de présentation validée (prix, mentorat, cohortes, aperçu, extrait vidéo, formateurs, questions), avec le formulaire de la liste d'attente. Elle marche sans JavaScript ; `assets/fermee.js` ajoute la vérification avant l'envoi, le mur des certificats et les onglets.
- **Inscription en deux temps** : le formulaire envoie un e-mail « Confirme ton inscription » (lien valable 48 h). Sans confirmation, l'adresse est effacée au bout de 7 jours. La page répond pareil qu'une adresse soit nouvelle ou déjà inscrite (on ne peut pas savoir qui est sur la liste) ; une adresse déjà confirmée reçoit « Tu es déjà sur la liste ».
- **Désinscription** : un lien signé dans chaque e-mail de la liste, plus les en-têtes `List-Unsubscribe` (le « Se désabonner » de Gmail, en un clic). Ouvrir le lien ne suffit pas, il faut cliquer (les antivirus ouvrent les liens). La ligne est effacée.
- **Protections** : champ piège contre les robots, 10 inscriptions par heure au plus depuis une connexion, 3 e-mails par jour au plus à une même adresse.
- **Liste d'attente** (administrateur, `/admin/liste-attente`) : le nombre d'inscrits confirmés, par parcours, la liste, « Effacer » et l'export pour Excel (`.csv`, séparateur « ; », formules neutralisées).
- Réglages dans `app/config.php` : `ouverture` (vide = « 2027 », une date = compte à rebours), `compteur_min` (le compteur « X inscrits » s'affiche à partir de 25 confirmés), `hub`.
- **Pages légales** : `/confidentialite` (liste d'attente et comptes) et `/mentions-legales` (hébergeur OVH ; l'éditeur sera ajouté avant l'ouverture au public).

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
- `app/liste.php` : la liste d'attente (inscription, confirmation, désinscription, e-mails).
- `assets/` : le style (`maquette.css`, `comptes.css`, `fermee.css`), la police, les scripts, les images et l'extrait vidéo.
- `outils/serveur.php` : le serveur d'essai. `outils/essais.js` : les essais automatiques (`node outils/essais.js`).

## Mise en ligne
L'offre OVH Perso n'accepte qu'un seul site relié à GitHub, et c'est le HUB. Ce site est donc livré **par le dépôt du HUB**
(`AzulixNQ/nojah`), dans son sous-dossier `academy/` ; chez OVH, `test.noweiz.com` pointe sur `hub/academy`.

1. Travailler ici (`AzulixNQ/noweiz-academy`, le code source), lancer `node outils/essais.js`.
2. Recopier ce dossier (sans `.git`) dans `academy/` du dépôt `nojah`, puis envoyer `nojah` sur GitHub.
3. Console OVH > Hébergements web > Mes sites > ⋮ de « Noweiz HUB » > Git > Déployer.
4. Contrôler `https://test.noweiz.com/sante` (`donnees_niveaux` doit valoir 2 : les données sont à la racine de l'hébergement, hors du HUB).

`.htaccess` rend ce dossier introuvable sous une autre adresse que `test.noweiz.com` (par exemple `noweiz.com/academy`).
