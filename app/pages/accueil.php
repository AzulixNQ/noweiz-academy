<?php
declare(strict_types=1);

/* L'accueil : la page de présentation de la formation (site fermé) et la liste d'attente.
   Le formulaire marche sans JavaScript : il est envoyé ici, puis la page revient avec « C'est presque fini ». */

$err = [];
$v = ['prenom' => '', 'email' => '', 'parcours' => 'pack'];
$envoye = false;
$alerte = '';

if (methode() === 'POST') {
    verifier_formulaire();
    $v['prenom'] = champ_recu('prenom', 80);
    $v['email'] = champ_recu('email', 190);
    $p = champ_recu('parcours', 10);
    $v['parcours'] = isset(PARCOURS[$p]) ? $p : 'pack';

    $m = verifier_prenom($v['prenom']);
    if ($m !== '') $err['prenom'] = $m;
    $m = verifier_email($v['email']);
    if ($m !== '') $err['email'] = $m;
    if (champ_recu('accord', 5) === '') $err['accord'] = 'Coche la case : sans ton accord, on ne peut pas t\'écrire à l\'ouverture.';

    $cle_ip = cle_limite('liste-ip', adresse_visiteur());
    if (champ_recu('site', 200) !== '') {
        // Le champ piège est rempli : un robot. Même réponse qu'une vraie inscription, rien n'est gardé.
        annoncer('liste-envoyee');
        rediriger('/#liste');
    } elseif (empty($err) && essais($cle_ip, 3600) >= 10) {
        $alerte = 'Trop d\'inscriptions depuis ta connexion. Réessaie dans ' . attente_minutes($cle_ip, 3600) . ' min.';
    } elseif (empty($err)) {
        noter_essai($cle_ip);
        if (liste_inscrire($v['prenom'], normaliser_email($v['email']), $v['parcours'])) {
            annoncer('liste-envoyee');
            rediriger('/#liste');
        }
        $alerte = "L'e-mail de confirmation n'est pas parti. Réessaie dans un instant ; si ça continue, préviens-nous sur Discord.";
    }
} else {
    $a = annonce();
    if ($a !== null && $a[0] === 'liste-envoyee') $envoye = true;
    elseif ($a !== null && ($t = texte_annonce($a[0], $a[1])) !== null) $GLOBALS['na_annonce'] = bloc_message($t[0], $t[1]);
}

$nom = (string) reglage('nom');
$compte = compte_courant();
$inscrits = liste_nombre();
$montrer_compteur = $inscrits >= (int) reglage('compteur_min');
$ouverture = (string) reglage('ouverture');
$fin = $ouverture !== '' ? strtotime($ouverture) : false;
$annee = reglage('compteur') === 'annee';
$prix = (bool) reglage('prix_visibles');
$titre = $nom . " : l'order flow avec Nojah, l'ICT avec Weizix";
$desc = "Une formation par cohortes : des modules pas à pas, des lives sur Discord. Ouverture en 2027. Inscris-toi sur la liste d'attente.";
$aria = static fn(string $c): string => isset($err[$c]) ? ' aria-invalid="true" aria-describedby="wl-' . $c . '-err"' : '';

/* Le footprint d'exemple du haut de page, tel qu'il s'affiche sans animation (les six premières bougies de assets/footprint.js).
   Chaque bougie : ses niveaux de prix (ligne 1 = le plus haut), puis ventes × achats. Les chiffres sont inventés, et c'est écrit
   sur la page. Marques : a = les acheteurs dominent, v = les vendeurs dominent, f = les achats absorbés. o / c : ouverture, clôture. */
