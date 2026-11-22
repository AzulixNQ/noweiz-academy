<?php
declare(strict_types=1);

/* Les e-mails du site, pour l'administrateur : par où ils partent (l'hébergement, ou la boîte contact),
   brancher la boîte (son mot de passe est tapé ici, gardé chiffré, jamais réaffiché), envoyer un e-mail d'essai. */

$compte = exiger_admin();
$reglage = smtp_reglage();
$branche = $reglage !== null && !empty($reglage['actif']);
$serveur = smtp_serveur();
$v = ['boite' => (string) ($reglage['utilisateur'] ?? 'contact@noweiz.fr'), 'essai' => (string) $compte['email']];
$err = [];
$message = '';

/** L'e-mail d'essai : le même habillage que les vrais. */
$essai = static function (string $boite): array {
    return gabarit_courriel(
        'L\'envoi des e-mails fonctionne',
        ['Cet e-mail d\'essai est parti du site ' . reglage('nom') . ' par la boîte ' . $boite . '.', 'S\'il est arrivé dans ta boîte de réception (et pas dans les indésirables), tout est bon : les inscriptions, les confirmations et les mots de passe oubliés partiront de la même façon.'],
        null,
        null,
        'Envoyé le ' . date('d/m/Y à H:i') . ' depuis la page « E-mails » de l\'administration.'
    );
};

if (methode() === 'POST') {
    verifier_formulaire();
    $action = champ_recu('action', 20);
    $cle = cle_limite('courriel-essai', (string) $compte['id']);

    if ($action === 'debrancher') {
        smtp_ecrire(null);
        annoncer('courriel-debranche');
        rediriger('/admin/courriel');
    }

    if ($action === 'brancher' || $action === 'essai') {
        $v['essai'] = normaliser_email(champ_recu('essai', 190));
        if (!filter_var($v['essai'], FILTER_VALIDATE_EMAIL)) $err['essai'] = 'Écris une adresse e-mail complète.';
        if (essais($cle, 3600) >= 12) $message = 'Trop d\'essais d\'affilée : réessaie dans ' . attente_minutes($cle, 3600) . ' minutes.';
    }

    if ($action === 'brancher' && $message === '') {
        $v['boite'] = normaliser_email(champ_recu('boite', 190));
        // Le mot de passe est pris tel quel (pas de nettoyage : un espace ou un symbole peut en faire partie).
        $mdp = is_string($_POST['mdp_boite'] ?? null) ? (string) $_POST['mdp_boite'] : '';
        if (!filter_var($v['boite'], FILTER_VALIDATE_EMAIL)) $err['boite'] = 'Écris l\'adresse complète de la boîte, par exemple contact@noweiz.fr.';
        if ($mdp === '' || strlen($mdp) > 200 || preg_match('/[\x00\r\n]/', $mdp)) $err['mdp_boite'] = 'Tape le mot de passe de la boîte (celui du webmail).';
        if ($err === []) {
            noter_essai($cle);
            [$t, $h] = $essai($v['boite']);
            $sujet = mb_encode_mimeheader('Essai d\'envoi ' . reglage('nom'), 'UTF-8', 'B');
            $limite = 'na-' . bin2hex(random_bytes(12));
            $corps = "--$limite\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($t))
                   . "--$limite\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($h))
                   . "--$limite--\r\n";
            $entetes = [
                'From: ' . mb_encode_mimeheader((string) reglage('courriel.nom'), 'UTF-8', 'B') . ' <' . $v['boite'] . '>',
                'MIME-Version: 1.0',
                'Content-Type: multipart/alternative; boundary="' . $limite . '"',
                'Auto-Submitted: auto-generated',
            ];
            $erreur = null;
            // L'essai part directement par la boîte (pas de repli sur l'hébergement) : on veut savoir si CE mot de passe marche.
            if (reglage('courriel.transport') !== 'mail') {
                $message = 'Ce site est en mode essai : les e-mails sont écrits dans un fichier, rien ne part.';
            } elseif (smtp_envoyer($serveur + ['utilisateur' => $v['boite'], 'mdp' => $mdp], $v['boite'], $v['essai'], message_smtp($v['boite'], $v['essai'], $sujet, $entetes, $corps), $erreur)) {
                smtp_brancher($v['boite'], $mdp);
                annoncer('courriel-branche');
                rediriger('/admin/courriel');
            } else {
                $message = (string) $erreur;
            }
        }
    } elseif ($action === 'essai' && $message === '' && $err === []) {
        noter_essai($cle);
        if (!$branche) {
            $message = 'La boîte n\'est pas branchée.';
        } else {
            [$t, $h] = $essai((string) $reglage['utilisateur']);
            // Un essai demandé à la main retente la boîte tout de suite, même si elle était mise de côté après une panne.
            if ((int) ($reglage['pause'] ?? 0) > 0) { $reglage['pause'] = 0; smtp_ecrire($reglage); }
            $parti = envoyer_courriel($v['essai'], 'Essai d\'envoi ' . reglage('nom'), $t, $h);
            $apres = smtp_reglage() ?? $reglage;
            if ($parti && (string) ($apres['erreur'] ?? '') === '') {
                $apres['test_le'] = time();
                smtp_ecrire($apres);
                annoncer('courriel-essai');
                rediriger('/admin/courriel');
            }
            $message = (string) ($apres['erreur'] ?? '') !== '' ? (string) $apres['erreur'] . ($parti ? ' L\'e-mail est parti par l\'hébergement à la place.' : '') : 'L\'e-mail n\'est pas parti.';
            $reglage = $apres;
        }
    }
}

