<?php
declare(strict_types=1);

/* Aperçu du nouveau style (22/11) : le haut de la future page de vente, pour le valider avant de refaire le reste.
   Rubans de lumière (assets/rubans.js), verre et profondeur, footprint vivant (assets/footprint.js), et le ruban qui devient
   le parcours des 8 semaines. Le vert ne sert que de lumière : jamais en fond.
   Les textes reprennent les faits de la page actuelle. Le footprint est un exemple dessiné : ses chiffres sont inventés,
   et c'est écrit dessus et dessous. */

$nom = (string) reglage('nom');

/* Le footprint d'exemple, tel qu'il s'affiche sans animation (les mêmes six premières bougies que dans assets/footprint.js).
   Chaque bougie : ses niveaux de prix (ligne 1 = le plus haut), puis ventes × achats.
   Marques : a = déséquilibre acheteur, v = déséquilibre vendeur, f = les achats absorbés. o / c : ouverture et clôture. */
$bougies = [
    1 => ['o' => 11, 'c' => 8, 'n' => [8 => [12, 48], 9 => [31, 140, 'a'], 10 => [44, 168, 'a'], 11 => [58, 96], 12 => [40, 22]]],
    2 => ['o' => 9, 'c' => 6, 'n' => [6 => [9, 61], 7 => [22, 154, 'a'], 8 => [36, 188, 'a'], 9 => [51, 120], 10 => [47, 35]]],
    3 => ['o' => 7, 'c' => 3, 'n' => [3 => [18, 97, 'a'], 4 => [27, 176, 'a'], 5 => [39, 203, 'a'], 6 => [62, 110], 7 => [55, 41], 8 => [30, 26]]],
    4 => ['o' => 3, 'c' => 2, 'n' => [1 => [212, 1240, 'f'], 2 => [187, 980, 'f'], 3 => [96, 310, 'a'], 4 => [74, 122], 5 => [60, 58]]],
    5 => ['o' => 2, 'c' => 7, 'n' => [2 => [44, 12], 3 => [201, 63, 'v'], 4 => [244, 71, 'v'], 5 => [190, 88, 'v'], 6 => [133, 90], 7 => [97, 104]]],
    6 => ['o' => 7, 'c' => 8, 'n' => [6 => [28, 9], 7 => [76, 31, 'v'], 8 => [64, 52], 9 => [22, 18]]],
];
$prix_haut = 24315.0;
$pas = 0.5;
$nb = static fn(int $n): string => number_format($n, 0, ',', "\u{202F}");
$absorbes = $bougies[4]['n'][1][1] + $bougies[4]['n'][2][1];
$certificats = count(glob(RACINE . '/assets/img/certificats/*.webp') ?: []);

if (!headers_sent()) entetes_securite();
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Aperçu du nouveau style, <?= e($nom) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#050606">
<meta name="color-scheme" content="dark">
<link rel="icon" href="/assets/icone.svg" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/nojah-sans.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(version_fichier('/assets/v2.css')) ?>">
<script src="<?= e(version_fichier('/assets/rubans.js')) ?>" defer></script>
<script src="<?= e(version_fichier('/assets/footprint.js')) ?>" defer></script>
<script src="<?= e(version_fichier('/assets/v2.js')) ?>" defer></script>
</head>
<body>
<a class="saut" href="#contenu">Aller au contenu</a>
<canvas id="rubans" aria-hidden="true"></canvas>
<div class="grain" aria-hidden="true"></div>

<div class="page">
<header class="haut">
  <div class="cadre haut-in">
    <a class="marque" href="#haut"><img src="/assets/icone.svg" alt="" width="28" height="28"><?= e($nom) ?></a>
    <span class="etiquette">Aperçu du nouveau style</span>
    <span class="etat"><i class="pouls" aria-hidden="true"></i>Ouverture en 2027</span>
    <a class="bouton bouton-vert bouton-petit" href="/#liste">Liste d'attente</a>
  </div>
</header>

<main id="contenu">
  <section class="hero" id="haut">
    <div class="cadre">
      <div class="hero-grille">
        <div class="hero-texte">
          <p class="etat"><i class="pouls" aria-hidden="true"></i>La liste d'attente est ouverte</p>
          <h1 class="titre"><span>L'order flow</span> <span>avec Nojah,</span> <span class="deux">l'ICT</span> <span class="deux">avec Weizix.</span></h1>
          <p class="chapo">Une formation en cohorte de 8 semaines. Un module s'ouvre chaque semaine, tu le valides par un quiz, et les lives se passent sur Discord avec ta promo.</p>
          <p class="sous-actions">Ouverture en 2027. Un e-mail à l'ouverture, rien d'autre.</p>
          <div class="actions">
            <a class="bouton bouton-vert" href="/#liste">M'inscrire sur la liste d'attente</a>
            <a class="bouton bouton-verre" href="/#extrait"><i class="lecture" aria-hidden="true"></i>Voir un extrait, 1 minute</a>
          </div>
        </div>

        <div class="scene-fp">
          <i class="foyer" data-foyer aria-hidden="true"></i>
          <div class="relief" data-relief>
            <figure class="fp verre" data-footprint aria-labelledby="fp-titre">
              <div class="fp-haut">
                <h2 id="fp-titre">Le footprint, niveau par niveau</h2>
                <span class="etiquette">Exemple illustratif</span>
                <span class="fp-cours"><i class="pouls" aria-hidden="true"></i>Bougie en cours</span>
              </div>
              <canvas class="fp-toile" aria-hidden="true"></canvas>
              <div class="fp-grille" aria-hidden="true">
                <p class="fp-note"><b>Absorption</b><span><?= e($nb($absorbes)) ?> achats sur deux niveaux, et le prix ne passe pas.</span></p>