$bougies = [
    1 => ['o' => 11, 'c' => 8, 'n' => [8 => [12, 48], 9 => [31, 140, 'a'], 10 => [44, 168, 'a'], 11 => [58, 96], 12 => [40, 22]]],
    2 => ['o' => 9, 'c' => 6, 'n' => [6 => [9, 61], 7 => [22, 154, 'a'], 8 => [36, 188, 'a'], 9 => [51, 120], 10 => [47, 35]]],
    3 => ['o' => 7, 'c' => 3, 'n' => [3 => [18, 97, 'a'], 4 => [27, 176, 'a'], 5 => [39, 203, 'a'], 6 => [62, 110], 7 => [55, 41], 8 => [30, 26]]],
    4 => ['o' => 3, 'c' => 2, 'n' => [1 => [212, 1240, 'f'], 2 => [187, 980, 'f'], 3 => [96, 310, 'a'], 4 => [74, 122], 5 => [60, 58]]],
    5 => ['o' => 2, 'c' => 7, 'n' => [2 => [44, 12], 3 => [201, 63, 'v'], 4 => [244, 71, 'v'], 5 => [190, 88, 'v'], 6 => [133, 90], 7 => [97, 104]]],
    6 => ['o' => 7, 'c' => 8, 'n' => [6 => [28, 9], 7 => [76, 31, 'v'], 8 => [64, 52], 9 => [22, 18]]],
];
$nb = static fn(int $n): string => number_format($n, 0, ',', "\u{202F}");
$absorbes = $bougies[4]['n'][1][1] + $bougies[4]['n'][2][1];
$certificats = count(glob(RACINE . '/assets/img/certificats/*.webp') ?: []);

if (!headers_sent()) {
    entetes_securite();
    // Le formulaire est en bas de page : son jeton (qui pose un témoin au premier passage) doit exister avant que la page parte.
    jeton_formulaire();
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titre) ?></title>
<meta name="description" content="<?= e($desc) ?>">
<?php if (reglage('test')): ?>
<meta name="robots" content="noindex, nofollow">
<?php endif; ?>
<meta name="theme-color" content="#050606">
<meta name="color-scheme" content="dark">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($titre) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<meta property="og:url" content="<?= e(url_site('/')) ?>">
<meta property="og:image" content="<?= e(url_site('/assets/img/partage.png')) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="canonical" href="<?= e(url_site('/')) ?>">
<link rel="icon" href="/assets/icone.svg" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/nojah-sans.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(version_fichier('/assets/v2.css')) ?>">
<script src="<?= e(version_fichier('/assets/rubans.js')) ?>" defer></script>
<script src="<?= e(version_fichier('/assets/footprint.js')) ?>" defer></script>
<script src="<?= e(version_fichier('/assets/v2.js')) ?>" defer></script>
<script src="<?= e(version_fichier('/assets/fermee.js')) ?>" defer></script>
</head>
<body>
<svg class="sprite" aria-hidden="true" focusable="false">
<symbol id="i-telegram" viewBox="0 0 24 24"><path d="M21.2 3.6 2.9 10.7c-.9.4-.9 1.6 0 1.9l4.5 1.6 1.7 5.3c.3.8 1.3 1 1.9.4l2.6-2.5 4.5 3.3c.7.5 1.7.1 1.9-.7l2.9-14.8c.2-1-.8-1.9-1.7-1.6Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="m7.6 14 10-7.1-7.8 8.6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></symbol>
  <symbol id="i-discord" viewBox="0 0 24 24"><path d="M8.3 6.4A15 15 0 0 1 12 6c1.3 0 2.5.1 3.7.4 1.8 2.5 2.7 5.3 2.6 8.6a12.7 12.7 0 0 1-4 2l-.9-1.6M8.3 6.4c-1.8 2.5-2.7 5.3-2.6 8.6a12.7 12.7 0 0 0 4 2l.9-1.6M7.6 14.7c2.9 1.5 5.9 1.5 8.8 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="9.7" cy="11.6" r="1.25" fill="currentColor"/><circle cx="14.3" cy="11.6" r="1.25" fill="currentColor"/></symbol>
  <symbol id="i-tiktok" viewBox="0 0 24 24"><path d="M14 3.5v11a3.8 3.8 0 1 1-3.8-3.8M14 3.5c.5 2.5 2.2 4.2 4.8 4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></symbol>
  <symbol id="i-instagram" viewBox="0 0 24 24"><rect x="3.5" y="3.5" width="17" height="17" rx="5" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="3.9" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="17.2" cy="6.8" r="1.05" fill="currentColor"/></symbol>
</svg>
<a class="saut" href="#contenu">Aller au contenu</a>
<canvas id="rubans" aria-hidden="true"></canvas>
<div class="grain" aria-hidden="true"></div>

<div class="page">
<header class="haut">
  <div class="cadre haut-in">
    <a class="marque" href="#haut"><img src="/assets/icone.svg" alt="" width="28" height="28"><?= e($nom) ?></a>
