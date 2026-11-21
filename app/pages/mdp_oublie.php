<?php
declare(strict_types=1);

/* « Mot de passe oublié » : on répond toujours la même chose, que le compte existe ou non. */

$email = '';
$erreur = '';
$envoye = false;

if (methode() === 'POST') {
    verifier_formulaire();
    $email = champ_recu('email', 190);
    $erreur = verifier_email($email);
    if ($erreur === '') {
        $cle_ip = cle_limite('oubli-ip', adresse_visiteur());
        $cle = cle_limite('oubli', normaliser_email($email));
        if (essais($cle_ip, 3600) >= 8) {
            $erreur = 'Trop de demandes. Réessaie dans ' . attente_minutes($cle_ip, 3600) . ' min.';
        } else {
            noter_essai($cle_ip);
            $compte = compte_par_email($email);
            if ($compte !== null && essais($cle, 3600) < 3) {
                noter_essai($cle);
                envoyer_lien_mdp($compte);
            }
            $envoye = true;
        }
    }
}

page_debut('Mot de passe oublié');
?>
<main class="auth" id="contenu">
  <div class="lights" aria-hidden="true"></div>
  <section class="join auth-card" aria-labelledby="titre">
<?php if ($envoye): ?>
    <h1 id="titre">Regarde tes e-mails</h1>
    <p class="auth-text">Si un compte existe avec l'adresse <b><?= e($email) ?></b>, on vient d'y envoyer un lien pour choisir un nouveau mot de passe. Il marche pendant <?= e(duree_lisible((int) reglage('mdp_lien_minutes'))) ?>.</p>
    <p class="auth-text">Rien reçu au bout de quelques minutes ? Regarde dans les indésirables, puis vérifie l'adresse.</p>
    <a class="btn btn-line btn-full" href="/connexion">Revenir à la connexion</a>
<?php else: ?>
    <h1 id="titre">Mot de passe oublié</h1>
    <p class="auth-text">Écris l'e-mail de ton compte : on t'envoie un lien pour choisir un nouveau mot de passe.</p>
    <form method="post" action="/mot-de-passe-oublie" novalidate>
      <?= champ_jeton() ?>
      <?= champ('email', 'Ton e-mail', ['type' => 'email', 'valeur' => $email, 'erreur' => $erreur, 'autocomplete' => 'username', 'inputmode' => 'email', 'placeholder' => 'toi@exemple.fr', 'max' => 190]) ?>
      <button type="submit" class="btn btn-main btn-full">Recevoir le lien</button>
    </form>
    <p class="auth-alt"><a href="/connexion">Revenir à la connexion</a></p>
<?php endif; ?>
  </section>
</main>
<?php
page_fin();
