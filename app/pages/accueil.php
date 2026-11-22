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
$titre = $nom . " : l'order flow avec Nojah, l'ICT avec Weizix";
$desc = "Une formation par cohortes : un module par semaine, des lives sur Discord. Ouverture en 2027. Inscris-toi sur la liste d'attente.";
$aria = static fn(string $c): string => isset($err[$c]) ? ' aria-invalid="true" aria-describedby="wl-' . $c . '-err"' : '';

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
<meta name="theme-color" content="#0B0B0C">
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
<link rel="stylesheet" href="<?= e(version_fichier('/assets/maquette.css')) ?>">
<link rel="stylesheet" href="<?= e(version_fichier('/assets/comptes.css')) ?>">
<link rel="stylesheet" href="<?= e(version_fichier('/assets/fermee.css')) ?>">
<script src="<?= e(version_fichier('/assets/fermee.js')) ?>" defer></script>
</head>
<body>
<a class="skip" href="#contenu">Aller au contenu</a>

<header class="top">
  <div class="wrap top-in">
    <div class="brand-box">
      <a class="brand" href="#haut"><?= e($nom) ?></a>
<?php if (reglage('test')): ?>
      <span class="tag">Version de test</span>
<?php endif; ?>
    </div>
    <nav class="top-nav" aria-label="Sections de la page">
      <a href="#parcours">Les parcours</a>
      <a href="#cohorte">Une cohorte</a>
      <a href="#apercu">L'espace élève</a>
      <a href="#formateurs">Les formateurs</a>
    </nav>
<?php if ($compte !== null): ?>
    <a class="btn btn-line btn-sm" href="/espace">Mon espace</a>
<?php else: ?>
    <a class="btn btn-main btn-sm" href="#liste">Liste d'attente</a>
<?php endif; ?>
  </div>
</header>

<main id="contenu">
<?php $ann = annonce_html(); if ($ann !== ''): ?>
  <div class="wrap annonce-haut"><?= $ann ?></div>
<?php endif; ?>
  <section class="hero" id="haut">
    <div class="lights" aria-hidden="true"></div>
    <div class="wrap">
      <div class="hero-grid">
        <div class="hero-copy">
          <p class="status"><i class="live" aria-hidden="true"></i>La liste d'attente est ouverte</p>
          <h1 class="lead"><span>L'order flow avec Nojah,</span> <span>l'ICT avec Weizix.</span></h1>
          <p class="sub">Une formation par cohortes : ta promo démarre à une date, un module s'ouvre chaque semaine, et les lives se passent sur Discord.</p>
        </div>

<?php if ($fin === false): ?>
        <div class="opening" id="ouverture">
          <p class="timer-label">Le site ouvre en</p>
          <div class="opening-row"><b>2027</b><span>La date exacte sera annoncée d'abord aux inscrits de la liste d'attente.</span></div>
        </div>
<?php else: ?>
        <div class="timer" id="compteur" role="timer" aria-label="Temps restant avant l'ouverture" data-fin="<?= (int) $fin ?>">
          <p class="timer-label">Le site ouvre dans</p>
          <div class="timer-row">
<?php $r = max(0, $fin - time()); foreach ([['t-j', intdiv($r, 86400), 'jours'], ['t-h', intdiv($r % 86400, 3600), 'heures'], ['t-m', intdiv($r % 3600, 60), 'minutes'], ['t-s', $r % 60, 'secondes']] as [$id, $n, $u]): ?>
            <div class="timer-unit"><b id="<?= $id ?>"><?= sprintf('%02d', $n) ?></b><span><?= $u ?></span></div>
<?php endforeach; ?>
          </div>
          <p class="timer-note">Ouverture le <?= e(date_fr($fin, true)) ?>, heure de Paris.</p>
        </div>
<?php endif; ?>

        <section class="join" id="liste" aria-labelledby="liste-titre"<?= !empty($err) || $alerte !== '' ? ' data-erreur' : '' ?>>
