<?php
declare(strict_types=1);

/* La page de vente d'Omegga, le terminal d'Azulix (partenaire de Noweiz).
   Les prix affichés comptent déjà le code NOWEIZ (−10 % à vie) ; chaque bouton « Choisir » ouvre le paiement Whop
   de la formule, avec le code appliqué. Le paiement, la clé et l'abonnement sont chez Whop, au nom d'Azulix.
   Sans JavaScript : les deux prix (au mois, à l'année) et les deux boutons restent visibles. */

$om = reglage('omegga');
if (!is_array($om) || empty($om['visible'])) {
    http_response_code(404);
    page_message('Page introuvable', 'Cette page n\'existe pas.', 'L\'adresse est peut-être mal recopiée.', '/', "Revenir à l'accueil");
    exit;
}

$nom = (string) reglage('nom');
$compte = compte_courant();
$code = (string) ($om['code'] ?? 'NOWEIZ');
$contact = (string) ($om['contact'] ?? '');
$titre = "Omegga, le terminal order flow : l'offre partenaire de " . $nom;
$desc = "Le footprint, le DOM, le CVD, les gros trades et l'absorption pour le NQ et le MNQ, sur ton PC avec Quantower et Rithmic. −10 % à vie avec le code " . $code . '.';

$euros = static fn(float $x): string => number_format($x, 2, ',', "\u{202F}") . "\u{00A0}€";
$remise = static fn(float $x): float => round($x * 0.9, 2);       // le code : −10 %
// le lien de paiement Whop d'une formule, avec le code (et le lien d'affilié de Johan quand il existe)
$paiement = static function (string $formule, string $periode) use ($om, $code): string {
    $plan = (string) ($om['plans'][$formule][$periode] ?? '');
    if ($plan === '') return '#formules';
    $q = ['promoCode' => $code];
    if (!empty($om['affilie'])) $q['a'] = (string) $om['affilie'];
    return 'https://whop.com/checkout/' . rawurlencode($plan) . '/?' . http_build_query($q);
};

$formules = [
    'basic' => ['Basic', 19.99, 199.0, "L'order flow de base, pour lire le marché.", [
        'Footprint acheteurs / vendeurs, déséquilibres, delta et CVD',
        'Gros trades en bulles',
        'VWAP (séance, New York, semaine, mois), profil de volume et TPO',
        'Niveaux de la veille et de la nuit',
        'Carnet et DOM réglable',
        'Trading démo et réel, copieur, garde-fou, alertes et journal',
        "30 jours d'historique au démarrage",
    ]],
    'pro' => ['Pro', 39.99, 399.0, 'Tout Basic, plus les signaux.', [
        'Tout ce qu\'a Basic',
        'Divergences prix / CVD, et leur journal',
        'Absorption confirmée par le prix',
        'Heatmap du carnet et icebergs',
        'LVN',
        '« Mes indicateurs » : tes propres indicateurs en JavaScript',
        "2 mois d'historique au démarrage",
    ]],
    'max' => ['Max', 59.99, 599.0, "Tout Pro, plus l'analyse de tes trades.", [
        'Tout ce qu\'a Pro',
        'Swing Flow : les jambes du marché, avec leur CVD',
        'Zones de confluence avec un score',
        'Replay bougie par bougie',
        '« Mes stats » sur tes trades',
        "2 mois de trades un par un, puis jusqu'à 6 mois à la minute",
    ]],
];

