<?php
declare(strict_types=1);

/* L'espace formateur, page d'accueil : déposer une leçon en glissant sa vidéo, programmer un live, les modules du parcours,
   les cohortes, et (administrateur) l'équipe de chaque parcours. */

$compte = exiger_formateur();
$admin = $compte['role'] === 'admin';
$geres = parcours_geres($compte);
$voulu = ctype_digit((string) ($_GET['p'] ?? '')) ? (int) $_GET['p'] : (int) (lire_temoin('parcours-formateur') ?: 0);
$parcours = null;
foreach ($geres as $p) if ((int) $p['id'] === $voulu) $parcours = $p;
if ($parcours === null && $geres !== []) $parcours = $geres[0];
if ($parcours !== null && isset($_GET['p']) && !headers_sent()) poser_temoin('parcours-formateur', (string) $parcours['id'], 180 * 86400);
$erreurs = [];

if (methode() === 'POST') {
    verifier_formulaire();
    $action = champ_recu('action', 30);
    $pid = (int) champ_recu('parcours', 12);
    if (!peut_gerer($compte, $pid)) refuser_parcours();
    $retour = '/formateur?p=' . $pid;
    if ($action === 'module') {
        $titre = texte_recu('titre', 120);
        if ($titre === '') { $erreurs['module'] = 'Donne un titre au module.'; }
        else { module_creer($pid, $titre, texte_recu('resume', 400, true)); annoncer('module-cree'); rediriger($retour . '#modules'); }
    } elseif ($action === 'deplacer') {
        $m = module_par_id((int) champ_recu('module', 12));
        if ($m !== null && (int) $m['parcours_id'] === $pid) module_deplacer((int) $m['id'], champ_recu('sens', 3) === '-1' ? -1 : 1);
        rediriger($retour . '#modules');
    } elseif ($action === 'live') {
        $c = cohorte_par_id((int) champ_recu('cohorte', 12));
        $debut = date_recue(champ_recu('jour', 10), champ_recu('heure', 5));
        $titre = texte_recu('titre', 120);
        $mid = (int) champ_recu('module', 12);
        $lien = texte_recu('lien', 300);
        if ($c === null || (int) $c['parcours_id'] !== $pid) $erreurs['live'] = 'Choisis la cohorte.';
        elseif ($debut === null) $erreurs['live'] = 'Donne la date et l\'heure du live.';
        elseif ($titre === '') $erreurs['live'] = 'Donne un titre au live (par exemple : « Les exercices du module 3 »).';
        elseif (!lien_discord_valable($lien)) $erreurs['live'] = 'Le lien doit être un lien Discord (https://discord.gg/… ou https://discord.com/…).';
        else {
            $m = $mid > 0 ? module_par_id($mid) : null;
            live_creer((int) $c['id'], $m !== null && (int) $m['parcours_id'] === $pid ? (int) $m['id'] : null, $debut, max(15, min(300, (int) champ_recu('duree', 3) ?: 60)), $titre, $lien);
            annoncer('live-cree');
            rediriger($retour);
        }
    } elseif ($action === 'cohorte' && $admin) {
        $nom = texte_recu('nom', 80);
        $debut = date_recue(champ_recu('jour', 10));
        $places = (int) champ_recu('places', 4);
        $lien = texte_recu('discord', 300);
        if ($nom === '') $erreurs['cohorte'] = 'Donne un nom à la cohorte (par exemple : « Cohorte 1 »).';
        elseif ($debut === null) $erreurs['cohorte'] = 'Donne la date de départ.';
        elseif ($places < 1 || $places > 500) $erreurs['cohorte'] = 'Le nombre de places va de 1 à 500.';
        elseif (!lien_discord_valable($lien)) $erreurs['cohorte'] = 'Le lien du salon doit être un lien Discord.';
        else { $cid = cohorte_creer($pid, $nom, $debut, $places, $lien); annoncer('cohorte-creee'); rediriger('/formateur/cohorte?id=' . $cid); }
    } elseif ($action === 'equipe' && $admin) {
        $c = compte_par_id((int) champ_recu('compte', 12));
        if ($c !== null && $c['role'] === 'formateur') { equipe_ajouter((int) $c['id'], $pid); annoncer('equipe-ajoute'); }
        rediriger($retour . '#equipe');
    } elseif ($action === 'equipe-retirer' && $admin) {
        equipe_retirer((int) champ_recu('compte', 12), $pid);
        annoncer('equipe-retire');
        rediriger($retour . '#equipe');
    } else {
        rediriger($retour);
    }
}

