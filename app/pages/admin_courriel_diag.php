<?php
declare(strict_types=1);

/* TEMPORAIRE (22/11) : trouver ce qui, dans nos e-mails, fait que le filtre d'OVH les jette.
   Envoie neuf variantes du même e-mail à la boîte contact ; celles qui arrivent disent ce qui gêne. À retirer ensuite. */

$compte = exiger_admin();
$smtp = smtp_actif();
$lignes = [];
if (methode() === 'POST' && $smtp !== null) {
    verifier_formulaire();
    $boite = $smtp['utilisateur'];
    [$t, $h] = gabarit_courriel('Essai de diagnostic', ['Bonjour,', 'Ceci est un e-mail d\'essai envoyé par le site pour un diagnostic.', 'Il n\'y a rien à faire.'], null, null, 'Envoyé le ' . date('d/m/Y à H:i') . '.');
    $sans_apercu = static fn(string $x): string => (string) preg_replace('~<div style="display:none[^>]*>.*?</div>~s', '', $x);
    $sans_image = static fn(string $x): string => (string) preg_replace('~<td style="vertical-align:middle;padding-right:12px;"><img[^>]*></td>~', '', $x);
    $sans_discord_h = static fn(string $x): string => (string) preg_replace('~, ou écris-nous sur <a href="[^"]*"[^>]*>[^<]*</a>\.~u', '.', $x);
    $sans_discord_t = static fn(string $x): string => (string) preg_replace('~, ou écris-nous sur le Discord : \S+~u', '.', $x);
    $auto = ['Auto-Submitted: auto-generated', 'X-Auto-Response-Suppress: All'];
    $mini = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>Essai</title></head><body><p>Bonjour,</p><p>Ceci est un e-mail d\'essai envoyé par le site pour un diagnostic.</p></body></html>';
    $variantes = [
        'A tout' => [$t, $h, $auto],
        'B texte seul' => [$t, null, $auto],
        'C sans en-tetes auto' => [$t, $h, []],
        'D sans apercu cache' => [$t, $sans_apercu($h), $auto],
        'E sans image' => [$t, $sans_image($h), $auto],
        'F sans Discord' => [$sans_discord_t($t), $sans_discord_h($h), $auto],
        'G html minimal' => [$sans_discord_t($t), $mini, $auto],
        'H sans apercu image Discord' => [$sans_discord_t($t), $sans_discord_h($sans_image($sans_apercu($h))), $auto],
        'I texte seul nu' => [$sans_discord_t($t), null, []],
    ];
    // Deuxième diagnostic : un seul e-mail tout simple, avec le journal de l'échange ; puis un essai avec un mot de passe faux
    // (tiré au hasard ici) pour vérifier que le serveur contrôle vraiment l'identification.
    $GLOBALS['na_smtp_journal'] = true;
    [$texte, $html, $plus] = $variantes['I texte seul nu'];
    $entetes = ['From: ' . reglage('courriel.nom') . ' <' . $boite . '>', 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: base64'];
    $erreur = null;
    smtp_journal('--- 1. vrai mot de passe, e-mail simple vers la boîte ---');
    $ok = smtp_envoyer($smtp, $boite, $boite, message_smtp($boite, $boite, 'Diag J journal', $entetes, chunk_split(base64_encode($texte))), $erreur);
    smtp_journal($ok ? '=> accepté' : '=> refusé : ' . $erreur);
    smtp_journal('--- 2. mot de passe faux (au hasard) ---');
    $faux = $smtp; $faux['mdp'] = 'faux-' . bin2hex(random_bytes(8));
    $ok = smtp_envoyer($faux, $boite, $boite, message_smtp($boite, $boite, 'Diag K faux mot de passe', $entetes, chunk_split(base64_encode($texte))), $erreur);
    smtp_journal($ok ? '=> ACCEPTÉ (anormal)' : '=> refusé : ' . $erreur);
    $lignes = smtp_journal();
}
page_debut('Diagnostic e-mails', ['zone' => 'app', 'courant' => 'courriel']);
?>
    <p class="crumb">Administration</p>
    <h1 class="h1-app">Diagnostic des e-mails</h1>
    <p class="intro-app">Page temporaire : envoie neuf variantes du même e-mail à la boîte contact, pour trouver ce que le filtre d'OVH n'aime pas.</p>
<?php if ($smtp === null): ?>
    <p class="msg msg-info" role="status">La boîte n'est pas branchée.</p>
<?php else: ?>
    <form class="mt-24" method="post" action="/admin/courriel/diagnostic">
      <?= champ_jeton() ?>
      <button type="submit" class="btn btn-main">Lancer le diagnostic</button>
    </form>
<?php endif; ?>
<?php if ($lignes !== []): ?>
    <ul class="list mt-24">
<?php foreach ($lignes as $l): ?>
      <li><?= e($l) ?></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php
page_fin(['zone' => 'app']);