<?php if ($envoye): ?>
          <h2 tabindex="-1" id="liste-titre">Plus qu'une étape : confirme ton adresse.</h2>
          <p class="join-after">On vient de t'envoyer un e-mail. Ouvre-le et clique sur « Confirmer mon inscription » : c'est ce qui te met sur la liste.</p>
          <ol class="join-steps">
            <li>Pas d'e-mail dans 5 minutes ? Regarde dans les <b>indésirables</b> ou l'onglet <b>Promotions</b>.</li>
            <li>Le lien marche pendant <?= e(duree_lisible((int) reglage('confirmation_heures') * 60)) ?>.</li>
          </ol>
          <p class="join-after">En attendant, rejoins le Discord : c'est là que se passent les lives et les annonces.</p>
          <a class="btn btn-main btn-full" href="<?= e((string) reglage('discord')) ?>" target="_blank" rel="noopener">Rejoindre le Discord</a>
<?php else: ?>
          <h2 id="liste-titre">Inscris-toi sur la liste d'attente</h2>
          <ul class="join-perks">
            <li>Tu passes en premier sur les 50&nbsp;places.</li>
            <li>Tu connais la date d'ouverture avant tout le monde.</li>
          </ul>
          <form id="wl-form" method="post" action="/#liste" novalidate>
            <?= champ_jeton() ?>
            <div class="field">
              <label for="wl-prenom">Ton prénom</label>
              <input class="input" id="wl-prenom" name="prenom" type="text" autocomplete="given-name" maxlength="60" placeholder="Prénom" required value="<?= e($v['prenom']) ?>"<?= $aria('prenom') ?>>
              <p class="err" id="wl-prenom-err"<?= isset($err['prenom']) ? '' : ' hidden' ?>><?= e($err['prenom'] ?? 'Écris ton prénom.') ?></p>
            </div>
            <div class="field">
              <label for="wl-email">Ton e-mail</label>
              <input class="input" id="wl-email" name="email" type="email" autocomplete="email" inputmode="email" maxlength="190" placeholder="toi@exemple.fr" required autocapitalize="none" autocorrect="off" spellcheck="false" value="<?= e($v['email']) ?>"<?= $aria('email') ?>>
              <p class="err" id="wl-email-err"<?= isset($err['email']) ? '' : ' hidden' ?>><?= e($err['email'] ?? "Cette adresse n'est pas complète. Exemple : toi@exemple.fr") ?></p>
            </div>
            <fieldset>
              <legend>Le parcours qui t'intéresse</legend>
              <div class="pills">
                <label class="pill pill-of"><input type="radio" name="parcours" id="wl-p-of" value="of"<?= $v['parcours'] === 'of' ? ' checked' : '' ?>><span><i class="dot dot-of"></i>Order flow</span></label>
                <label class="pill pill-ict"><input type="radio" name="parcours" id="wl-p-ict" value="ict"<?= $v['parcours'] === 'ict' ? ' checked' : '' ?>><span><i class="dot dot-ict"></i>ICT</span></label>
                <label class="pill"><input type="radio" name="parcours" id="wl-p-pack" value="pack"<?= $v['parcours'] === 'pack' ? ' checked' : '' ?>><span>Les deux</span></label>
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
            <button type="submit" class="btn btn-main btn-full" id="wl-envoi">M'inscrire sur la liste</button>
            <p class="err" id="wl-envoi-err" role="alert"<?= $alerte !== '' ? '' : ' hidden' ?>><?= e($alerte) ?></p>
          </form>
<?php endif; ?>
<?php if ($montrer_compteur): ?>
          <p class="join-count"><i class="live" aria-hidden="true"></i><span><b><?= e(number_format($inscrits, 0, ',', "\u{202F}")) ?></b> inscrits</span></p>