// ce qu'Omegga montre, par usage ; la dernière colonne dit dans quelles formules
$fonctions = [
    ['Lire qui agresse', [
        ['Le footprint', 'Les achats et les ventes à chaque prix de la bougie, et les déséquilibres entre les deux.', 'Les 3 formules'],
        ['Le delta et le CVD', 'Qui a été le plus agressif, bougie par bougie et depuis le début de la séance.', 'Les 3 formules'],
        ['Les gros trades', 'Une bulle par gros ordre au marché, à son prix, plus grosse quand il y a plus de contrats.', 'Les 3 formules'],
        ['Les divergences', 'Le prix fait un nouveau sommet, le CVD non : Omegga trace le trait, et tu notes ce que tu en fais.', 'Pro et Max'],
        ["L'absorption", "Des ordres agressifs qui tapent un niveau sans le faire céder, marqués d'un losange.", 'Pro et Max'],
    ]],
    ['Voir le carnet', [
        ['Le DOM', 'Les acheteurs et les vendeurs en attente, le volume échangé à chaque prix et les trades des 30 dernières secondes.', 'Les 3 formules'],
        ['La heatmap', 'Où les gros ordres attendaient, et depuis combien de temps.', 'Pro et Max'],
        ['Les icebergs', 'Un ordre caché qui se recharge au même prix.', 'Pro et Max'],
    ]],
    ['Savoir où sont les niveaux', [
        ['Les VWAP', 'De la séance, de New York, de la semaine et du mois.', 'Les 3 formules'],
        ['Le profil de volume et le TPO', 'Le POC, la zone de valeur et le temps passé à chaque prix.', 'Les 3 formules'],
        ['Les niveaux de la veille et de la nuit', 'Le haut et le bas de l\'Asie et de Londres, la clôture et le POC de la veille.', 'Les 3 formules'],
        ['Les LVN', 'Les prix où il s\'est peu échangé, que le marché traverse vite.', 'Pro et Max'],
        ['Les confluences', 'Les zones où plusieurs niveaux se rejoignent, avec un score.', 'Max'],
        ['Swing Flow', 'Les jambes du marché tracées toutes seules : sommets et creux confirmés, HH / HL / LH / LL, points, durée et CVD de chaque jambe, Fibonacci. Une lecture, pas un signal.', 'Max'],
    ]],
    ['Trader et progresser', [
        ['Le trading démo et réel', 'Par Quantower, sur le graphique ou le DOM. Le réel est désactivé au départ.', 'Les 3 formules'],
        ['Le copieur', 'Tes ordres recopiés sur tes autres comptes.', 'Les 3 formules'],
        ['Le garde-fou', 'Il te stoppe à ta perte du jour, à ton nombre de trades, après une série de pertes ou avant une annonce.', 'Les 3 formules'],
        ['Les alertes et le journal', 'Un son quand un signal apparaît, et chaque trade noté.', 'Les 3 formules'],
        ['« Mes indicateurs »', 'Écris tes propres indicateurs en JavaScript.', 'Pro et Max'],
        ['Le replay et « Mes stats »', 'Revis une séance bougie par bougie, et regarde ce qui marche dans tes trades.', 'Max'],
    ]],
];

$etapes = [
    ['Prends ta formule', 'Au mois, les 48 premières heures sont gratuites. Ta clé de licence arrive tout de suite, sur Whop.'],
    ['Télécharge Omegga', 'Sur Whop, dans ta page Omegga : copie ta clé, clique sur « Download », colle-la et télécharge le kit.'],
    ['Installe-le', 'Extrais le zip, puis double-clique sur « Installer Omegga ». Si Windows prévient : « Informations complémentaires », puis « Exécuter quand même ».'],
    ['Connecte Quantower', 'Avec les identifiants Rithmic de ta prop firm et la connexion automatique au démarrage. Dans « Connection settings », coche « Enable \'Market by Order\' (MBO) mode », puis reconnecte-toi : sans lui, la heatmap et le carnet restent vides.'],
    ['Ajoute le pont', 'Sur un graphique NQ ou MNQ (contrat en cours), ajoute l\'indicateur « Omegga Bridge », puis enregistre ton espace de travail.'],
    ['Lance Omegga et colle ta clé', 'Double-clique sur l\'icône Omegga : elle ouvre Quantower s\'il est fermé. Colle ta clé, clique sur « Activer ».'],
];
// la vidéo d'installation : ses chapitres (en secondes), pour aller directement à une étape
$tuto = [
    'video' => '/assets/video/omegga-installation.mp4',
    'affiche' => '/assets/img/omegga-installation.jpg',
    'version' => '3',                 // à changer à chaque nouvelle vidéo : la vidéo est gardée 7 jours par les navigateurs
    'duree' => '13 min',
    'chapitres' => [[0, 'Ce qu\'il te faut'], [57, 'Ton abonnement'], [141, 'Ta clé'], [198, 'Tu as déjà une clé ?'], [325, 'L\'installation'], [419, 'Quantower'], [636, 'Lancer et activer'], [722, 'Au quotidien']],
];

