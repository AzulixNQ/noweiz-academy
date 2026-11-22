<?php
declare(strict_types=1);

/* Une question de quiz : l'énoncé, 2 à 4 réponses, la bonne, et l'explication montrée quand le quiz est réussi. */

$compte = exiger_formateur();
$id = ctype_digit((string) ($_GET['id'] ?? '')) ? (int) $_GET['id'] : 0;
$question = $id > 0 ? question_par_id($id) : null;
$mid = $question !== null ? (int) $question['module_id'] : (ctype_digit((string) ($_GET['m'] ?? '')) ? (int) $_GET['m'] : 0);
$module = module_par_id($mid);
if ($module === null || ($id > 0 && $question === null) || !peut_gerer($compte, (int) $module['parcours_id'])) refuser_parcours();

$valeurs = [
    'enonce' => $question['enonce'] ?? '',
    'choix' => array_pad($question['choix'] ?? [], 4, ''),
    'bonne' => (int) ($question['bonne'] ?? 0),
    'explication' => (string) ($question['explication'] ?? ''),
];
$erreur = '';

if (methode() === 'POST') {
    verifier_formulaire();
    $enonce = texte_recu('enonce', 400, true);
    $choix = [];
    $bonne = -1;
    $coche = (string) ($_POST['bonne'] ?? '');
    for ($k = 0; $k < 4; $k++) {
        $t = texte_recu('choix_' . $k, 200);
        if ($t === '') continue;
        if ($coche === (string) $k) $bonne = count($choix);
        $choix[] = $t;
    }
    $explication = texte_recu('explication', 600, true);
    $valeurs = ['enonce' => $enonce, 'choix' => array_pad(array_map(static fn($k) => texte_recu('choix_' . $k, 200), range(0, 3)), 4, ''), 'bonne' => (int) $coche, 'explication' => $explication];
    if ($enonce === '') $erreur = 'Écris la question.';
    elseif (count($choix) < 2) $erreur = 'Il faut au moins deux réponses.';
    elseif ($bonne < 0) $erreur = 'Coche la bonne réponse (parmi celles qui sont remplies).';
    else {
        question_enregistrer($question !== null ? $id : null, $mid, $enonce, $choix, $bonne, $explication);
        annoncer('question-enregistree');
        rediriger(isset($_POST['encore']) ? '/formateur/question?m=' . $mid : '/formateur/module?id=' . $mid . '#quiz');
    }
}

page_debut($question !== null ? 'Question ' . (int) $question['numero'] : 'Nouvelle question', ['zone' => 'app', 'courant' => 'formateur']);
?>
    <p class="crumb"><a href="/formateur?p=<?= (int) $module['parcours_id'] ?>">Espace formateur</a> / <a href="/formateur/module?id=<?= $mid ?>#quiz">Module <?= (int) $module['numero'] ?></a> / <?= $question !== null ? 'Question ' . (int) $question['numero'] : 'Nouvelle question' ?></p>
    <h1 class="h1-app"><?= $question !== null ? 'Question ' . (int) $question['numero'] : 'Nouvelle question' ?></h1>
    <?= annonce_html() ?>
    <section class="box mt-28 mw-860" aria-label="La question">
<?php if ($erreur !== ''): ?><?= bloc_message('err', $erreur) ?><?php endif; ?>
      <form method="post" action="/formateur/question?<?= $question !== null ? 'id=' . $id : 'm=' . $mid ?>">
        <?= champ_jeton() ?>
        <div class="field"><label for="q-enonce">La question</label><textarea class="input" id="q-enonce" name="enonce" rows="2" maxlength="400" required placeholder="Beaucoup d'achats au marché arrivent sur un niveau, et le prix ne monte pas. Que se passe-t-il ?"><?= e($valeurs['enonce']) ?></textarea></div>
        <fieldset class="champs-reponses">
          <legend>Les réponses : coche la bonne</legend>
<?php foreach (['A', 'B', 'C', 'D'] as $k => $lettre): ?>
          <div class="reponse">
            <input type="radio" name="bonne" id="q-bonne-<?= $k ?>" value="<?= $k ?>"<?= $valeurs['bonne'] === $k ? ' checked' : '' ?> required>
            <label for="q-bonne-<?= $k ?>" class="cache">La réponse <?= $lettre ?> est la bonne</label>
            <label for="q-choix-<?= $k ?>" class="reponse-lettre"><?= $lettre ?></label>
            <input class="input" id="q-choix-<?= $k ?>" name="choix_<?= $k ?>" maxlength="200" value="<?= e($valeurs['choix'][$k]) ?>"<?= $k < 2 ? ' required' : '' ?> placeholder="<?= $k < 2 ? 'Réponse ' . $lettre : 'Réponse ' . $lettre . ' (facultative)' ?>">
          </div>
<?php endforeach; ?>
          <p class="hint">L'ordre ne compte pas pour toi : tu peux toujours mettre la bonne en A.</p>
        </fieldset>
        <div class="field"><label for="q-expl">Explication <span class="opt-note">(montrée quand le quiz est réussi)</span></label><textarea class="input" id="q-expl" name="explication" rows="3" maxlength="600"><?= e($valeurs['explication']) ?></textarea></div>
        <div class="lecon-act">
          <button type="submit" class="btn btn-main">Enregistrer</button>
<?php if ($question === null): ?>
          <button type="submit" name="encore" value="1" class="btn btn-line">Enregistrer et en ajouter une autre</button>
<?php endif; ?>
        </div>
      </form>
    </section>
<?php
page_fin(['zone' => 'app']);
