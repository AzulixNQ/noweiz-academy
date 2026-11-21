<?php
declare(strict_types=1);

if (compte_courant() !== null) rediriger('/espace');

$premier = nombre_comptes() === 0;
$jeton_invitation = is_string($_GET['invitation'] ?? null) ? $_GET['invitation'] : '';
$invitation = $jeton_invitation !== '' ? lien_valable('invitation', $jeton_invitation) : null;

if (!$premier && $invitation === null) {
    if ($jeton_invitation !== '') {
        page_message('Invitation expirée', "Ce lien d'invitation n'est plus valable.", "Il a déjà servi, ou il a expiré. Demande un nouveau lien à la personne qui te l'a envoyé.", '/connexion', 'Se connecter');
    } else {
        page_message('Inscriptions fermées', 'Les inscriptions ne sont pas ouvertes.', "Un compte se crée avec un lien d'invitation. Les inscriptions ouvriront avec la cohorte 1, en 2027.", '/connexion', 'Se connecter');
    }
    exit;
}

$v = ['prenom' => '', 'email' => '', 'discord' => ''];
$err = [];
$erreur = '';

if (methode() === 'POST') {
    verifier_formulaire();
    $v['prenom'] = champ_recu('prenom', 80);
    $v['email'] = champ_recu('email', 190);
    $v['discord'] = ltrim(champ_recu('discord', 60), '@');
    $mdp = is_string($_POST['mdp'] ?? null) ? substr($_POST['mdp'], 0, 200) : '';

    $cle_ip = cle_limite('inscription-ip', adresse_visiteur());
    if (champ_recu('site') !== '') {
        $erreur = "L'inscription n'a pas pu se faire. Recharge la page et recommence.";   // champ piège rempli : un robot
    } elseif (essais($cle_ip, 3600) >= 10) {
        $erreur = "Trop d'inscriptions depuis cette connexion. Réessaie dans " . attente_minutes($cle_ip, 3600) . ' min.';
    } else {
        foreach (['prenom' => verifier_prenom($v['prenom']), 'email' => verifier_email($v['email']), 'discord' => verifier_discord($v['discord']),
                  'mdp' => verifier_mot_de_passe($mdp, $v['email'], $v['prenom'])] as $champ => $message) {
            if ($message !== '') $err[$champ] = $message;
        }
        if (!$err) {
            noter_essai($cle_ip);
            [$compte, $message] = creer_compte($v['prenom'], $v['email'], $mdp, $v['discord'], $invitation);
            if ($compte === null) {
                $erreur = $message;
            } else {
                $parti = envoyer_confirmation($compte, true);
                ouvrir_session((int) $compte['id']);
                annoncer($parti ? 'compte-cree' : 'compte-cree-sans-mail');
                rediriger('/espace');
            }
        }
    }
}

$action = '/inscription' . ($jeton_invitation !== '' ? '?invitation=' . rawurlencode($jeton_invitation) : '');

page_debut('Créer ton compte');
?>
<main class="auth" id="contenu">
  <div class="lights" aria-hidden="true"></div>
  <section class="join auth-card" aria-labelledby="titre">
    <h1 id="titre">Créer ton compte</h1>
<?php if ($premier): ?>
    <p class="auth-text">C'est le premier compte du site : il aura les droits d'administrateur.</p>
<?php else: ?>
    <p class="auth-text">Tu es invité comme <?= e(mb_strtolower(ROLES[$invitation['role']] ?? 'Élève')) ?>. Ce lien marche jusqu'au <?= e(date_fr((int) $invitation['expire_le'])) ?>, une seule fois.</p>
<?php endif; ?>
    <?= $erreur !== '' ? bloc_message('err', $erreur) : '' ?>
    <form method="post" action="<?= e($action) ?>" novalidate>
      <?= champ_jeton() ?>
      <?= champ('prenom', 'Ton prénom', ['valeur' => $v['prenom'], 'erreur' => $err['prenom'] ?? '', 'autocomplete' => 'given-name', 'placeholder' => 'Prénom', 'max' => 60]) ?>
      <?= champ('email', 'Ton e-mail', ['type' => 'email', 'valeur' => $v['email'], 'erreur' => $err['email'] ?? '', 'autocomplete' => 'email', 'inputmode' => 'email', 'placeholder' => 'toi@exemple.fr', 'max' => 190,
          'aide' => 'On t\'y envoie un e-mail pour confirmer que c\'est bien la tienne.']) ?>
      <?= champ('mdp', 'Ton mot de passe', ['type' => 'password', 'erreur' => $err['mdp'] ?? '', 'autocomplete' => 'new-password', 'mdp' => true, 'max' => 72,
          'aide' => reglage('mdp_min') . ' caractères ou plus. Une phrase que tu retiens marche très bien.']) ?>
      <?= champ('discord', 'Ton pseudo Discord', ['valeur' => $v['discord'], 'erreur' => $err['discord'] ?? '', 'autocomplete' => 'off', 'placeholder' => 'pseudo', 'max' => 40, 'facultatif' => true, 'brut' => true,
          'aide' => 'Il servira à t\'ouvrir le salon privé de ta cohorte.']) ?>
      <div class="piege" aria-hidden="true"><label for="c-site">Ne remplis pas ce champ</label><input id="c-site" name="site" type="text" tabindex="-1" autocomplete="off"></div>
      <button type="submit" class="btn btn-main btn-full">Créer mon compte</button>
    </form>
    <p class="auth-note">On garde ton prénom, ton e-mail et ton pseudo Discord pour faire marcher ton compte, rien d'autre. Tu peux le supprimer quand tu veux depuis « Mon compte ». <a href="/confidentialite">En savoir plus</a></p>
  </section>
  <p class="auth-under">Déjà un compte ? <a href="/connexion">Se connecter</a></p>
</main>
<?php
page_fin();
