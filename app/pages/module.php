<?php
declare(strict_types=1);

/* Un module : la leçon en cours (vidéo et texte), la liste des leçons, le quiz, le live de la semaine. */

$compte = exiger_connexion();
$id = (string) ($_GET['id'] ?? '');
$a = ctype_digit($id) ? acces_module($compte, (int) $id) : null;
if ($a === null) {
    http_response_code(404);
    page_message('Module fermé', "Ce module n'est pas ouvert pour toi.", "Il s'ouvre plus tard, ou il n'existe pas.", '/parcours', 'Revenir à mon parcours');
    exit;
}
['cohorte' => $cohorte, 'module' => $module, 'prog' => $prog] = $a;
$apercu = !empty($cohorte['apercu']);
$lecons = lecons_module((int) $module['id']);
[, $vues] = vues_compte((int) $compte['id'], (int) $cohorte['parcours_id']);

// « Leçon vue » : on note, puis on passe à la suivante (ou au quiz).
if (methode() === 'POST') {
    verifier_formulaire();
    $lid = (int) champ_recu('lecon', 12);
    $position = null;
    foreach ($lecons as $i => $l) if ((int) $l['id'] === $lid) $position = $i;
    if ($position === null) rediriger('/module?id=' . (int) $module['id']);
    if (!$apercu) marquer_vue((int) $compte['id'], $lid);
    $suivante = $lecons[$position + 1] ?? null;
    if ($suivante !== null) rediriger('/module?id=' . (int) $module['id'] . '&l=' . (int) $suivante['id']);
    rediriger((int) $module['nb_questions'] > 0 ? '/quiz?m=' . (int) $module['id'] : '/module?id=' . (int) $module['id'] . '&l=' . $lid);
}

// La leçon affichée : celle demandée, sinon la première pas encore vue.
$lecon = null;
$demande = (string) ($_GET['l'] ?? '');
foreach ($lecons as $i => $l) if (ctype_digit($demande) && (int) $l['id'] === (int) $demande) $lecon = $i;
if ($lecon === null) {
    foreach ($lecons as $i => $l) if (!isset($vues[(int) $l['id']])) { $lecon = $i; break; }
}
if ($lecon === null) $lecon = 0;
$l = $lecons[$lecon] ?? null;
$precedente = $lecons[$lecon - 1] ?? null;
$suivante = $lecons[$lecon + 1] ?? null;
$vue = $l !== null && isset($vues[(int) $l['id']]);

$suite = null;
foreach ($prog as $m) if ((int) $m['position'] === (int) $module['position'] + 1) $suite = $m;
$live = ligne('SELECT * FROM lives WHERE cohorte_id = ? AND module_id = ? ORDER BY debut DESC LIMIT 1', [$cohorte['id'], $module['id']]);

page_debut('Module ' . (int) $module['numero'], ['zone' => 'app', 'courant' => 'parcours']);
?>
    <p class="crumb"><a href="/parcours">Mon parcours</a> / Module <?= (int) $module['numero'] ?></p>
    <h1 class="h1-app">Module <?= (int) $module['numero'] ?> · <?= e($module['titre']) ?></h1>
    <?= annonce_html() ?>
<?php if (!empty($module['essai'])): ?>
    <p class="essai-note">Contenu d'essai pour tester le site : les vraies leçons seront celles de <?= e($cohorte['formateur']) ?>.</p>
<?php endif; ?>
<?php if ($l === null): ?>
    <p class="msg msg-info mt-28" role="status">Les leçons de ce module ne sont pas encore en ligne.</p>
<?php else: ?>
    <div class="lesson">
      <div class="lesson-main">
<?php if (fichier_video($l['video']) !== null): ?>
        <video class="player-vrai" controls preload="metadata" playsinline controlslist="nodownload noremoteplayback" disablepictureinpicture
               src="<?= e(lien_video($compte, 'l', (int) $l['id'])) ?>" aria-label="Vidéo de la leçon <?= (int) $l['numero'] ?>"></video>
