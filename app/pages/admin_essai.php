<?php
declare(strict_types=1);

/* La cohorte d'essai, pour l'administrateur : la créer, y inscrire d'autres comptes (Johan, Weizix…), l'effacer. */

require_once RACINE . '/app/essai.php';

$compte = exiger_admin();
$erreur = '';

if (methode() === 'POST') {
    verifier_formulaire();
    $action = champ_recu('action', 20);
    $essai = essai_cohorte();
    if ($action === 'creer' && essai_possible()) {
        $cid = essai_creer((int) $compte['id']);
        poser_temoin('cohorte', (string) $cid, 180 * 86400);
        annoncer('essai-cree');
        rediriger('/admin/essai');
    } elseif ($action === 'inscrire' && $essai !== null) {
        $qui = champ_recu('compte', 12);
        $c = ctype_digit($qui) ? compte_par_id((int) $qui) : null;
        if ($c !== null && !valeur('SELECT 1 FROM inscriptions WHERE compte_id = ? AND cohorte_id = ?', [$c['id'], $essai['id']])) {
            requete('INSERT INTO inscriptions (compte_id, cohorte_id, cree_le) VALUES (?, ?, ?)', [$c['id'], $essai['id'], time()]);
            annoncer('essai-inscrit');
        }
        rediriger('/admin/essai');
    } elseif ($action === 'retirer' && $essai !== null) {
        $qui = champ_recu('compte', 12);
        if (ctype_digit($qui)) {
            requete('DELETE FROM inscriptions WHERE compte_id = ? AND cohorte_id = ?', [(int) $qui, $essai['id']]);
            annoncer('essai-retire');
        }
        rediriger('/admin/essai');
    } elseif ($action === 'supprimer' && $essai !== null) {
        essai_supprimer();
        effacer_temoin('cohorte');
        annoncer('essai-supprime');
        rediriger('/admin/essai');
    }
    rediriger('/admin/essai');
}

$essai = essai_cohorte();
page_debut("Cohorte d'essai", ['zone' => 'app', 'courant' => 'essai']);
?>
    <p class="crumb">Administration</p>
    <h1 class="h1-app">Cohorte d'essai</h1>
    <?= annonce_html() ?>
<?php if ($essai === null): ?>
    <p class="intro-app">Pour essayer l'espace élève avant que les vrais cours existent : une cohorte d'order flow qui a démarré il y a 15 jours (semaine 3), avec les 8 modules de la trame, 5 leçons chacun, un quiz de 3 questions par module, une vraie vidéo en module 3 si un fichier video-essai.mp4 est posé dans le dossier de données et deux lives. Tu y es inscrit tout de suite ; tu pourras y inscrire Johan ou Weizix. Tout s'efface d'un clic.</p>
<?php if (essai_possible()): ?>
    <form class="mt-24" method="post" action="/admin/essai">
      <?= champ_jeton() ?>
      <input type="hidden" name="action" value="creer">
      <button type="submit" class="btn btn-main">Créer la cohorte d'essai</button>
    </form>
<?php else: ?>
    <p class="msg msg-info" role="status">Le parcours order flow a déjà de vrais modules : on ne mélange pas l'essai avec eux.</p>
<?php endif; ?>
<?php else:
    $inscrits = lignes('SELECT c.id, c.prenom, c.role FROM inscriptions i JOIN comptes c ON c.id = i.compte_id WHERE i.cohorte_id = ? ORDER BY c.prenom', [$essai['id']]);
    $ids = array_map(static fn($r) => (int) $r['id'], $inscrits);
    $autres = array_values(array_filter(lignes('SELECT id, prenom, role FROM comptes ORDER BY prenom'), static fn($r) => !in_array((int) $r['id'], $ids, true)));
?>
    <div class="grid">
      <section class="box box-wide" aria-labelledby="es-t">
        <p class="eyebrow" id="es-t">En place</p>
        <h2><?= e($essai['nom']) ?> · <?= e($essai['parcours_nom']) ?></h2>
        <p>Départ le <?= e(date_fr((int) $essai['debut'])) ?>, donc semaine <?= semaine_cohorte($essai, count(modules_parcours((int) $essai['parcours_id']))) ?>. Le module 1 est ouvert ; les suivants s'ouvrent quand le quiz d'avant est validé (et à leur date).</p>
        <div class="row-act"><a class="btn btn-main m-0" href="/espace?c=<?= (int) $essai['id'] ?>">Ouvrir l'espace élève</a></div>
      </section>
      <section class="box" aria-labelledby="es-i">
        <p class="eyebrow" id="es-i">Inscrits</p>
        <h2><?= count($inscrits) ?> compte<?= count($inscrits) > 1 ? 's' : '' ?></h2>
        <ul class="list">
<?php foreach ($inscrits as $r): ?>
          <li><span class="grow"><?= e($r['prenom']) ?> <small>(<?= e(ROLES[$r['role']] ?? '') ?>)</small></span>
            <form method="post" action="/admin/essai"><?= champ_jeton() ?><input type="hidden" name="action" value="retirer"><input type="hidden" name="compte" value="<?= (int) $r['id'] ?>"><button type="submit" class="btn btn-line btn-sm">Retirer</button></form></li>
<?php endforeach; ?>
        </ul>
<?php if ($autres !== []): ?>
        <form method="post" action="/admin/essai" class="copie">
          <?= champ_jeton() ?>
          <input type="hidden" name="action" value="inscrire">
          <label class="cache" for="es-qui">Compte à inscrire</label>
          <select class="input" id="es-qui" name="compte">
<?php foreach ($autres as $r): ?>
            <option value="<?= (int) $r['id'] ?>"><?= e($r['prenom']) ?> (<?= e(ROLES[$r['role']] ?? '') ?>)</option>
<?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-line">Inscrire</button>
        </form>
<?php endif; ?>
      </section>
    </div>
    <form class="mt-28" method="post" action="/admin/essai">
      <?= champ_jeton() ?>
      <input type="hidden" name="action" value="supprimer">
      <button type="submit" class="btn btn-line">Effacer la cohorte d'essai</button>
      <p class="hint mt-8">Efface la cohorte, ses modules, ses leçons, les quiz, la progression de chacun et la vidéo copiée. Les comptes restent.</p>
    </form>
<?php endif; ?>
<?php
page_fin(['zone' => 'app']);