<?php if (reglage('test')): ?>
    <span class="etiquette">Version de test</span>
<?php endif; ?>
    <nav class="haut-nav" aria-label="Sections de la page">
      <a href="#rythme">Le rythme</a>
      <a href="#parcours">Les parcours</a>
      <a href="#apercu">L'espace élève</a>
      <a href="#formateurs">Les formateurs</a>
    </nav>
<?php if ($compte !== null): ?>
    <a class="bouton bouton-verre bouton-petit" href="/espace">Mon espace</a>
<?php else: ?>
    <a class="bouton bouton-vert bouton-petit" href="#liste">Liste d'attente</a>
<?php endif; ?>
  </div>
</header>

<main id="contenu">
<?php $ann = annonce_html(); if ($ann !== ''): ?>
  <div class="cadre annonce-haut"><?= $ann ?></div>
<?php endif; ?>
  <section class="hero" id="haut">
    <div class="cadre">
      <div class="hero-grille">
        <div class="hero-texte">
          <p class="etat"><i class="pouls" aria-hidden="true"></i>La liste d'attente est ouverte</p>
          <h1 class="titre"><span>L'order flow</span> <span>avec Nojah,</span> <span class="deux">l'ICT</span> <span class="deux">avec Weizix.</span></h1>
          <p class="chapo">Une formation en cohorte, avec ta promo. Les modules s'ouvrent un par un, tu valides chacun par un quiz, et les lives se passent sur Discord.</p>
<?php if ($fin === false): ?>
          <p class="sous-actions" id="ouverture">Ouverture en 2027. La date exacte sera annoncée d'abord aux inscrits de la liste d'attente.</p>
<?php else: ?>
          <div class="compteur" id="compteur" role="timer" aria-label="Temps restant avant l'ouverture" data-fin="<?= (int) $fin ?>">
            <p class="compteur-titre"><?= $annee ? 'Ouverture en ' . e(date('Y', $fin)) . '. Il reste' : 'Le site ouvre dans' ?></p>
            <div class="compteur-rang">
<?php $r = max(0, $fin - time()); foreach ([['t-j', intdiv($r, 86400), 'jours'], ['t-h', intdiv($r % 86400, 3600), 'heures'], ['t-m', intdiv($r % 3600, 60), 'minutes'], ['t-s', $r % 60, 'secondes']] as [$id, $n, $u]): ?>
              <div class="compteur-case"><b id="<?= $id ?>"><?= sprintf('%02d', $n) ?></b><span><?= $u ?></span></div>
<?php endforeach; ?>
            </div>
            <p class="compteur-note"><?= $annee ? 'avant ' . e(date('Y', $fin)) . '. La date exacte sera annoncée d\'abord aux inscrits de la liste d\'attente.' : 'Ouverture le ' . e(date_fr($fin, true)) . ', heure de Paris.' ?></p>
          </div>
<?php endif; ?>
          <div class="actions">
            <a class="bouton bouton-vert" href="#liste">M'inscrire sur la liste d'attente</a>
          </div>
<?php $reseaux = (array) reglage('reseaux'); if ($reseaux): ?>
          <ul class="reseaux" aria-label="Nos réseaux">
<?php foreach ($reseaux as [$ic, $nomr, $lien]): ?>
            <li><a href="<?= e((string) $lien) ?>" target="_blank" rel="noopener"><svg class="reseau-ico" aria-hidden="true"><use href="#i-<?= e((string) $ic) ?>"/></svg><span><?= e((string) $nomr) ?></span></a></li>
<?php endforeach; ?>
          </ul>
