<?php
declare(strict_types=1);

/* Le lien « mot de passe oublié » reçu par e-mail : choisir un nouveau mot de passe. */

$jeton = is_string($_GET['lien'] ?? null) ? $_GET['lien'] : '';
$lien = lien_valable('mdp', $jeton);
$compte = $lien !== null ? compte_par_id((int) $lien['compte_id']) : null;

if ($compte === null) {
    page_message('Lien expiré', "Ce lien n'est plus valable.", 'Il a déjà servi, ou il a expiré. Demande un nouveau lien.', '/mot-de-passe-oublie', 'Demander un nouveau lien');
    exit;
}

$erreur = '';
if (methode() === 'POST') {
    verifier_formulaire();
    $mdp = is_string($_POST['mdp'] ?? null) ? substr($_POST['mdp'], 0, 200) : '';
    $erreur = verifier_mot_de_passe($mdp, $compte['email'], $compte['prenom']);
    if ($erreur === '') {
        consommer_lien((int) $lien['id'], (int) $compte['id']);
        changer_mot_de_passe($compte, $mdp);
        // Il a ouvert un lien reçu à cette adresse : elle est bien la sienne.
        if ($compte['confirme_le'] === null) requete('UPDATE comptes SET confirme_le = ? WHERE id = ?', [time(), $compte['id']]);
        oublier_essais(cle_limite('connexion', $compte['email']));
        fermer_session();
        annoncer('mdp-nouveau');
        rediriger('/connexion');
    }
}

page_debut('Nouveau mot de passe');
?>
<main class="auth" id="contenu">
  <div class="lights" aria-hidden="true"></div>
  <section class="join auth-card" aria-labelledby="titre">
    <h1 id="titre">Choisis un nouveau mot de passe</h1>
    <p class="auth-text">Pour le compte <b><?= e(masquer_email($compte['email'])) ?></b>. Tous tes appareils seront déconnectés.</p>
    <form method="post" action="/nouveau-mot-de-passe?lien=<?= e(rawurlencode($jeton)) ?>" novalidate>
      <?= champ_jeton() ?>
      <?= champ('mdp', 'Ton nouveau mot de passe', ['type' => 'password', 'erreur' => $erreur, 'autocomplete' => 'new-password', 'mdp' => true, 'max' => 72,
          'aide' => reglage('mdp_min') . ' caractères ou plus. Une phrase que tu retiens marche très bien.']) ?>
      <button type="submit" class="btn btn-main btn-full">Enregistrer ce mot de passe</button>
    </form>
  </section>
</main>
<?php
page_fin();
