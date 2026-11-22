<?php
declare(strict_types=1);

page_debut('Mentions légales');
?>
<main class="wrap page texte" id="contenu">
  <h1 class="h1">Mentions légales</h1>
  <p class="intro">Qui édite et qui héberge le site <?= e((string) reglage('nom')) ?>.</p>

  <h2>Éditeur du site</h2>
<?php if (reglage('test')): ?>
  <p class="msg msg-info">Version de test : l'éditeur du site (nom ou société, adresse, téléphone, numéro d'immatriculation) et le directeur de la publication seront indiqués ici avant l'ouverture au public.</p>
<?php endif; ?>

  <h2>Contact</h2>
  <p>Par e-mail : <a href="mailto:<?= e((string) reglage('contact')) ?>"><?= e((string) reglage('contact')) ?></a>. Ou sur le <a href="<?= e((string) reglage('discord')) ?>" target="_blank" rel="noopener">Discord</a>.</p>

  <h2>Hébergeur</h2>
  <p>OVH SAS, au capital de 50 000 000 €, RCS Lille Métropole 424 761 419 00045.<br>2 rue Kellermann, 59100 Roubaix, France. Téléphone : +33 9 72 10 10 07.</p>

  <h2>Avertissement sur le trading</h2>
  <p>Le trading comporte un risque de perte, qui peut dépasser la somme engagée sur certains produits. <?= e((string) reglage('nom')) ?> est une formation : Nojah et Weizix y partagent leur méthode, pas des conseils en investissement. Les payouts montrés sont ceux de Nojah ; ils ne promettent aucun résultat à qui que ce soit.</p>

  <h2>Contenus</h2>
  <p>Les textes, les vidéos et les images du site appartiennent à <?= e((string) reglage('nom')) ?> ou à leurs auteurs. Ils ne peuvent pas être repris sans accord.</p>

  <p><a class="btn btn-line" href="/">Revenir au site</a></p>
</main>
<?php
page_fin();
