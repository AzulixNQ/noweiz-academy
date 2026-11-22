<?php
declare(strict_types=1);

/* La formation : parcours, modules, leçons, quiz, cohortes, progression, classement, lives et vidéos.

   Les règles :
   - le module n s'ouvre pour une cohorte le jour du départ + (n − 1) semaines, et seulement si le module n − 1 est validé ;
   - un module est validé quand son quiz est réussi (réglage quiz_seuil, 70 % par défaut) ; sans quiz, quand toutes ses leçons sont vues ;
   - un administrateur ou un formateur qui n'est pas inscrit dans une cohorte la voit en aperçu : tout est ouvert, rien n'est enregistré ;
   - les points : 10 par leçon vue, 10 par bonne réponse (meilleur essai de chaque quiz). Barème à valider par Nojah et Weizix. */

const SEMAINE = 7 * 86400;

function lignes(string $sql, array $params = []): array
{
    return requete($sql, $params)->fetchAll();
}

function quiz_seuil(): int
{
    return max(1, min(100, (int) reglage('quiz_seuil', 70)));
}

/* ---------- les cohortes ---------- */

function cohorte_par_id(int $id): ?array
{
    return ligne('SELECT c.*, p.cle AS parcours_cle, p.nom AS parcours_nom, p.formateur FROM cohortes c JOIN parcours p ON p.id = c.parcours_id WHERE c.id = ?', [$id]);
}

/**
 * Les cohortes qu'un compte peut ouvrir : les siennes d'abord, puis (administrateur ou formateur) toutes les autres, en aperçu.
 * Chaque cohorte reçoit 'inscrit' (bool) et 'apercu' (bool).
 */
function cohortes_visibles(array $compte): array
{
    static $cache = [];
    $id = (int) $compte['id'];
    if (isset($cache[$id])) return $cache[$id];
    $base = 'SELECT c.*, p.cle AS parcours_cle, p.nom AS parcours_nom, p.formateur FROM cohortes c JOIN parcours p ON p.id = c.parcours_id';
    $miennes = lignes($base . ' JOIN inscriptions i ON i.cohorte_id = c.id WHERE i.compte_id = ? ORDER BY c.debut DESC, c.id DESC', [$id]);
    $out = [];
    foreach ($miennes as $c) $out[(int) $c['id']] = $c + ['inscrit' => true, 'apercu' => false];
    if (in_array($compte['role'], ['admin', 'formateur'], true)) {
        foreach (lignes($base . ' ORDER BY c.debut DESC, c.id DESC') as $c) {
            if (!isset($out[(int) $c['id']])) $out[(int) $c['id']] = $c + ['inscrit' => false, 'apercu' => true];
        }
    }
    return $cache[$id] = array_values($out);
}

/** La cohorte affichée : celle choisie (?c= dans l'adresse, puis gardée dans un témoin), sinon la plus récente des siennes. */
function cohorte_active(array $compte): ?array
{
    static $cache = [];
    $id = (int) $compte['id'];
    if (array_key_exists($id, $cache)) return $cache[$id];
    $toutes = cohortes_visibles($compte);
    if ($toutes === []) return $cache[$id] = null;
    $demande = isset($_GET['c']) && is_string($_GET['c']) && ctype_digit($_GET['c']) ? (int) $_GET['c'] : 0;
    $garde = ctype_digit(lire_temoin('cohorte')) ? (int) lire_temoin('cohorte') : 0;
    foreach ([$demande, $garde] as $n => $voulu) {
        if ($voulu === 0) continue;
        foreach ($toutes as $c) {
            if ((int) $c['id'] === $voulu) {
                if ($n === 0 && $voulu !== $garde && !headers_sent()) poser_temoin('cohorte', (string) $voulu, 180 * 86400);
                return $cache[$id] = $c;
            }
        }
    }
    return $cache[$id] = $toutes[0];
}

