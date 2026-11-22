<?php
declare(strict_types=1);

/* Le quiz d'un module, corrigé sur le serveur. Réussi : on montre toutes les corrections. Raté : on dit quelles questions
   sont fausses, sans donner la bonne réponse (sinon le quiz se refait par cœur), et on peut le refaire autant de fois
   qu'on veut. On garde le meilleur essai. */

$compte = exiger_connexion();
$m = (string) ($_GET['m'] ?? '');
$a = ctype_digit($m) ? acces_module($compte, (int) $m) : null;
$questions = $a !== null ? questions_module((int) $a['module']['id']) : [];
if ($a === null || $questions === []) {
    http_response_code(404);
    page_message('Quiz fermé', "Ce quiz n'est pas ouvert pour toi.", "Le module s'ouvre plus tard, ou il n'a pas de quiz.", '/parcours', 'Revenir à mon parcours');
    exit;
}
['cohorte' => $cohorte, 'module' => $module, 'prog' => $prog] = $a;
$apercu = !empty($cohorte['apercu']);
$seuil = quiz_seuil();
$lettres = ['A', 'B', 'C', 'D', 'E', 'F'];

$resultat = null;
$erreur = '';
if (methode() === 'POST') {
    verifier_formulaire();
    $reponses = [];
    foreach ($questions as $q) {
        $v = $_POST['q' . $q['id']] ?? null;
        if (is_string($v) && ctype_digit($v) && (int) $v < count($q['choix'])) $reponses[(int) $q['id']] = (int) $v;
    }
    if (count($reponses) < count($questions)) {
        $erreur = 'Réponds à toutes les questions avant de valider (' . (count($questions) - count($reponses)) . ' sans réponse).';
    } else {
        [$note, $total, $detail] = corriger_quiz($questions, $reponses);
        if (!$apercu) enregistrer_quiz((int) $compte['id'], (int) $module['id'], $note, $total, $reponses);
        $pct = intdiv(100 * $note, max(1, $total));
        $resultat = ['note' => $note, 'total' => $total, 'pct' => $pct, 'reussi' => $pct >= $seuil, 'detail' => $detail, 'reponses' => $reponses];
    }
}
$suite = null;
foreach ($prog as $x) if ((int) $x['position'] === (int) $module['position'] + 1) $suite = $x;

page_debut('Quiz du module ' . (int) $module['numero'], ['zone' => 'app', 'courant' => 'parcours']);
?>
    <p class="crumb"><a href="/parcours">Mon parcours</a> / <a href="/module?id=<?= (int) $module['id'] ?>">Module <?= (int) $module['numero'] ?></a> / Quiz</p>
    <h1 class="h1-app">Quiz du module <?= (int) $module['numero'] ?></h1>
    <?= annonce_html() ?>
<?php if ($resultat !== null): ?>
    <section class="quiz mt-28 mw-860" aria-labelledby="q-res">
      <div class="score"><b id="q-res"><?= $resultat['note'] ?>/<?= $resultat['total'] ?></b><span><?= $resultat['reussi'] ? 'Quiz validé.' : 'Pas encore : il faut ' . $seuil . ' % de bonnes réponses.' ?></span></div>
      <p class="quiz-expl"><?php
        if ($apercu) echo 'Aperçu : ce résultat n\'est pas enregistré.';
        elseif ($resultat['reussi'] && $suite !== null) echo $suite['ouvre_le'] > time() ? 'Le module ' . (int) $suite['numero'] . ' s\'ouvre le ' . e(date_fr((int) $suite['ouvre_le'])) . '.' : 'Le module ' . (int) $suite['numero'] . ' est ouvert.';
        elseif ($resultat['reussi']) echo 'C\'était le dernier module : ton certificat est prêt si tous les quiz sont validés.';
        else echo 'Revois les leçons des questions fausses, puis refais le quiz : on garde ton meilleur essai.';
      ?></p>
      <div class="lecon-act">
