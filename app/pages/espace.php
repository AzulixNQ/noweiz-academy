<?php
declare(strict_types=1);

$compte = exiger_connexion();
$admin = $compte['role'] === 'admin';

page_debut('Mon espace', ['zone' => 'app', 'courant' => 'espace']);
?>
    <p class="crumb"><?= e((string) reglage('nom')) ?></p>
    <h1 class="h1-app">Bonjour <?= e($compte['prenom']) ?></h1>
    <?= annonce_html() ?>
<?php if ($compte['confirme_le'] === null): ?>
    <div class="msg msg-info msg-act" role="status">
      <p>Ton adresse e-mail n'est pas encore confirmée : ouvre l'e-mail qu'on t'a envoyé et clique sur le lien.</p>
      <form method="post" action="/compte">
        <?= champ_jeton() ?>
        <input type="hidden" name="action" value="renvoyer">
        <button type="submit" class="btn btn-line btn-sm">Renvoyer l'e-mail</button>
      </form>
    </div>
<?php endif; ?>
    <div class="grid">
      <section class="box box-wide" aria-labelledby="e-form">
        <p class="eyebrow" id="e-form">Ta formation</p>
        <h2>Aucune cohorte pour l'instant</h2>
        <p class="box-text">Les cohortes ouvrent en 2027. Quand tu en rejoins une, ton module de la semaine, ta progression et le classement s'affichent ici.</p>
      </section>
      <section class="box" aria-labelledby="e-live">
        <p class="eyebrow" id="e-live">Les lives</p>
        <h2>Rien de prévu</h2>
        <p>Les lives se passent sur Discord. Tu peux déjà rejoindre le serveur.</p>
        <a class="btn btn-line" href="<?= e((string) reglage('discord')) ?>" target="_blank" rel="noopener">Rejoindre le Discord</a>
      </section>
    </div>
    <div class="grid">
      <section class="box" aria-labelledby="e-compte">
        <p class="eyebrow" id="e-compte">Ton compte</p>
        <h2 class="coupe"><?= e($compte['email']) ?></h2>
        <dl class="fiche">
          <div><dt>Rôle</dt><dd><?= e(ROLES[$compte['role']] ?? 'Élève') ?></dd></div>
          <div><dt>Adresse e-mail</dt><dd><?= $compte['confirme_le'] !== null ? 'Confirmée' : 'À confirmer' ?></dd></div>
          <div><dt>Pseudo Discord</dt><dd><?= $compte['discord'] !== null ? e($compte['discord']) : 'Pas encore donné' ?></dd></div>
          <div><dt>Compte créé le</dt><dd><?= e(date_fr((int) $compte['cree_le'])) ?></dd></div>
        </dl>
        <a class="btn btn-line" href="/compte">Modifier mon compte</a>
      </section>
<?php if ($admin):
    $n = nombre_comptes();
    $attente = (int) valeur("SELECT COUNT(*) FROM liens WHERE type = 'invitation' AND utilise_le IS NULL AND expire_le > ?", [time()]);
?>
      <section class="box" aria-labelledby="e-admin">
        <p class="eyebrow" id="e-admin">Administration</p>
        <h2><?= $n ?> compte<?= $n > 1 ? 's' : '' ?></h2>
        <p><?= $attente === 0 ? 'Aucune invitation en attente.' : ($attente === 1 ? 'Une invitation en attente.' : $attente . ' invitations en attente.') ?> Pour l'instant, un compte se crée seulement avec un lien d'invitation.</p>
        <a class="btn btn-main" href="/admin">Inviter quelqu'un</a>
      </section>
<?php endif; ?>
    </div>
<?php
page_fin(['zone' => 'app']);
