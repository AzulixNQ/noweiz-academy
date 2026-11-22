<?php
declare(strict_types=1);

/* Se désinscrire de la liste d'attente. Le lien de chaque e-mail porte une signature : elle suffit pour prouver que c'est bien
   le destinataire (pas de mot de passe à donner). Le simple fait d'ouvrir la page ne désinscrit pas (les antivirus des messageries
   ouvrent les liens) : il faut cliquer sur le bouton. Gmail et les autres messageries peuvent aussi désinscrire en un clic,
   par un envoi direct sur cette adresse (List-Unsubscribe-Post). */

$i = is_string($_GET['i'] ?? null) ? $_GET['i'] : '';
$s = is_string($_GET['s'] ?? null) ? $_GET['s'] : '';
$l = liste_par_lien($i, $s);

if (methode() === 'POST') {
    if ($l !== null) liste_supprimer((int) $l['id']);
    if (($_POST['List-Unsubscribe'] ?? '') === 'One-Click') {           // la messagerie, pas une personne
        header('Content-Type: text/plain; charset=utf-8');
        echo 'OK';
        exit;
    }
    page_message('Désinscription', "C'est fait : tu n'es plus sur la liste.", "Ton adresse est effacée de la liste d'attente. Tu ne recevras plus d'e-mail de notre part.", '/', 'Revenir au site');
    exit;
}

if ($l === null) {
    page_message('Désinscription', "Tu n'es plus sur la liste.", "Cette adresse n'est pas (ou plus) sur la liste d'attente : tu ne recevras pas d'e-mail de notre part.", '/', 'Revenir au site');
    exit;
}

page_debut('Désinscription', ['annonce' => false]);
?>
<main class="auth" id="contenu">
  <div class="lights" aria-hidden="true"></div>
  <section class="join auth-card">
    <h1>Te désinscrire de la liste d'attente ?</h1>
    <p class="auth-text">L'adresse <b><?= e(masquer_email($l['email'])) ?></b> sera effacée, et tu ne seras pas prévenu de l'ouverture.</p>
    <form method="post" action="<?= e('/liste-attente/desinscription?i=' . (int) $l['id'] . '&s=' . liste_signature($l)) ?>">
      <button type="submit" class="btn btn-main btn-full">Me désinscrire</button>
    </form>
    <p class="auth-alt"><a href="/">Non, rester sur la liste</a></p>
  </section>
</main>
<?php
page_fin();
