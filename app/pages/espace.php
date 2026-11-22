<?php
declare(strict_types=1);

$compte = exiger_connexion();
$admin = $compte['role'] === 'admin';
$cohorte = cohorte_active($compte);
$toutes = cohortes_visibles($compte);

page_debut('Mon espace', ['zone' => 'app', 'courant' => 'espace']);

if ($cohorte !== null) {
    $prog = progression($compte, $cohorte);
    $nb = count($prog);
    $sem = semaine_cohorte($cohorte, $nb);
    $actuel = module_a_travailler($prog);
    $valides = nombre_valides($prog);
    $live = prochain_live((int) $cohorte['id']);
    $rang = classement($cohorte);
    $moi = null;
    foreach ($rang as $l) if ($l['compte_id'] === (int) $compte['id']) $moi = $l;
    $apercu = !empty($cohorte['apercu']);
}
?>
<?php if ($cohorte === null): ?>
    <p class="crumb"><?= e((string) reglage('nom')) ?></p>
    <h1 class="h1-app">Bonjour <?= e($compte['prenom']) ?></h1>
<?php else: ?>
    <p class="crumb"><?= e($cohorte['nom']) ?> · <?= e($cohorte['parcours_nom']) ?> avec <?= e($cohorte['formateur']) ?> · départ le <?= e(date_fr((int) $cohorte['debut'])) ?></p>
    <h1 class="h1-app"><?php
        if ($nb === 0) echo 'Bonjour ' . e($compte['prenom']);
        elseif ($sem === 0) echo 'La cohorte démarre le ' . e(date_fr((int) $cohorte['debut']));
        elseif ($actuel !== null) echo 'Semaine ' . $sem . ' : ' . e($actuel['titre']);
        else echo 'Bonjour ' . e($compte['prenom']);
    ?></h1>
<?php endif; ?>
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
<?php if (count($toutes) > 1): ?>
    <nav class="cohortes" aria-label="Changer de cohorte">
<?php foreach ($toutes as $c): ?>
      <a href="/espace?c=<?= (int) $c['id'] ?>"<?= $cohorte !== null && (int) $c['id'] === (int) $cohorte['id'] ? ' aria-current="page"' : '' ?>><?= e($c['parcours_nom'] . ' · ' . $c['nom']) ?><?= $c['apercu'] ? ' (aperçu)' : '' ?></a>
<?php endforeach; ?>
    </nav>
<?php endif; ?>
<?php if ($cohorte !== null && $apercu): ?>
    <p class="msg msg-info" role="status">Aperçu : tu n'es pas inscrit dans cette cohorte. Tout est ouvert pour que tu puisses relire les modules ; ta progression n'est pas enregistrée.</p>
<?php endif; ?>

<?php if ($cohorte === null): ?>
    <div class="grid grid-foyer">
      <i class="foyer-carte" data-foyer aria-hidden="true"></i>
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
<?php elseif ($nb === 0): ?>
    <div class="grid grid-foyer">
      <i class="foyer-carte" data-foyer aria-hidden="true"></i>
      <section class="box box-wide" aria-labelledby="e-form">
        <p class="eyebrow" id="e-form">Ta formation</p>
        <h2>Les modules arrivent bientôt</h2>
        <p class="box-text"><?= e($cohorte['formateur']) ?> prépare les modules du parcours <?= e($cohorte['parcours_nom']) ?>. Ils s'afficheront ici.</p>
      </section>
    </div>
<?php else: ?>
    <div class="grid grid-foyer">
      <i class="foyer-carte" data-foyer aria-hidden="true"></i>
      <section class="box box-wide" aria-labelledby="e-mod">
<?php if ($sem === 0 && !$apercu): ?>
        <p class="eyebrow" id="e-mod">Avant le départ</p>
        <h2>Le module 1 s'ouvre le <?= e(date_fr((int) $cohorte['debut'])) ?></h2>
        <p>Rejoins le salon Discord de ta cohorte en attendant : c'est là que se passent les lives.</p>
