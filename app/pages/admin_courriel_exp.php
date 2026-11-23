<?php
declare(strict_types=1);

/* TEMPORAIRE (23/11) : les e-mails du site n'arrivent plus depuis le 22/11 au matin. Le dernier qui est arrivé (21/11)
   partait de ne-pas-repondre@noweiz.com ; depuis le 22/11 à 10:23, ils partent de contact@noweiz.com, une adresse qui
   renvoie vers la boîte contact@noweiz.fr. Cette page envoie, par l'hébergement (mail()), le même e-mail avec plusieurs
   expéditeurs, à l'adresse de l'administrateur connecté. Ceux qui arrivent disent ce qui gêne. À retirer ensuite. */

$compte = exiger_admin();
$lignes = [];
if (methode() === 'POST') {
    verifier_formulaire();
    $a = (string) $compte['email'];
    $heure = date('H\hi');
    $texte = "Bonjour,\r\n\r\nCeci est un e-mail d'essai envoyé par le site pour trouver pourquoi les e-mails n'arrivent plus.\r\nIl n'y a rien à faire.\r\n";
    [$gt, $gh] = gabarit_courriel('Essai des expéditeurs', ['Bonjour,', 'Ceci est un e-mail d\'essai envoyé par le site pour trouver pourquoi les e-mails n\'arrivent plus.', 'Il n\'y a rien à faire.'], null, null, 'Envoyé le ' . date('d/m/Y à H:i') . '.');
    $variantes = [
        'A' => ['ne-pas-repondre@noweiz.com', 'ne-pas-repondre@noweiz.com', false, 'comme le 21/11, texte simple'],
        'B' => ['ne-pas-repondre@noweiz.com', 'ne-pas-repondre@noweiz.com', true, 'comme le 21/11, avec le gabarit actuel'],
        'C' => ['contact@noweiz.com', 'contact@noweiz.com', false, 'comme maintenant, texte simple'],
        'D' => ['ne-pas-repondre@noweiz.fr', 'ne-pas-repondre@noweiz.fr', false, 'domaine noweiz.fr'],
        'E' => ['ne-pas-repondre@noweiz.com', '', false, 'sans enveloppe (-f)'],
    ];
    foreach ($variantes as $k => [$de, $retour, $gabarit, $quoi]) {
        $entetes = ['From: ' . mb_encode_mimeheader('Noweiz Academy', 'UTF-8', 'B') . ' <' . $de . '>', 'MIME-Version: 1.0'];
        if ($gabarit) {
            $limite = 'na-' . bin2hex(random_bytes(12));
            $entetes[] = 'Content-Type: multipart/alternative; boundary="' . $limite . '"';
            $corps = "--$limite\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($gt))
                   . "--$limite\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($gh))
                   . "--$limite--\r\n";
        } else {
            $entetes[] = 'Content-Type: text/plain; charset=UTF-8';
            $entetes[] = 'Content-Transfer-Encoding: base64';
            $corps = chunk_split(base64_encode($texte));
        }
        $sujet = mb_encode_mimeheader('Essai ' . $k . ' ' . $heure . ' : ' . $quoi, 'UTF-8', 'B');
        $ok = $retour !== ''
            ? @mail($a, $sujet, $corps, implode("\r\n", $entetes), '-f' . $retour)
            : @mail($a, $sujet, $corps, implode("\r\n", $entetes));
        $lignes[] = $k . ' (' . $de . ($retour !== '' ? ', enveloppe ' . $retour : ', sans enveloppe') . ', ' . $quoi . ') : ' . ($ok ? 'accepté par l\'hébergement' : 'refusé');
    }
}
page_debut('Essai des expéditeurs', ['zone' => 'app', 'courant' => 'courriel']);
?>
    <p class="crumb">Administration</p>
    <h1 class="h1-app">Essai des expéditeurs</h1>
    <p class="intro-app">Page temporaire : envoie cinq fois le même e-mail par l'hébergement, avec des expéditeurs différents, à ton adresse. Regarde ensuite lesquels arrivent.</p>
    <form class="mt-24" method="post" action="/admin/courriel/expediteurs">
      <?= champ_jeton() ?>
      <button type="submit" class="btn btn-main">Envoyer les cinq essais</button>
    </form>
<?php if ($lignes !== []): ?>
    <ul class="list mt-24">
<?php foreach ($lignes as $l): ?>
      <li><?= e($l) ?></li>
<?php endforeach; ?>
    </ul>
<?php endif; ?>
<?php
page_fin(['zone' => 'app']);
