<?php
declare(strict_types=1);

/* La cohorte d'essai : de quoi tester l'espace élève avant que Nojah et Weizix aient tourné leurs cours.
   8 modules d'order flow (les titres de la trame), 5 leçons chacun, 3 questions de quiz par module, une vraie vidéo
   (l'extrait sur l'absorption) dans le module 3, un live passé avec son replay et un live à venir.
   Tout ce qui est créé ici porte la marque « essai » et s'efface d'un clic. Les questions sont des notions de base,
   écrites pour l'essai : ce ne sont pas celles de Nojah. */

function essai_contenu(): array
{
    return [
        ['Ordres limite et ordres au marché', "Qui attend dans le carnet, qui frappe au marché, et ce qui fait vraiment bouger le prix.",
            ['Le carnet et le prix', "L'ordre au marché", "L'ordre limite", 'Qui fait bouger le prix', 'Exercice : lire une exécution'],
            [["Un ordre au marché à l'achat est exécuté…", ['Tout de suite, au meilleur prix vendeur disponible', 'Seulement au prix que tu as fixé', 'À la clôture de la séance'],
              "L'ordre au marché prend la liquidité en face : il passe tout de suite au meilleur prix vendeur, et plus loin si la quantité ne suffit pas."],
             ["Un ordre limite d'achat placé sous le prix actuel…", ['Attend dans le carnet que le prix descende jusqu\'à lui', 'Passe tout de suite au prix actuel', 'Est refusé par la bourse'],
              "Il ajoute de la liquidité au carnet et ne s'exécute que si le prix vient le chercher."],
             ['Qu\'est-ce qui fait monter le prix ?', ['Les achats au marché qui consomment toutes les offres d\'un niveau', 'Les ordres limite d\'achat qui attendent', 'Le nombre de traders connectés'],
              "Quand les achats au marché ont consommé toutes les offres d'un niveau, il faut aller chercher le niveau suivant : le prix monte."]]],
        ["Le carnet d'ordres et le footprint", 'Lire le carnet, puis le footprint : ce qui s\'est vraiment échangé, prix par prix.',
            ["Lire le carnet d'ordres", 'Bid, ask et spread', 'Le footprint, bougie par bougie', "Le delta d'une bougie", 'Exercice : lire un footprint'],
            [["Dans un footprint, le delta d'une bougie, c'est…", ['Le volume acheté au marché moins le volume vendu au marché', 'Le plus haut moins le plus bas', 'Le volume total de la bougie'],
              'Le delta compare ce qui a été acheté au marché (à l\'ask) et vendu au marché (au bid).'],
             ['Le spread, c\'est…', ['L\'écart entre le meilleur prix acheteur et le meilleur prix vendeur', 'La commission du broker', 'L\'écart entre l\'ouverture et la clôture'],
              'Bid et ask ne sont pas au même prix : le spread est l\'écart entre les deux.'],
             ['Pour chaque prix d\'une bougie, le footprint montre…', ['Le volume échangé au bid et à l\'ask', 'Les ordres limite encore en attente', 'Le nombre de traders'],
              'Le footprint montre ce qui s\'est échangé ; les ordres en attente, c\'est le carnet.']]],
        ["L'absorption", 'Beaucoup d\'achats, et le prix ne monte pas : qui est en face, et ce que ça change pour ton trade.',
            ["C'est quoi, une absorption", 'La repérer sur le footprint', 'Qui absorbe ?', 'Trier les absorptions', "L'absorption dans un trade"],
            [["Beaucoup d'achats au marché arrivent sur un niveau, et le prix ne monte pas. Que se passe-t-il ?", ['Un vendeur à cours limité absorbe ces achats', "Il n'y a pas assez d'acheteurs", 'Le prix monte toujours avec un temps de retard'],
              "Quelqu'un vend en face avec des ordres limite assez gros pour tout prendre : les achats sont absorbés."],
             ['Sur un footprint, une absorption vendeuse ressemble souvent à…', ['Un gros volume acheté sur un niveau, sans progression du prix', 'Une bougie sans volume', 'Un écart de prix à l\'ouverture'],
              'Beaucoup d\'achats au même endroit, et le prix ne dépasse pas ce niveau.'],
             ["Après une absorption vendeuse en haut d'un mouvement, à quoi faut-il penser ?", ['À une baisse possible si les acheteurs s\'épuisent', 'À une hausse immédiate, forcément', 'À rien : ça ne change rien'],
              'Les acheteurs ont frappé sans faire monter le prix : s\'ils s\'épuisent, le prix peut se retourner. À confirmer avec le reste du contexte.']]],
        ['Le volume profile', 'Où le marché a échangé le plus, où il est passé vite, et comment s\'en servir.',
            ['Le volume par prix', 'Le POC', 'La value area', 'Zones de fort et de faible volume', 'Exercice : lire le profil d\'une séance'],
            [['Le POC d\'un volume profile, c\'est…', ['Le prix où il s\'est échangé le plus de volume', 'Le prix d\'ouverture', 'Le plus haut de la séance'],
              'POC veut dire « point of control » : le prix le plus échangé de la période.'],
             ['La value area regroupe en général…', ['Environ 70 % du volume échangé', 'Environ 10 % du volume', 'Tout le volume de la séance'],
              'Par convention, la value area contient à peu près 70 % du volume autour du POC.'],
             ['Une zone de faible volume, le prix a tendance à…', ['La traverser vite', 'Y rester longtemps', 'Ne jamais l\'atteindre'],
              'Peu de volume veut dire peu d\'intérêt à ces prix : le marché y passe souvent rapidement.']]],
        ['La VWAP et ses déviations', 'Le prix moyen du jour pondéré par le volume, et les bandes qui disent quand le prix est étiré.',
            ['Ce que calcule la VWAP', 'La VWAP ancrée', 'Les déviations', 'Retour à la VWAP ou continuation', 'Exercice : une séance avec ses déviations'],
            [['La VWAP, c\'est…', ['Le prix moyen pondéré par le volume', 'La moyenne des clôtures', 'Le prix médian de la séance'],
              'Chaque prix compte en proportion du volume échangé : c\'est le prix moyen payé dans la séance.'],
             ['Les bandes de déviation de la VWAP se calculent avec…', ['L\'écart-type des prix autour de la VWAP', 'Le plus haut et le plus bas de la veille', 'Le volume total'],
              'Les bandes sont placées à un, deux ou trois écarts-types de la VWAP.'],
             ['Le prix touche une déviation extrême au-dessus de la VWAP. Que peut-on dire ?', ['Il est étiré : il peut revenir vers la VWAP ou continuer, le flux d\'ordres aide à trancher', 'Il va forcément revenir à la VWAP', 'Il va forcément continuer à monter'],
              'Une même déviation peut finir de deux façons : c\'est la lecture du flux qui fait la différence.']]],
        ['Le CVD et les divergences', 'Le delta cumulé : qui a gagné la bataille sur la durée, et quand il ne suit plus le prix.',
            ['Le delta cumulé (CVD)', 'Le CVD et le prix ensemble', 'Les divergences', 'Les pièges du CVD', 'Exercice : repérer une divergence'],
            [['Le CVD, c\'est…', ['La somme des deltas, bougie après bougie', 'Le volume d\'une seule bougie', 'La différence entre deux moyennes mobiles'],
              'CVD veut dire « cumulative volume delta » : on additionne le delta de chaque bougie.'],
             ['Le prix fait un nouveau plus haut, mais le CVD fait un plus haut plus bas. On parle de…', ['Divergence', 'Confirmation', 'Absorption acheteuse'],
              'Le prix et le CVD ne vont plus dans le même sens : c\'est une divergence.'],
             ['Une divergence suffit-elle, seule, pour entrer en position ?', ['Non : il faut d\'autres éléments (niveau, absorption, contexte)', 'Oui, toujours', 'Oui, si elle dure plus d\'une heure'],
              'Une divergence est un indice, pas un signal à elle seule.']]],
        ['Construire un trade : entrée, stop, objectif', 'Le plan avant de cliquer : où entrer, où tu as tort, où tu sors.',
            ["Le plan avant l'entrée", 'Placer le stop', "Choisir l'objectif", 'La taille de position et le risque', 'Exercice : un trade de A à Z'],
            [['Où placer le stop d\'un trade ?', ['Là où l\'idée du trade est fausse', 'Toujours à 10 points', 'Là où la perte ne se voit pas'],
              'Le stop se met là où le scénario n\'a plus de sens, puis la taille de position s\'adapte.'],
             ['Risquer 1 % d\'un compte de 50 000 $, c\'est accepter de perdre au plus…', ['500 $', '50 $', '5 000 $'],
              '1 % de 50 000 $, c\'est 500 $.'],
             ['Stop à 10 points, objectif à 20 points : quel est le ratio gain/risque ?', ['2', '0,5', '20'],
              '20 points à gagner pour 10 points risqués : 2 pour 1.']]],
        ['Gérer un compte de prop firm', 'Les règles du challenge, le drawdown, et un plan de risque pour les tenir.',
            ['Les règles d\'un challenge', 'Le drawdown', 'La perte maximale par jour', 'Du challenge au compte financé', 'Exercice : ton plan de risque'],
            [['Dans un challenge de prop firm, dépasser la perte maximale autorisée…', ['Fait perdre le compte', 'Donne un simple avertissement', 'N\'a pas de conséquence'],
              'C\'est la règle principale : la dépasser, c\'est perdre le compte.'],
             ['Un drawdown « suiveur » (trailing)…', ['Monte avec le plus haut atteint par le compte', 'Reste toujours au même niveau', 'Descend quand tu gagnes'],
              'Il suit le plus haut du compte : chaque nouveau plus haut remonte la limite.'],
             ['Pour tenir les règles, le plus utile est…', ['Fixer à l\'avance une perte maximale par jour, plus petite que celle de la prop firm', 'Doubler la taille après une perte', 'Trader toute la journée'],
              'Une limite personnelle plus stricte laisse de la marge avant la règle de la prop firm.']]],
    ];
}

