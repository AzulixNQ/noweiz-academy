<?php
declare(strict_types=1);

$compte = exiger_connexion();
$cohorte = cohorte_active($compte);
if ($cohorte === null) rediriger('/espace');
$lives = lives_cohorte((int) $cohorte['id']);
$maintenant = time();
$a_venir = array_values(array_filter($lives, static fn($l) => (int) $l['debut'] + (int) $l['duree'] * 60 > $maintenant));
$passes = array_reverse(array_values(array_filter($lives, static fn($l) => (int) $l['debut'] + (int) $l['duree'] * 60 <= $maintenant)));
$voir = (string) ($_GET['replay'] ?? '');
$replay = null;
if (ctype_digit($voir)) foreach ($passes as $l) if ((int) $l['id'] === (int) $voir && fichier_video($l['replay']) !== null) $replay = $l;
$salon = (string) ($cohorte['discord'] ?: reglage('discord'));

page_debut('Lives et replays', ['zone' => 'app', 'courant' => 'lives']);
?>
    <p class="crumb"><?= e($cohorte['nom']) ?> · <?= e($cohorte['parcours_nom']) ?> avec <?= e($cohorte['formateur']) ?></p>
    <h1 class="h1-app">Lives et replays</h1>
    <?= annonce_html() ?>
<?php if ($replay !== null): ?>
    <section class="lesson mw-980" aria-labelledby="r-titre">
      <div class="lesson-main">
        <video class="player-vrai" controls preload="metadata" playsinline controlslist="nodownload noremoteplayback" disablepictureinpicture
               src="<?= e(lien_video($compte, 'r', (int) $replay['id'])) ?>" aria-label="Replay : <?= e($replay['titre']) ?>"></video>
        <div>
          <h2 id="r-titre">Replay · <?= e($replay['titre']) ?></h2>
          <p class="lecon-texte">Live du <?= e(date_fr((int) $replay['debut'], true)) ?><?= $replay['replay_duree'] ? ', ' . e(duree_video((int) $replay['replay_duree'])) : '' ?>.</p>
          <div class="lecon-act"><a class="btn btn-line" href="/lives">Tous les lives</a></div>
        </div>
      </div>
    </section>
<?php endif; ?>
    <p class="intro-app">Les lives se passent sur Discord, dans le salon de la cohorte. Le replay s'ajoute ici après chaque live.</p>
<?php if ($lives === []): ?>
    <p class="msg msg-info mt-28" role="status">Aucun live n'est encore prévu pour cette cohorte.</p>
<?php endif; ?>
<?php if ($a_venir !== []): ?>
    <h2 class="eyebrow mt-32">À venir</h2>
    <ol class="lives">
<?php foreach ($a_venir as $l): ?>
      <li class="mod<?= $l === $a_venir[0] ? ' now' : '' ?>">
        <span class="live-date"><?= e(date_fr((int) $l['debut'], true)) ?></span>
        <div class="mid"><h2><?= e($l['titre']) ?></h2><small><?= (int) $l['duree'] ?> min, sur Discord</small></div>
        <div class="end-side"><a class="btn btn-line btn-sm" href="<?= e((string) ($l['lien'] ?: $salon)) ?>" target="_blank" rel="noopener">Ouvrir le salon</a></div>
      </li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>
<?php if ($passes !== []): ?>
    <h2 class="eyebrow mt-32">Déjà passés</h2>
    <ol class="lives">
<?php foreach ($passes as $l): ?>
      <li class="mod">
        <span class="live-date"><?= e(date_fr((int) $l['debut'])) ?></span>
        <div class="mid"><h2><?= e($l['titre']) ?></h2><small><?= fichier_video($l['replay']) !== null ? 'Replay' . ($l['replay_duree'] ? ' · ' . e(duree_video((int) $l['replay_duree'])) : '') : 'Replay bientôt en ligne' ?></small></div>
        <div class="end-side"><?php if (fichier_video($l['replay']) !== null): ?><a class="btn btn-line btn-sm" href="/lives?replay=<?= (int) $l['id'] ?>">Voir le replay</a><?php endif; ?></div>
      </li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>
<?php
page_fin(['zone' => 'app']);