<?php foreach ($bougies as $k => $b): foreach ($b['n'] as $l => $c):
    $marque = $c[2] ?? '';
?>
                <span class="fp-c l<?= $l ?> k<?= $k ?><?= $k === 6 ? ' vif' : '' ?>"><i<?= $marque === 'v' ? ' class="vente"' : '' ?>><?= e($nb($c[0])) ?></i><i<?= $marque === 'a' ? ' class="achat"' : ($marque === 'f' ? ' class="fort"' : '') ?>><?= e($nb($c[1])) ?></i></span>
<?php endforeach; ?>
                <i class="fp-corps k<?= $k ?> l<?= min($b['o'], $b['c']) ?> h<?= abs($b['o'] - $b['c']) + 1 ?> <?= $b['c'] < $b['o'] ? 'monte' : 'baisse' ?>"></i>
<?php endforeach; ?>
<?php for ($l = 1; $l <= 12; $l++): ?>
                <span class="fp-prix l<?= $l ?> k7"><?= e(number_format($prix_haut - ($l - 1) * $pas, 2, ',', "\u{202F}")) ?></span>
<?php endfor; ?>
              </div>
              <div class="fp-pied" aria-hidden="true">
<?php foreach ($bougies as $k => $b):
    $d = 0;
    foreach ($b['n'] as $c) $d += $c[1] - $c[0];
?>
                <span class="k<?= $k ?> <?= $d >= 0 ? 'plus' : 'moins' ?>"><?= ($d > 0 ? '+' : ($d < 0 ? "\u{2212}" : '')) . e($nb(abs($d))) ?></span>
<?php endforeach; ?>
                <span class="fp-prix k7">Delta</span>
              </div>
              <figcaption class="fp-legende">À gauche les ventes, à droite les achats, niveau de prix par niveau de prix. En vert, là où les acheteurs dominent ; en blanc, les vendeurs. Les chiffres de cet exemple sont inventés.</figcaption>
            </figure>
            <p class="carte carte-lecture verre" data-lecture aria-hidden="true"><small>Ce qu'on y lit</small><b>Absorption</b><span><?= e($nb($absorbes)) ?> achats sur deux niveaux, et le prix ne passe pas.</span></p>
            <p class="carte carte-cvd verre" aria-hidden="true"><small>Delta cumulé</small><b data-cvd>0</b><canvas data-cvd-courbe></canvas></p>
          </div>
        </div>
      </div>

      <dl class="faits verre" id="bref">
        <div><dt>8 semaines</dt><dd>du premier module à ton bilan avec ton formateur</dd></div>
        <div><dt>2 parcours</dt><dd>l'order flow, l'ICT, ou les deux ensemble</dd></div>
        <div><dt>70&nbsp;%</dt><dd>au quiz d'un module pour ouvrir le suivant</dd></div>
        <div><dt>20 à 50</dt><dd>élèves par cohorte, et des lives sur Discord</dd></div>
        <div><dt><?= $certificats ?> certificats</dt><dd>de payout de Nojah, à vérifier un par un</dd></div>
      </dl>
    </div>
  </section>

  <section class="parcours" id="rythme" data-parcours data-avance="7" aria-labelledby="parcours-titre">
    <div class="parcours-colle">
      <div class="cadre parcours-in">
        <div class="parcours-tete">
          <h2 class="titre-2" id="parcours-titre"><span>Huit semaines,</span> <span>un module à la fois.</span></h2>
          <p class="parcours-texte">Chaque semaine : des vidéos courtes, un quiz, puis le live sur Discord avec ta promo. À 70&nbsp;% au quiz, le module suivant s'ouvre.</p>
        </div>
        <ol class="chemin">
<?php for ($s = 1; $s <= 8; $s++): ?>
          <li class="etape"><i class="noeud" data-noeud aria-hidden="true"></i><span class="etape-nom">Semaine <?= $s ?></span></li>
<?php endfor; ?>
        </ol>
        <p class="parcours-fin verre">Et à la fin : 30&nbsp;minutes seul avec ton formateur pour faire le point, puis ton certificat.</p>
      </div>
    </div>
  </section>

  <section class="suite">
    <div class="cadre">
      <div class="suite-boite verre">
        <h2>L'aperçu s'arrête ici.</h2>
        <p>Seul le haut de la page est refait dans le nouveau style. Les parcours et leurs prix, la cohorte, l'espace élève, les formateurs, les questions et le formulaire suivront, puis les pages de compte, l'espace élève et le HUB.</p>
        <div class="actions">
          <a class="bouton bouton-verre" href="/">Revoir la page actuelle</a>
        </div>
      </div>
    </div>
  </section>
</main>

<footer class="pied">
  <div class="cadre">
    <p>Le trading comporte un risque de perte. Cette formation ne promet aucun gain : Nojah et Weizix y partagent leur méthode, pas des conseils en investissement.</p>
  </div>
</footer>
</div>
</body>
</html>
