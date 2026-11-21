<?php
declare(strict_types=1);

$moi = exiger_admin();
$erreur = '';
$note = '';

if (methode() === 'POST') {
    verifier_formulaire();
    $action = champ_recu('action', 30);

    if ($action === 'inviter') {
        $note = champ_recu('note', 80);
        $role = champ_recu('role', 20);
        if (!isset(ROLES[$role])) $role = 'eleve';
        if ($note === '') {
            $erreur = "Écris pour qui est l'invitation : ça t'aidera à t'y retrouver dans la liste.";
        } else {
            $jeton = creer_lien('invitation', (int) reglage('invitation_jours') * 86400, null, $role, $note, (int) $moi['id']);
            // Le lien n'est montré qu'une fois, à la page suivante : la base n'en garde que l'empreinte.
            poser_temoin('invitation', $jeton, 120);
            rediriger('/admin#invitation');
        }
    } elseif ($action === 'annuler') {
        requete("DELETE FROM liens WHERE id = ? AND type = 'invitation' AND utilise_le IS NULL", [(int) champ_recu('lien', 12)]);
        annoncer('invitation-annulee');
        rediriger('/admin');
    } elseif ($action === 'role') {
        $cible = compte_par_id((int) champ_recu('compte', 12));
        $role = champ_recu('role', 20);
        if ($cible !== null && isset(ROLES[$role]) && $cible['role'] !== $role) {
            if ($cible['role'] === 'admin' && nombre_admins() === 1) {
                $erreur = 'Il faut garder au moins un administrateur : donne d\'abord ce rôle à quelqu\'un d\'autre.';
            } else {
                requete('UPDATE comptes SET role = ? WHERE id = ?', [$role, $cible['id']]);
                annoncer('role-change');
                rediriger('/admin');
            }
        } else {
            rediriger('/admin');
        }
    } else {
        rediriger('/admin');
    }
}

$nouveau = lire_temoin('invitation');
if ($nouveau !== '') effacer_temoin('invitation');
if (lien_valable('invitation', $nouveau) === null) $nouveau = '';

$comptes = requete('SELECT id, prenom, email, discord, role, confirme_le, cree_le, vu_le FROM comptes ORDER BY cree_le DESC, id DESC')->fetchAll();
$invitations = requete("SELECT id, role, note, cree_le, expire_le FROM liens WHERE type = 'invitation' AND utilise_le IS NULL AND expire_le > ? ORDER BY cree_le DESC, id DESC", [time()])->fetchAll();

page_debut('Comptes et invitations', ['zone' => 'app', 'courant' => 'admin']);
?>
    <p class="crumb"><a href="/espace">Mon espace</a></p>
    <h1 class="h1-app">Comptes et invitations</h1>
    <?= annonce_html() ?>
    <?= $erreur !== '' ? bloc_message('err', $erreur) : '' ?>
<?php if ($nouveau !== ''): ?>
    <section class="box box-wide lien-neuf" id="invitation" aria-labelledby="l-titre">
      <p class="eyebrow">Invitation créée</p>
      <h2 id="l-titre">Envoie ce lien à la personne invitée</h2>
      <p class="box-text">Il marche une seule fois, pendant <?= (int) reglage('invitation_jours') ?> jours. Copie-le maintenant : il ne sera plus affiché.</p>
      <div class="copie">
        <input class="input" id="lien-invitation" type="text" readonly value="<?= e(url_site('/inscription?invitation=' . $nouveau)) ?>" aria-label="Lien d'invitation">
        <button type="button" class="btn btn-main" data-copier="lien-invitation" hidden>Copier le lien</button>
      </div>
      <p class="hint" id="copie-etat" role="status"></p>
    </section>