<?php endif; ?>
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
                <span class="fp-prix l<?= $l ?> k7"><?= e(number_format(24315.0 - ($l - 1) * 0.5, 2, ',', "\u{202F}")) ?></span>
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
        <div><dt>Pas à pas</dt><dd>un module à la fois, jusqu'à ton bilan avec ton formateur</dd></div>
        <div><dt>2 parcours</dt><dd>l'order flow, l'ICT, ou les deux ensemble</dd></div>
        <div><dt>70&nbsp;%</dt><dd>au quiz d'un module pour ouvrir le suivant</dd></div>
        <div><dt>20 à 50</dt><dd>élèves par cohorte, et des lives sur Discord</dd></div>
        <div><dt><?= $certificats ?> certificats</dt><dd>de payout de Nojah et Weizix, à vérifier un par un</dd></div>
      </dl>
    </div>
  </section>

  <section class="parcours" id="rythme" data-chemin data-avance="7" aria-labelledby="parcours-titre">
    <div class="parcours-colle">
      <div class="cadre parcours-in">
        <div class="parcours-tete">
          <h2 class="titre-2" id="parcours-titre"><span>Ta promo,</span> <span>un module à la fois.</span></h2>
          <p class="parcours-texte">Ta cohorte, c'est ta promo : 20 à 50 élèves qui avancent au même rythme. À chaque module, des vidéos courtes, un quiz, puis le live sur Discord. À 70&nbsp;% au quiz, le module suivant s'ouvre.</p>
        </div>
        <ol class="chemin">
<?php foreach (['Vidéos', 'Quiz', 'Live', 'Vidéos', 'Quiz', 'Live', '…', 'Bilan'] as $etape): /* 8 étapes : le ruban lumineux passe par 8 points (rubans.js) ; la durée des formations n'est pas encore fixée */ ?>
          <li class="etape"><i class="noeud" data-noeud aria-hidden="true"></i><span class="etape-nom"><?= e($etape) ?></span></li>
<?php endforeach; ?>
        </ol>
        <p class="parcours-fin verre">Et à la fin : 30&nbsp;minutes seul avec ton formateur pour faire le point, ton certificat, et l'accès aux cours et aux replays à vie.</p>
      </div>
    </div>
  </section>

  <section class="bloc" id="parcours" aria-labelledby="prix-titre">
    <div class="cadre">
      <div class="bloc-tete">
        <h2 class="titre-2" id="prix-titre"><span>Deux parcours,</span> <span>trois façons de venir.</span></h2>
        <p class="bloc-texte">Chaque parcours a ses cohortes. Tu prends l'un, l'autre, ou les deux ensemble. Tout se paie en 2 à 4 fois.</p>
      </div>
      <div class="offres">
        <article class="offre offre-of verre">
          <p class="offre-qui"><i class="point point-of" aria-hidden="true"></i>Avec Nojah</p>
          <h3>Order flow</h3>
          <p class="offre-texte">Lire qui achète et qui vend, en direct : carnet d'ordres, footprint, absorption, volume profile, VWAP.</p>
          <ul class="offre-liste">
            <li>Des modules pas à pas, un quiz par module</li>
            <li>Lives sur Discord et replays</li>
            <li>Un appel bilan avec Nojah, à la fin</li>
            <li>Certificat de fin de parcours</li>
          </ul>
<?php if ($prix): ?>
          <p class="offre-prix"><b>599,99&nbsp;€</b><span>1&nbsp;199,99&nbsp;€ avec le mentorat</span></p>
<?php else: ?>
          <p class="offre-prix offre-prix-bientot"><b>Prix annoncé bientôt</b><span>d'abord aux inscrits de la liste d'attente</span></p>
<?php endif; ?>
          <a class="bouton bouton-verre" href="#liste" data-parcours="wl-p-of">Être prévenu à l'ouverture</a>
        </article>
        <article class="offre offre-ict verre">
          <p class="offre-qui"><i class="point point-ict" aria-hidden="true"></i>Avec Weizix</p>
          <h3>ICT</h3>
          <p class="offre-texte">La liquidité, la structure du marché et les déséquilibres de prix (fair value gaps).</p>
          <ul class="offre-liste">
            <li>Des modules pas à pas, un quiz par module</li>
            <li>Lives sur Discord et replays</li>
            <li>Un appel bilan avec Weizix, à la fin</li>
            <li>Certificat de fin de parcours</li>
          </ul>
<?php if ($prix): ?>
          <p class="offre-prix"><b>349,99&nbsp;€</b><span>699,99&nbsp;€ avec le mentorat</span></p>
<?php else: ?>
          <p class="offre-prix offre-prix-bientot"><b>Prix annoncé bientôt</b><span>d'abord aux inscrits de la liste d'attente</span></p>