<?php endif; ?>
        </section>
      </div>
      <div class="name-box"><p class="name" aria-hidden="true"><?= e($nom) ?></p></div>
    </div>
  </section>

  <section class="sec" id="parcours">
    <div class="wrap">
      <h2 class="h2">Deux parcours, trois façons de venir</h2>
      <p class="intro">Chaque parcours a ses cohortes. Tu prends l'un, l'autre, ou les deux ensemble.</p>
      <div class="cards sec-body">
        <article class="card l1">
          <p class="card-who"><i class="dot dot-of"></i>Avec Nojah</p>
          <h3>Order flow</h3>
          <p class="card-text">Lire qui achète et qui vend, en direct : carnet d'ordres, footprint, absorption, volume profile, VWAP.</p>
          <ul class="card-list">
            <li>Un module par semaine</li>
            <li>Un quiz par module</li>
            <li>Lives sur Discord et replays</li>
            <li>Un appel bilan avec Nojah, à la fin</li>
            <li>Certificat de fin de parcours</li>
          </ul>
          <div class="card-price"><b>599,99&nbsp;€</b><span>Payable en 2 à 4 fois</span><span>1&nbsp;199,99&nbsp;€ avec le mentorat</span></div>
          <a class="btn btn-line" href="#liste" data-parcours="wl-p-of">Être prévenu à l'ouverture</a>
        </article>
        <article class="card l2">
          <p class="card-who"><i class="dot dot-ict"></i>Avec Weizix</p>
          <h3>ICT</h3>
          <p class="card-text">La liquidité, la structure du marché et les déséquilibres de prix (fair value gaps).</p>
          <ul class="card-list">
            <li>Un module par semaine</li>
            <li>Un quiz par module</li>
            <li>Lives sur Discord et replays</li>
            <li>Un appel bilan avec Weizix, à la fin</li>
            <li>Certificat de fin de parcours</li>
          </ul>
          <div class="card-price"><b>349,99&nbsp;€</b><span>Payable en 2 à 4 fois</span><span>699,99&nbsp;€ avec le mentorat</span></div>
          <a class="btn btn-line" href="#liste" data-parcours="wl-p-ict">Être prévenu à l'ouverture</a>
        </article>
        <article class="card l12">
          <p class="card-who"><span class="dots"><i class="dot dot-of"></i><i class="dot dot-ict"></i></span>Avec Nojah et Weizix</p>
          <h3>Le pack</h3>
          <p class="card-text">Les deux parcours, pour lire le même marché de deux façons.</p>
          <ul class="card-list">
            <li>Tout l'order flow et tout l'ICT</li>
            <li>Les lives des deux cohortes</li>
            <li>Deux certificats</li>
            <li>Un appel bilan avec chacun, à la fin</li>
          </ul>
          <div class="card-price"><b>899,99&nbsp;€</b><span>Payable en 2 à 4 fois, 49,99&nbsp;€ de moins que les deux pris séparément</span><span>1&nbsp;799,99&nbsp;€ avec le mentorat</span></div>
          <a class="btn btn-main" href="#liste" data-parcours="wl-p-pack">Être prévenu à l'ouverture</a>
        </article>
      </div>
      <div class="plus">
        <div class="plus-copy">
          <h3>Le mentorat, en option</h3>
          <p>Après ta cohorte, 4 appels individuels de 45&nbsp;minutes avec ton formateur, dans les 8 semaines qui suivent. Vous regardez tes trades ensemble, un par un.</p>
        </div>
        <p class="plus-price"><b>Dès 699,99&nbsp;€</b><span>formation comprise : 699,99&nbsp;€ l'ICT, 1&nbsp;199,99&nbsp;€ l'order flow, 1&nbsp;799,99&nbsp;€ le pack</span></p>
      </div>
    </div>
  </section>

  <section class="sec" id="cohorte">
    <div class="wrap">
      <h2 class="h2">Comment se passe une cohorte</h2>
      <p class="intro">Une cohorte, c'est ta promo : 20 à 50 élèves qui avancent ensemble, au même rythme.</p>
      <ol class="steps sec-body">
        <li><p class="n">1</p><h3>Tu rejoins une promo</h3><p>Une date de départ, 20 à 50 élèves, un salon Discord réservé à ta cohorte.</p></li>
        <li><p class="n">2</p><h3>Un module par semaine</h3><p>Des vidéos courtes, puis un quiz. Le quiz validé ouvre le module suivant.</p></li>
        <li><p class="n">3</p><h3>Les lives sur Discord</h3><p>On revoit les exercices ensemble, tu poses tes questions. Le replay rejoint le module.</p></li>
        <li><p class="n">4</p><h3>Le bilan, puis tu gardes tout</h3><p>À la fin, 30&nbsp;minutes seul avec ton formateur pour faire le point. Tu gardes ton certificat, les cours et les replays à vie.</p></li>
      </ol>
      <div class="weeks">
        <p class="weeks-title">Exemple : ta cohorte en semaine 3</p>
        <ol>
          <li class="ok"><small>Semaine 1</small><b>Validé</b></li>
          <li class="ok"><small>Semaine 2</small><b>Validé</b></li>
          <li class="now"><small>Semaine 3</small><b>Ouvert</b></li>