<?php else: ?>
        <div class="player player-vide"><p>La vidéo de cette leçon n'est pas encore en ligne.</p></div>
<?php endif; ?>
        <div>
          <h2>Leçon <?= (int) $l['numero'] ?> · <?= e($l['titre']) ?></h2>
<?php if (!empty($l['duree'])): ?>
          <p class="hint mt-4"><?= e(duree_video((int) $l['duree'])) ?></p>
<?php endif; ?>
<?php if (!empty($l['texte'])): ?>
          <p class="lecon-texte"><?= e($l['texte']) ?></p>
<?php endif; ?>
          <div class="lecon-act">
<?php if ($precedente !== null): ?>
            <a class="btn btn-line" href="/module?id=<?= (int) $module['id'] ?>&amp;l=<?= (int) $precedente['id'] ?>">Leçon précédente</a>
<?php endif; ?>
            <form method="post" action="/module?id=<?= (int) $module['id'] ?>">
              <?= champ_jeton() ?>
              <input type="hidden" name="lecon" value="<?= (int) $l['id'] ?>">
              <button type="submit" class="btn btn-main"><?php
                if ($suivante !== null) echo $vue || $apercu ? 'Leçon suivante' : 'Leçon vue, passer à la suivante';
                elseif ((int) $module['nb_questions'] > 0) echo $vue || $apercu ? 'Aller au quiz' : 'Leçon vue, passer au quiz';
                else echo $vue ? 'Leçon vue' : 'Marquer comme vue';
              ?></button>
            </form>
          </div>
        </div>
      </div>
      <aside class="lesson-side" aria-label="Contenu du module">
        <div class="mini">
          <p class="eyebrow">Dans ce module</p>
          <ol class="list">
<?php foreach ($lecons as $i => $x):
    $etat = isset($vues[(int) $x['id']]) ? 'Vue' : ($i === $lecon ? 'En cours' : '');
?>
            <li<?= $i === $lecon ? ' class="cur"' : '' ?>><i class="ring<?= isset($vues[(int) $x['id']]) ? ' full' : '' ?>"></i><span class="grow"><a href="/module?id=<?= (int) $module['id'] ?>&amp;l=<?= (int) $x['id'] ?>"<?= $i === $lecon ? ' aria-current="page"' : '' ?>><?= e($x['titre']) ?></a></span><?php if ($etat !== ''): ?><small><?= $etat ?></small><?php endif; ?></li>
<?php endforeach; ?>
<?php if ((int) $module['nb_questions'] > 0): ?>
            <li><i class="ring<?= $module['valide'] ? ' full' : '' ?>"></i><span class="grow"><a href="/quiz?m=<?= (int) $module['id'] ?>">Quiz du module</a></span><small><?= $module['valide'] ? 'Validé' : ($module['quiz'] !== null ? 'À refaire' : 'À faire') ?></small></li>
<?php endif; ?>
          </ol>
        </div>
<?php if ($live !== null): ?>
        <div class="mini">
          <p class="eyebrow">Live du module</p>
          <b><?= e(date_fr((int) $live['debut'], true)) ?>, sur Discord</b>
          <p><?= e($live['titre']) ?>. <?= $live['replay'] !== null ? 'Le replay est dans « Lives et replays ».' : 'Le replay s\'ajoute après le live.' ?></p>
        </div>
<?php endif; ?>
<?php if ($suite !== null): ?>
        <div class="mini mini-dash">
          <p class="eyebrow">Module suivant</p>
          <b>Module <?= (int) $suite['numero'] ?> · <?= e($suite['titre']) ?></b>
          <p><?= $suite['ouvert'] ? 'Ouvert.' : e(texte_verrou($suite, $prog)) . '.' ?></p>
        </div>
<?php endif; ?>
      </aside>
    </div>
<?php endif; ?>
<?php
page_fin(['zone' => 'app']);
