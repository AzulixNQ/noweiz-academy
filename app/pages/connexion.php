<?php
declare(strict_types=1);

if (compte_courant() !== null) rediriger('/espace');

$email = '';
$erreur = '';

if (methode() === 'POST') {
    verifier_formulaire();
    $email = champ_recu('email', 190);
    $mdp = is_string($_POST['mdp'] ?? null) ? substr($_POST['mdp'], 0, 200) : '';

    $cle = cle_limite('connexion', normaliser_email($email));
    $cle_ip = cle_limite('connexion-ip', adresse_visiteur());
    if (essais($cle, 900) >= 8) {
        $erreur = "Trop d'essais avec cette adresse. Réessaie dans " . attente_minutes($cle, 900) . ' min, ou demande un nouveau mot de passe.';
    } elseif (essais($cle_ip, 900) >= 40) {
        $erreur = "Trop d'essais. Réessaie dans " . attente_minutes($cle_ip, 900) . ' min.';
    } else {
        $c = ($email !== '' && $mdp !== '') ? authentifier($email, $mdp) : null;
        if ($c === null) {
            noter_essai($cle);
            noter_essai($cle_ip);
            $erreur = 'E-mail ou mot de passe incorrect.';
        } else {
            oublier_essais($cle);
            ouvrir_session((int) $c['id']);
            rediriger('/espace');
        }
    }
}

$premier = nombre_comptes() === 0;

page_debut('Connexion', ['courant' => 'connexion']);
?>
<main class="auth" id="contenu">
  <div class="lights" aria-hidden="true"></div>
  <section class="join auth-card" aria-labelledby="titre">
    <h1 id="titre">Se connecter</h1>
    <?= annonce_html() ?>
    <?= $erreur !== '' ? bloc_message('err', $erreur) : '' ?>
    <form method="post" action="/connexion" novalidate>
      <?= champ_jeton() ?>
      <?= champ('email', 'Ton e-mail', ['type' => 'email', 'valeur' => $email, 'autocomplete' => 'username', 'inputmode' => 'email', 'placeholder' => 'toi@exemple.fr', 'max' => 190]) ?>
      <?= champ('mdp', 'Ton mot de passe', ['type' => 'password', 'autocomplete' => 'current-password', 'mdp' => true, 'max' => 200]) ?>
      <button type="submit" class="btn btn-main btn-full">Me connecter</button>
    </form>
    <p class="auth-alt"><a href="/mot-de-passe-oublie">Mot de passe oublié ?</a></p>
  </section>
<?php if ($premier): ?>
  <p class="auth-under">Aucun compte n'existe encore. <a href="/inscription">Créer le premier compte</a></p>
<?php else: ?>
  <p class="auth-under">Pas encore de compte ? Il se crée avec un lien d'invitation.</p>
<?php endif; ?>
</main>
<?php
page_fin();
