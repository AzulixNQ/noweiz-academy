<?php
declare(strict_types=1);

$compte = exiger_connexion();
$cohorte = cohorte_active($compte);
if ($cohorte === null) rediriger('/espace');
$prog = progression($compte, $cohorte);
$apercu = !empty($cohorte['apercu']);
$cadenas = '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="10" height="7" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 0 1 5 0v2"/></svg>';

page_debut('Mon parcours', ['zone' => 'app', 'courant' => 'parcours']);
?>
    <p class="crumb"><?= e($cohorte['nom']) ?> · départ le <?= e(date_fr((int) $cohorte['debut'])) ?></p>
    <h1 class="h1-app">Mon parcours : <?= e(mb_strtolower($cohorte['parcours_nom'])) ?></h1>
    <?= annonce_html() ?>
    <p class="intro-app"><?= $apercu
        ? 'Aperçu : tous les modules sont ouverts pour toi.'
        : 'Un module s\'ouvre chaque semaine pour ta cohorte, une fois le quiz du module précédent validé (' . quiz_seuil() . ' % de bonnes réponses).' ?></p>
<?php if ($prog === []): ?>
    <p class="msg msg-info mt-28" role="status">Les modules de ce parcours ne sont pas encore en ligne.</p>
<?php else: ?>
    <ol class="mods">
<?php foreach ($prog as $m):
    $classe = $m['etat'] === 'verrouille' ? ' lock' : ((!$m['valide'] && $m['ouvert'] && !$apercu) ? ' now' : '');
    $details = (int) $m['nb_lecons'] . ' leçon' . ($m['nb_lecons'] > 1 ? 's' : '') . ((int) $m['nb_questions'] > 0 ? ' · 1 quiz' : '');
?>
      <li class="mod<?= $classe ?>">
        <span class="no"><?= sprintf('%02d', (int) $m['numero']) ?></span>
        <div class="mid"><h2><?= e($m['titre']) ?></h2><small><?= e($details) ?></small></div>
        <div class="end-side">
<?php if ($m['etat'] === 'verrouille'): ?>
          <?= $cadenas ?><span><?= e(texte_verrou($m, $prog)) ?></span>
<?php elseif ($m['valide']): ?>
          <span><?= $m['quiz'] !== null ? 'Quiz validé · ' . (int) $m['quiz']['note'] . '/' . (int) $m['quiz']['total'] : 'Validé' ?></span>
          <a class="btn btn-line btn-sm" href="/module?id=<?= (int) $m['id'] ?>">Revoir</a>
<?php else: ?>
          <span><?= $apercu ? 'Aperçu' : (int) $m['vues'] . ' leçon' . ($m['vues'] > 1 ? 's' : '') . ' sur ' . (int) $m['nb_lecons'] . ((int) $m['nb_questions'] > 0 ? ($m['quiz'] !== null ? ' · quiz à refaire' : ' · quiz à faire') : '') ?></span>
          <a class="btn <?= $apercu ? 'btn-line' : 'btn-main' ?> btn-sm" href="/module?id=<?= (int) $m['id'] ?>"><?= $apercu ? 'Ouvrir' : ($m['vues'] > 0 ? 'Continuer' : 'Commencer') ?></a>
<?php endif; ?>
        </div>
      </li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>
<?php
page_fin(['zone' => 'app']);
