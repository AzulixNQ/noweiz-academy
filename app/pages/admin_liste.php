<?php
declare(strict_types=1);

/* La liste d'attente, vue par l'administrateur : le nombre d'inscrits, par parcours, la liste et son export. */

$moi = exiger_admin();

if (methode() === 'POST') {
    verifier_formulaire();
    if (champ_recu('action', 20) === 'supprimer') {
        liste_supprimer((int) champ_recu('ligne', 12));
        annoncer('liste-supprime');
    }
    rediriger('/admin/liste-attente');
}

liste_menage();
$confirmes = requete("SELECT id, prenom, email, parcours, cree_le, confirme_le FROM liste_attente WHERE statut = 'confirme' ORDER BY confirme_le DESC, id DESC")->fetchAll();
$en_attente = (int) valeur("SELECT COUNT(*) FROM liste_attente WHERE statut = 'attente'");
$par = array_fill_keys(array_keys(PARCOURS), 0);
foreach ($confirmes as $c) if (isset($par[$c['parcours']])) $par[$c['parcours']]++;
$n = count($confirmes);

page_debut("Liste d'attente", ['zone' => 'app', 'courant' => 'liste']);
?>
    <p class="crumb"><a href="/espace">Mon espace</a></p>
    <h1 class="h1-app">Liste d'attente</h1>
    <?= annonce_html() ?>
    <div class="kpis">
      <dl class="kpi"><dt>Inscrits</dt><dd><?= $n ?></dd><dd>adresses confirmées</dd></dl>
      <dl class="kpi"><dt>Order flow</dt><dd><?= $par['of'] ?></dd><dd>avec Nojah</dd></dl>
      <dl class="kpi"><dt>ICT</dt><dd><?= $par['ict'] ?></dd><dd>avec Weizix</dd></dl>
      <dl class="kpi"><dt>Les deux</dt><dd><?= $par['pack'] ?></dd><dd>le pack</dd></dl>
    </div>
    <p class="hint hint-liste">Seules les adresses confirmées comptent. <?= $en_attente === 0 ? "Aucune inscription n'attend sa confirmation." : ($en_attente === 1 ? 'Une inscription attend sa confirmation' : $en_attente . ' inscriptions attendent leur confirmation') . ' : sans confirmation, elles sont effacées au bout de 7 jours.' ?></p>

    <section class="box box-liste" aria-labelledby="t-liste">
      <div class="box-head">
        <h2 id="t-liste">Les inscrits</h2>
<?php if ($n > 0): ?>
        <a class="btn btn-line btn-sm" href="/admin/liste-attente.csv">Télécharger la liste (Excel, CSV)</a>
<?php endif; ?>
      </div>
<?php if ($n === 0): ?>
      <p class="box-text">Personne pour l'instant. Partage la page d'accueil : chaque inscription confirmée apparaît ici.</p>
<?php else: ?>
      <div class="table-box">
        <table class="table-s">
          <thead><tr><th scope="col">Prénom</th><th scope="col">E-mail</th><th scope="col">Parcours</th><th scope="col">Confirmé le</th><th scope="col"><span class="cache">Action</span></th></tr></thead>
          <tbody>
<?php foreach ($confirmes as $c): ?>
            <tr>
              <td><?= e($c['prenom']) ?></td>
              <td class="coupe"><?= e($c['email']) ?></td>
              <td><?= e(PARCOURS[$c['parcours']] ?? $c['parcours']) ?></td>
              <td><?= e(date_fr((int) $c['confirme_le'])) ?></td>
              <td class="td-act">
                <form method="post" action="/admin/liste-attente">
                  <?= champ_jeton() ?><input type="hidden" name="action" value="supprimer"><input type="hidden" name="ligne" value="<?= (int) $c['id'] ?>">
                  <button type="submit" class="btn btn-warn btn-xs" aria-label="Effacer <?= e($c['prenom']) ?> de la liste">Effacer</button>
                </form>
              </td>
            </tr>
<?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="hint">« Effacer » sert quand quelqu'un demande à être retiré sans passer par le lien des e-mails.</p>
<?php endif; ?>
    </section>
<?php
page_fin(['zone' => 'app']);