if (!headers_sent()) entetes_securite();
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
<meta property="og:url" content="<?= e(url_site('/omegga')) ?>">
<meta property="og:image" content="<?= e(url_site('/assets/img/omegga-apercu.webp')) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="canonical" href="<?= e(url_site('/omegga')) ?>">
<link rel="icon" href="/assets/icone.svg" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/nojah-sans.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(version_fichier('/assets/v2.css')) ?>">
<script src="<?= e(version_fichier('/assets/rubans.js')) ?>" defer></script>
<script src="<?= e(version_fichier('/assets/v2.js')) ?>" defer></script>
<script src="<?= e(version_fichier('/assets/omegga.js')) ?>" defer></script>
<script src="<?= e(version_fichier('/assets/omegga-vente.js')) ?>" defer></script>
<script src="<?= e(version_fichier('/assets/omegga-tuto.js')) ?>" defer></script>
</head>
<body>
<a class="saut" href="#contenu">Aller au contenu</a>
<canvas id="rubans" aria-hidden="true"></canvas>
<div class="grain" aria-hidden="true"></div>

<div class="page">
<header class="haut">
  <div class="cadre haut-in">
    <a class="marque" href="/"><img src="/assets/icone.svg" alt="" width="28" height="28"><?= e($nom) ?></a>
<?php if (reglage('test')): ?>
    <span class="etiquette">Version de test</span>
<?php endif; ?>
    <nav class="haut-nav" aria-label="Sections de la page">
      <a href="#fonctions">Ce qu'il montre</a>
      <a href="#formules">Les formules</a>
      <a href="#installer">L'installer</a>
      <a href="#questions">Questions</a>
    </nav>
    <a class="bouton bouton-vert bouton-petit" href="#formules">Voir les formules</a>
  </div>
</header>

<main id="contenu">
  <section class="hero vente-hero" id="haut">
    <div class="cadre">
      <div class="vente-grille">
        <div class="hero-texte vente-texte">
          <p class="partenaire-tag"><img src="/assets/img/omegga-symbole.svg" width="22" height="20" alt=""><b>Notre partenaire</b></p>
          <h1 class="titre vente-titre"><span>Omegga,</span> <span class="deux">le terminal</span> <span class="deux">order flow.</span></h1>
          <p class="chapo">Le footprint, le DOM, le CVD, les gros trades et l'absorption, dans un seul terminal pour le NQ et le MNQ. Il tourne sur ton PC, avec Quantower et tes propres données Rithmic.</p>
          <div class="code-promo vente-code">
            <p class="code-promo-remise"><b>−10&nbsp;%</b><span>à vie avec le code</span></p>
            <p class="code-promo-code"><?= e($code) ?></p>
          </div>
          <p class="vente-code-note">Déjà compté dans les prix de cette page, et appliqué tout seul au paiement. Au mois, les 48 premières heures sont gratuites.</p>
          <div class="actions">
            <a class="bouton bouton-vert" href="#formules">Voir les formules</a>
            <a class="bouton bouton-verre" href="#installer">Comment l'installer</a>
          </div>
        </div>
        <div class="vente-scene">
          <i class="foyer" data-foyer aria-hidden="true"></i>
          <figure class="partenaire-ecran om" data-omegga aria-labelledby="om-titre">
            <div class="om-haut">
              <h2 class="om-nom" id="om-titre">Omegga en action</h2>
              <span class="etiquette">Exemple illustratif</span>
              <span class="om-direct"><i class="pouls" aria-hidden="true"></i>NQ</span>
            </div>
            <div class="om-onglets" role="group" aria-label="Choisir l'écran d'Omegga">
              <button type="button" data-scene="0" aria-pressed="true"><span>DOM</span><i aria-hidden="true"></i></button>
              <button type="button" data-scene="1" aria-pressed="false"><span>Scanner</span><i aria-hidden="true"></i></button>
              <button type="button" data-scene="2" aria-pressed="false"><span>Gros trades</span><i aria-hidden="true"></i></button>
            </div>
            <canvas class="om-toile" aria-hidden="true"></canvas>
            <img class="om-capture" src="/assets/img/omegga-apercu.webp" width="1600" height="900" decoding="async" alt="Omegga : deux graphiques du NQ en footprint, le TPO, les niveaux gamma et les options du QQQ.">
            <p class="carte om-lecture verre" data-om-lecture aria-live="polite"><small>Ce qu'on y voit</small><b>Les acheteurs montent</b><span>Ils prennent les ventes, niveau après niveau.</span></p>
            <figcaption class="om-legende"><span class="om-legende-vif">Le DOM, le scanner et les gros trades d'Omegga, dessinés pour l'exemple : les chiffres sont inventés.</span><span class="om-legende-fixe">Capture d'Omegga, avec des données simulées.</span></figcaption>
          </figure>
        </div>
      </div>
    </div>
  </section>

  <section class="bloc" id="fonctions" aria-labelledby="fonctions-titre">
    <div class="cadre">
      <div class="bloc-tete">
        <h2 class="titre-2" id="fonctions-titre"><span>Tout l'order flow,</span> <span>dans une seule fenêtre.</span></h2>
        <p class="bloc-texte">Omegga se branche sur Quantower et lit tes données Rithmic en direct. Voici ce qu'il te montre, et dans quelles formules.</p>
      </div>
      <div class="fonctions">
