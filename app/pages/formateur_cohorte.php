<?php
declare(strict_types=1);

/* Une cohorte, côté formateur : le suivi des élèves, les quiz, les lives et leurs replays, les inscrits. */

$compte = exiger_formateur();
$admin = $compte['role'] === 'admin';
$id = ctype_digit((string) ($_GET['id'] ?? '')) ? (int) $_GET['id'] : 0;
$cohorte = cohorte_par_id($id);
if ($cohorte === null || !peut_gerer($compte, (int) $cohorte['parcours_id'])) refuser_parcours();
$erreurs = [];

if (methode() === 'POST') {
    verifier_formulaire();
    $action = champ_recu('action', 30);
    $retour = '/formateur/cohorte?id=' . $id;
    if ($action === 'inscrire') {
        $c = compte_par_id((int) champ_recu('compte', 12));
        if ($c !== null) { inscrire_cohorte((int) $c['id'], $id); annoncer('eleve-inscrit'); }
        rediriger($retour . '#eleves');
    } elseif ($action === 'desinscrire') {
        desinscrire_cohorte((int) champ_recu('compte', 12), $id);
        annoncer('eleve-retire');
        rediriger($retour . '#eleves');
    } elseif ($action === 'live-supprimer') {
        $l = live_par_id((int) champ_recu('live', 12));
        if ($l !== null && (int) $l['cohorte_id'] === $id) { live_supprimer((int) $l['id']); annoncer('live-supprime'); }
        rediriger($retour . '#lives');
    } elseif ($action === 'replay-retirer') {
        $l = live_par_id((int) champ_recu('live', 12));
        if ($l !== null && (int) $l['cohorte_id'] === $id) { live_sans_replay((int) $l['id']); annoncer('video-retiree'); }
        rediriger($retour . '#lives');
    } elseif ($action === 'modifier' && $admin) {
        $nom = texte_recu('nom', 80);
        $debut = date_recue(champ_recu('jour', 10));
        $places = (int) champ_recu('places', 4);
        $lien = texte_recu('discord', 300);
        if ($nom === '') $erreurs['cohorte'] = 'Donne un nom à la cohorte.';
        elseif ($debut === null) $erreurs['cohorte'] = 'Donne la date de départ.';
        elseif ($places < 1 || $places > 500) $erreurs['cohorte'] = 'Le nombre de places va de 1 à 500.';
        elseif (!lien_discord_valable($lien)) $erreurs['cohorte'] = 'Le lien du salon doit être un lien Discord.';
        else { cohorte_modifier($id, $nom, $debut, $places, $lien); annoncer('profil'); rediriger($retour . '#reglages'); }
    } else {
        rediriger($retour);
    }
}

$mods = modules_parcours((int) $cohorte['parcours_id']);
$nb = count($mods);
$sem = semaine_cohorte($cohorte, $nb);
$suivi = suivi_cohorte($cohorte);
$compte_etats = array_count_values(array_column($suivi, 'etat'));
$retard = array_values(array_filter($suivi, static fn($s) => $s['etat'] === 'en_retard'));
$lives = lives_cohorte($id);
$maintenant = time();
$module_semaine = $sem > 0 ? ($mods[$sem - 1] ?? null) : null;
$stats = [];
foreach ($mods as $m) {
    $s = stats_quiz($cohorte, $m);
    if ($s !== null && $s['eleves'] > 0) $stats[] = ['module' => $m, 'stats' => $s];
}
$inscrits = array_map(static fn($s) => (int) $s['compte']['id'], $suivi);
$autres = array_values(array_filter(lignes('SELECT id, prenom, email, role FROM comptes ORDER BY prenom'), static fn($r) => !in_array((int) $r['id'], $inscrits, true)));

