<?php
declare(strict_types=1);

page_debut('Confidentialité');
$test = (bool) reglage('test');
?>
<main class="wrap page texte" id="contenu">
  <h1 class="h1">Ce que le site garde sur toi</h1>
  <p class="intro">Le strict nécessaire pour te prévenir de l'ouverture et faire marcher ton compte. Rien n'est vendu ni partagé, et il n'y a ni publicité ni outil de suivi.</p>

  <h2 id="liste-attente">La liste d'attente</h2>
  <ul>
    <li><b>Ce qu'on garde</b> : ton prénom, ton e-mail, le parcours qui t'intéresse, la date de ton inscription et celle de ta confirmation.</li>
    <li><b>Pourquoi</b> : t'écrire au sujet de l'ouverture de la formation (la date, ta place prioritaire, les prix). Rien d'autre.</li>
    <li><b>Sur quelle base</b> : ton accord, donné en cochant la case du formulaire puis en cliquant sur le lien de confirmation. Tu peux le retirer quand tu veux.</li>
    <li><b>Combien de temps</b> : une inscription jamais confirmée est effacée au bout de 7 jours. Une inscription confirmée est gardée jusqu'à ta désinscription, et au plus tard 3 ans.</li>
    <li><b>Te désinscrire</b> : le lien en bas de chaque e-mail. Ton adresse est alors effacée, tout de suite.</li>
  </ul>

  <h2>Ton compte</h2>
  <ul>
    <li><b>Ton prénom, ton e-mail et ton pseudo Discord</b> (si tu le donnes) : pour t'identifier, t'écrire au sujet de ton compte et t'ouvrir le salon de ta cohorte.</li>
    <li><b>Ton mot de passe</b>, sous une forme brouillée : personne ne peut le lire, pas même l'équipe.</li>
    <li><b>Les dates</b> de création du compte et de ta dernière visite.</li>
    <li><b>Tes connexions en cours</b>, pour te garder connecté <?= (int) reglage('session_jours') ?> jours sur un appareil.</li>
    <li><b>Les essais ratés</b> (connexion, inscriptions trop nombreuses), sous une forme qui ne permet pas de retrouver ton e-mail ni ton adresse IP, pendant un jour au plus : ils servent à bloquer les robots.</li>
  </ul>
  <p>Le site t'écrit seulement pour ton compte : confirmer ton adresse, choisir un nouveau mot de passe, te prévenir d'un changement de mot de passe ou d'adresse. Tu peux voir et corriger tes informations, et <b>supprimer ton compte</b>, à tout moment depuis « Mon compte » : tout est effacé tout de suite.</p>

  <h2>Les témoins (cookies)</h2>
  <p>Deux ou trois petits fichiers, tous nécessaires : un pour ta connexion, un pour protéger les formulaires, un pour afficher un message d'une page à la suivante. Aucun ne sert à te suivre : il n'y a donc pas de bandeau à accepter.</p>

  <h2>Qui y a accès</h2>
  <p>L'équipe de <?= e((string) reglage('nom')) ?>. Les informations sont gardées chez OVHcloud, l'hébergeur du site, en France ; les e-mails partent de ses serveurs. OVHcloud n'a pas le droit de s'en servir pour son propre compte.</p>

  <h2>Tes droits</h2>
  <p>Tu peux demander à voir tes informations, à les corriger, à les faire effacer, à en limiter l'usage, à les récupérer, ou retirer ton accord. Pour toute demande, écris-nous à <a href="mailto:<?= e((string) reglage('contact')) ?>"><?= e((string) reglage('contact')) ?></a> ou sur le <a href="<?= e((string) reglage('discord')) ?>" target="_blank" rel="noopener">Discord</a> ; la réponse arrive sous un mois. Si elle ne te convient pas, tu peux saisir la CNIL sur <a href="https://www.cnil.fr/fr/plaintes" target="_blank" rel="noopener">cnil.fr</a>.</p>

<?php if ($test): ?>
  <p class="msg msg-info">Version de test : le nom du responsable du site et une adresse e-mail de contact seront indiqués ici avant l'ouverture au public.</p>
<?php endif; ?>
  <p><a class="btn btn-line" href="/">Revenir au site</a></p>
</main>
<?php
page_fin();