<?php foreach ($fonctions as [$groupe, $lignes]): ?>
        <section class="fonctions-groupe" aria-label="<?= e($groupe) ?>">
          <h3 class="fonctions-nom"><?= e($groupe) ?></h3>
          <dl class="fonctions-liste">
<?php foreach ($lignes as [$quoi, $texte, $ou]): ?>
            <div class="fonction">
              <dt><?= e($quoi) ?></dt>
              <dd><?= e($texte) ?></dd>
              <dd class="fonction-ou<?= $ou === 'Les 3 formules' ? '' : ' fonction-plus' ?>"><?= e($ou) ?></dd>
            </div>
<?php endforeach; ?>
          </dl>
        </section>
<?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="bloc" id="formules" aria-labelledby="formules-titre">
    <div class="cadre">
      <div class="formules-tete">
        <div class="bloc-tete">
          <h2 class="titre-2" id="formules-titre"><span>Trois formules,</span> <span>−10&nbsp;% à vie.</span></h2>
          <p class="bloc-texte">Les prix comptent déjà le code <?= e($code) ?>. Il reste appliqué à chaque renouvellement, tant que ton abonnement est actif.</p>
        </div>
        <div class="periode" role="group" aria-label="Payer au mois ou à l'année" data-periode-choix hidden>
          <button type="button" data-periode="mois" aria-pressed="true">Au mois</button>
          <button type="button" data-periode="an" aria-pressed="false">À l'année <small>≈&nbsp;2&nbsp;mois offerts</small></button>
        </div>
      </div>
      <div class="formules" data-formules>
<?php foreach ($formules as $cle => [$fnom, $mois, $an, $phrase, $contenu]): ?>
        <article class="formule verre<?= $cle === 'pro' ? ' formule-pro' : '' ?>" aria-labelledby="f-<?= e($cle) ?>">
          <h3 class="formule-nom" id="f-<?= e($cle) ?>">Omegga <?= e($fnom) ?></h3>
          <p class="formule-phrase"><?= e($phrase) ?></p>
          <div class="formule-prix formule-mois">
            <p><b><?= e($euros($remise($mois))) ?></b><span>par mois</span></p>
            <p class="formule-avant"><s><?= e($euros($mois)) ?></s> sans le code.</p>
            <p class="formule-essai">48&nbsp;h gratuites pour essayer</p>
          </div>
          <div class="formule-prix formule-an">
            <p><b><?= e($euros($remise($an))) ?></b><span>par an</span></p>
            <p class="formule-avant">Soit <?= e($euros(round($remise($an) / 12, 2))) ?> par mois. <s><?= e($euros($an)) ?></s> sans le code.</p>
          </div>
          <a class="bouton bouton-vert bouton-plein formule-choix formule-mois" href="<?= e($paiement($cle, 'mois')) ?>" rel="noopener sponsored">Essayer <?= e($fnom) ?> 48&nbsp;h<span class="sans-js"> (au mois)</span></a>
          <a class="bouton bouton-verre bouton-plein formule-choix formule-an" href="<?= e($paiement($cle, 'an')) ?>" rel="noopener sponsored">Choisir <?= e($fnom) ?><span class="sans-js"> à l'année</span></a>
          <ul class="formule-contenu">