<?php if ($resultat['reussi'] && $suite !== null && $suite['ouvre_le'] <= time() && !$apercu): ?>
        <a class="btn btn-main" href="/module?id=<?= (int) $suite['id'] ?>">Ouvrir le module <?= (int) $suite['numero'] ?></a>
<?php elseif (!$resultat['reussi']): ?>
        <a class="btn btn-main" href="/quiz?m=<?= (int) $module['id'] ?>">Refaire le quiz</a>
        <a class="btn btn-line" href="/module?id=<?= (int) $module['id'] ?>">Revoir les leçons</a>
<?php else: ?>
        <a class="btn btn-main" href="/parcours">Revenir à mon parcours</a>
<?php endif; ?>
      </div>
    </section>
    <div class="quiz-form mw-860">
<?php foreach ($questions as $i => $q):
    $d = $resultat['detail'][(int) $q['id']];
?>
      <section class="quiz" aria-labelledby="q-<?= (int) $q['id'] ?>">
        <div class="quiz-head"><span id="q-<?= (int) $q['id'] ?>">Question <?= $i + 1 ?> sur <?= count($questions) ?></span><span><?= $d['juste'] ? 'Juste' : 'Faux' ?></span></div>
        <h2><?= e($q['enonce']) ?></h2>
        <div class="opts opts-col">
<?php foreach (ordre_choix((int) $compte['id'], (int) $q['id'], count($q['choix'])) as $pos => $k):
    $texte = $q['choix'][$k];
    $choisi = (int) $d['reponse'] === $k;
    $classe = 'neutre';
    $marque = '';
    if ($choisi) { $classe = $d['juste'] ? 'juste' : 'faux'; $marque = 'Ta réponse'; }
    elseif ($resultat['reussi'] && $k === (int) $q['bonne']) { $classe = 'juste'; $marque = 'Bonne réponse'; }
?>
          <div class="ans <?= $classe ?>"><i><?= $lettres[$pos] ?? ($pos + 1) ?></i><span><?= e($texte) ?></span><?php if ($marque !== ''): ?><span class="marque"><?= $marque ?></span><?php endif; ?></div>
<?php endforeach; ?>
        </div>
<?php if ($resultat['reussi'] && !empty($q['explication'])): ?>
        <p class="quiz-expl"><?= e($q['explication']) ?></p>
<?php endif; ?>
      </section>
<?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="intro-app"><?= count($questions) ?> questions. Il faut <?= $seuil ?> % de bonnes réponses pour valider le module<?= $suite !== null ? ' et ouvrir le suivant' : '' ?>. Tu peux le refaire autant de fois que tu veux.<?= $module['quiz'] !== null ? ' Ton meilleur essai : ' . (int) $module['quiz']['note'] . '/' . (int) $module['quiz']['total'] . '.' : '' ?></p>
<?php if ($erreur !== ''): ?>
    <?= bloc_message('err', $erreur) ?>
<?php endif; ?>
    <form method="post" action="/quiz?m=<?= (int) $module['id'] ?>" class="quiz-form mw-860">
      <?= champ_jeton() ?>
<?php foreach ($questions as $i => $q): ?>
      <div class="quiz"><fieldset class="quiz-q">
        <legend><small>Question <?= $i + 1 ?> sur <?= count($questions) ?></small><?= e($q['enonce']) ?></legend>
        <div class="opts">
<?php foreach (ordre_choix((int) $compte['id'], (int) $q['id'], count($q['choix'])) as $pos => $k):
    $texte = $q['choix'][$k];
    $coche = isset($_POST['q' . $q['id']]) && (string) $_POST['q' . $q['id']] === (string) $k;
?>
          <label class="ans"><input type="radio" name="q<?= (int) $q['id'] ?>" value="<?= $k ?>"<?= $coche ? ' checked' : '' ?> required><i><?= $lettres[$pos] ?? ($pos + 1) ?></i><span><?= e($texte) ?></span></label>
<?php endforeach; ?>
        </div>
      </fieldset></div>
<?php endforeach; ?>
      <div><button type="submit" class="btn btn-main">Valider mes réponses</button></div>
    </form>
<?php endif; ?>
<?php
page_fin(['zone' => 'app']);
