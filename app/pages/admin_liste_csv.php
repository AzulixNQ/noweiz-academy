<?php
declare(strict_types=1);

/* L'export de la liste d'attente, pour Excel (séparateur « ; », accents conservés). */

exiger_admin();
$lignes = requete("SELECT prenom, email, parcours, cree_le, confirme_le FROM liste_attente WHERE statut = 'confirme' ORDER BY confirme_le, id")->fetchAll();

entetes_securite();
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="liste-attente-' . date('Y-m-d') . '.csv"');

// Une cellule qui commence par = + - @ serait lue comme une formule par Excel : on la neutralise.
$cellule = static function (string $v): string {
    if ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) $v = "'" . $v;
    return '"' . str_replace('"', '""', $v) . '"';
};
$sortie = "\u{FEFF}" . implode(';', array_map($cellule, ['Prénom', 'E-mail', 'Parcours', 'Inscrit le', 'Confirmé le'])) . "\r\n";
foreach ($lignes as $l) {
    $sortie .= implode(';', array_map($cellule, [
        (string) $l['prenom'], (string) $l['email'], PARCOURS[$l['parcours']] ?? (string) $l['parcours'],
        date('d/m/Y H:i', (int) $l['cree_le']), date('d/m/Y H:i', (int) $l['confirme_le']),
    ])) . "\r\n";
}
echo $sortie;