<?php endif; ?>
          <a class="bouton bouton-verre" href="#liste" data-parcours="wl-p-ict">Être prévenu à l'ouverture</a>
        </article>
        <article class="offre offre-pack verre">
          <p class="offre-qui"><span class="points"><i class="point point-of" aria-hidden="true"></i><i class="point point-ict" aria-hidden="true"></i></span>Avec Nojah et Weizix</p>
          <h3>Le pack</h3>
          <p class="offre-texte">Les deux parcours, pour lire le même marché de deux façons.</p>
          <ul class="offre-liste">
            <li>Tout l'order flow et tout l'ICT</li>
            <li>Les lives des deux cohortes</li>
            <li>Un appel bilan avec chacun, à la fin</li>
            <li>Deux certificats</li>
          </ul>
<?php if ($prix): ?>
          <p class="offre-prix"><b>899,99&nbsp;€</b><span>49,99&nbsp;€ de moins que les deux pris séparément</span><span>1&nbsp;799,99&nbsp;€ avec le mentorat</span></p>
<?php else: ?>
          <p class="offre-prix offre-prix-bientot"><b>Prix annoncé bientôt</b><span>d'abord aux inscrits de la liste d'attente</span></p>
<?php endif; ?>
          <a class="bouton bouton-vert" href="#liste" data-parcours="wl-p-pack">Être prévenu à l'ouverture</a>
        </article>
      </div>
      <div class="mentorat verre">
        <div>
          <h3>Le mentorat, en option</h3>
          <p>Après ta cohorte, 4 appels individuels de 45&nbsp;minutes avec ton formateur. Vous regardez tes trades ensemble, un par un.</p>
        </div>
<?php if ($prix): ?>
        <p class="mentorat-prix"><b>Dès 699,99&nbsp;€</b><span>formation comprise : 699,99&nbsp;€ l'ICT, 1&nbsp;199,99&nbsp;€ l'order flow, 1&nbsp;799,99&nbsp;€ le pack</span></p>
<?php endif; ?>
      </div>
    </div>
  </section>

  <section class="bloc" id="apercu" aria-labelledby="apercu-titre">
    <div class="cadre">
      <div class="bloc-tete">
        <h2 class="titre-2" id="apercu-titre"><span>Ton espace élève.</span></h2>
        <p class="bloc-texte">Tout est au même endroit : ton module de la semaine, le prochain live, ta progression et le classement de ta cohorte.</p>
      </div>
      <div class="tabs onglets" role="group" aria-label="Écran à afficher" hidden>
        <button type="button" class="onglet" aria-pressed="true" data-shot="espace">Tableau de bord</button>
        <button type="button" class="onglet" aria-pressed="false" data-shot="modules">Mon parcours</button>
        <button type="button" class="onglet" aria-pressed="false" data-shot="module">Un module</button>
      </div>
      <figure class="ecran verre">
        <img id="shot-img" src="/assets/img/apercu-espace.webp" width="1440" height="900" loading="lazy" decoding="async" alt="Aperçu du tableau de bord de l'élève : module de la semaine, prochain live, progression et classement.">
      </figure>
      <p class="ecran-note"><span id="shot-cap">Le tableau de bord : ce que tu as à faire cette semaine, d'un coup d'œil.</span> Les noms, les titres et les chiffres de l'aperçu sont des exemples.</p>
    </div>
  </section>

  <section class="bloc" id="formateurs" aria-labelledby="formateurs-titre">
    <div class="cadre">
      <div class="bloc-tete">
        <h2 class="titre-2" id="formateurs-titre"><span>Les formateurs.</span></h2>
      </div>
      <div class="formateurs">
        <article class="formateur formateur-of verre">
          <h3>Nojah</h3>
          <dl>
            <div><dt>Sa méthode</dt><dd>L'order flow</dd></div>
            <div><dt>Son marché</dt><dd>Le Nasdaq, en futures : NQ et MNQ</dd></div>
            <div><dt>Comptes funded</dt><dd>Lucid Trading et Topstep, tradés avec Weizix</dd></div>
          </dl>
        </article>
        <article class="formateur formateur-ict verre">
          <h3>Weizix</h3>
          <dl>
            <div><dt>Sa méthode</dt><dd>L'ICT (Inner Circle Trader)</dd></div>
            <div><dt>L'ICT, en bref</dt><dd>La liquidité, la structure du marché et les déséquilibres de prix (fair value gaps)</dd></div>
            <div><dt>Comptes funded</dt><dd>Lucid Trading et Topstep, tradés avec Nojah</dd></div>
          </dl>
        </article>
      </div>
    </div>
    <div class="preuves">
      <div class="cadre preuves-tete">
        <div>
          <h3>Leurs payouts, certificat par certificat</h3>
          <p class="preuves-total"><span class="gain">34&nbsp;674&nbsp;$</span> depuis juin 2026. Nojah et Weizix tradent ensemble, sur les mêmes comptes : les certificats sont au nom de Nojah.</p>
        </div>
        <p><a href="<?= e((string) reglage('hub')) ?>" target="_blank" rel="noopener">Voir tous les certificats sur Noweiz HUB</a></p>
      </div>
      <div class="wall" id="wall" aria-hidden="true"></div>
    </div>
  </section>

