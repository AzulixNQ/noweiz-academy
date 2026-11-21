<?php
declare(strict_types=1);

$compte = exiger_connexion();
$id = (int) $compte['id'];
$session_id = (int) $compte['session_id'];

$err = [];          // $err['email']['email'] = message
$v = ['prenom' => $compte['prenom'], 'discord' => (string) $compte['discord'], 'email' => ''];

if (methode() === 'POST') {
    verifier_formulaire();
    $action = champ_recu('action', 30);
    $mdp_saisi = static fn(string $nom): string => is_string($_POST[$nom] ?? null) ? substr($_POST[$nom], 0, 200) : '';
    // Vérifier le mot de passe actuel, avec la même limite d'essais qu'à la connexion.
    $mdp_ok = static function (string $mdp) use ($compte): string {
        $cle = cle_limite('connexion', $compte['email']);
        if (essais($cle, 900) >= 8) return "Trop d'essais. Réessaie dans " . attente_minutes($cle, 900) . ' min.';
        if ($mdp === '') return 'Écris ton mot de passe actuel.';
        if (!password_verify($mdp, $compte['hachage'])) { noter_essai($cle); return "Ce n'est pas ton mot de passe actuel."; }
        return '';
    };

    if ($action === 'profil') {
        $v['prenom'] = champ_recu('prenom', 80);
        $v['discord'] = ltrim(champ_recu('discord', 60), '@');
        foreach (['prenom' => verifier_prenom($v['prenom']), 'discord' => verifier_discord($v['discord'])] as $c => $m) if ($m !== '') $err['profil'][$c] = $m;
        if (empty($err['profil'])) {
            requete('UPDATE comptes SET prenom = ?, discord = ? WHERE id = ?', [$v['prenom'], $v['discord'] !== '' ? $v['discord'] : null, $id]);
            annoncer('profil');
            rediriger('/compte');
        }
    } elseif ($action === 'email') {
        $v['email'] = champ_recu('email', 190);
        $m = verifier_email($v['email']);
        $nouvelle = normaliser_email($v['email']);
        if ($m === '' && $nouvelle === $compte['email']) $m = "C'est déjà l'adresse de ton compte.";
        if ($m !== '') $err['email']['email'] = $m;
        $m = $mdp_ok($mdp_saisi('mdp_email'));
        if ($m !== '') $err['email']['mdp_email'] = $m;
        if (empty($err['email'])) {
            if (compte_par_email($nouvelle) !== null) {
                $err['email']['email'] = 'Un autre compte utilise déjà cette adresse.';
            } else {
                requete('UPDATE comptes SET email = ?, confirme_le = NULL WHERE id = ?', [$nouvelle, $id]);
                requete("DELETE FROM liens WHERE type IN ('confirmation', 'mdp') AND compte_id = ?", [$id]);
                fermer_autres_sessions($id, $session_id);
                courriel_adresse_changee($compte['email'], $compte['prenom'], $nouvelle);
                $parti = envoyer_confirmation(compte_par_id($id));
                annoncer($parti ? 'email-change' : 'email-change-sans-mail');
                rediriger('/compte');
            }
        }
    } elseif ($action === 'mdp') {
        $m = $mdp_ok($mdp_saisi('mdp_actuel'));
        if ($m !== '') $err['mdp']['mdp_actuel'] = $m;
        $nouveau = $mdp_saisi('mdp_nouveau');
        $m = verifier_mot_de_passe($nouveau, $compte['email'], $compte['prenom']);
        if ($m === '' && $nouveau === $mdp_saisi('mdp_actuel')) $m = 'Choisis un mot de passe différent de l\'actuel.';
        if ($m !== '') $err['mdp']['mdp_nouveau'] = $m;
        if (empty($err['mdp'])) {
            changer_mot_de_passe($compte, $nouveau, $session_id);
            annoncer('mdp-change');
            rediriger('/compte');
        }
    } elseif ($action === 'sessions') {
        $n = fermer_autres_sessions($id, $session_id);
        annoncer($n > 0 ? 'sessions-fermees' : 'sessions-aucune', (string) $n);
        rediriger('/compte');
    } elseif ($action === 'renvoyer') {
        $cle = cle_limite('confirmation', (string) $id);
        if ($compte['confirme_le'] !== null) {
            annoncer('adresse-confirmee');
        } elseif (essais($cle, 3600) >= 3) {
            annoncer('trop');
        } else {
            noter_essai($cle);
            annoncer(envoyer_confirmation($compte) ? 'confirmation-envoyee' : 'confirmation-echec');
        }
        rediriger('/compte');
    } elseif ($action === 'supprimer') {
        $m = $mdp_ok($mdp_saisi('mdp_suppr'));
        if ($m === '' && $compte['role'] === 'admin' && nombre_admins() === 1 && nombre_comptes() > 1) {
            $m = "Tu es le seul administrateur : donne d'abord ce rôle à quelqu'un d'autre.";
        }
        if ($m !== '') {
            $err['supprimer']['mdp_suppr'] = $m;
        } else {
            supprimer_compte($id);
            effacer_temoin('session');
            annoncer('compte-supprime');
            rediriger('/connexion');
        }
    } else {
        rediriger('/compte');
    }
}

$autres = (int) valeur('SELECT COUNT(*) FROM sessions WHERE compte_id = ? AND id <> ? AND expire_le > ?', [$id, $session_id, time()]);
$aide_mdp = reglage('mdp_min') . ' caractères ou plus. Une phrase que tu retiens marche très bien.';

