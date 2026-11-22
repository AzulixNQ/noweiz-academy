<?php
declare(strict_types=1);

/* Le certificat : quand tous les modules du parcours sont validés. Une page à imprimer (ou à enregistrer en PDF). */

$compte = exiger_connexion();
$cohorte = cohorte_active($compte);
if ($cohorte === null) rediriger('/espace');
$prog = progression($compte, $cohorte);
$nb = count($prog);
$valides = nombre_valides($prog);
$pret = $nb > 0 && $valides === $nb && empty($cohorte['apercu']);
$fin = 0;
if ($pret) $fin = (int) valeur('SELECT MAX(q.fait_le) FROM quiz q JOIN modules m ON m.id = q.module_id WHERE q.compte_id = ? AND m.parcours_id = ?', [$compte['id'], $cohorte['parcours_id']]);

page_debut('Certificat', ['zone' => 'app', 'courant' => 'certificat']);
?>
    <p class="crumb no-print"><?= e($cohorte['nom']) ?> · <?= e($cohorte['parcours_nom']) ?></p>
    <h1 class="h1-app no-print">Ton certificat</h1>
    <?= annonce_html() ?>
<?php if (!$pret): ?>
    <p class="intro-app"><?= !empty($cohorte['apercu'])
        ? 'Aperçu : le certificat se débloque pour un élève quand il a validé tous les modules.'
        : 'Il se débloque quand les ' . $nb . ' modules sont validés. Tu en as validé ' . $valides . '.' ?></p>
    <div class="lecon-act"><a class="btn btn-main" href="/parcours">Voir mon parcours</a></div>
<?php else: ?>
    <div class="lecon-act no-print"><a class="btn btn-main" href="#" data-imprimer>Imprimer ou enregistrer en PDF</a></div>
    <article class="cert" aria-label="Certificat">
      <p><?= e((string) reglage('nom')) ?> certifie que</p>
      <p class="cert-nom"><?= e($compte['prenom']) ?></p>
      <p>a suivi et validé les <?= $nb ?> modules du parcours <b><?= e($cohorte['parcours_nom']) ?></b> avec <?= e($cohorte['formateur']) ?>, <?= e($cohorte['nom']) ?>.</p>
      <div class="cert-pied"><span>Validé le <?= e(date_fr($fin ?: time())) ?></span><span>Formation, pas un diplôme d'État</span></div>
    </article>
<?php endif; ?>
<?php
page_fin(['zone' => 'app']);