<?php $om = reglage('omegga'); if (is_array($om) && !empty($om['visible'])): ?>
  <section class="bloc" id="omegga" aria-labelledby="omegga-titre">
    <div class="cadre">
      <div class="partenaire verre">
        <i class="partenaire-lueur" aria-hidden="true"></i>
        <div class="partenaire-tete">
          <p class="partenaire-tag"><img src="/assets/img/omegga-symbole.svg" width="22" height="20" alt=""><b>Notre partenaire</b><span>Publicité</span></p>
          <h2 class="titre-2" id="omegga-titre"><span>Omegga,</span> <span>le terminal order flow.</span></h2>
        </div>
        <div class="partenaire-texte">
          <p class="bloc-texte">Le footprint, le CVD, la VWAP, les profils de volume, le TPO et l'absorption, dans un seul terminal pour le NQ et le MNQ. Il tourne sur ton PC, avec Quantower et tes propres données Rithmic.</p>
          <div class="code-promo">
            <p class="code-promo-remise"><b>−10&nbsp;%</b><span>à vie avec le code</span></p>
            <p class="code-promo-code"><?= e((string) $om['code']) ?></p>
          </div>
          <ul class="partenaire-prix" aria-label="Prix par mois avec le code">
            <li><span>Basic</span><b>17,99&nbsp;€</b><s>19,99&nbsp;€</s></li>
            <li><span>Pro</span><b>35,99&nbsp;€</b><s>39,99&nbsp;€</s></li>
            <li><span>Max</span><b>53,99&nbsp;€</b><s>59,99&nbsp;€</s></li>
          </ul>
          <p class="partenaire-note">Par mois avec le code, sur chaque renouvellement. Aussi à l'année.</p>
          <a class="bouton bouton-vert" href="<?= e((string) $om['lien']) ?>" target="_blank" rel="noopener sponsored">Découvrir Omegga</a>
          <p class="partenaire-mention">Publicité. Omegga est édité par Azulix, partenaire de Noweiz : Noweiz touche une commission sur les abonnements pris avec le code. C'est un outil d'analyse, pas un service de signaux ni un conseil en investissement. Le trading de contrats à terme comporte un risque élevé de perte.</p>
        </div>
        <figure class="partenaire-ecran">
          <img src="/assets/img/omegga-apercu.webp" width="1600" height="900" loading="lazy" decoding="async" alt="Omegga : deux graphiques du NQ en footprint, le TPO, les niveaux gamma et les options du QQQ.">
          <figcaption>Capture d'Omegga, avec des données simulées.</figcaption>
        </figure>
      </div>
    </div>
  </section>
<?php endif; ?>

  <section class="bloc" id="questions" aria-labelledby="questions-titre">
    <div class="cadre questions">
      <div class="bloc-tete">
        <h2 class="titre-2" id="questions-titre"><span>Questions</span> <span>fréquentes.</span></h2>
      </div>
      <div class="faq">
        <details open><summary>Quand ouvre le site ?</summary><p>En 2027. La date exacte sera annoncée d'abord aux inscrits de la liste d'attente.</p></details>
        <details><summary>À quoi sert la liste d'attente ?</summary><p>Tu laisses ton e-mail et on te prévient à l'ouverture. Les inscrits passent en premier sur les 50&nbsp;places de la cohorte 1.</p></details>
