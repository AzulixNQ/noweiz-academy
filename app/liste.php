<?php
declare(strict_types=1);

/* La liste d'attente : on s'inscrit sur la page d'accueil, on confirme par e-mail (sans confirmation, l'adresse est effacée
   au bout de 7 jours), on se désinscrit en un clic depuis chaque e-mail : la ligne est alors effacée.
   La page ne dit jamais si une adresse est déjà sur la liste : la réponse est la même, l'e-mail fait le reste. */

const PARCOURS = ['of' => 'Order flow', 'ict' => 'ICT', 'pack' => 'Les deux'];

function liste_par_email(string $email): ?array
{
    return ligne('SELECT * FROM liste_attente WHERE email = ?', [$email]);
}

function liste_par_id(int $id): ?array
{
    return ligne('SELECT * FROM liste_attente WHERE id = ?', [$id]);
}

/** Le nombre d'inscrits confirmés (le compteur de la page n'affiche que ceux-là). */
function liste_nombre(): int
{
    return (int) valeur("SELECT COUNT(*) FROM liste_attente WHERE statut = 'confirme'");
}

/** Efface les inscriptions jamais confirmées, 7 jours après. */
function liste_menage(): void
{
    requete("DELETE FROM liste_attente WHERE statut = 'attente' AND cree_le < ?", [time() - 7 * 86400]);
}

/**
 * Inscrit (ou réinscrit) une adresse et envoie l'e-mail qui convient. Renvoie true si tout s'est bien passé de notre côté
 * (y compris quand rien n'est envoyé parce que cette adresse a déjà reçu 3 e-mails dans la journée).
 */
function liste_inscrire(string $prenom, string $email, string $parcours): bool
{
    if (random_int(1, 20) === 1) liste_menage();
    $cle = cle_limite('liste-mail', $email);
    $trop = essais($cle, 86400) >= 3;           // jamais plus de 3 e-mails par jour à une même adresse
    $maintenant = time();
    $ligne = liste_par_email($email);

    if ($ligne !== null && $ligne['statut'] === 'confirme') {
        // Déjà inscrite : on met à jour le prénom et le parcours, et on lui dit qu'elle est déjà sur la liste.
        requete('UPDATE liste_attente SET prenom = ?, parcours = ? WHERE id = ?', [$prenom, $parcours, $ligne['id']]);
        if ($trop) return true;
        noter_essai($cle);
        return courriel_liste_deja(liste_par_id((int) $ligne['id']));
    }

    $jeton = bin2hex(random_bytes(32));
    $expire = $maintenant + (int) reglage('confirmation_heures') * 3600;
    if ($ligne === null) {
        try {
            requete("INSERT INTO liste_attente (email, prenom, parcours, statut, jeton, jeton_expire, cree_le, accord_le) VALUES (?, ?, ?, 'attente', ?, ?, ?, ?)",
                [$email, $prenom, $parcours, empreinte($jeton), $expire, $maintenant, $maintenant]);
        } catch (PDOException $e) {
            return liste_inscrire($prenom, $email, $parcours);     // la même adresse vient d'arriver par un autre envoi
        }
    } else {
        if ($trop) return true;
        requete('UPDATE liste_attente SET prenom = ?, parcours = ?, jeton = ?, jeton_expire = ?, accord_le = ? WHERE id = ?',
            [$prenom, $parcours, empreinte($jeton), $expire, $maintenant, $ligne['id']]);
    }
    if ($trop) return true;
    noter_essai($cle);
    return courriel_liste_confirmer(liste_par_email($email), url_site('/liste-attente/confirmer?lien=' . $jeton));
}

/** Le lien de confirmation : renvoie l'inscription confirmée, ou null si le lien ne vaut rien. */
function liste_confirmer(string $jeton): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $jeton)) return null;
    $l = ligne("SELECT * FROM liste_attente WHERE jeton = ? AND statut = 'attente' AND jeton_expire > ?", [empreinte($jeton), time()]);
    if ($l === null) return null;
    requete("UPDATE liste_attente SET statut = 'confirme', confirme_le = ?, jeton = NULL, jeton_expire = NULL WHERE id = ?", [time(), $l['id']]);
    return liste_par_id((int) $l['id']);
}

/** La signature du lien de désinscription : elle ne se devine pas, et elle ne marche que pour cette ligne et cette adresse. */
function liste_signature(array $l): string
{
    return substr(hash_hmac('sha256', 'desinscription|' . $l['id'] . '|' . $l['email'], sel()), 0, 32);
}

function liste_lien_desinscription(array $l): string
{
    return url_site('/liste-attente/desinscription?i=' . (int) $l['id'] . '&s=' . liste_signature($l));
}

/** L'inscription désignée par un lien de désinscription, ou null. */
function liste_par_lien(string $i, string $s): ?array
{
    if (!ctype_digit($i) || !preg_match('/^[a-f0-9]{32}$/', $s)) return null;
    $l = liste_par_id((int) $i);
    return $l !== null && hash_equals(liste_signature($l), $s) ? $l : null;
}

function liste_supprimer(int $id): void
{
    requete('DELETE FROM liste_attente WHERE id = ?', [$id]);
}

/* ---------- les e-mails de la liste ---------- */

function entetes_desinscription(array $l): array
{
    // Gmail et Yahoo affichent « Se désabonner » à côté de l'expéditeur, et le désabonnement se fait en un clic (RFC 8058).
    return ['List-Unsubscribe: <' . liste_lien_desinscription($l) . '>', 'List-Unsubscribe-Post: List-Unsubscribe=One-Click'];
}

function courriel_liste_confirmer(?array $l, string $lien): bool
{
    if ($l === null) return false;
    [$t, $h] = gabarit_courriel(
        'Confirme ton inscription',
        ['Bonjour ' . $l['prenom'] . ',',
         'Tu as demandé à être prévenu de l\'ouverture de ' . reglage('nom') . ' (parcours : ' . (PARCOURS[$l['parcours']] ?? $l['parcours']) . ').',
         'Clique sur le bouton pour confirmer : sans confirmation, on ne garde pas ton adresse.'],
        'Confirmer mon inscription',
        $lien,
        'Ce lien marche pendant ' . duree_lisible((int) reglage('confirmation_heures') * 60) . ". Si ce n'est pas toi, ne fais rien : ton adresse sera effacée."
    );
    return envoyer_courriel($l['email'], 'Confirme ton inscription à la liste d\'attente', $t, $h);
}

function courriel_liste_deja(?array $l): bool
{
    if ($l === null) return false;
    [$t, $h] = gabarit_courriel(
        'Tu es déjà sur la liste',
        ['Bonjour ' . $l['prenom'] . ',',
         'Ton adresse est déjà sur la liste d\'attente de ' . reglage('nom') . ' : tu n\'as rien à faire. On t\'écrira à l\'ouverture, avant tout le monde.',
         'Le parcours qui t\'intéresse est maintenant : ' . (PARCOURS[$l['parcours']] ?? $l['parcours']) . '.'],
        'Rejoindre le Discord',
        (string) reglage('discord'),
        'Pour ne plus recevoir nos e-mails, désinscris-toi ici : ' . liste_lien_desinscription($l)
    );
    return envoyer_courriel($l['email'], 'Tu es déjà sur la liste d\'attente', $t, $h, entetes_desinscription($l));
}
