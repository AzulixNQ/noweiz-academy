# Noweiz Academy : la page d'accueil, la liste d'attente, les comptes, l'espace élève et l'espace formateur (version de test)

Le début du vrai site de la formation : de vrais comptes, avec e-mail et mot de passe, dans une vraie base.
Le design est celui de la maquette validée (`assets/maquette.css` en est la copie).

## Ce qui marche
- **Créer un compte** : le tout premier compte est l'administrateur ; ensuite, un compte se crée seulement avec un lien d'invitation (valable 7 jours, une seule fois).
- **Se connecter, se déconnecter**, rester connecté 30 jours sur un appareil.
- **E-mails automatiques** : bienvenue avec le lien pour confirmer l'adresse, « mot de passe oublié », alerte quand le mot de passe ou l'adresse change.
- **Mon compte** : prénom, pseudo Discord, adresse e-mail, mot de passe, déconnecter les autres appareils, supprimer le compte.
- **Comptes et invitations** (administrateur) : inviter, annuler une invitation, changer un rôle (élève, formateur, administrateur).

## La page d'accueil et la liste d'attente (22/11)
- **L'accueil** (`/`) est la page de présentation validée (prix, mentorat, cohortes, aperçu, formateurs, questions ; l'extrait vidéo a été retiré le 26/11 à la demande de Nojah), avec le formulaire de la liste d'attente. Elle marche sans JavaScript ; `assets/fermee.js` ajoute la vérification avant l'envoi, le mur des certificats et les onglets.
- **Inscription en deux temps** : le formulaire envoie un e-mail « Confirme ton inscription » (lien valable 48 h). Sans confirmation, l'adresse est effacée au bout de 7 jours. La page répond pareil qu'une adresse soit nouvelle ou déjà inscrite (on ne peut pas savoir qui est sur la liste) ; une adresse déjà confirmée reçoit « Tu es déjà sur la liste ».
- **Désinscription** : un lien signé dans chaque e-mail de la liste, plus les en-têtes `List-Unsubscribe` (le « Se désabonner » de Gmail, en un clic). Ouvrir le lien ne suffit pas, il faut cliquer (les antivirus ouvrent les liens). La ligne est effacée.
- **Protections** : champ piège contre les robots, 10 inscriptions par heure au plus depuis une connexion, 3 e-mails par jour au plus à une même adresse.
- **Liste d'attente** (administrateur, `/admin/liste-attente`) : le nombre d'inscrits confirmés, par parcours, la liste, « Effacer » et l'export pour Excel (`.csv`, séparateur « ; », formules neutralisées).
- Réglages dans `app/config.php` : `ouverture` (vide = « 2027 », une date = compte à rebours), `compteur_min` (le compteur « X inscrits » s'affiche à partir de 25 confirmés), `hub`.
- **Pages légales** : `/confidentialite` (liste d'attente et comptes) et `/mentions-legales` (hébergeur OVH ; l'éditeur sera ajouté avant l'ouverture au public).

## Les e-mails (22/11)
- **Par où ils partent** : par l'hébergement (`mail()`), ou par la boîte e-mail du site si elle est branchée (page admin « E-mails », `/admin/courriel`, code dans `app/smtp.php` : `smtp.mail.ovh.net`, port 465 chiffré, 587 en secours). Par la boîte, l'expéditeur est l'adresse de la boîte (contact@noweiz.fr) ; le mot de passe est tapé par l'administrateur, gardé chiffré (clé dans `<données>/cle-courriel`), jamais réaffiché ; si la boîte refuse ou ne répond pas, l'e-mail part par l'hébergement et la page affiche la panne.
- **Problème ouvert au 22/11 : aucun e-mail du site n'arrive.** La boîte est débranchée, le site envoie par l'hébergement.
  - Le 21/11, les e-mails du site (par l'hébergement) arrivaient dans Gmail.
  - Le 22/11 : par l'hébergement, rien dans la boîte contact (11:58, 12:20, 14:47) ni dans Gmail (vers 15:50). OVH les compte comme envoyés, 0 erreur.
  - Le 22/11 par la boîte (15:16 à 15:46) : identification correcte (un faux mot de passe est refusé), OVH répond `250 2.0.0 Ok: queued as D81E0C0F46`, et rien n'arrive, ni dans la boîte contact ni dans Gmail, même un e-mail en texte simple sans lien ni en-tête particulier (15 e-mails, 9 variantes). Aucun retour d'erreur dans la boîte. Le compte n'est pas « bloqué pour spam ».
  - Écrits dans le webmail de la même boîte, les e-mails arrivent en quelques secondes (y compris avec le lien Discord ou un lien du site) : ce n'est donc ni le contenu, ni la boîte, ni les DNS (SPF et DKIM passent).
  - Écarté aussi : l'expéditeur d'enveloppe inexistant (corrigé à 14:45, sans effet).
  - Piste : le filtrage anti-spam d'OVH sur ce qui part des hébergements mutualisés (plusieurs cas identiques sur le forum OVH, « accepté mais non délivré », sans explication). À faire : retester le 23/11 ; si ça dure, ticket OVH avec l'heure et le numéro de file, ou un service d'envoi par API (Brevo, Mailjet : gratuits à ce volume).
- Sans la boîte : « Noweiz Academy <contact@noweiz.com> » (`courriel.expediteur`), enveloppe `courriel.retours` = contact@noweiz.com. Chez OVH, contact@noweiz.com est une redirection vers la boîte contact@noweiz.fr.
- Gabarit aux couleurs du site : bandeau noir avec l'icône (`assets/img/courriel-icone.png`), filet vert citron vers blanc, bouton vert. Mise en page en tableaux (Outlook).

## L'espace élève (22/11)
- **Pages** : `/espace` (tableau de bord : module de la semaine, prochain live, progression, classement, à venir), `/parcours` (les modules), `/module?id=` (la leçon, sa vidéo, la liste des leçons, le live du module), `/quiz?m=`, `/lives` (à venir et replays), `/classement`, `/certificat` (à imprimer ou enregistrer en PDF).
- **Règles** (`app/cours.php`) : le module n s'ouvre au départ de la cohorte + (n − 1) semaines, et seulement si le quiz du module n − 1 est validé (`quiz_seuil`, 70 %). Sans quiz, un module est validé quand ses leçons sont vues. Quiz corrigé sur le serveur ; raté, on dit quelles questions sont fausses sans donner les bonnes réponses ; on garde le meilleur essai. Points : 10 par leçon vue, 10 par bonne réponse (à valider par Nojah et Weizix). Le classement n'affiche que les prénoms.
- **Aperçu** : un administrateur ou un formateur qui n'est pas inscrit dans une cohorte la voit avec tout ouvert, sans rien enregistrer.
- **Vidéos** : rangées dans `<données>/videos/`, hors du site, sous un nom tiré au hasard. Lien signé (ce compte, cette connexion, 4 heures), accès revérifié à chaque morceau, morceaux de 4 Mo (206), refus si l'adresse est ouverte dans un onglet (`Sec-Fetch-Dest`), pas de bouton Télécharger dans le lecteur ni de menu « Enregistrer sous ». Rien n'empêche un élève de filmer son écran.
- **Cohorte d'essai** (administrateur, `/admin/essai`) : une cohorte d'order flow partie il y a 15 jours, avec les 8 modules de la trame, 5 leçons chacun, 3 questions par module (notions de base écrites pour l'essai, pas celles de Nojah), une vraie vidéo en module 3 si un fichier `video-essai.mp4` est posé dans le dossier de données, un live passé avec replay et le prochain live. On peut y inscrire d'autres comptes. Tout s'efface d'un clic (vidéo copiée comprise).
- La suppression d'un compte efface aussi ses inscriptions, leçons vues et quiz.

## L'espace formateur (22/11)
- **Pour qui** : l'administrateur (tous les parcours) et les formateurs de l'équipe d'un parcours (l'administrateur les ajoute dans « L'équipe du parcours »). Menu « Espace formateur ».
- **`/formateur`** : « Nouvelle leçon » (choisir le module, glisser la vidéo, le titre se remplit avec le nom du fichier, « Mettre la leçon en ligne »), « Programmer un live » (cohorte, jour, heure de Paris, titre, module, lien Discord), les modules (ajouter, monter, descendre), les cohortes (l'administrateur les crée), l'équipe.
- **`/formateur/module`** : titre et phrase du module, ses leçons (ordre, vidéo ou pas), son quiz (ajouter, modifier, effacer une question), « Voir comme un élève », effacer le module (avec confirmation).
- **`/formateur/lecon`** : la vidéo (glisser pour ajouter ou remplacer, retirer), le titre et le texte, effacer la leçon.
- **`/formateur/question`** : l'énoncé, 2 à 4 réponses, la bonne, l'explication. Les réponses sont mélangées pour chaque élève (toujours dans le même ordre pour lui).
- **`/formateur/cohorte`** : le suivi (élèves, à jour, en retard, pas commencé ; tableau de progression ; à relancer ; moyenne des quiz et question la plus ratée), les lives (effacer ; après le live, glisser le replay), les inscrits (inscrire un compte, retirer), les réglages (administrateur).
- **Envoi des vidéos** (`/formateur/envoi`, `assets/formateur.js`) : le navigateur découpe le fichier en morceaux (8 Mo au plus, toujours sous la limite de PHP), chaque morceau est renvoyé jusqu'à 4 fois si la connexion coupe, le serveur dit où il en est. À la fin : vérification que c'est une vidéo MP4 (boîte `ftyp`), rangement dans `<données>/videos/`, durée lue par le navigateur. Envois abandonnés effacés après un jour. Taille maximale : `video_max_mo` (4 Go). `/sante` donne `envoi_mo`, la limite de PHP.

## Ce qui n'existe pas encore
Le paiement (inscription automatique dans une cohorte), le lien avec Discord (rôles, salons), les présences aux lives.

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
- `app/smtp.php` : l'envoi des e-mails par la boîte du site (connexion chiffrée, mot de passe gardé chiffré, repli sur l'hébergement).
- `app/cours.php` : la formation (cohortes, modules, progression, quiz, classement, lives, vidéos protégées). `app/essai.php` : la cohorte d'essai. `app/formateur.php` : l'espace formateur (équipe, modules, leçons, questions, cohortes, lives, envoi des vidéos, suivi).
- `assets/` : le style (`maquette.css`, `comptes.css`, `fermee.css`), la police, les scripts, les images.
- `outils/serveur.php` : le serveur d'essai. `outils/essais.js` : les essais automatiques (`node outils/essais.js`).

## Mise en ligne
L'offre OVH Perso n'accepte qu'un seul site relié à GitHub, et c'est le HUB. Ce site est donc livré **par le dépôt du HUB**
(`AzulixNQ/nojah`), dans son sous-dossier `academy/` ; chez OVH, `academy.noweiz.com` (et l'ancienne adresse `test.noweiz.com`, renvoyée vers elle) pointe sur `hub/academy`.

1. Travailler ici (`AzulixNQ/noweiz-academy`, le code source), lancer `node outils/essais.js`.
2. Recopier ce dossier (sans `.git`) dans `academy/` du dépôt `nojah`, puis envoyer `nojah` sur GitHub.
3. Console OVH > Hébergements web > Mes sites > ⋮ de « Noweiz HUB » > Git > Déployer.
4. Contrôler `https://academy.noweiz.com/sante` (`donnees_niveaux` doit valoir 2 : les données sont à la racine de l'hébergement, hors du HUB).

`.htaccess` rend ce dossier introuvable sous une autre adresse que `academy.noweiz.com` (par exemple `noweiz.com/academy`).
