<?php
declare(strict_types=1);

/* Le lien « Confirmer mon inscription » de l'e-mail de la liste d'attente. */

$jeton = is_string($_GET['lien'] ?? null) ? $_GET['lien'] : '';
$l = liste_confirmer($jeton);

if ($l === null) {
    page_message('Lien expiré', "Ce lien n'est plus valable.",
        "Il a déjà servi (ton inscription est alors déjà confirmée), ou il a expiré. Pour recevoir un nouveau lien, inscris-toi à nouveau sur la page d'accueil.",
        '/#liste', "Revenir à la liste d'attente");
    exit;
}

page_debut('Inscription confirmée', ['annonce' => false]);
?>
<main class="auth" id="contenu">
  <div class="lights" aria-hidden="true"></div>
  <section class="join auth-card">
    <h1>C'est confirmé, <?= e($l['prenom']) ?>. Tu es sur la liste.</h1>
    <p class="auth-text">On t'écrit à l'ouverture, avant tout le monde, avec ta place prioritaire et le prix de lancement. En attendant, les lives et les annonces se passent sur le Discord.</p>
    <a class="btn btn-main btn-full" href="<?= e((string) reglage('discord')) ?>" target="_blank" rel="noopener">Rejoindre le Discord</a>
    <p class="auth-alt"><a href="/">Revenir au site</a></p>
    <p class="auth-note">Tu peux te désinscrire à tout moment avec le lien en bas de nos e-mails.</p>
  </section>
</main>
<?php
page_fin();