page_debut($cohorte['nom'], ['zone' => 'app', 'courant' => 'formateur']);
?>
    <p class="crumb"><a href="/formateur?p=<?= (int) $cohorte['parcours_id'] ?>">Espace formateur</a> / <?= e($cohorte['parcours_nom']) ?> · <?= $sem === 0 ? 'départ le ' . e(date_fr((int) $cohorte['debut'])) : 'semaine ' . $sem . ' sur ' . max(1, $nb) ?></p>
    <h1 class="h1-app"><?= e($cohorte['nom']) ?><?php if (!empty($cohorte['essai'])): ?> <span class="tag">essai</span><?php endif; ?></h1>
    <?= annonce_html() ?>
    <dl class="kpis">
      <div class="kpi"><dt>Élèves</dt><dd><?= count($suivi) ?></dd><dd>sur <?= (int) $cohorte['places'] ?> places</dd></div>
      <div class="kpi"><dt>À jour</dt><dd><?= ($compte_etats['a_jour'] ?? 0) + ($compte_etats['termine'] ?? 0) ?></dd><dd><?= $module_semaine !== null ? 'module ' . (int) $module_semaine['numero'] . ' ouvert' : 'avant le départ' ?></dd></div>
      <div class="kpi"><dt>En retard</dt><dd><?= count($retard) ?></dd><dd>bloqués par un quiz</dd></div>
      <div class="kpi"><dt>Pas commencé</dt><dd><?= $compte_etats['pas_commence'] ?? 0 ?></dd><dd>aucune leçon vue</dd></div>
    </dl>

    <div class="grid grid-haut">
      <section class="box box-large" aria-labelledby="s-el">
        <div class="box-head"><h2 id="s-el">Progression de chaque élève</h2><small class="note-faint"><?= count($suivi) ?> élève<?= count($suivi) > 1 ? 's' : '' ?></small></div>
<?php if ($suivi === []): ?>
        <p>Personne n'est encore inscrit. Ajoute des élèves plus bas.</p>
<?php else: ?>
        <div class="table-box">
          <table class="table-s">
            <thead><tr><th scope="col">Élève</th><th scope="col">Modules validés</th><th scope="col">Leçons vues</th><th scope="col">Dernier quiz</th><th scope="col">Dernière visite</th><th scope="col">État</th></tr></thead>
            <tbody>
<?php foreach ($suivi as $s): $d = $s['dernier_quiz']; ?>
              <tr>
                <td><?= e($s['compte']['prenom']) ?><?= $s['compte']['discord'] ? '<small class="sous">' . e($s['compte']['discord']) . '</small>' : '' ?></td>
                <td><?= (int) $s['valides'] ?> / <?= $nb ?></td>
                <td><?= (int) $s['vues'] ?></td>
                <td><?= $d !== null ? 'Module ' . (int) $d['numero'] . ' · ' . (int) $d['note'] . '/' . (int) $d['total'] : '—' ?></td>
                <td><?= $s['compte']['vu_le'] ? e(date_fr((int) $s['compte']['vu_le'])) : '—' ?></td>
                <td><span class="etat etat-<?= e($s['etat']) ?>"><?= e(etat_eleve($s['etat'])) ?></span></td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php endif; ?>
      </section>
      <div class="colonne">
        <section class="box" aria-labelledby="s-rel">
          <h2 id="s-rel">À relancer</h2>
<?php if ($retard === []): ?>
          <p>Personne n'est bloqué par un quiz.</p>
<?php else: ?>
          <p><?= count($retard) ?> élève<?= count($retard) > 1 ? 's n\'ont' : ' n\'a' ?> pas validé un quiz : la suite ne s'est pas ouverte.</p>
          <ul class="list">
<?php foreach ($retard as $s): ?>
            <li><span class="grow"><?= e($s['compte']['prenom']) ?></span><small>module <?= (int) $s['bloque']['numero'] ?> fermé</small></li>
<?php endforeach; ?>
          </ul>
<?php endif; ?>
        </section>
<?php foreach (array_slice(array_reverse($stats), 0, 2) as $st): $s = $st['stats']; ?>
        <section class="box">
          <h2>Quiz du module <?= (int) $st['module']['numero'] ?></h2>
          <p><?= (int) $s['eleves'] ?> élève<?= $s['eleves'] > 1 ? 's l\'ont' : ' l\'a' ?> fait. Moyenne des meilleurs essais : <?= e(number_format((float) $s['moyenne'], 1, ',', '')) ?> sur <?= (int) $s['total'] ?>.<?php if ($s['ratee'] !== null && $s['ratee']['pct'] < 100): ?> La plus ratée est la <?= (int) $s['ratee']['numero'] ?> (<?= (int) $s['ratee']['pct'] ?> % de bonnes réponses) : à reprendre au live.<?php endif; ?></p>
        </section>
<?php endforeach; ?>
      </div>
    </div>

    <h2 class="titre-section" id="lives">Lives et replays</h2>
    <p class="intro-app">Pour programmer un live, passe par la page d'accueil de l'espace formateur. Après le live, glisse le replay sur sa ligne.</p>
<?php if ($lives === []): ?>
    <p class="note-faint mt-16">Aucun live programmé.</p>
