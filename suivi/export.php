<?php
/*
 * Export des statistiques (JSON ou CSV) pour le rapport périodique. Protégé par la clé du tableau de bord,
 * à transmettre dans l'en-tête X-Suivi-Cle (de préférence) ou dans le paramètre cle.
 *
 *   export.php?du=2026-10-01&au=2026-10-31            -> JSON : période, période précédente, pistes d'amélioration
 *   export.php?du=…&au=…&format=csv&table=jours       -> CSV d'une table (jours, pages, sources, clics, sections)
 *   option tests=1 : inclure les essais
 */

require_once __DIR__ . '/analyse.php';

suivi_entetes_prives();

if (!suivi_cle_valide(suivi_cle_recue())) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Accès refusé';
    exit;
}

$du = isset($_GET['du']) ? (string) $_GET['du'] : gmdate('Y-m-d', time() - 29 * 86400);
$au = isset($_GET['au']) ? (string) $_GET['au'] : gmdate('Y-m-d');
if (count(suivi_liste_jours($du, $au)) === 0) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Période invalide (format AAAA-MM-JJ)';
    exit;
}
$tests = !empty($_GET['tests']);
$agg = suivi_agreger($du, $au, $tests);
list($pdu, $pau) = suivi_periode_precedente($du, $au);
$prec = suivi_agreger($pdu, $pau, $tests);

$format = isset($_GET['format']) ? (string) $_GET['format'] : 'json';
if ($format === 'csv') {
    $table = isset($_GET['table']) && preg_match('/^(jours|pages|sources|clics|sections)$/', (string) $_GET['table']) ? (string) $_GET['table'] : 'jours';
    $lignes = array();
    if ($table === 'pages') {
        $lignes[] = array('page', 'nom', 'vues', 'visiteurs', 'temps_moyen_s', 'defilement_moyen_pct', 'entrees', 'rebonds', 'sorties', 'contacts');
        foreach ($agg['pages'] as $p) {
            $lignes[] = array($p['page'], $p['nom'], $p['vues'], $p['visiteurs'], $p['temps_moyen_s'], $p['defilement_moyen'], $p['entrees'], $p['rebonds'], $p['sorties'], $p['contacts']);
        }
    } elseif ($table === 'sources') {
        $lignes[] = array('source', 'visites', 'visites_avec_contact');
        foreach ($agg['sources'] as $s) {
            $lignes[] = array($s['source'], $s['visites'], $s['contacts']);
        }
    } elseif ($table === 'clics') {
        $lignes[] = array('categorie', 'libelle', 'cible', 'page', 'nombre');
        foreach ($agg['clics'] as $c) {
            $lignes[] = array($c['cat'], $c['nom'], $c['cible'], $c['page'], $c['n']);
        }
    } elseif ($table === 'sections') {
        $lignes[] = array('page', 'section', 'nombre');
        foreach ($agg['sections'] as $s) {
            $lignes[] = array($s['page'], $s['section'], $s['n']);
        }
    } else {
        $lignes[] = array('jour', 'visiteurs', 'visites', 'pages_vues', 'contacts');
        foreach ($agg['par_jour'] as $j) {
            $lignes[] = array($j['jour'], $j['visiteurs'], $j['visites'], $j['vues'], $j['contacts']);
        }
    }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="frequentation-' . $table . '-' . $du . '_' . $au . '.csv"');
    $sortie = fopen('php://output', 'w');
    fwrite($sortie, "\xEF\xBB\xBF"); /* pour que Excel reconnaisse les accents */
    foreach ($lignes as $l) {
        /* une cellule qui commencerait par = + - @ serait prise pour une formule par Excel */
        foreach ($l as $i => $cellule) {
            if (is_string($cellule) && $cellule !== '' && strpos('=+-@', $cellule[0]) !== false) {
                $l[$i] = "'" . $cellule;
            }
        }
        fputcsv($sortie, $l, ';');
    }
    fclose($sortie);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(array(
    'genere' => gmdate('Y-m-d\TH:i:s\Z'),
    'demonstration' => is_file(suivi_dossier() . '/DEMO.txt'), /* true = données fictives, à ne jamais présenter comme réelles */
    'tests_inclus' => $tests,
    'periode' => $agg,
    'precedente' => $prec,
    'suggestions' => suivi_suggestions($agg, $prec),
), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