page_debut('Espace formateur', ['zone' => 'app', 'courant' => 'formateur']);
?>
    <p class="crumb">Espace formateur</p>
<?php if ($parcours === null): ?>
    <h1 class="h1-app">Espace formateur</h1>
    <?= annonce_html() ?>
    <p class="msg msg-info mt-28" role="status">L'administrateur ne t'a pas encore confié de parcours. Dès que c'est fait, tu pourras déposer tes leçons et programmer tes lives ici.</p>
<?php else:
    $pid = (int) $parcours['id'];
    $mods = modules_parcours($pid);
    $cohortes = cohortes_parcours($pid);
    $videos = [];
    foreach (lignes('SELECT l.module_id, COUNT(*) AS n FROM lecons l JOIN modules m ON m.id = l.module_id WHERE m.parcours_id = ? AND l.video IS NOT NULL GROUP BY l.module_id', [$pid]) as $v) $videos[(int) $v['module_id']] = (int) $v['n'];
    $choix_module = (int) ($_GET['m'] ?? 0);
?>
    <h1 class="h1-app"><?= e($parcours['nom']) ?> avec <?= e($parcours['formateur']) ?></h1>
    <?= annonce_html() ?>
    <?= onglets_parcours($geres, $pid) ?>

    <div class="grid">
      <section class="box box-wide" aria-labelledby="f-lecon">
        <p class="eyebrow" id="f-lecon">Nouvelle leçon</p>
<?php if ($mods === []): ?>
        <h2>Crée d'abord un module</h2>
        <p>Une leçon se range dans un module (une semaine de la formation). Ajoute ton premier module plus bas, puis reviens ici.</p>
<?php else: ?>
        <h2>Glisse ta vidéo, donne un titre, c'est en ligne</h2>
        <form method="post" action="/formateur/lecon" class="form-lecon" data-nouvelle-lecon>
          <?= champ_jeton() ?>
          <input type="hidden" name="action" value="creer">
          <div class="champs-2">
            <div class="field"><label for="nl-module">Module</label>
              <select class="input" id="nl-module" name="module" required>
<?php foreach ($mods as $m): ?>
                <option value="<?= (int) $m['id'] ?>"<?= (int) $m['id'] === $choix_module ? ' selected' : '' ?>><?= (int) $m['numero'] ?> · <?= e($m['titre']) ?></option>
<?php endforeach; ?>
              </select></div>
            <div class="field"><label for="nl-titre">Titre de la leçon</label><input class="input" id="nl-titre" name="titre" maxlength="120" required placeholder="Par exemple : Qui absorbe ?"></div>
          </div>
          <div class="field"><label for="nl-texte">Texte sous la vidéo <span class="opt-note">(facultatif)</span></label><textarea class="input" id="nl-texte" name="texte" rows="3" maxlength="4000"></textarea></div>
          <?= depot_video('lecon', null) ?>
          <div class="row-act"><button type="submit" class="btn btn-main m-0">Mettre la leçon en ligne</button><small>Sans vidéo, la leçon est créée et tu ajoutes la vidéo après.</small></div>
        </form>
<?php endif; ?>
      </section>
      <section class="box" aria-labelledby="f-live">
        <p class="eyebrow" id="f-live">Programmer un live</p>
<?php if ($cohortes === []): ?>
        <h2>Pas encore de cohorte</h2>
        <p><?= $admin ? 'Crée une cohorte plus bas : les lives se programment pour une cohorte.' : "L'administrateur crée les cohortes : les lives se programment pour une cohorte." ?></p>
<?php else: ?>
<?php if (isset($erreurs['live'])): ?><?= bloc_message('err', $erreurs['live']) ?><?php endif; ?>
        <form method="post" action="/formateur?p=<?= $pid ?>" class="form-live">
          <?= champ_jeton() ?>
          <input type="hidden" name="action" value="live"><input type="hidden" name="parcours" value="<?= $pid ?>">
          <div class="field"><label for="lv-cohorte">Cohorte</label><select class="input" id="lv-cohorte" name="cohorte">
<?php foreach ($cohortes as $c): ?>
            <option value="<?= (int) $c['id'] ?>"><?= e($c['nom']) ?></option>