<?php else: ?>
    <ol class="lives">
<?php foreach ($lives as $l):
    $passe = (int) $l['debut'] + (int) $l['duree'] * 60 <= $maintenant;
?>
      <li class="mod mod-live">
        <span class="live-date"><?= e(date_fr((int) $l['debut'], true)) ?></span>
        <div class="mid"><h2><?= e($l['titre']) ?></h2><small><?= (int) $l['duree'] ?> min<?= $passe ? ' · ' . (fichier_video($l['replay']) !== null ? 'replay en ligne' . ($l['replay_duree'] ? ' (' . e(duree_video((int) $l['replay_duree'])) . ')' : '') : 'pas encore de replay') : ' · à venir' ?></small></div>
        <div class="end-side">
<?php if ($passe && fichier_video($l['replay']) !== null): ?>
          <?= bouton_action('replay-retirer', 'Retirer le replay', ['live' => $l['id']]) ?>
<?php endif; ?>
          <?= bouton_action('live-supprimer', 'Effacer', ['live' => $l['id']]) ?>
        </div>
<?php if ($passe): ?>
        <div class="mod-depot"><?= depot_video('replay', (int) $l['id'], fichier_video($l['replay']) !== null ? 'Remplacer le replay' : 'Glisse le replay ici') ?></div>
<?php endif; ?>
      </li>
<?php endforeach; ?>
    </ol>
<?php endif; ?>

    <h2 class="titre-section" id="eleves">Les inscrits</h2>
    <section class="box mw-980" aria-label="Les inscrits">
      <p>En attendant le paiement en ligne, on inscrit les élèves à la main : ils doivent d'abord avoir un compte (invitation depuis « Comptes et invitations »).</p>
<?php if ($suivi !== []): ?>
      <ul class="list">
<?php foreach ($suivi as $s): ?>
        <li><span class="grow"><?= e($s['compte']['prenom']) ?> <small class="sous-inline"><?= e(masquer_email((string) $s['compte']['email'])) ?></small></span><?= bouton_action('desinscrire', 'Retirer', ['compte' => $s['compte']['id']]) ?></li>
<?php endforeach; ?>
      </ul>
<?php endif; ?>
<?php if ($autres !== []): ?>
      <form method="post" action="/formateur/cohorte?id=<?= $id ?>" class="copie">
        <?= champ_jeton() ?>
        <input type="hidden" name="action" value="inscrire">
        <label class="cache" for="in-qui">Compte à inscrire</label>
        <select class="input" id="in-qui" name="compte">
<?php foreach ($autres as $r): ?>
          <option value="<?= (int) $r['id'] ?>"><?= e($r['prenom']) ?> (<?= e(ROLES[$r['role']] ?? '') ?>, <?= e(masquer_email((string) $r['email'])) ?>)</option>
<?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-line">Inscrire</button>
      </form>
<?php endif; ?>
    </section>

<?php if ($admin): ?>
    <h2 class="titre-section" id="reglages">Réglages de la cohorte</h2>
    <section class="box mw-980" aria-label="Réglages de la cohorte">
<?php if (isset($erreurs['cohorte'])): ?><?= bloc_message('err', $erreurs['cohorte']) ?><?php endif; ?>
      <form method="post" action="/formateur/cohorte?id=<?= $id ?>">
        <?= champ_jeton() ?>
        <input type="hidden" name="action" value="modifier">
        <div class="champs-3">
          <div class="field"><label for="ch-nom">Nom</label><input class="input" id="ch-nom" name="nom" maxlength="80" required value="<?= e($cohorte['nom']) ?>"></div>
          <div class="field"><label for="ch-jour">Départ (module 1)</label><input class="input" type="date" id="ch-jour" name="jour" required value="<?= e(date('Y-m-d', (int) $cohorte['debut'])) ?>"></div>
          <div class="field"><label for="ch-places">Places</label><input class="input" type="number" id="ch-places" name="places" min="1" max="500" value="<?= (int) $cohorte['places'] ?>"></div>
        </div>
        <div class="field"><label for="ch-discord">Lien du salon Discord</label><input class="input" type="url" id="ch-discord" name="discord" maxlength="300" value="<?= e((string) $cohorte['discord']) ?>" placeholder="https://discord.gg/…"></div>
        <button type="submit" class="btn btn-line">Enregistrer</button>
      </form>
    </section>
<?php endif; ?>
<?php
page_fin(['zone' => 'app']);