<?php if ($prix): ?>
        <details><summary>Combien ça coûte ?</summary><p>349,99&nbsp;€ le parcours ICT, 599,99&nbsp;€ le parcours order flow, 899,99&nbsp;€ les deux ensemble. Avec le mentorat : 699,99&nbsp;€, 1&nbsp;199,99&nbsp;€ et 1&nbsp;799,99&nbsp;€.</p></details>
<?php else: ?>
        <details><summary>Combien ça coûte ?</summary><p>Les prix seront annoncés d'abord aux inscrits de la liste d'attente, avant l'ouverture.</p></details>
<?php endif; ?>
        <details><summary>Je pourrai payer en plusieurs fois ?</summary><p>Oui, en 2 à 4 fois. L'accès reste ouvert tant que les échéances sont payées.</p></details>
        <details><summary>Je garde l'accès après ma cohorte ?</summary><p>Oui, à vie : les modules, les quiz et les replays des lives.</p></details>
        <details><summary>Et si je rate un live ?</summary><p>Le replay est ajouté au module concerné.</p></details>
        <details><summary>Et après la formation ?</summary><p>Tu as un appel bilan de 30&nbsp;minutes avec ton formateur, compris dans le prix. Pour continuer, le mentorat ajoute 4 appels individuels de 45&nbsp;minutes : il se choisit avec la formation<?= $prix ? ' (699,99&nbsp;€ l\'ICT, 1&nbsp;199,99&nbsp;€ l\'order flow, 1&nbsp;799,99&nbsp;€ le pack, formation comprise)' : '' ?>.</p></details>
        <details><summary>Que faites-vous de mon e-mail ?</summary><p>On s'en sert seulement pour t'écrire au sujet de l'ouverture. Tu peux te désinscrire quand tu veux, avec le lien en bas de chaque e-mail. Le détail est sur la page <a href="/confidentialite#liste-attente">Confidentialité</a>.</p></details>
      </div>
    </div>
  </section>

  <section class="bloc bloc-fin" aria-labelledby="liste-titre">
    <div class="cadre">
      <div class="inscription verre" id="liste"<?= !empty($err) || $alerte !== '' ? ' data-erreur' : '' ?>>
        <i class="inscription-lueur" aria-hidden="true"></i>
<?php if ($envoye): ?>
        <div class="inscription-texte">
          <h2 class="titre-2" tabindex="-1" id="liste-titre"><span>Plus qu'une étape :</span> <span>confirme ton adresse.</span></h2>
          <p class="bloc-texte">On vient de t'envoyer un e-mail. Ouvre-le et clique sur « Confirmer mon inscription » : c'est ce qui te met sur la liste.</p>
        </div>
        <div class="inscription-form">
          <ol class="apres">
            <li>Pas d'e-mail dans 5 minutes ? Regarde dans les <b>indésirables</b> ou l'onglet <b>Promotions</b>.</li>
            <li>Le lien marche pendant <?= e(duree_lisible((int) reglage('confirmation_heures') * 60)) ?>.</li>
            <li>En attendant, rejoins le Discord : c'est là que se passent les lives et les annonces.</li>
          </ol>
          <a class="bouton bouton-vert bouton-plein" href="<?= e((string) reglage('discord')) ?>" target="_blank" rel="noopener">Rejoindre le Discord</a>
        </div>
<?php else: ?>
        <div class="inscription-texte">
          <h2 class="titre-2" id="liste-titre">Inscris-toi sur la liste d'attente</h2>
          <ul class="avantages">
            <li>Tu passes en premier sur les 50&nbsp;places de la cohorte 1.</li>
            <li>Tu connais la date d'ouverture avant tout le monde.</li>
            <li>Un e-mail à l'ouverture, rien d'autre.</li>
          </ul>
<?php if ($montrer_compteur): ?>
          <p class="inscrits"><i class="pouls" aria-hidden="true"></i><span><b><?= e(number_format($inscrits, 0, ',', "\u{202F}")) ?></b> inscrits</span></p>
