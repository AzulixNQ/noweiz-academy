<?php
declare(strict_types=1);

/* Les e-mails automatiques : bienvenue et confirmation de l'adresse, mot de passe oublié, alertes de sécurité.
   Chaque e-mail part en deux versions (texte et HTML). En essai (transport « fichier »), rien ne part :
   l'e-mail est écrit dans le dossier de données. */

function envoyer_courriel(string $a, string $sujet, string $texte, string $html, array $en_plus = []): bool
{
    if (!filter_var($a, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $a . $sujet . implode('', $en_plus))) return false;
    $expediteur = (string) reglage('courriel.expediteur');
    $retours = (string) (reglage('courriel.retours') ?: $expediteur);
    $nom = (string) reglage('courriel.nom');

    if (reglage('courriel.transport') === 'fichier') {
        $dossier = dossier_donnees() . '/courriels';
        if (!is_dir($dossier)) mkdir($dossier, 0700, true);
        $fichier = sprintf('%s/%s-%s.json', $dossier, number_format(microtime(true), 4, '', ''), bin2hex(random_bytes(3)));
        return file_put_contents($fichier, json_encode(['a' => $a, 'sujet' => $sujet, 'texte' => $texte, 'html' => $html, 'entetes' => $en_plus], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false;
    }

    $limite = 'na-' . bin2hex(random_bytes(12));
    $entetes = [
        'From: ' . mb_encode_mimeheader($nom, 'UTF-8', 'B') . ' <' . $expediteur . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $limite . '"',
        'Auto-Submitted: auto-generated',
        'X-Auto-Response-Suppress: All',
    ];
    foreach ($en_plus as $e) $entetes[] = $e;
    $corps = "--$limite\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($texte))
           . "--$limite\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
           . "--$limite--\r\n";
    $sujet_code = mb_encode_mimeheader($sujet, 'UTF-8', 'B');

    try {
        $ok = @mail($a, $sujet_code, $corps, implode("\r\n", $entetes), '-f' . $retours);
        if (!$ok) $ok = @mail($a, $sujet_code, $corps, implode("\r\n", $entetes));
    } catch (Throwable $e) {
        $ok = false;
    }
    if (!$ok) error_log('E-mail non parti : ' . $sujet);
    return $ok;
}

/** Met en forme un e-mail : un titre, des paragraphes, un bouton, une note. Renvoie [texte, html]. */
function gabarit_courriel(string $titre, array $paragraphes, ?string $bouton = null, ?string $lien = null, string $note = ''): array
{
    $nom = (string) reglage('nom');
    $pied = "Une question ? Réponds simplement à cet e-mail, ou écris-nous sur le Discord : " . reglage('discord');

    $texte = $titre . "\n\n" . implode("\n\n", $paragraphes);
    if ($lien !== null) $texte .= "\n\n" . $bouton . " :\n" . $lien;
    if ($note !== '') $texte .= "\n\n" . $note;
    $texte .= "\n\n-- \n" . $nom . "\n" . $pied . "\n";

    /* La version HTML reprend le site : un bandeau noir avec l'icône et le nom, le filet qui va du vert citron (Nojah) au blanc
       (Weizix) comme dans l'icône, puis le message sur fond blanc et le bouton vert du site.
       Des tableaux plutôt que des blocs : c'est ce qu'Outlook sait afficher. Si les images sont bloquées, il ne manque que
       l'icône : le nom est du texte. */
    $h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $police = "font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;";
    $discord = (string) reglage('discord');
    // La ligne grise qu'affiche la boîte de réception sous l'objet : la première phrase utile, pas « Bonjour Louis, ».
    $apercu = '';
    foreach ($paragraphes as $p) if (!preg_match('/^(Bonjour|Bienvenue)\b/u', $p)) { $apercu = $p; break; }

    $html = '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
          . '<meta name="color-scheme" content="light"><meta name="supported-color-schemes" content="light"><title>' . $h($titre) . '</title></head>'
          . '<body style="margin:0;padding:0;background:#f4f4f2;-webkit-text-size-adjust:100%;">'
          . '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">' . $h($apercu) . str_repeat('&#847;&zwnj;&nbsp;', 40) . '</div>'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f4f4f2;"><tr><td align="center" style="padding:32px 14px;">'
          . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;">'
          // le bandeau
          . '<tr><td style="background:#0B0B0C;border-radius:16px 16px 0 0;padding:18px 26px;">'
          . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
          . '<td style="vertical-align:middle;padding-right:12px;"><img src="' . $h(url_site('/assets/img/courriel-icone.png')) . '" width="30" height="30" alt="" style="display:block;border:0;"></td>'
          . '<td style="vertical-align:middle;' . $police . 'font-size:18px;line-height:1;font-weight:700;letter-spacing:-0.02em;color:#ffffff;">' . $h($nom) . '</td>'
          . '</tr></table></td></tr>'
          // le filet vert citron → blanc
          . '<tr><td style="height:3px;line-height:3px;font-size:0;background:#C6F432;background-image:linear-gradient(90deg,#C6F432 0%,#C6F432 30%,#ffffff 100%);">&nbsp;</td></tr>'
          // le message
          . '<tr><td style="background:#ffffff;border:1px solid #e3e3df;border-top:0;border-radius:0 0 16px 16px;padding:30px 26px 28px;' . $police . 'color:#0B0B0C;">'
          . '<h1 style="margin:0 0 18px;font-size:24px;line-height:1.25;font-weight:700;letter-spacing:-0.025em;color:#0B0B0C;">' . $h($titre) . '</h1>';
    foreach ($paragraphes as $p) $html .= '<p style="margin:0 0 14px;font-size:16px;line-height:1.55;color:#33333a;">' . nl2br($h($p)) . '</p>';
    if ($lien !== null) {
        $html .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 20px;"><tr><td style="background:#C6F432;border-radius:999px;">'
               . '<a href="' . $h($lien) . '" style="display:inline-block;padding:15px 28px;border:1px solid #B5E21F;border-radius:999px;' . $police . 'font-size:16px;line-height:1;font-weight:700;color:#0B0B0C;text-decoration:none;">' . $h((string) $bouton) . '</a>'
               . '</td></tr></table>'
               . '<p style="margin:0 0 6px;font-size:13.5px;line-height:1.5;color:#6b6b72;">Le bouton ne marche pas ? Copie ce lien dans ton navigateur :</p>'
               . '<p style="margin:0;font-size:13.5px;line-height:1.5;word-break:break-all;"><a href="' . $h($lien) . '" style="color:#33333a;">' . $h($lien) . '</a></p>';
    }
    if ($note !== '') $html .= '<p style="margin:22px 0 0;padding-top:18px;border-top:1px solid #eeeeea;font-size:14.5px;line-height:1.5;color:#6b6b72;">' . $h($note) . '</p>';
    $html .= '</td></tr>'
           // le pied
           . '<tr><td style="padding:18px 8px 0;' . $police . 'font-size:13px;line-height:1.5;color:#6b6b72;">'
           . 'Une question ? Réponds simplement à cet e-mail'
           . ($discord !== '' ? ', ou écris-nous sur <a href="' . $h($discord) . '" style="color:#33333a;">le Discord ' . $h($nom) . '</a>.' : '.')
           . '</td></tr></table></td></tr></table></body></html>';

    return [$texte, $html];
}

function duree_lisible(int $minutes): string
{
    if ($minutes < 120) return $minutes . ' minutes';
    $h = intdiv($minutes, 60);
    return $h < 48 ? $h . ' heures' : intdiv($h, 24) . ' jours';
}

/* ---------- les e-mails ---------- */

function courriel_bienvenue(array $compte, string $lien): bool
{
    [$t, $h] = gabarit_courriel(
        'Bienvenue, ' . $compte['prenom'] . '.',
        ['Ton compte ' . reglage('nom') . ' est créé.', 'Il reste une chose à faire : confirmer que cette adresse e-mail est bien la tienne.'],
        'Confirmer mon adresse',
        $lien,
        'Ce lien marche pendant ' . duree_lisible((int) reglage('confirmation_heures') * 60) . ". Si tu n'as pas créé de compte, ne fais rien : sans confirmation, l'adresse reste marquée comme non vérifiée."
    );
    return envoyer_courriel($compte['email'], 'Bienvenue sur ' . reglage('nom') . ' : confirme ton adresse', $t, $h);
}

function courriel_confirmation(array $compte, string $lien): bool
{
    [$t, $h] = gabarit_courriel(
        'Confirme ton adresse e-mail',
        ['Bonjour ' . $compte['prenom'] . ',', 'Clique sur le bouton pour confirmer que cette adresse est bien la tienne.'],
        'Confirmer mon adresse',
        $lien,
        'Ce lien marche pendant ' . duree_lisible((int) reglage('confirmation_heures') * 60) . '.'
    );
    return envoyer_courriel($compte['email'], 'Confirme ton adresse e-mail', $t, $h);
}

function courriel_mdp_oublie(array $compte, string $lien): bool
{
    [$t, $h] = gabarit_courriel(
        'Choisis un nouveau mot de passe',
        ['Bonjour ' . $compte['prenom'] . ',', 'Quelqu\'un a demandé un nouveau mot de passe pour ton compte ' . reglage('nom') . '. Si c\'est toi, clique sur le bouton.'],
        'Choisir un nouveau mot de passe',
        $lien,
        'Ce lien marche pendant ' . duree_lisible((int) reglage('mdp_lien_minutes')) . ", une seule fois. Si ce n'est pas toi, ne fais rien : ton mot de passe ne change pas."
    );
    return envoyer_courriel($compte['email'], 'Ton nouveau mot de passe ' . reglage('nom'), $t, $h);
}

function courriel_mdp_change(array $compte): bool
{
    [$t, $h] = gabarit_courriel(
        'Ton mot de passe a été changé',
        ['Bonjour ' . $compte['prenom'] . ',', 'Le mot de passe de ton compte ' . reglage('nom') . ' vient d\'être changé. Les autres appareils ont été déconnectés.'],
        'Ce n\'est pas moi : choisir un nouveau mot de passe',
        url_site('/mot-de-passe-oublie'),
        'Si c\'est bien toi, tu n\'as rien à faire.'
    );
    return envoyer_courriel($compte['email'], 'Ton mot de passe a été changé', $t, $h);
}

function courriel_adresse_changee(string $ancienne, string $prenom, string $nouvelle): bool
{
    [$t, $h] = gabarit_courriel(
        'L\'adresse e-mail de ton compte a changé',
        ['Bonjour ' . $prenom . ',', 'Ton compte ' . reglage('nom') . ' utilise maintenant une autre adresse : ' . masquer_email($nouvelle) . '. Cette adresse-ci ne sert plus à te connecter.'],
        null,
        null,
        'Si ce n\'est pas toi, préviens-nous tout de suite sur le Discord.'
    );
    return envoyer_courriel($ancienne, 'L\'adresse e-mail de ton compte a changé', $t, $h);
}

/** lo…@exemple.fr : assez pour reconnaître son adresse, pas assez pour la donner à quelqu'un d'autre. */
function masquer_email(string $email): string
{
    [$avant, $apres] = array_pad(explode('@', $email, 2), 2, '');
    return mb_substr($avant, 0, 2) . '…@' . $apres;
}