<?php foreach ($contenu as $ligne): ?>
            <li<?= str_starts_with($ligne, 'Tout ce') ? ' class="formule-tout"' : '' ?>><?= e($ligne) ?></li>
<?php endforeach; ?>
          </ul>
        </article>
<?php endforeach; ?>
      </div>
      <div class="formules-pied">
        <p>Prix TTC. Paiement par carte, Apple Pay ou Google Pay, sécurisé par Whop. Une clé de licence pour 1 PC, livrée tout de suite ; mises à jour automatiques ; en français ou en anglais.</p>
        <p>Au mois, les 48 premières heures sont gratuites : tu ne paies rien le jour de l'inscription, Whop te prévient par e-mail environ 24&nbsp;h avant le premier paiement, et tu peux annuler avant depuis ton compte Whop. Un essai par personne.</p>
        <p>Sans engagement : tu arrêtes ton abonnement quand tu veux depuis ton compte Whop, et Omegga marche jusqu'à la fin de la période payée. Ta clé arrive dès le paiement : pour ça, tu renonces au délai de rétractation de 14 jours (on te le demande au paiement).</p>
      </div>
    </div>
  </section>

  <section class="bloc" id="installer" aria-labelledby="installer-titre">
    <div class="cadre installer">
<?php if (is_file(__DIR__ . '/../..' . $tuto['video'])): ?>
      <div class="tuto">
        <video class="tuto-video" controls preload="none" playsinline poster="<?= e($tuto['affiche'] . '?v=' . $tuto['version']) ?>" data-tuto aria-label="Vidéo : installer Omegga, étape par étape (<?= e($tuto['duree']) ?>)">
          <source src="<?= e($tuto['video'] . '?v=' . $tuto['version']) ?>" type="video/mp4">
        </video>
        <ol class="tuto-chap" data-tuto-chap hidden aria-label="Aller à un chapitre de la vidéo">
<?php foreach ($tuto['chapitres'] as $i => [$ct, $cnom]): ?>
          <li><button type="button" data-t="<?= (int) $ct ?>"><?= e(($i + 1) . '. ' . $cnom) ?><span><?= e(sprintf('%d:%02d', intdiv((int) $ct, 60), (int) $ct % 60)) ?></span></button></li>
<?php endforeach; ?>
        </ol>
        <p class="tuto-note">Sans voix : tout est écrit à l'écran. Mets en pause à chaque étape.</p>
      </div>
<?php endif; ?>
      <div class="installer-tete">
        <h2 class="titre-2" id="installer-titre"><span>De zéro à Omegga,</span> <span>en 30 minutes.</span></h2>
        <h3 class="installer-sous">Ce qu'il te faut</h3>
        <ul class="besoins">
          <li><b>Un PC sous Windows 10 ou 11</b><span>Omegga ne tourne pas sur Mac.</span></li>
          <li><b>Google Chrome</b><span>Omegga s'ouvre dans sa propre fenêtre Chrome.</span></li>
          <li><b>Quantower</b><span>La version gratuite suffit.</span></li>
          <li><b>Un compte connecté par Rithmic</b><span>Avec les données du CME pour le NQ et le MNQ, comme les comptes de prop firm de Lucid Trading.</span></li>
        </ul>
      </div>
      <ol class="etapes-om">
<?php foreach ($etapes as [$t, $d]): ?>
        <li><b><?= e($t) ?></b><span><?= e($d) ?></span></li>
