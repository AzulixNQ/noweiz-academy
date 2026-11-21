<?php
declare(strict_types=1);

page_debut('Confidentialité');
?>
<main class="wrap page texte" id="contenu">
  <h1 class="h1">Ce que le site garde sur toi</h1>
  <p class="intro">Le strict nécessaire pour faire marcher ton compte. Rien n'est vendu ni partagé, et il n'y a ni publicité ni outil de suivi.</p>

  <h2>Ce qui est gardé</h2>
  <ul>
    <li><b>Ton prénom, ton e-mail et ton pseudo Discord</b> (si tu le donnes) : pour t'identifier, t'écrire au sujet de ton compte et t'ouvrir le salon de ta cohorte.</li>
    <li><b>Ton mot de passe</b>, sous une forme brouillée : personne ne peut le lire, pas même l'équipe.</li>
    <li><b>Les dates</b> de création du compte et de ta dernière visite.</li>
    <li><b>Tes connexions en cours</b>, pour te garder connecté <?= (int) reglage('session_jours') ?> jours sur un appareil.</li>
    <li><b>Les essais de connexion ratés</b>, sous une forme qui ne permet pas de retrouver ton e-mail ni ton adresse IP, pendant un jour au plus : ils servent à bloquer quelqu'un qui essaierait des mots de passe à la chaîne.</li>
  </ul>

  <h2>Les témoins (cookies)</h2>
  <p>Deux ou trois petits fichiers, tous nécessaires : un pour ta connexion, un pour protéger les formulaires, un pour afficher un message d'une page à la suivante. Aucun ne sert à te suivre : il n'y a donc pas de bandeau à accepter.</p>

  <h2>Les e-mails</h2>
  <p>Le site t'écrit seulement pour ton compte : confirmer ton adresse, choisir un nouveau mot de passe, te prévenir d'un changement de mot de passe ou d'adresse.</p>

  <h2>Tes droits</h2>
  <p>Tu peux voir et corriger tes informations, et <b>supprimer ton compte</b>, à tout moment depuis « Mon compte » : tout est effacé tout de suite. Pour toute autre demande, écris-nous sur le <a href="<?= e((string) reglage('discord')) ?>" target="_blank" rel="noopener">Discord</a>.</p>

  <h2>Où sont les données</h2>
  <p>Chez OVHcloud, sur l'hébergement du site.</p>

<?php if (reglage('test')): ?>
  <p class="msg msg-info">Version de test : le nom et l'adresse du responsable du site seront indiqués ici avant l'ouverture au public.</p>
<?php endif; ?>
  <p><a class="btn btn-line" href="/">Revenir au site</a></p>
</main>
<?php
page_fin();