<?php endif; ?>

    <div class="formes">
      <section class="box" aria-labelledby="a-inv">
        <h2 id="a-inv">Inviter quelqu'un</h2>
        <p>Pour l'instant, un compte se crée seulement avec un lien d'invitation.</p>
        <form method="post" action="/admin" novalidate>
          <?= champ_jeton() ?><input type="hidden" name="action" value="inviter">
          <div class="two">
            <?= champ('note', 'Pour qui ?', ['valeur' => $note, 'autocomplete' => 'off', 'placeholder' => 'Weizix', 'max' => 80]) ?>
            <div class="field">
              <label for="c-role">Son rôle</label>
              <select class="input" id="c-role" name="role">
<?php foreach (ROLES as $cle => $libelle): ?>
                <option value="<?= e($cle) ?>"><?= e($libelle) ?></option>
<?php endforeach; ?>
              </select>
            </div>
          </div>
          <p class="hint">Élève : son espace. Formateur : le suivi des cohortes, quand il existera. Administrateur : tout, y compris cette page.</p>
          <button type="submit" class="btn btn-main">Créer le lien d'invitation</button>
        </form>
      </section>

      <section class="box" aria-labelledby="a-att">
        <h2 id="a-att">Invitations en attente</h2>
<?php if (!$invitations): ?>
        <p>Aucune invitation en attente.</p>
<?php else: ?>
        <div class="table-box">
          <table class="table-s">
            <thead><tr><th scope="col">Pour</th><th scope="col">Rôle</th><th scope="col">Créée le</th><th scope="col">Marche jusqu'au</th><th scope="col"><span class="cache">Action</span></th></tr></thead>
            <tbody>
<?php foreach ($invitations as $i): ?>
              <tr>
                <th scope="row"><?= e((string) $i['note']) ?></th>
                <td><?= e(ROLES[$i['role']] ?? 'Élève') ?></td>
                <td><?= e(date_fr((int) $i['cree_le'])) ?></td>
                <td><?= e(date_fr((int) $i['expire_le'])) ?></td>
                <td class="td-act">
                  <form method="post" action="/admin">
                    <?= champ_jeton() ?><input type="hidden" name="action" value="annuler"><input type="hidden" name="lien" value="<?= (int) $i['id'] ?>">
                    <button type="submit" class="btn btn-line btn-xs" aria-label="Annuler l'invitation pour <?= e((string) $i['note']) ?>">Annuler</button>
                  </form>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
<?php endif; ?>
      </section>

      <section class="box" aria-labelledby="a-comptes">
        <h2 id="a-comptes"><?= count($comptes) ?> compte<?= count($comptes) > 1 ? 's' : '' ?></h2>
        <div class="table-box">
          <table>
            <thead><tr><th scope="col">Prénom</th><th scope="col">E-mail</th><th scope="col">Discord</th><th scope="col">Créé le</th><th scope="col">Dernière visite</th><th scope="col">Rôle</th></tr></thead>
            <tbody>
<?php foreach ($comptes as $c): ?>
              <tr>
                <th scope="row"><?= e($c['prenom']) ?><?= (int) $c['id'] === (int) $moi['id'] ? ' <span class="tag">toi</span>' : '' ?></th>
                <td><?= e($c['email']) ?><?= $c['confirme_le'] === null ? ' <span class="tag">à confirmer</span>' : '' ?></td>
                <td><?= $c['discord'] !== null ? e($c['discord']) : '<span class="pale">—</span>' ?></td>
                <td><?= e(date_fr((int) $c['cree_le'])) ?></td>
                <td><?= e(date_fr($c['vu_le'] !== null ? (int) $c['vu_le'] : null)) ?></td>
                <td class="td-act">
                  <form method="post" action="/admin" class="role-form">
                    <?= champ_jeton() ?><input type="hidden" name="action" value="role"><input type="hidden" name="compte" value="<?= (int) $c['id'] ?>">
                    <select class="input input-xs" name="role" aria-label="Rôle de <?= e($c['prenom']) ?>">
<?php foreach (ROLES as $cle => $libelle): ?>
                      <option value="<?= e($cle) ?>"<?= $c['role'] === $cle ? ' selected' : '' ?>><?= e($libelle) ?></option>
<?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-line btn-xs">Changer</button>
                  </form>
                </td>
              </tr>
<?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </div>
<?php
page_fin(['zone' => 'app']);