<?php endforeach; ?>
          </select></div>
          <div class="champs-2">
            <div class="field"><label for="lv-jour">Jour</label><input class="input" type="date" id="lv-jour" name="jour" required value="<?= e(date('Y-m-d', (int) strtotime('next thursday'))) ?>"></div>
            <div class="field"><label for="lv-heure">Heure (Paris)</label><input class="input" type="time" id="lv-heure" name="heure" required value="20:00"></div>
          </div>
          <div class="field"><label for="lv-titre">Titre</label><input class="input" id="lv-titre" name="titre" maxlength="120" required placeholder="Les exercices du module 3"></div>
          <div class="champs-2">
            <div class="field"><label for="lv-module">Module <span class="opt-note">(facultatif)</span></label><select class="input" id="lv-module" name="module"><option value="0">Aucun</option>
<?php foreach ($mods as $m): ?>
              <option value="<?= (int) $m['id'] ?>"><?= (int) $m['numero'] ?> · <?= e($m['titre']) ?></option>
<?php endforeach; ?>
            </select></div>
            <div class="field"><label for="lv-duree">Durée (min)</label><input class="input" type="number" id="lv-duree" name="duree" min="15" max="300" step="15" value="60"></div>
          </div>
          <div class="field"><label for="lv-lien">Lien du salon Discord <span class="opt-note">(facultatif)</span></label><input class="input" type="url" id="lv-lien" name="lien" maxlength="300" placeholder="https://discord.gg/…"></div>
          <button type="submit" class="btn btn-line">Programmer le live</button>
        </form>
<?php endif; ?>
      </section>
    </div>

    <h2 class="titre-section" id="modules">Les modules</h2>
<?php if ($mods === []): ?>
    <p class="intro-app">Aucun module pour l'instant. Un module = une semaine de la formation : quelques leçons, un quiz, un live.</p>
<?php else: ?>
    <ol class="mods">
<?php foreach ($mods as $i => $m):
    $nv = $videos[(int) $m['id']] ?? 0;
?>
      <li class="mod">
        <span class="no"><?= sprintf('%02d', (int) $m['numero']) ?></span>
        <div class="mid"><h2><a class="mod-lien" href="/formateur/module?id=<?= (int) $m['id'] ?>"><?= e($m['titre']) ?></a><?php if (!empty($m['essai'])): ?> <span class="tag">essai</span><?php endif; ?></h2>
          <small><?= (int) $m['nb_lecons'] ?> leçon<?= $m['nb_lecons'] > 1 ? 's' : '' ?> · <?= $nv ?> vidéo<?= $nv > 1 ? 's' : '' ?> · <?= (int) $m['nb_questions'] === 0 ? 'pas de quiz' : 'quiz de ' . (int) $m['nb_questions'] . ' question' . ($m['nb_questions'] > 1 ? 's' : '') ?></small></div>
        <div class="end-side">
          <?= $i > 0 ? bouton_action('deplacer', '↑', ['parcours' => $pid, 'module' => $m['id'], 'sens' => -1], 'btn btn-line btn-sm btn-carre', 'Remonter le module ' . $m['numero']) : '' ?>
          <?= $i < count($mods) - 1 ? bouton_action('deplacer', '↓', ['parcours' => $pid, 'module' => $m['id'], 'sens' => 1], 'btn btn-line btn-sm btn-carre', 'Descendre le module ' . $m['numero']) : '' ?>
          <a class="btn btn-line btn-sm" href="/formateur/module?id=<?= (int) $m['id'] ?>">Ouvrir</a>
        </div>
      </li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>
    <section class="box mt-16 mw-980" aria-labelledby="f-mod">
      <h2 id="f-mod">Ajouter un module</h2>
<?php if (isset($erreurs['module'])): ?><?= bloc_message('err', $erreurs['module']) ?><?php endif; ?>
      <form method="post" action="/formateur?p=<?= $pid ?>">
        <?= champ_jeton() ?>
        <input type="hidden" name="action" value="module"><input type="hidden" name="parcours" value="<?= $pid ?>">
        <div class="champs-2">
          <div class="field"><label for="md-titre">Titre</label><input class="input" id="md-titre" name="titre" maxlength="120" required placeholder="Par exemple : L'absorption"></div>
          <div class="field"><label for="md-resume">En une phrase <span class="opt-note">(facultatif)</span></label><input class="input" id="md-resume" name="resume" maxlength="400" placeholder="Ce que l'élève saura faire à la fin"></div>
        </div>
        <button type="submit" class="btn btn-line">Ajouter le module <?= count($mods) + 1 ?></button>
      </form>
    </section>

    <h2 class="titre-section" id="cohortes">Les cohortes</h2>
<?php if ($cohortes === []): ?>
    <p class="intro-app">Aucune cohorte. <?= $admin ? 'Crée la première ci-dessous.' : "L'administrateur crée les cohortes." ?></p>