function essai_cohorte(): ?array
{
    $id = valeur('SELECT id FROM cohortes WHERE essai = 1 ORDER BY id LIMIT 1');
    return $id ? cohorte_par_id((int) $id) : null;
}

/** Peut-on créer l'essai ? Non si le parcours order flow a déjà de vrais modules (on ne mélange pas). */
function essai_possible(): bool
{
    $of = (int) valeur("SELECT id FROM parcours WHERE cle = 'of'");
    return $of > 0 && !valeur('SELECT 1 FROM modules WHERE parcours_id = ? AND essai = 0', [$of]) && essai_cohorte() === null;
}

/** Crée la cohorte d'essai et y inscrit $compte_id. Renvoie l'id de la cohorte. */
function essai_creer(int $compte_id): int
{
    $of = (int) valeur("SELECT id FROM parcours WHERE cle = 'of'");
    $maintenant = time();
    $debut = (int) strtotime('today', $maintenant - 15 * 86400);       // il y a 15 jours, à minuit : la cohorte est en semaine 3

    // La vraie vidéo de l'essai, si on en a posé une dans le dossier de données (video-essai.mp4) : copiée sous un nom tiré au hasard.
    // (L'extrait sur l'absorption n'est plus sur le site depuis le 26/11, à la demande de Nojah.)
    $video = null;
    $source = (string) (getenv('NA_VIDEO_ESSAI') ?: dossier_donnees() . '/video-essai.mp4');
    if (is_file($source)) {
        $nom = bin2hex(random_bytes(16)) . '.mp4';
        if (@copy($source, dossier_videos() . '/' . $nom)) $video = $nom;
    }

    bloc_debut();
    try {
        requete('INSERT INTO cohortes (parcours_id, nom, debut, places, discord, essai, cree_le) VALUES (?, ?, ?, ?, ?, 1, ?)',
            [$of, "Cohorte d'essai", $debut, 50, (string) reglage('discord'), $maintenant]);
        $cid = (int) base()->lastInsertId();
        requete('INSERT INTO inscriptions (compte_id, cohorte_id, cree_le) VALUES (?, ?, ?)', [$compte_id, $cid, $maintenant]);
        $modules = [];
        foreach (essai_contenu() as $i => [$titre, $resume, $lecons, $questions]) {
            requete('INSERT INTO modules (parcours_id, numero, titre, resume, essai, cree_le) VALUES (?, ?, ?, ?, 1, ?)', [$of, $i + 1, $titre, $resume, $maintenant]);
            $mid = (int) base()->lastInsertId();
            $modules[$i + 1] = $mid;
            foreach ($lecons as $j => $lt) {
                $avec_video = $i === 2 && $j === 0 && $video !== null;
                requete('INSERT INTO lecons (module_id, numero, titre, texte, video, duree, cree_le) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$mid, $j + 1, $lt, $avec_video ? "Leçon d'essai avec une vraie vidéo. Les autres leçons n'ont pas encore de vidéo." : "Leçon d'essai : la vidéo et le texte de Nojah viendront ici.",
                     $avec_video ? $video : null, $avec_video ? 64 : null, $maintenant]);
            }
            foreach ($questions as $k => [$enonce, $choix, $explication]) {
                // la bonne réponse est écrite en premier ; on mélange pour qu'elle ne soit pas toujours en A
                $ordre = array_keys($choix);
                shuffle($ordre);
                $melange = array_map(static fn($x) => $choix[$x], $ordre);
                requete('INSERT INTO questions (module_id, numero, enonce, choix, bonne, explication) VALUES (?, ?, ?, ?, ?, ?)',
                    [$mid, $k + 1, $enonce, json_encode($melange, JSON_UNESCAPED_UNICODE), (int) array_search(0, $ordre, true), $explication]);
            }
        }
        // Un live passé (module 2) avec son replay, et le prochain live (module 3), jeudi à 20 h.
        requete('INSERT INTO lives (cohorte_id, module_id, debut, duree, titre, lien, replay, replay_duree, cree_le) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$cid, $modules[2], (int) strtotime('today 20:00', $debut + 10 * 86400), 60, "Live d'essai : les exercices du module 2", null, $video, $video !== null ? 64 : null, $maintenant]);
        requete('INSERT INTO lives (cohorte_id, module_id, debut, duree, titre, lien, replay, replay_duree, cree_le) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$cid, $modules[3], (int) strtotime('next thursday 20:00', $maintenant), 60, "Live d'essai : les exercices du module 3", null, null, null, $maintenant]);
        bloc_fin();
    } catch (Throwable $e) {
        bloc_annuler();
        if ($video !== null) @unlink(dossier_videos() . '/' . $video);
        throw $e;
    }
    return $cid;
}

/** Efface tout l'essai : cohortes, inscriptions, lives, modules, leçons, questions, progression, vidéos. */
function essai_supprimer(): void
{
    $fichiers = [];
    foreach (lignes('SELECT video FROM lecons WHERE video IS NOT NULL AND module_id IN (SELECT id FROM modules WHERE essai = 1)') as $l) $fichiers[] = $l['video'];
    foreach (lignes('SELECT replay FROM lives WHERE replay IS NOT NULL AND cohorte_id IN (SELECT id FROM cohortes WHERE essai = 1)') as $l) $fichiers[] = $l['replay'];
    bloc_debut();
    try {
        requete('DELETE FROM vues WHERE lecon_id IN (SELECT l.id FROM lecons l JOIN modules m ON m.id = l.module_id WHERE m.essai = 1)');
        requete('DELETE FROM quiz WHERE module_id IN (SELECT id FROM modules WHERE essai = 1)');
        requete('DELETE FROM questions WHERE module_id IN (SELECT id FROM modules WHERE essai = 1)');
        requete('DELETE FROM lecons WHERE module_id IN (SELECT id FROM modules WHERE essai = 1)');
        requete('DELETE FROM lives WHERE cohorte_id IN (SELECT id FROM cohortes WHERE essai = 1)');
        requete('DELETE FROM inscriptions WHERE cohorte_id IN (SELECT id FROM cohortes WHERE essai = 1)');
        requete('DELETE FROM modules WHERE essai = 1');
        requete('DELETE FROM cohortes WHERE essai = 1');
        bloc_fin();
    } catch (Throwable $e) {
        bloc_annuler();
        throw $e;
    }
    // une vidéo encore utilisée ailleurs (une vraie leçon) reste
    foreach (array_unique($fichiers) as $f) {
        if (valeur('SELECT 1 FROM lecons WHERE video = ?', [$f]) || valeur('SELECT 1 FROM lives WHERE replay = ?', [$f])) continue;
        $chemin = fichier_video($f);
        if ($chemin !== null) @unlink($chemin);
    }
}