/** La semaine de la cohorte : 0 avant le départ, puis 1, 2… (sans dépasser le nombre de modules). */
function semaine_cohorte(array $cohorte, int $nb_modules): int
{
    $ecart = time() - (int) $cohorte['debut'];
    if ($ecart < 0) return 0;
    return max(1, min(max(1, $nb_modules), intdiv($ecart, SEMAINE) + 1));
}

/** Les cohortes d'un parcours où un compte est inscrit. */
function inscrit_parcours(int $compte_id, int $parcours_id): bool
{
    return (bool) valeur('SELECT 1 FROM inscriptions i JOIN cohortes c ON c.id = i.cohorte_id WHERE i.compte_id = ? AND c.parcours_id = ?', [$compte_id, $parcours_id]);
}

/* ---------- les modules, les leçons, les questions ---------- */

function modules_parcours(int $parcours_id): array
{
    return lignes('SELECT m.*, (SELECT COUNT(*) FROM lecons l WHERE l.module_id = m.id) AS nb_lecons, (SELECT COUNT(*) FROM questions q WHERE q.module_id = m.id) AS nb_questions
                   FROM modules m WHERE m.parcours_id = ? ORDER BY m.numero', [$parcours_id]);
}

function module_par_id(int $id): ?array
{
    return ligne('SELECT * FROM modules WHERE id = ?', [$id]);
}

function lecons_module(int $module_id): array
{
    return lignes('SELECT * FROM lecons WHERE module_id = ? ORDER BY numero', [$module_id]);
}

function lecon_par_id(int $id): ?array
{
    return ligne('SELECT l.*, m.parcours_id, m.numero AS module_numero, m.titre AS module_titre FROM lecons l JOIN modules m ON m.id = l.module_id WHERE l.id = ?', [$id]);
}

/** Les questions d'un module ; 'choix' est décodé en liste de textes. */
function questions_module(int $module_id): array
{
    $qs = lignes('SELECT * FROM questions WHERE module_id = ? ORDER BY numero', [$module_id]);
    foreach ($qs as &$q) {
        $c = json_decode((string) $q['choix'], true);
        $q['choix'] = is_array($c) ? array_values(array_map('strval', $c)) : [];
    }
    return $qs;
}

/* ---------- la progression ---------- */

/** Les leçons vues par un compte, par module : [module_id => nombre]. Et la liste des leçons vues : [lecon_id => true]. */
function vues_compte(int $compte_id, int $parcours_id): array
{
    $par_module = [];
    $liste = [];
    foreach (lignes('SELECT v.lecon_id, l.module_id FROM vues v JOIN lecons l ON l.id = v.lecon_id JOIN modules m ON m.id = l.module_id WHERE v.compte_id = ? AND m.parcours_id = ?', [$compte_id, $parcours_id]) as $v) {
        $par_module[(int) $v['module_id']] = ($par_module[(int) $v['module_id']] ?? 0) + 1;
        $liste[(int) $v['lecon_id']] = true;
    }
    return [$par_module, $liste];
}

/** Le meilleur essai de chaque quiz : [module_id => ['note' => , 'total' => , 'pct' => ]]. */
function meilleurs_quiz(int $compte_id, int $parcours_id): array
{
    $out = [];
    foreach (lignes('SELECT q.module_id, q.note, q.total FROM quiz q JOIN modules m ON m.id = q.module_id WHERE q.compte_id = ? AND m.parcours_id = ?', [$compte_id, $parcours_id]) as $q) {
        $t = max(1, (int) $q['total']);
        $pct = intdiv(100 * (int) $q['note'], $t);
        $m = (int) $q['module_id'];
        if (!isset($out[$m]) || $pct > $out[$m]['pct']) $out[$m] = ['note' => (int) $q['note'], 'total' => $t, 'pct' => $pct];
    }
    return $out;
}

/**
 * La progression d'un compte dans une cohorte, module par module. Chaque module reçoit :
 * ouvre_le, vues, quiz (meilleur essai ou null), valide, ouvert, etat ('valide', 'en_cours', 'verrouille'),
 * raison du verrou ('date' ou 'quiz'), position (1, 2…).
 */
function progression(array $compte, array $cohorte): array
{
    static $cache = [];
    $cle = $compte['id'] . '-' . $cohorte['id'];
    if (isset($cache[$cle])) return $cache[$cle];
    $mods = modules_parcours((int) $cohorte['parcours_id']);
    [$vues] = vues_compte((int) $compte['id'], (int) $cohorte['parcours_id']);
    $quiz = meilleurs_quiz((int) $compte['id'], (int) $cohorte['parcours_id']);
    $apercu = !empty($cohorte['apercu']);
    $maintenant = time();
    $seuil = quiz_seuil();
    $precedent = true;
    $out = [];
    foreach ($mods as $i => $m) {
        $mid = (int) $m['id'];
        $ouvre_le = (int) $cohorte['debut'] + $i * SEMAINE;
        $nb_vues = min((int) $m['nb_lecons'], $vues[$mid] ?? 0);
        $q = $quiz[$mid] ?? null;
        $valide = (int) $m['nb_questions'] > 0 ? ($q !== null && $q['pct'] >= $seuil) : ((int) $m['nb_lecons'] > 0 && $nb_vues >= (int) $m['nb_lecons']);
        if ($apercu) {
            $ouvert = true;
            $raison = null;
        } else {
            $ouvert = $maintenant >= $ouvre_le && $precedent;
            $raison = $maintenant < $ouvre_le ? 'date' : (!$precedent ? 'quiz' : null);
        }
        $out[] = $m + [
            'position' => $i + 1,
            'ouvre_le' => $ouvre_le,
            'vues' => $nb_vues,
            'quiz' => $q,
            'valide' => $valide,
            'ouvert' => $ouvert,
            'etat' => $valide ? 'valide' : ($ouvert ? 'en_cours' : 'verrouille'),
            'raison' => $ouvert ? null : $raison,
        ];
        $precedent = $valide;
    }
    return $cache[$cle] = $out;
}

/** Le module à travailler : le premier ouvert et pas encore validé ; sinon le dernier ouvert. */
function module_a_travailler(array $prog): ?array
{
    $dernier = null;
    foreach ($prog as $m) {
        if ($m['ouvert'] && !$m['valide']) return $m;
        if ($m['ouvert']) $dernier = $m;
    }
    return $dernier;
}

function nombre_valides(array $prog): int
{
    return count(array_filter($prog, static fn($m) => $m['valide']));
}

/** Le texte du verrou d'un module. */
function texte_verrou(array $m, array $prog): string
{
    if ($m['raison'] === 'date') return "S'ouvre le " . date_fr((int) $m['ouvre_le']);
    $prec = $prog[$m['position'] - 2] ?? null;
    return $prec !== null ? "S'ouvre quand le quiz du module " . $prec['numero'] . ' est validé' : 'Verrouillé';
}

/* ---------- les points et le classement ---------- */

function points_compte(int $compte_id, int $parcours_id): int
{
    [, $liste] = vues_compte($compte_id, $parcours_id);
    $pts = 10 * count($liste);
    foreach (meilleurs_quiz($compte_id, $parcours_id) as $q) {
        // le meilleur essai en nombre de bonnes réponses (et pas en pourcentage)
        $pts += 10 * $q['note'];
    }
    return $pts;
}

/** Le classement d'une cohorte : [['compte_id', 'prenom', 'points', 'rang'], …], du premier au dernier. */
function classement(array $cohorte): array
{
    $eleves = lignes('SELECT c.id, c.prenom FROM inscriptions i JOIN comptes c ON c.id = i.compte_id WHERE i.cohorte_id = ?', [$cohorte['id']]);
    $out = [];
    foreach ($eleves as $e) $out[] = ['compte_id' => (int) $e['id'], 'prenom' => $e['prenom'], 'points' => points_compte((int) $e['id'], (int) $cohorte['parcours_id'])];
    usort($out, static fn($a, $b) => [$b['points'], $a['prenom']] <=> [$a['points'], $b['prenom']]);
    $rang = 0;
    $avant = null;
    foreach ($out as $i => &$l) {
        if ($l['points'] !== $avant) $rang = $i + 1;    // ex aequo : même rang
        $l['rang'] = $rang;
        $avant = $l['points'];
    }
    return $out;
}

/* ---------- les leçons vues et les quiz ---------- */

function marquer_vue(int $compte_id, int $lecon_id): void
{
    if (valeur('SELECT 1 FROM vues WHERE compte_id = ? AND lecon_id = ?', [$compte_id, $lecon_id])) return;
    try {
        requete('INSERT INTO vues (compte_id, lecon_id, vue_le) VALUES (?, ?, ?)', [$compte_id, $lecon_id, time()]);
    } catch (PDOException $e) {
        // déjà notée par un autre onglet au même moment
    }
}

/**
 * Corrige un quiz. $reponses : [question_id => index du choix]. Renvoie [note, total, détail par question].
 * Une question sans réponse compte comme fausse.
 */
function corriger_quiz(array $questions, array $reponses): array
{
    $note = 0;
    $detail = [];
    foreach ($questions as $q) {
        $r = $reponses[(int) $q['id']] ?? null;
        $juste = $r !== null && (int) $r === (int) $q['bonne'];
        if ($juste) $note++;
        $detail[(int) $q['id']] = ['reponse' => $r, 'juste' => $juste];
    }
    return [$note, count($questions), $detail];
}

function enregistrer_quiz(int $compte_id, int $module_id, int $note, int $total, array $reponses): void
{
    requete('INSERT INTO quiz (compte_id, module_id, note, total, reponses, fait_le) VALUES (?, ?, ?, ?, ?, ?)',
        [$compte_id, $module_id, $note, $total, json_encode($reponses), time()]);
}

/* ---------- les lives ---------- */

function lives_cohorte(int $cohorte_id): array
{
    return lignes('SELECT * FROM lives WHERE cohorte_id = ? ORDER BY debut', [$cohorte_id]);
}

function prochain_live(int $cohorte_id): ?array
{
    return ligne('SELECT * FROM lives WHERE cohorte_id = ? AND debut + duree * 60 > CAST(? AS INTEGER) ORDER BY debut LIMIT 1', [$cohorte_id, time()]);
}

/* ---------- l'accès à une leçon ---------- */

/**
 * Ce qu'il faut pour afficher une leçon à ce compte, ou null s'il n'y a pas droit :
 * ['lecon', 'cohorte', 'module' (avec sa progression), 'prog'].
 * On cherche d'abord dans la cohorte affichée, puis dans les autres cohortes du compte sur ce parcours.
 */
function acces_module(array $compte, int $module_id): ?array
{
    $module = module_par_id($module_id);
    if ($module === null) return null;
    $active = cohorte_active($compte);
    $candidates = cohortes_visibles($compte);
    if ($active !== null) array_unshift($candidates, $active);
    foreach ($candidates as $c) {
        if ((int) $c['parcours_id'] !== (int) $module['parcours_id']) continue;
        $prog = progression($compte, $c);
        foreach ($prog as $m) {
            if ((int) $m['id'] === $module_id && $m['ouvert']) return ['cohorte' => $c, 'module' => $m, 'prog' => $prog];
        }
    }
    return null;
}

function acces_lecon(array $compte, int $lecon_id): ?array
{
    $lecon = lecon_par_id($lecon_id);
    if ($lecon === null) return null;
    $a = acces_module($compte, (int) $lecon['module_id']);
    return $a === null ? null : $a + ['lecon' => $lecon];
}

/** Un replay : le live et sa cohorte, si le compte peut ouvrir cette cohorte. */
function acces_replay(array $compte, int $live_id): ?array
{
    $live = ligne('SELECT * FROM lives WHERE id = ?', [$live_id]);
    if ($live === null || $live['replay'] === null) return null;
    foreach (cohortes_visibles($compte) as $c) if ((int) $c['id'] === (int) $live['cohorte_id']) return ['live' => $live, 'cohorte' => $c];
    return null;
}

/* ---------- les vidéos ---------- */

/* Les vidéos sont rangées hors du site, dans <données>/videos, sous un nom tiré au hasard. On ne les lit qu'avec un lien
   signé : il vaut pour ce compte, cette connexion et cette vidéo, et il expire au bout de quelques heures. Le serveur vérifie
   aussi, à chaque morceau, que le compte a toujours accès à la leçon. Rien n'empêche un élève de filmer son écran. */

function dossier_videos(): string
{
    $d = dossier_donnees() . '/videos';
    if (!is_dir($d)) @mkdir($d, 0700, true);
    return $d;
}

function fichier_video(?string $nom): ?string
{
    if ($nom === null || !preg_match('/^[a-f0-9]{32}\.mp4$/', $nom)) return null;
    $f = dossier_videos() . '/' . $nom;
    return is_file($f) ? $f : null;
}

function signature_video(array $compte, string $genre, int $id, int $expire): string
{
    return substr(hash_hmac('sha256', 'video|' . $genre . '|' . $id . '|' . $compte['id'] . '|' . $compte['session_id'] . '|' . $expire, sel()), 0, 40);
}

/** Le lien d'une vidéo : $genre 'l' (leçon) ou 'r' (replay d'un live). */
function lien_video(array $compte, string $genre, int $id): string
{
    $expire = time() + 4 * 3600;
    return '/video?g=' . $genre . '&id=' . $id . '&e=' . $expire . '&s=' . signature_video($compte, $genre, $id, $expire);
}

/** Envoie une vidéo, entière ou par morceaux (Range), sans jamais la proposer au téléchargement. */
function servir_video(string $fichier): void
{
    $taille = filesize($fichier);
    $debut = 0;
    $fin = $taille - 1;
    $morceau = 4 * 1024 * 1024;     // au plus 4 Mo par réponse : le lecteur demande la suite
    $plage = $_SERVER['HTTP_RANGE'] ?? '';
    header_remove('X-Powered-By');
    header('Content-Type: video/mp4');
    header('Accept-Ranges: bytes');
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    header('Content-Disposition: inline');
    header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
    header('Cross-Origin-Resource-Policy: same-origin');
    if ($plage !== '') {
        if (!preg_match('/^bytes=(\d*)-(\d*)$/', $plage, $m) || ($m[1] === '' && $m[2] === '')) {
            http_response_code(416);
            header('Content-Range: bytes */' . $taille);
            return;
        }
        if ($m[1] === '') {                     // les N derniers octets
            $debut = max(0, $taille - (int) $m[2]);
        } else {
            $debut = (int) $m[1];
            if ($m[2] !== '') $fin = min($fin, (int) $m[2]);
        }
        if ($debut > $fin || $debut >= $taille) {
            http_response_code(416);
            header('Content-Range: bytes */' . $taille);
            return;
        }
        $fin = min($fin, $debut + $morceau - 1);
        http_response_code(206);
        header('Content-Range: bytes ' . $debut . '-' . $fin . '/' . $taille);
    } else {
        $fin = min($fin, $morceau - 1);
        if ($fin < $taille - 1) {
            http_response_code(206);
            header('Content-Range: bytes 0-' . $fin . '/' . $taille);
        }
    }
    $longueur = $fin - $debut + 1;
    header('Content-Length: ' . $longueur);
    if (methode() === 'HEAD') return;
    $f = fopen($fichier, 'rb');
    if ($f === false) return;
    fseek($f, $debut);
    $reste = $longueur;
    while ($reste > 0 && !feof($f)) {
        $bloc = fread($f, min(65536, $reste));
        if ($bloc === false || $bloc === '') break;
        echo $bloc;
        $reste -= strlen($bloc);
    }
    fclose($f);
}

/** La durée d'une vidéo en clair : « 12 min ». */
function duree_video(?int $secondes): string
{
    if (!$secondes) return '';
    $min = max(1, (int) round($secondes / 60));
    return $min < 60 ? $min . ' min' : intdiv($min, 60) . ' h ' . sprintf('%02d', $min % 60);
}