$quand = static fn(int $t): string => date('d/m/Y à H:i', $t);
page_debut('E-mails', ['zone' => 'app', 'courant' => 'courriel']);
?>
    <p class="crumb">Administration</p>
    <h1 class="h1-app">E-mails du site</h1>
    <?= annonce_html() ?>
<?php if ($message !== ''): ?>
    <p class="msg msg-err" role="alert"><?= e($message) ?></p>
<?php endif; ?>
    <p class="intro-app">Les e-mails automatiques (liste d'attente, confirmation d'adresse, mot de passe oublié) peuvent partir de deux façons. Par l'hébergement, ils ne sont pas signés : certaines boîtes les jettent ou les rangent dans les indésirables. Par la boîte contact, ils sont signés comme ceux que tu écris depuis le webmail, et ils arrivent.</p>
    <div class="formes">
      <section class="box" aria-labelledby="ce-etat">
        <p class="eyebrow" id="ce-etat">Aujourd'hui</p>
<?php if ($branche): ?>
        <h2>Les e-mails partent par la boîte <?= e((string) $reglage['utilisateur']) ?></h2>
        <p>Branchée le <?= e($quand((int) ($reglage['le'] ?? 0))) ?>. Dernier essai réussi : <?= e($quand((int) ($reglage['test_le'] ?? 0))) ?>. Serveur : <?= e($serveur['hote']) ?>.</p>
<?php if ((string) ($reglage['erreur'] ?? '') !== ''): ?>
        <p class="msg msg-err" role="status">Dernière panne, le <?= e($quand((int) $reglage['erreur_le'])) ?> : <?= e((string) $reglage['erreur']) ?> Tant que ça dure, les e-mails partent par l'hébergement. Si le mot de passe de la boîte a changé, retape-le ci-dessous.</p>
<?php endif; ?>
        <form method="post" action="/admin/courriel" novalidate>
          <?= champ_jeton() ?><input type="hidden" name="action" value="essai">
          <div class="un">
            <?= champ('essai', 'Envoyer un e-mail d\'essai à', ['type' => 'email', 'valeur' => $v['essai'], 'erreur' => $err['essai'] ?? '', 'autocomplete' => 'off', 'inputmode' => 'email', 'max' => 190]) ?>
          </div>
          <button type="submit" class="btn btn-main">Envoyer l'essai</button>
        </form>
<?php else: ?>
        <h2>Les e-mails partent par l'hébergement</h2>
        <p>Ils ne sont pas signés. Pour qu'ils arrivent partout, branche la boîte contact ci-dessous.</p>
<?php endif; ?>
      </section>

      <section class="box" aria-labelledby="ce-boite">
        <h2 id="ce-boite"><?= $branche ? 'Changer de boîte ou de mot de passe' : 'Brancher la boîte contact' ?></h2>
        <p>Le site se connecte à la boîte comme le ferait une application de messagerie, envoie un e-mail d'essai, et ne garde le mot de passe que si l'essai est parti. Le mot de passe est gardé chiffré ; il n'est jamais réaffiché.</p>
        <form method="post" action="/admin/courriel" novalidate autocomplete="off">
          <?= champ_jeton() ?><input type="hidden" name="action" value="brancher">
          <div class="two">
            <?= champ('boite', 'Adresse de la boîte', ['type' => 'email', 'valeur' => $v['boite'], 'erreur' => $err['boite'] ?? '', 'autocomplete' => 'off', 'inputmode' => 'email', 'max' => 190]) ?>
            <?= champ('mdp_boite', 'Mot de passe de la boîte', ['type' => 'password', 'erreur' => $err['mdp_boite'] ?? '', 'autocomplete' => 'off', 'mdp' => true, 'max' => 200, 'aide' => 'Celui du webmail de la boîte, pas celui de ton compte ici.']) ?>
          </div>
<?php if (!$branche): ?>
          <div class="un">
            <?= champ('essai', 'Envoyer l\'e-mail d\'essai à', ['type' => 'email', 'valeur' => $v['essai'], 'erreur' => $err['essai'] ?? '', 'autocomplete' => 'off', 'inputmode' => 'email', 'max' => 190]) ?>
          </div>
<?php else: ?>
          <input type="hidden" name="essai" value="<?= e($v['essai']) ?>">
<?php endif; ?>
          <p class="hint">Si tu changes un jour le mot de passe de la boîte chez OVH, reviens le retaper ici : en attendant, les e-mails partiront par l'hébergement.</p>
          <button type="submit" class="btn <?= $branche ? 'btn-line' : 'btn-main' ?>">Brancher et envoyer l'essai</button>
        </form>
      </section>
<?php if ($branche): ?>

      <section class="box" aria-labelledby="ce-retour">
        <h2 id="ce-retour">Revenir à l'envoi par l'hébergement</h2>
        <p>Le mot de passe de la boîte est effacé du site.</p>
        <form method="post" action="/admin/courriel">
          <?= champ_jeton() ?><input type="hidden" name="action" value="debrancher">
          <button type="submit" class="btn btn-line">Débrancher la boîte</button>
        </form>
      </section>
<?php endif; ?>
    </div>
<?php
page_fin(['zone' => 'app']);