<?php else: ?>
    <ol class="mods">
<?php foreach ($cohortes as $c):
    $sem = semaine_cohorte($c, count($mods));
?>
      <li class="mod">
        <div class="mid"><h2><a class="mod-lien" href="/formateur/cohorte?id=<?= (int) $c['id'] ?>"><?= e($c['nom']) ?></a><?php if (!empty($c['essai'])): ?> <span class="tag">essai</span><?php endif; ?></h2>
          <small>Départ le <?= e(date_fr((int) $c['debut'])) ?> · <?= $sem === 0 ? 'pas commencée' : 'semaine ' . $sem . ' sur ' . max(1, count($mods)) ?> · <?= (int) $c['nb_eleves'] ?> élève<?= $c['nb_eleves'] > 1 ? 's' : '' ?> sur <?= (int) $c['places'] ?></small></div>
        <div class="end-side"><a class="btn btn-line btn-sm" href="/formateur/cohorte?id=<?= (int) $c['id'] ?>">Suivre</a></div>
      </li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>
<?php if ($admin): ?>
    <section class="box mt-16 mw-980" aria-labelledby="f-coh">
      <h2 id="f-coh">Créer une cohorte</h2>
<?php if (isset($erreurs['cohorte'])): ?><?= bloc_message('err', $erreurs['cohorte']) ?><?php endif; ?>
      <form method="post" action="/formateur?p=<?= $pid ?>">
        <?= champ_jeton() ?>
        <input type="hidden" name="action" value="cohorte"><input type="hidden" name="parcours" value="<?= $pid ?>">
        <div class="champs-3">
          <div class="field"><label for="ch-nom">Nom</label><input class="input" id="ch-nom" name="nom" maxlength="80" required value="Cohorte <?= count(array_filter($cohortes, static fn($c) => empty($c['essai']))) + 1 ?>"></div>
          <div class="field"><label for="ch-jour">Départ (module 1)</label><input class="input" type="date" id="ch-jour" name="jour" required></div>
          <div class="field"><label for="ch-places">Places</label><input class="input" type="number" id="ch-places" name="places" min="1" max="500" value="50"></div>
        </div>
        <div class="field"><label for="ch-discord">Lien du salon Discord de la cohorte <span class="opt-note">(facultatif)</span></label><input class="input" type="url" id="ch-discord" name="discord" maxlength="300" placeholder="https://discord.gg/…"></div>
        <button type="submit" class="btn btn-line">Créer la cohorte</button>
      </form>
    </section>

    <h2 class="titre-section" id="equipe">L'équipe du parcours</h2>
<?php
    $equipe = equipe_parcours($pid);
    $ids = array_map(static fn($r) => (int) $r['id'], $equipe);
    $formateurs = array_values(array_filter(lignes("SELECT id, prenom FROM comptes WHERE role = 'formateur' ORDER BY prenom"), static fn($r) => !in_array((int) $r['id'], $ids, true)));
?>
    <section class="box mw-980" aria-label="L'équipe du parcours">
      <p>Les formateurs de l'équipe déposent les leçons, programment les lives et suivent les élèves de ce parcours. Toi, administrateur, tu as accès à tout.</p>
<?php if ($equipe === []): ?>
      <p class="note-faint">Personne dans l'équipe pour l'instant.</p>
<?php else: ?>
      <ul class="list">
<?php foreach ($equipe as $r): ?>
        <li><span class="grow"><?= e($r['prenom']) ?></span><?= bouton_action('equipe-retirer', 'Retirer', ['parcours' => $pid, 'compte' => $r['id']]) ?></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
<?php if ($formateurs !== []): ?>
      <form method="post" action="/formateur?p=<?= $pid ?>" class="copie">
        <?= champ_jeton() ?>
        <input type="hidden" name="action" value="equipe"><input type="hidden" name="parcours" value="<?= $pid ?>">
        <label class="cache" for="eq-qui">Formateur à ajouter</label>
        <select class="input" id="eq-qui" name="compte">
<?php foreach ($formateurs as $r): ?>
          <option value="<?= (int) $r['id'] ?>"><?= e($r['prenom']) ?></option>
<?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-line">Ajouter à l'équipe</button>
      </form>
<?php else: ?>
      <p class="note-faint">Pour ajouter quelqu'un : invite-le depuis « Comptes et invitations » avec le rôle Formateur.</p>
<?php endif; ?>
    </section>
<?php endif; ?>
<?php endif; ?>
<?php
page_fin(['zone' => 'app']);