<?php endif; ?>
        </div>
        <form class="inscription-form" id="wl-form" method="post" action="/#liste" novalidate>
          <?= champ_jeton() ?>
          <div class="champ">
            <label for="wl-prenom">Ton prénom</label>
            <input class="saisie" id="wl-prenom" name="prenom" type="text" autocomplete="given-name" maxlength="60" placeholder="Prénom" required value="<?= e($v['prenom']) ?>"<?= $aria('prenom') ?>>
            <p class="err" id="wl-prenom-err"<?= isset($err['prenom']) ? '' : ' hidden' ?>><?= e($err['prenom'] ?? 'Écris ton prénom.') ?></p>
          </div>
          <div class="champ">
            <label for="wl-email">Ton e-mail</label>
            <input class="saisie" id="wl-email" name="email" type="email" autocomplete="email" inputmode="email" maxlength="190" placeholder="toi@exemple.fr" required autocapitalize="none" autocorrect="off" spellcheck="false" value="<?= e($v['email']) ?>"<?= $aria('email') ?>>
            <p class="err" id="wl-email-err"<?= isset($err['email']) ? '' : ' hidden' ?>><?= e($err['email'] ?? "Cette adresse n'est pas complète. Exemple : toi@exemple.fr") ?></p>
          </div>
          <fieldset class="choix">
            <legend>Le parcours qui t'intéresse</legend>
            <div class="pastilles">
              <label class="pastille pastille-of"><input type="radio" name="parcours" id="wl-p-of" value="of"<?= $v['parcours'] === 'of' ? ' checked' : '' ?>><span><i class="point point-of" aria-hidden="true"></i>Order flow</span></label>
              <label class="pastille pastille-ict"><input type="radio" name="parcours" id="wl-p-ict" value="ict"<?= $v['parcours'] === 'ict' ? ' checked' : '' ?>><span><i class="point point-ict" aria-hidden="true"></i>ICT</span></label>
              <label class="pastille"><input type="radio" name="parcours" id="wl-p-pack" value="pack"<?= $v['parcours'] === 'pack' ? ' checked' : '' ?>><span>Les deux</span></label>
            </div>
          </fieldset>
          <div class="piege" aria-hidden="true">
            <label for="wl-site">Laisse ce champ vide</label>
            <input id="wl-site" name="site" type="text" tabindex="-1" autocomplete="off">
          </div>
          <label class="accord" for="wl-accord">
            <input type="checkbox" id="wl-accord" name="accord" value="1" required<?= isset($err['accord']) ? ' aria-invalid="true" aria-describedby="wl-accord-err"' : '' ?>>
            <span>J'accepte de recevoir les e-mails de <?= e($nom) ?> sur l'ouverture. <a href="/confidentialite#liste-attente">Ce qu'on fait de ton e-mail</a></span>
          </label>
          <p class="err" id="wl-accord-err"<?= isset($err['accord']) ? '' : ' hidden' ?>>Coche la case : sans ton accord, on ne peut pas t'écrire à l'ouverture.</p>
          <button type="submit" class="bouton bouton-vert bouton-plein" id="wl-envoi">M'inscrire sur la liste</button>
          <p class="err" id="wl-envoi-err" role="alert"<?= $alerte !== '' ? '' : ' hidden' ?>><?= e($alerte) ?></p>
        </form>
<?php endif; ?>
      </div>
    </div>
  </section>
</main>

<footer class="pied">
  <div class="cadre pied-in">
    <p>Le trading comporte un risque de perte. Cette formation ne promet aucun gain : Nojah et Weizix y partagent leur méthode, pas des conseils en investissement.</p>
<?php if ($reseaux): ?>
    <ul class="reseaux reseaux-pied" aria-label="Nos réseaux">
<?php foreach ($reseaux as [$ic, $nomr, $lien]): ?>
      <li><a href="<?= e((string) $lien) ?>" target="_blank" rel="noopener" aria-label="<?= e((string) $nomr) ?>"><svg class="reseau-ico" aria-hidden="true"><use href="#i-<?= e((string) $ic) ?>"/></svg></a></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
    <p class="pied-liens"><a href="/confidentialite">Confidentialité</a><a href="/mentions-legales">Mentions légales</a><a href="<?= $compte !== null ? '/espace' : '/connexion' ?>" class="pale"><?= $compte !== null ? 'Mon espace' : 'Se connecter' ?></a></p>
  </div>
</footer>
</div>
</body>
</html>