<?php endforeach; ?>
      </ol>
    </div>
  </section>

  <section class="bloc" id="questions" aria-labelledby="questions-titre">
    <div class="cadre questions">
      <div class="bloc-tete">
        <h2 class="titre-2" id="questions-titre"><span>Questions</span> <span>fréquentes.</span></h2>
      </div>
      <div class="faq">
        <details open><summary>Le −10&nbsp;% dure combien de temps&nbsp;?</summary><p>À vie : il s'applique à chaque renouvellement, au mois comme à l'année, tant que ton abonnement reste actif. Le code <?= e($code) ?> est déjà mis quand tu cliques sur « Choisir ». S'il n'apparaît pas au paiement, tape-le dans « Code promo ».</p></details>
        <details><summary>Comment marche l'essai gratuit&nbsp;?</summary><p>Il existe sur les formules au mois. Tu mets ta carte, mais rien n'est prélevé pendant 48&nbsp;h : tu as Omegga en entier, avec ta clé. Whop t'envoie un rappel environ 24&nbsp;h avant le premier paiement. Pour ne pas continuer, annule depuis ton compte Whop avant la fin des 48&nbsp;h. Un essai par personne.</p></details>
        <details><summary>On m'a donné une clé, je n'ai rien acheté&nbsp;?</summary><p>Une clé qui commence par OMEGGA- (bêta-testeur, partenaire, cadeau) suffit : pas d'abonnement ni de compte Whop. Ouvre <b>omegga.azulix.workers.dev</b> dans Chrome, colle ta clé, télécharge le kit, puis installe Omegga et colle la même clé au lancement. Elle a sa formule et sa durée, par exemple Max pendant 1&nbsp;an. Tout est dans le chapitre « Tu as déjà une clé ? » de la vidéo.</p></details>
        <details><summary>Ça marche avec ma prop firm&nbsp;?</summary><p>Omegga marche avec tout compte connecté par Rithmic qui a les données du CME, comme les comptes de Lucid Trading. Avant de trader avec, vérifie les règles de ta prop firm sur les logiciels tiers.</p></details>
        <details><summary>Et sur Mac&nbsp;?</summary><p>Non : Omegga tourne sur Windows 10 ou 11, comme Quantower.</p></details>
        <details><summary>Omegga passe des ordres tout seul&nbsp;?</summary><p>Non. Il ne prend aucune décision : c'est toi qui passes tes ordres, sur le graphique ou le DOM. Le trading réel est désactivé au départ, et le copieur ne fait que recopier tes ordres sur tes autres comptes.</p></details>
        <details><summary>Je peux l'installer sur plusieurs PC&nbsp;?</summary><p>Une clé marche sur 1 PC. Pour changer de PC, libère l'ancien dans Paramètres, puis Licence, et entre ta clé sur le nouveau.</p></details>
        <details><summary>Où vont mes données&nbsp;?</summary><p>Tes données de marché restent sur ton PC : Omegga les lit dans ton Quantower et ne les envoie nulle part. Seule ta clé est vérifiée de temps en temps, et Omegga marche aussi quelques jours sans internet.</p></details>
        <details><summary>Comment j'arrête mon abonnement&nbsp;?</summary><p>Depuis ton compte Whop, quand tu veux. Il ne se renouvelle plus, et Omegga marche jusqu'à la fin de la période déjà payée.</p></details>
        <details><summary>Qui vend Omegga&nbsp;?</summary><p>Azulix, qui l'édite. Le paiement et l'abonnement passent par Whop. <?= e($nom) ?> est partenaire d'Omegga et touche une commission sur les abonnements pris depuis cette page ou avec le code <?= e($code) ?>.<?php if ($contact !== ''): ?> Une question, un souci&nbsp;? Écris à <a href="mailto:<?= e($contact) ?>"><?= e($contact) ?></a>.<?php endif; ?></p></details>
      </div>
    </div>
  </section>

  <section class="bloc bloc-fin vente-fin" aria-labelledby="fin-titre">
    <div class="cadre">
      <div class="vente-fin-in verre">
        <h2 class="titre-2" id="fin-titre"><span>Prêt à lire</span> <span>le flux&nbsp;?</span></h2>
        <p class="bloc-texte">Choisis ta formule : le code <?= e($code) ?> est déjà appliqué.</p>
        <a class="bouton bouton-vert" href="#formules">Voir les formules</a>
      </div>
    </div>
  </section>
</main>

<footer class="pied">
  <div class="cadre pied-in">
    <p>Partenaire. Omegga est édité et vendu par Azulix ; <?= e($nom) ?> touche une commission sur les abonnements pris depuis cette page ou avec le code <?= e($code) ?>. C'est un outil d'analyse et d'exécution, pas un service de signaux ni un conseil en investissement. Le trading de contrats à terme comporte un risque élevé de perte.</p>
    <p class="pied-liens"><a href="/">L'Academy</a><a href="/confidentialite">Confidentialité</a><a href="/mentions-legales">Mentions légales</a><?php if ($contact !== ''): ?><a href="mailto:<?= e($contact) ?>" class="pale">Contact Omegga</a><?php endif; ?></p>
  </div>
</footer>
</div>
</body>
</html>
