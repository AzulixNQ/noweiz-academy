<?php
declare(strict_types=1);

/* Le classement de la cohorte : prénoms seulement, et seulement pour les membres de la cohorte (et l'équipe). */

$compte = exiger_connexion();
$cohorte = cohorte_active($compte);
if ($cohorte === null) rediriger('/espace');
$rang = classement($cohorte);

page_debut('Classement', ['zone' => 'app', 'courant' => 'classement']);
?>
    <p class="crumb"><?= e($cohorte['nom']) ?> · <?= e($cohorte['parcours_nom']) ?></p>
    <h1 class="h1-app">Classement de la cohorte</h1>
    <?= annonce_html() ?>
    <p class="intro-app">10 points par leçon vue, 10 points par bonne réponse (le meilleur essai de chaque quiz). On n'affiche que les prénoms.</p>
<?php if ($rang === []): ?>
    <p class="msg msg-info mt-28" role="status">Aucun élève n'est encore inscrit dans cette cohorte.</p>
<?php else: ?>
    <section class="box mt-28 mw-640" aria-label="Classement">
      <ol class="list rank serre">
<?php foreach ($rang as $l):
    $moi = $l['compte_id'] === (int) $compte['id'];
?>
        <li<?= $moi ? ' class="me"' : '' ?>><span class="pos"><?= (int) $l['rang'] ?></span><span class="grow"><?= $moi ? 'Toi (' . e($l['prenom']) . ')' : e($l['prenom']) ?></span><span class="num"><?= (int) $l['points'] ?> pts</span></li>
<?php endforeach; ?>
      </ol>
    </section>
<?php endif; ?>
<?php
page_fin(['zone' => 'app']);
