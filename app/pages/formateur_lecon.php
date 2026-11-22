<?php
declare(strict_types=1);

/* Une leçon, côté formateur : la créer, changer son titre et son texte, ajouter ou remplacer sa vidéo, l'effacer. */

$compte = exiger_formateur();
$json = str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json');
$id = ctype_digit((string) ($_GET['id'] ?? '')) ? (int) $_GET['id'] : 0;
$lecon = $id > 0 ? lecon_par_id($id) : null;
if ($id > 0 && ($lecon === null || !peut_gerer($compte, (int) $lecon['parcours_id']))) refuser_parcours();
$erreur = '';

if (methode() === 'POST') {
    verifier_formulaire();
    $action = champ_recu('action', 20);
    if ($action === 'creer') {
        $module = module_par_id((int) champ_recu('module', 12));
        $titre = texte_recu('titre', 120);
        if ($module === null || !peut_gerer($compte, (int) $module['parcours_id'])) refuser_parcours();
        if ($titre === '') {
            $erreur = 'Donne un titre à la leçon.';
            if ($json) { http_response_code(422); header('Content-Type: application/json'); echo json_encode(['erreur' => $erreur]); exit; }
        } else {
            $nid = lecon_creer((int) $module['id'], $titre, texte_recu('texte', 4000, true));
            annoncer('lecon-creee');
            if ($json) { header('Content-Type: application/json'); header('Cache-Control: no-store'); echo json_encode(['id' => $nid, 'retour' => '/formateur/lecon?id=' . $nid]); exit; }
            rediriger('/formateur/lecon?id=' . $nid);
        }
    } elseif ($lecon !== null && $action === 'modifier') {
        $titre = texte_recu('titre', 120);
        if ($titre === '') $erreur = 'Donne un titre à la leçon.';
        else { lecon_modifier($id, $titre, texte_recu('texte', 4000, true)); annoncer('profil'); rediriger('/formateur/lecon?id=' . $id); }
    } elseif ($lecon !== null && $action === 'sans-video') {
        lecon_sans_video($id);
        annoncer('video-retiree');
        rediriger('/formateur/lecon?id=' . $id);
    } elseif ($lecon !== null && $action === 'supprimer') {
        lecon_supprimer($id);
        annoncer('lecon-supprimee');
        rediriger('/formateur/module?id=' . (int) $lecon['module_id']);
    } else {
        rediriger($lecon !== null ? '/formateur/lecon?id=' . $id : '/formateur');
    }
}

// Nouvelle leçon : ?m=<module>
if ($lecon === null) {
    $mid = ctype_digit((string) ($_GET['m'] ?? '')) ? (int) $_GET['m'] : (int) champ_recu('module', 12);
    $module = module_par_id($mid);
    if ($module === null || !peut_gerer($compte, (int) $module['parcours_id'])) refuser_parcours();
    page_debut('Nouvelle leçon', ['zone' => 'app', 'courant' => 'formateur']);
    ?>
    <p class="crumb"><a href="/formateur?p=<?= (int) $module['parcours_id'] ?>">Espace formateur</a> / <a href="/formateur/module?id=<?= (int) $module['id'] ?>">Module <?= (int) $module['numero'] ?></a> / Nouvelle leçon</p>
    <h1 class="h1-app">Nouvelle leçon</h1>
    <?= annonce_html() ?>
    <section class="box mt-28 mw-860" aria-label="Nouvelle leçon">
<?php if ($erreur !== ''): ?><?= bloc_message('err', $erreur) ?><?php endif; ?>
      <form method="post" action="/formateur/lecon" class="form-lecon" data-nouvelle-lecon>
        <?= champ_jeton() ?>
        <input type="hidden" name="action" value="creer"><input type="hidden" name="module" value="<?= (int) $module['id'] ?>">
        <p class="note-faint">Module <?= (int) $module['numero'] ?> · <?= e($module['titre']) ?></p>
        <div class="field"><label for="nl-titre">Titre de la leçon</label><input class="input" id="nl-titre" name="titre" maxlength="120" required value="<?= e(texte_recu('titre', 120)) ?>"></div>
        <div class="field"><label for="nl-texte">Texte sous la vidéo <span class="opt-note">(facultatif)</span></label><textarea class="input" id="nl-texte" name="texte" rows="4" maxlength="4000"><?= e(texte_recu('texte', 4000, true)) ?></textarea></div>
        <?= depot_video('lecon', null) ?>
        <div class="row-act"><button type="submit" class="btn btn-main m-0">Mettre la leçon en ligne</button></div>
      </form>
    </section>
<?php
    page_fin(['zone' => 'app']);
    exit;
}

