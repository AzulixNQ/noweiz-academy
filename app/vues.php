<?php
declare(strict_types=1);

/* L'habillage des pages : en-tête, espace connecté, champs de formulaire, annonces. Le style vient de la maquette validée. */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function date_fr(?int $ts, bool $heure = false): string
{
    if (!$ts) return 'jamais';
    $mois = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $j = (int) date('j', $ts);
    $d = ($j === 1 ? '1er' : $j) . ' ' . $mois[(int) date('n', $ts) - 1] . ' ' . date('Y', $ts);
    return $heure ? $d . ' à ' . date('H:i', $ts) : $d;
}

function version_fichier(string $chemin): string
{
    $f = RACINE . $chemin;
    return $chemin . '?v=' . (is_file($f) ? substr((string) filemtime($f), -6) : '0');
}

/** Les annonces connues : code → [genre, message]. Le détail (un nombre) remplace %s. */
function texte_annonce(string $code, string $detail = ''): ?array
{
    $liste = [
        'compte-cree' => ['ok', "Ton compte est créé. On t'a envoyé un e-mail pour confirmer ton adresse : regarde aussi dans les indésirables."],
        'compte-cree-sans-mail' => ['info', "Ton compte est créé. L'e-mail de confirmation n'est pas parti : tu peux le renvoyer depuis « Mon compte »."],
        'deconnecte' => ['ok', 'Tu es déconnecté.'],
        'profil' => ['ok', "C'est enregistré."],
        'email-change' => ['ok', "Ton adresse est changée. On t'a envoyé un e-mail pour la confirmer."],
        'email-change-sans-mail' => ['info', "Ton adresse est changée. L'e-mail de confirmation n'est pas parti : renvoie-le depuis cette page."],
        'mdp-change' => ['ok', 'Ton mot de passe est changé. Les autres appareils sont déconnectés.'],
        'mdp-nouveau' => ['ok', 'Ton mot de passe est changé. Tu peux te connecter.'],
        'sessions-fermees' => ['ok', 'Appareils déconnectés : %s.'],
        'sessions-aucune' => ['info', "Aucun autre appareil n'était connecté."],
        'confirmation-envoyee' => ['ok', 'E-mail envoyé. Regarde aussi dans les indésirables.'],
        'confirmation-echec' => ['err', "L'e-mail n'est pas parti. Réessaie dans un instant."],
        'adresse-confirmee' => ['ok', 'Ton adresse e-mail est confirmée.'],
        'compte-supprime' => ['ok', 'Ton compte est supprimé.'],
        'invitation-annulee' => ['ok', 'Invitation annulée : son lien ne marche plus.'],
        'role-change' => ['ok', 'Rôle changé.'],
        'trop' => ['err', 'Trop de demandes. Réessaie dans quelques minutes.'],
    ];
    if (!isset($liste[$code])) return null;
    [$genre, $msg] = $liste[$code];
    return [$genre, str_replace('%s', preg_replace('/\D/', '', $detail) ?? '', $msg)];
}

function bloc_message(string $genre, string $texte): string
{
    $role = $genre === 'err' ? 'alert' : 'status';
    return '<p class="msg msg-' . e($genre) . '" role="' . $role . '">' . e($texte) . '</p>';
}

/** Le haut de chaque page. $o : zone ('site' ou 'app'), courant (entrée du menu), annonce (false pour la laisser à la page). */
function page_debut(string $titre, array $o = []): void
{
    $zone = ($o['zone'] ?? 'site') === 'app' ? 'app' : 'site';
    $compte = compte_courant();
    $nom = (string) reglage('nom');
    if (!headers_sent()) entetes_securite();
    ?>
<!doctype html>
<html lang="fr" data-zone="<?= $zone ?>" data-etat="ouvert">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($titre) ?>, <?= e($nom) ?></title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#0B0B0C">
<meta name="color-scheme" content="dark">
<link rel="icon" href="/assets/icone.svg" type="image/svg+xml">
<link rel="preload" href="/assets/fonts/nojah-sans.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(version_fichier('/assets/maquette.css')) ?>">
<link rel="stylesheet" href="<?= e(version_fichier('/assets/comptes.css')) ?>">
<script src="<?= e(version_fichier('/assets/comptes.js')) ?>" defer></script>
</head>
<body>
<a class="saut" href="#contenu">Aller au contenu</a>
<header class="top">
  <div class="wrap top-in">
    <div class="brand-box">
      <a class="brand" href="/"><?= e($nom) ?></a>
<?php if (reglage('test')): ?>
      <span class="tag">Version de test</span>
<?php endif; ?>
    </div>
<?php if ($compte !== null): ?>
    <form method="post" action="/deconnexion">
      <input type="hidden" name="jeton" value="<?= e(jeton_formulaire()) ?>">
      <button type="submit" class="btn btn-line btn-sm">Se déconnecter</button>
    </form>
<?php elseif (($o['courant'] ?? '') !== 'connexion'): ?>
    <a class="btn btn-line btn-sm" href="/connexion">Se connecter</a>
<?php endif; ?>
  </div>
</header>
<?php
    if ($zone === 'app' && $compte !== null) {
        $courant = (string) ($o['courant'] ?? '');
        $menu = ['espace' => ['/espace', 'Tableau de bord'], 'compte' => ['/compte', 'Mon compte']];
        if ($compte['role'] === 'admin') $menu['admin'] = ['/admin', 'Comptes et invitations'];
        ?>
<div class="app">
  <nav class="side" aria-label="Ton espace">
    <div class="side-box"><small><?= e(ROLES[$compte['role']] ?? 'Élève') ?></small><b><?= e($compte['prenom']) ?></b></div>
    <div class="side-nav">
<?php foreach ($menu as $cle => [$lien, $libelle]): ?>
      <a href="<?= e($lien) ?>"<?= $cle === $courant ? ' aria-current="page"' : '' ?>><?= e($libelle) ?></a>
<?php endforeach; ?>
    </div>
    <a class="btn btn-line btn-sm" href="<?= e((string) reglage('discord')) ?>" target="_blank" rel="noopener">Rejoindre le Discord</a>
    <p class="side-foot">Connecté : <?= e($compte['email']) ?></p>
  </nav>
  <main class="main" id="contenu">
<?php
    }
    if (($o['annonce'] ?? true) !== false) {
        $a = annonce();
        $t = $a !== null ? texte_annonce($a[0], $a[1]) : null;
        if ($t !== null) $GLOBALS['na_annonce'] = bloc_message($t[0], $t[1]);
    }
}