<?php elseif ($actuel !== null): ?>
        <p class="eyebrow" id="e-mod"><?= $actuel['valide'] ? 'Dernier module ouvert' : 'Ton module de la semaine' ?></p>
        <h2>Module <?= (int) $actuel['numero'] ?> · <?= e($actuel['titre']) ?></h2>
<?php if (!empty($actuel['resume'])): ?>
        <p class="mw-52ch"><?= e($actuel['resume']) ?></p>
<?php endif; ?>
        <div>
          <div class="bar-head"><span><?= (int) $actuel['vues'] ?> leçon<?= $actuel['vues'] > 1 ? 's' : '' ?> sur <?= (int) $actuel['nb_lecons'] ?></span><span class="doux"><?= (int) $actuel['nb_questions'] === 0 ? '' : ($actuel['valide'] ? 'Quiz validé' : ($actuel['quiz'] !== null ? 'Quiz à refaire' : 'Quiz à faire')) ?></span></div>
          <div class="bar" role="img" aria-label="<?= (int) $actuel['vues'] ?> leçons vues sur <?= (int) $actuel['nb_lecons'] ?>"><?php for ($i = 0; $i < (int) $actuel['nb_lecons']; $i++) echo $i < (int) $actuel['vues'] ? '<i class="on"></i>' : '<i></i>'; ?></div>
        </div>
<?php
        $suite = null;
        foreach ($prog as $m) if ((int) $m['position'] === (int) $actuel['position'] + 1) $suite = $m;
        if ((int) $actuel['vues'] < (int) $actuel['nb_lecons']) { $bouton = 'Continuer la leçon ' . ((int) $actuel['vues'] + 1); $lien = '/module?id=' . (int) $actuel['id']; }
        elseif ((int) $actuel['nb_questions'] > 0 && !$actuel['valide']) { $bouton = 'Faire le quiz'; $lien = '/quiz?m=' . (int) $actuel['id']; }
        else { $bouton = 'Revoir le module'; $lien = '/module?id=' . (int) $actuel['id']; }
?>
        <div class="row-act"><a class="btn btn-main m-0" href="<?= e($lien) ?>"><?= e($bouton) ?></a><?php if ($suite !== null && !$apercu): ?><small><?= $actuel['valide'] ? e(texte_verrou($suite, $prog)) : 'Le quiz validé ouvre le module ' . (int) $suite['numero'] . ($suite['ouvre_le'] > time() ? ' le ' . e(date_fr((int) $suite['ouvre_le'])) : '') ?></small><?php endif; ?></div>
<?php endif; ?>
      </section>
      <section class="box" aria-labelledby="e-live">
        <p class="eyebrow" id="e-live">Prochain live</p>
<?php if ($live !== null): ?>
        <h2><?= e(date_fr((int) $live['debut'], true)) ?></h2>
        <p><?= e($live['titre']) ?>, sur Discord avec <?= e($cohorte['formateur']) ?>.</p>
        <a class="btn btn-line" href="<?= e((string) ($live['lien'] ?: ($cohorte['discord'] ?: reglage('discord')))) ?>" target="_blank" rel="noopener">Ouvrir le salon du live</a>
<?php else: ?>
        <h2>Pas encore de date</h2>
        <p>Les lives de la cohorte s'afficheront ici, avec leur replay.</p>
        <a class="btn btn-line" href="/lives">Lives et replays</a>
<?php endif; ?>
      </section>
    </div>
    <div class="grid">
      <section class="box" aria-labelledby="e-prog">
        <p class="eyebrow" id="e-prog">Ta progression</p>
        <h2><?= $valides ?> module<?= $valides > 1 ? 's' : '' ?> validé<?= $valides > 1 ? 's' : '' ?> sur <?= $nb ?></h2>
        <ol class="list">
<?php foreach ($prog as $m): ?>
          <li<?= $m['etat'] === 'verrouille' ? ' class="lock"' : '' ?>><i class="ring<?= $m['valide'] ? ' full' : '' ?>"></i><span class="grow"><?= (int) $m['numero'] ?> · <?= e($m['titre']) ?></span><small><?= $m['valide'] ? 'Validé' : ($m['ouvert'] ? 'En cours' : 'Verrouillé') ?></small></li>