<?php for ($s = 4; $s <= 8; $s++): ?>
          <li><small>Semaine <?= $s ?></small><b>Verrouillé</b></li>
<?php endfor; ?>
        </ol>
        <p class="weeks-note">Le module suivant s'ouvre la semaine d'après, une fois le quiz du module en cours validé.</p>
      </div>
    </div>
  </section>

  <section class="sec" id="apercu">
    <div class="wrap">
      <h2 class="h2">Ton espace élève</h2>
      <p class="intro">Tout est au même endroit : ton module de la semaine, le prochain live, ta progression et le classement de ta cohorte.</p>
      <div class="sec-body glow">
        <div class="tabs" role="group" aria-label="Écran à afficher" hidden>
          <button type="button" class="tab" aria-pressed="true" data-shot="espace">Tableau de bord</button>
          <button type="button" class="tab" aria-pressed="false" data-shot="modules">Mon parcours</button>
          <button type="button" class="tab" aria-pressed="false" data-shot="module">Un module</button>
        </div>
        <figure class="shot">
          <img id="shot-img" src="/assets/img/apercu-espace.webp" width="1440" height="900" loading="lazy" decoding="async" alt="Aperçu du tableau de bord de l'élève : module de la semaine, prochain live, progression et classement.">
        </figure>
        <p class="shot-cap"><span id="shot-cap">Le tableau de bord : ce que tu as à faire cette semaine, d'un coup d'œil.</span> Les noms, les titres et les chiffres de l'aperçu sont des exemples.</p>
      </div>
    </div>
  </section>

  <section class="sec" id="extrait">
    <div class="wrap clip">
      <div class="clip-copy">
        <h2 class="h2">Un extrait : l'absorption, par Nojah</h2>
        <p class="intro">Une minute pour comprendre pourquoi beaucoup d'achats ne font pas toujours monter le prix. Dans la formation, chaque notion a ses vidéos, son quiz et son live.</p>
      </div>
      <figure class="clip-video">
        <video controls playsinline preload="none" poster="/assets/video/absorption-v2-affiche.webp" src="/assets/video/absorption-v2.mp4" aria-label="Extrait vidéo : l'absorption expliquée par Nojah, 1 minute 04"></video>
      </figure>
    </div>
  </section>

  <section class="sec" id="formateurs">
    <div class="wrap">
      <h2 class="h2">Les formateurs</h2>
      <div class="cards sec-body">
        <article class="who l1">
          <h3>Nojah</h3>
          <dl>
            <div><dt>Sa méthode</dt><dd>L'order flow</dd></div>
            <div><dt>Son marché</dt><dd>Le Nasdaq, en futures : NQ et MNQ</dd></div>
            <div><dt>Comptes funded</dt><dd>Lucid Trading et Topstep, avec des payouts depuis juin 2026</dd></div>
            <div><dt>Ses payouts</dt><dd><span class="money">33&nbsp;115&nbsp;$</span></dd></div>
          </dl>
        </article>
        <article class="who l2">
          <h3>Weizix</h3>
          <dl>
            <div><dt>Sa méthode</dt><dd>L'ICT (Inner Circle Trader)</dd></div>
            <div><dt>L'ICT, en bref</dt><dd>La liquidité, la structure du marché et les déséquilibres de prix (fair value gaps)</dd></div>
          </dl>
        </article>
      </div>
    </div>
    <div class="proof">
      <div class="wrap proof-head">
        <h3>Les payouts de Nojah, certificat par certificat</h3>
        <p><a href="<?= e((string) reglage('hub')) ?>" target="_blank" rel="noopener">Voir tous les certificats sur Noweiz HUB</a></p>
      </div>
      <div class="wall" id="wall" aria-hidden="true"></div>
    </div>
  </section>

  <section class="sec" id="questions">
    <div class="wrap">
      <h2 class="h2">Questions fréquentes</h2>
      <dl class="faq sec-body">
        <div><dt>Quand ouvre le site ?</dt><dd>En 2027. La date exacte sera annoncée d'abord aux inscrits de la liste d'attente.</dd></div>
        <div><dt>À quoi sert la liste d'attente ?</dt><dd>Tu laisses ton e-mail et on te prévient à l'ouverture. Les inscrits passent en premier sur les 50&nbsp;places de la cohorte 1.</dd></div>
        <div><dt>Combien ça coûte ?</dt><dd>349,99&nbsp;€ le parcours ICT, 599,99&nbsp;€ le parcours order flow, 899,99&nbsp;€ les deux ensemble. Avec le mentorat : 699,99&nbsp;€, 1&nbsp;199,99&nbsp;€ et 1&nbsp;799,99&nbsp;€.</dd></div>
        <div><dt>Que faites-vous de mon e-mail ?</dt><dd>On s'en sert seulement pour t'écrire au sujet de l'ouverture. Tu peux te désinscrire quand tu veux, avec le lien en bas de chaque e-mail. Le détail est sur la page <a href="/confidentialite#liste-attente">Confidentialité</a>.</dd></div>
        <div><dt>Je garde l'accès après ma cohorte ?</dt><dd>Oui, à vie : les modules, les quiz et les replays des lives.</dd></div>
        <div><dt>Je pourrai payer en plusieurs fois ?</dt><dd>Oui, en 2 à 4 fois. L'accès reste ouvert tant que les échéances sont payées.</dd></div>
        <div><dt>Et si je rate un live ?</dt><dd>Le replay est ajouté au module concerné.</dd></div>
        <div><dt>Et après la formation ?</dt><dd>Tu as un appel bilan de 30&nbsp;minutes avec ton formateur, compris dans le prix. Pour continuer, le mentorat ajoute 4 appels individuels de 45&nbsp;minutes : il se choisit avec la formation (699,99&nbsp;€ l'ICT, 1&nbsp;199,99&nbsp;€ l'order flow, 1&nbsp;799,99&nbsp;€ le pack, formation comprise).</dd></div>
      </dl>
    </div>
  </section>

  <section class="sec sec-fin">
    <div class="lights" aria-hidden="true"></div>
    <div class="wrap end">
      <h2 class="h2">Sois là à l'ouverture.</h2>
      <a class="btn btn-main" href="#liste">M'inscrire sur la liste d'attente</a>
    </div>
  </section>
</main>

<footer class="foot">
  <div class="wrap foot-in">
    <p>Le trading comporte un risque de perte. Cette formation ne promet aucun gain : Nojah et Weizix y partagent leur méthode, pas des conseils en investissement.</p>
    <p class="foot-links"><a href="/confidentialite">Confidentialité</a><a href="/mentions-legales">Mentions légales</a><a href="<?= $compte !== null ? '/espace' : '/connexion' ?>" class="pale"><?= $compte !== null ? 'Mon espace' : 'Se connecter' ?></a></p>
  </div>
</footer>
</body>
</html>