/** L'annonce de la page (une fois), à placer là où elle doit s'afficher. */
function annonce_html(): string
{
    $h = (string) ($GLOBALS['na_annonce'] ?? '');
    unset($GLOBALS['na_annonce']);
    return $h;
}

function page_fin(array $o = []): void
{
    $zone = ($o['zone'] ?? 'site') === 'app' ? 'app' : 'site';
    if ($zone === 'app' && compte_courant() !== null) echo "  </main>\n</div>\n";
    ?>
<footer class="foot">
  <div class="wrap foot-in">
    <p>© <?= date('Y') ?> <?= e((string) reglage('nom')) ?>.<?= reglage('test') ? ' Version de test : rien ici n\'est encore ouvert au public.' : '' ?></p>
    <p><a href="/confidentialite">Confidentialité</a></p>
  </div>
</footer>
</body>
</html>
<?php
}

/** Une page d'un seul message (lien expiré, accès refusé, page introuvable). */
function page_message(string $titre, string $h1, string $texte, string $lien = '/', string $libelle = "Revenir à l'accueil"): void
{
    page_debut($titre, ['annonce' => false]);
    ?>
<main class="auth" id="contenu">
  <div class="lights" aria-hidden="true"></div>
  <section class="join auth-card">
    <h1><?= e($h1) ?></h1>
    <p class="auth-text"><?= e($texte) ?></p>
    <a class="btn btn-main btn-full" href="<?= e($lien) ?>"><?= e($libelle) ?></a>
  </section>
</main>
<?php
    page_fin();
}

/**
 * Un champ de formulaire. $o : type, valeur, erreur, aide, autocomplete, placeholder, inputmode, max, mdp (bouton Afficher), facultatif.
 */
function champ(string $nom, string $libelle, array $o = []): string
{
    $id = 'c-' . $nom;
    $type = (string) ($o['type'] ?? 'text');
    $erreur = (string) ($o['erreur'] ?? '');
    $aide = (string) ($o['aide'] ?? '');
    $decrit = [];
    if ($erreur !== '') $decrit[] = $id . '-err';
    if ($aide !== '') $decrit[] = $id . '-aide';

    $attrs = ' class="input" id="' . $id . '" name="' . e($nom) . '" type="' . e($type) . '"';
    if ($type !== 'password') $attrs .= ' value="' . e((string) ($o['valeur'] ?? '')) . '"';
    foreach (['autocomplete', 'placeholder', 'inputmode'] as $a) if (isset($o[$a])) $attrs .= ' ' . $a . '="' . e((string) $o[$a]) . '"';
    if (isset($o['max'])) $attrs .= ' maxlength="' . (int) $o['max'] . '"';
    if (in_array($type, ['email', 'password'], true) || !empty($o['brut'])) $attrs .= ' autocapitalize="none" autocorrect="off" spellcheck="false"';
    if (empty($o['facultatif'])) $attrs .= ' required';
    if ($erreur !== '') $attrs .= ' aria-invalid="true"';
    if ($decrit) $attrs .= ' aria-describedby="' . implode(' ', $decrit) . '"';

    $h = '<div class="field"><label for="' . $id . '">' . e($libelle) . (!empty($o['facultatif']) ? ' <span class="opt-note">(facultatif)</span>' : '') . '</label>';
    if (!empty($o['mdp'])) {
        $h .= '<div class="pw"><input' . $attrs . '><button type="button" class="pw-voir" data-voir="' . $id . '" aria-pressed="false" hidden>Afficher</button></div>';
    } else {
        $h .= '<input' . $attrs . '>';
    }
    if ($erreur !== '') $h .= '<p class="err" id="' . $id . '-err">' . e($erreur) . '</p>';
    if ($aide !== '') $h .= '<p class="hint" id="' . $id . '-aide">' . e($aide) . '</p>';
    return $h . '</div>';
}

function champ_jeton(): string
{
    return '<input type="hidden" name="jeton" value="' . e(jeton_formulaire()) . '">';
}