<?php endforeach; ?>
        </ol>
        <a class="doux t-15" href="/parcours">Voir tout mon parcours</a>
      </section>
      <section class="box" aria-labelledby="e-rank">
        <p class="eyebrow" id="e-rank">Classement de la cohorte</p>
<?php if ($rang === []): ?>
        <h2>Aucun élève inscrit</h2>
<?php else: ?>
        <h2><?= $moi !== null ? 'Tu es ' . ($moi['rang'] === 1 ? '1er' : $moi['rang'] . 'e') . ' sur ' . count($rang) : count($rang) . ' élève' . (count($rang) > 1 ? 's' : '') ?></h2>
        <ol class="list rank serre">
<?php foreach (array_slice($rang, 0, 5) as $l): ?>
          <li<?= $moi !== null && $l['compte_id'] === $moi['compte_id'] ? ' class="me"' : '' ?>><span class="pos"><?= (int) $l['rang'] ?></span><span class="grow"><?= $moi !== null && $l['compte_id'] === $moi['compte_id'] ? 'Toi' : e($l['prenom']) ?></span><span class="num"><?= (int) $l['points'] ?> pts</span></li>
<?php endforeach; ?>
        </ol>
<?php endif; ?>
        <p class="note-faint">10 points par leçon vue, 10 par bonne réponse aux quiz.</p>
      </section>
      <section class="box" aria-labelledby="e-next">
        <p class="eyebrow" id="e-next">À venir</p>
        <ul class="next">
<?php
    $a_venir = 0;
    if ($actuel !== null && (int) $actuel['nb_questions'] > 0 && !$actuel['valide'] && !$apercu) {
        echo '          <li><b>Quiz du module ' . (int) $actuel['numero'] . '</b><span>À valider pour ouvrir la suite (' . quiz_seuil() . " % de bonnes réponses).</span></li>\n";
        $a_venir++;
    }
    foreach ($prog as $m) {
        if ($m['ouvert'] || $a_venir >= 2) continue;
        echo '          <li><b>Module ' . (int) $m['numero'] . ' · ' . e($m['titre']) . '</b><span>' . e(texte_verrou($m, $prog)) . "</span></li>\n";
        $a_venir++;
    }
?>
          <li><b>Ton certificat</b><span><?= $valides === $nb ? '<a href="/certificat">Disponible : ouvre-le et imprime-le.</a>' : 'Disponible quand les ' . $nb . ' modules sont validés.' ?></span></li>
        </ul>
      </section>
    </div>
<?php endif; ?>
<?php if ($admin):
    $n = nombre_comptes();
    $attente = (int) valeur("SELECT COUNT(*) FROM liens WHERE type = 'invitation' AND utilise_le IS NULL AND expire_le > ?", [time()]);
?>
    <div class="grid">
      <section class="box" aria-labelledby="e-admin">
        <p class="eyebrow" id="e-admin">Administration</p>
        <h2><?= $n ?> compte<?= $n > 1 ? 's' : '' ?></h2>
        <p><?= $attente === 0 ? 'Aucune invitation en attente.' : ($attente === 1 ? 'Une invitation en attente.' : $attente . ' invitations en attente.') ?> Pour l'instant, un compte se crée seulement avec un lien d'invitation.</p>
        <a class="btn btn-main" href="/admin">Inviter quelqu'un</a>
      </section>
      <section class="box" aria-labelledby="e-compte">
        <p class="eyebrow" id="e-compte">Ton compte</p>
        <h2 class="coupe"><?= e($compte['email']) ?></h2>
        <dl class="fiche">
          <div><dt>Rôle</dt><dd><?= e(ROLES[$compte['role']] ?? 'Élève') ?></dd></div>
          <div><dt>Pseudo Discord</dt><dd><?= $compte['discord'] !== null ? e($compte['discord']) : 'Pas encore donné' ?></dd></div>
        </dl>
        <a class="btn btn-line" href="/compte">Modifier mon compte</a>
      </section>
    </div>
<?php elseif ($cohorte === null): ?>
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
    </div>
<?php endif; ?>
<?php
page_fin(['zone' => 'app']);