$module = module_par_id((int) $lecon['module_id']);
$confirmer = isset($_GET['supprimer']);
page_debut('Leçon ' . (int) $lecon['numero'], ['zone' => 'app', 'courant' => 'formateur']);
?>
    <p class="crumb"><a href="/formateur?p=<?= (int) $module['parcours_id'] ?>">Espace formateur</a> / <a href="/formateur/module?id=<?= (int) $module['id'] ?>">Module <?= (int) $module['numero'] ?></a> / Leçon <?= (int) $lecon['numero'] ?></p>
    <h1 class="h1-app">Leçon <?= (int) $lecon['numero'] ?> · <?= e($lecon['titre']) ?></h1>
    <?= annonce_html() ?>
<?php if ($confirmer): ?>
    <section class="box mt-28 mw-640 box-danger" aria-labelledby="sup-t">
      <h2 id="sup-t">Effacer cette leçon ?</h2>
      <p>La leçon, sa vidéo et les « leçon vue » des élèves seront effacées. On ne peut pas revenir en arrière.</p>
      <div class="lecon-act">
        <?= bouton_action('supprimer', 'Effacer la leçon', [], 'btn btn-main') ?>
        <a class="btn btn-line" href="/formateur/lecon?id=<?= $id ?>">Garder la leçon</a>
      </div>
    </section>
<?php endif; ?>
    <div class="lesson">
      <div class="lesson-main">
        <section class="box" aria-labelledby="v-t">
          <h2 id="v-t">La vidéo</h2>
<?php if (fichier_video($lecon['video']) !== null): ?>
          <video class="player-vrai" controls preload="metadata" playsinline controlslist="nodownload noremoteplayback" disablepictureinpicture src="<?= e(lien_video($compte, 'l', $id)) ?>" aria-label="Vidéo de la leçon"></video>
          <p class="note-faint">En ligne<?= $lecon['duree'] ? ' · ' . e(duree_video((int) $lecon['duree'])) : '' ?>. Glisse une autre vidéo pour la remplacer.</p>
          <?= depot_video('lecon', $id, 'Remplacer la vidéo') ?>
          <?= bouton_action('sans-video', 'Retirer la vidéo') ?>
<?php else: ?>
          <p>Pas encore de vidéo : les élèves voient « La vidéo de cette leçon n'est pas encore en ligne ».</p>
          <?= depot_video('lecon', $id) ?>
<?php endif; ?>
        </section>
      </div>
      <aside class="lesson-side">
        <section class="box" aria-labelledby="t-t">
          <h2 id="t-t">Titre et texte</h2>
<?php if ($erreur !== ''): ?><?= bloc_message('err', $erreur) ?><?php endif; ?>
          <form method="post" action="/formateur/lecon?id=<?= $id ?>">
            <?= champ_jeton() ?>
            <input type="hidden" name="action" value="modifier">
            <div class="field"><label for="l-titre">Titre</label><input class="input" id="l-titre" name="titre" maxlength="120" required value="<?= e($lecon['titre']) ?>"></div>
            <div class="field"><label for="l-texte">Texte sous la vidéo</label><textarea class="input" id="l-texte" name="texte" rows="6" maxlength="4000"><?= e((string) $lecon['texte']) ?></textarea></div>
            <button type="submit" class="btn btn-line">Enregistrer</button>
          </form>
        </section>
        <a class="lien-danger" href="/formateur/lecon?id=<?= $id ?>&amp;supprimer=1">Effacer cette leçon</a>
      </aside>
    </div>
<?php
page_fin(['zone' => 'app']);
