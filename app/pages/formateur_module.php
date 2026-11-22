<?php
declare(strict_types=1);

/* Un module, côté formateur : son titre, ses leçons (ordre, vidéo), son quiz, et l'effacer. */

$compte = exiger_formateur();
$id = ctype_digit((string) ($_GET['id'] ?? '')) ? (int) $_GET['id'] : 0;
$module = module_par_id($id);
if ($module === null || !peut_gerer($compte, (int) $module['parcours_id'])) refuser_parcours();
$erreur = '';

if (methode() === 'POST') {
    verifier_formulaire();
    $action = champ_recu('action', 20);
    $retour = '/formateur/module?id=' . $id;
    if ($action === 'modifier') {
        $titre = texte_recu('titre', 120);
        if ($titre === '') $erreur = 'Donne un titre au module.';
        else { module_modifier($id, $titre, texte_recu('resume', 400, true)); annoncer('profil'); rediriger($retour); }
    } elseif ($action === 'lecon-deplacer') {
        $l = lecon_par_id((int) champ_recu('lecon', 12));
        if ($l !== null && (int) $l['module_id'] === $id) lecon_deplacer((int) $l['id'], champ_recu('sens', 3) === '-1' ? -1 : 1);
        rediriger($retour . '#lecons');
    } elseif ($action === 'question-supprimer') {
        $q = question_par_id((int) champ_recu('question', 12));
        if ($q !== null && (int) $q['module_id'] === $id) { question_supprimer((int) $q['id']); annoncer('question-supprimee'); }
        rediriger($retour . '#quiz');
    } elseif ($action === 'supprimer') {
        module_supprimer($id);
        annoncer('module-supprime');
        rediriger('/formateur?p=' . (int) $module['parcours_id'] . '#modules');
    } else {
        rediriger($retour);
    }
}

$lecons = lecons_module($id);
$questions = questions_module($id);
$lettres = ['A', 'B', 'C', 'D'];
$confirmer = isset($_GET['supprimer']);
$cohorte_apercu = ligne('SELECT id FROM cohortes WHERE parcours_id = ? ORDER BY debut DESC LIMIT 1', [$module['parcours_id']]);

page_debut('Module ' . (int) $module['numero'], ['zone' => 'app', 'courant' => 'formateur']);
?>
    <p class="crumb"><a href="/formateur?p=<?= (int) $module['parcours_id'] ?>">Espace formateur</a> / Module <?= (int) $module['numero'] ?></p>
    <h1 class="h1-app">Module <?= (int) $module['numero'] ?> · <?= e($module['titre']) ?></h1>
    <?= annonce_html() ?>
<?php if ($confirmer): ?>
    <section class="box mt-28 mw-640 box-danger" aria-labelledby="sup-t">
      <h2 id="sup-t">Effacer ce module ?</h2>
      <p>Ses <?= count($lecons) ?> leçons (avec leurs vidéos), son quiz et la progression des élèves sur ce module seront effacés. Les lives restent. On ne peut pas revenir en arrière.</p>
      <div class="lecon-act">
        <?= bouton_action('supprimer', 'Effacer le module', [], 'btn btn-main') ?>
        <a class="btn btn-line" href="/formateur/module?id=<?= $id ?>">Garder le module</a>
      </div>
    </section>
<?php endif; ?>
    <div class="grid">
      <section class="box box-wide" aria-labelledby="l-t" id="lecons">
        <div class="box-head"><h2 id="l-t">Les leçons</h2><a class="btn btn-main btn-sm m-0" href="/formateur/lecon?m=<?= $id ?>">Ajouter une leçon</a></div>
<?php if ($lecons === []): ?>
        <p>Aucune leçon. Ajoute la première : un titre, une vidéo, c'est tout.</p>
<?php else: ?>
        <ol class="list liste-gestion">
<?php foreach ($lecons as $i => $l): ?>
          <li>
            <span class="pos"><?= (int) $l['numero'] ?></span>
            <span class="grow"><a href="/formateur/lecon?id=<?= (int) $l['id'] ?>"><?= e($l['titre']) ?></a><small class="sous"><?= fichier_video($l['video']) !== null ? 'Vidéo' . ($l['duree'] ? ' · ' . e(duree_video((int) $l['duree'])) : '') : 'Pas de vidéo' ?></small></span>
            <?= $i > 0 ? bouton_action('lecon-deplacer', '↑', ['lecon' => $l['id'], 'sens' => -1], 'btn btn-line btn-sm btn-carre', 'Remonter la leçon ' . $l['numero']) : '' ?>
            <?= $i < count($lecons) - 1 ? bouton_action('lecon-deplacer', '↓', ['lecon' => $l['id'], 'sens' => 1], 'btn btn-line btn-sm btn-carre', 'Descendre la leçon ' . $l['numero']) : '' ?>
          </li>
<?php endforeach; ?>
        </ol>
<?php endif; ?>
      </section>
      <section class="box" aria-labelledby="m-t">
        <h2 id="m-t">Le module</h2>
<?php if ($erreur !== ''): ?><?= bloc_message('err', $erreur) ?><?php endif; ?>
        <form method="post" action="/formateur/module?id=<?= $id ?>">
          <?= champ_jeton() ?>
          <input type="hidden" name="action" value="modifier">
          <div class="field"><label for="m-titre">Titre</label><input class="input" id="m-titre" name="titre" maxlength="120" required value="<?= e($module['titre']) ?>"></div>
          <div class="field"><label for="m-resume">En une phrase</label><textarea class="input" id="m-resume" name="resume" rows="3" maxlength="400"><?= e((string) $module['resume']) ?></textarea></div>
          <button type="submit" class="btn btn-line">Enregistrer</button>
        </form>
<?php if ($cohorte_apercu !== null): ?>
        <a class="doux t-15" href="/module?id=<?= $id ?>&amp;c=<?= (int) $cohorte_apercu['id'] ?>">Voir comme un élève</a>
<?php endif; ?>
      </section>
    </div>
    <section class="box mt-16 mw-980" aria-labelledby="q-t" id="quiz">
      <div class="box-head"><h2 id="q-t">Le quiz</h2><a class="btn btn-line btn-sm m-0" href="/formateur/question?m=<?= $id ?>">Ajouter une question</a></div>
      <p><?= $questions === []
          ? 'Pas de quiz : le module est validé quand toutes ses leçons sont vues.'
          : count($questions) . ' question' . (count($questions) > 1 ? 's' : '') . '. Il faut ' . quiz_seuil() . ' % de bonnes réponses pour valider le module et ouvrir le suivant.' ?></p>
<?php if ($questions !== []): ?>
      <ol class="list liste-gestion">
<?php foreach ($questions as $q): ?>
        <li>
          <span class="pos"><?= (int) $q['numero'] ?></span>
          <span class="grow"><a href="/formateur/question?id=<?= (int) $q['id'] ?>"><?= e($q['enonce']) ?></a><small class="sous">Bonne réponse : <?= $lettres[(int) $q['bonne']] ?? '?' ?> · <?= e($q['choix'][(int) $q['bonne']] ?? '') ?></small></span>
          <?= bouton_action('question-supprimer', 'Effacer', ['question' => $q['id']]) ?>
        </li>
<?php endforeach; ?>
      </ol>
<?php endif; ?>
    </section>
    <p class="mt-28"><a class="lien-danger" href="/formateur/module?id=<?= $id ?>&amp;supprimer=1">Effacer ce module</a></p>
<?php
page_fin(['zone' => 'app']);