page_debut('Mon compte', ['zone' => 'app', 'courant' => 'compte']);
?>
    <p class="crumb"><a href="/espace">Mon espace</a></p>
    <h1 class="h1-app">Mon compte</h1>
    <?= annonce_html() ?>
    <div class="formes">
      <section class="box" aria-labelledby="f-profil">
        <h2 id="f-profil">Tes informations</h2>
        <form method="post" action="/compte" novalidate>
          <?= champ_jeton() ?><input type="hidden" name="action" value="profil">
          <div class="two">
            <?= champ('prenom', 'Ton prénom', ['valeur' => $v['prenom'], 'erreur' => $err['profil']['prenom'] ?? '', 'autocomplete' => 'given-name', 'max' => 60]) ?>
            <?= champ('discord', 'Ton pseudo Discord', ['valeur' => $v['discord'], 'erreur' => $err['profil']['discord'] ?? '', 'autocomplete' => 'off', 'placeholder' => 'pseudo', 'max' => 40, 'facultatif' => true, 'brut' => true]) ?>
          </div>
          <p class="hint">Le pseudo Discord servira à t'ouvrir le salon privé de ta cohorte.</p>
          <button type="submit" class="btn btn-main">Enregistrer</button>
        </form>
      </section>

      <section class="box" aria-labelledby="f-email">
        <h2 id="f-email">Ton adresse e-mail</h2>
        <p>Aujourd'hui : <b class="fort"><?= e($compte['email']) ?></b>, <?= $compte['confirme_le'] !== null ? 'confirmée' : 'pas encore confirmée' ?>.</p>
<?php if ($compte['confirme_le'] === null): ?>
        <form method="post" action="/compte">
          <?= champ_jeton() ?><input type="hidden" name="action" value="renvoyer">
          <button type="submit" class="btn btn-line btn-sm">Renvoyer l'e-mail de confirmation</button>
        </form>
<?php endif; ?>
        <form method="post" action="/compte" novalidate>
          <?= champ_jeton() ?><input type="hidden" name="action" value="email">
          <div class="two">
            <?= champ('email', 'Ta nouvelle adresse', ['type' => 'email', 'valeur' => $v['email'], 'erreur' => $err['email']['email'] ?? '', 'autocomplete' => 'email', 'inputmode' => 'email', 'placeholder' => 'toi@exemple.fr', 'max' => 190]) ?>
            <?= champ('mdp_email', 'Ton mot de passe actuel', ['type' => 'password', 'erreur' => $err['email']['mdp_email'] ?? '', 'autocomplete' => 'current-password', 'mdp' => true, 'max' => 200]) ?>
          </div>
          <p class="hint">On t'enverra un e-mail à la nouvelle adresse pour la confirmer, et un autre à l'ancienne pour te prévenir.</p>
          <button type="submit" class="btn btn-line">Changer d'adresse</button>
        </form>
      </section>

      <section class="box" aria-labelledby="f-mdp">
        <h2 id="f-mdp">Ton mot de passe</h2>
        <form method="post" action="/compte" novalidate>
          <?= champ_jeton() ?><input type="hidden" name="action" value="mdp">
          <div class="two">
            <?= champ('mdp_actuel', 'Ton mot de passe actuel', ['type' => 'password', 'erreur' => $err['mdp']['mdp_actuel'] ?? '', 'autocomplete' => 'current-password', 'mdp' => true, 'max' => 200]) ?>
            <?= champ('mdp_nouveau', 'Ton nouveau mot de passe', ['type' => 'password', 'erreur' => $err['mdp']['mdp_nouveau'] ?? '', 'autocomplete' => 'new-password', 'mdp' => true, 'max' => 72, 'aide' => $aide_mdp]) ?>
          </div>
          <button type="submit" class="btn btn-line">Changer de mot de passe</button>
        </form>
      </section>

      <section class="box" aria-labelledby="f-app">
        <h2 id="f-app">Tes appareils</h2>
        <p><?= $autres === 0 ? "Tu n'es connecté que sur cet appareil." : ($autres === 1 ? 'Tu es aussi connecté sur un autre appareil.' : "Tu es aussi connecté sur $autres autres appareils.") ?> Une connexion dure <?= (int) reglage('session_jours') ?> jours.</p>
<?php if ($autres > 0): ?>
        <form method="post" action="/compte">
          <?= champ_jeton() ?><input type="hidden" name="action" value="sessions">
          <button type="submit" class="btn btn-line">Déconnecter les autres appareils</button>
        </form>
<?php endif; ?>
      </section>

      <section class="box box-danger" aria-labelledby="f-suppr">
        <h2 id="f-suppr">Supprimer ton compte</h2>
        <p>Ton compte et tout ce qui s'y rattache sont effacés tout de suite. Ça ne s'annule pas.</p>
        <form method="post" action="/compte" novalidate>
          <?= champ_jeton() ?><input type="hidden" name="action" value="supprimer">
          <div class="un">
            <?= champ('mdp_suppr', 'Ton mot de passe actuel', ['type' => 'password', 'erreur' => $err['supprimer']['mdp_suppr'] ?? '', 'autocomplete' => 'current-password', 'mdp' => true, 'max' => 200]) ?>
          </div>
          <button type="submit" class="btn btn-warn">Supprimer mon compte</button>
        </form>
      </section>
    </div>
<?php
page_fin(['zone' => 'app']);
