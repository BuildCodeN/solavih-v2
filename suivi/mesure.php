<?php
/*
 * Réception des mesures d'audience envoyées par js/frequentation.js (voir suivi/LISEZ-MOI.txt).
 * Répond toujours « 204 sans contenu » : un visiteur ne doit jamais rien voir ni être gêné par la mesure.
 */

require_once __DIR__ . '/lib.php';

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');
header('X-Content-Type-Options: nosniff');

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

/* Réponse unique, quoi qu'il arrive ensuite. */
function suivi_terminer()
{
    http_response_code(204);
    exit;
}

/* Respect de « Ne pas me suivre » et du signal Global Privacy Control : rien n'est enregistré. */
if ((isset($_SERVER['HTTP_DNT']) && $_SERVER['HTTP_DNT'] === '1') || (isset($_SERVER['HTTP_SEC_GPC']) && $_SERVER['HTTP_SEC_GPC'] === '1')) {
    suivi_terminer();
}

$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
if (suivi_est_robot($ua) || !suivi_hote_autorise() || suivi_dossier() === '' || suivi_config() === null) {
    suivi_terminer(); /* pas encore installé : on n'enregistre rien */
}

$brut = file_get_contents('php://input', false, null, 0, SUIVI_OCTETS_MAX + 1);
if ($brut === false || $brut === '' || strlen($brut) > SUIVI_OCTETS_MAX) {
    suivi_terminer();
}
$donnees = json_decode($brut, true);
if (!is_array($donnees)) {
    suivi_terminer();
}
$liste = isset($donnees['evts']) && is_array($donnees['evts']) ? array_slice($donnees['evts'], 0, 25) : array($donnees);
$essai = !empty($donnees['x']);

$jour = gmdate('Y-m-d');
$visiteur = suivi_visiteur($jour);
$heure = gmdate('Y-m-d\TH:i:s\Z');

foreach ($liste as $brutEvenement) {
    $e = suivi_valider($brutEvenement);
    if ($e === null) {
        continue;
    }
    if (!suivi_quota_ok($jour, $visiteur)) {
        break;
    }
    $e['t'] = $heure;
    $e['v'] = $visiteur;
    $e['d'] = suivi_appareil($ua, isset($e['w']) ? $e['w'] : 0);
    unset($e['w']);
    if ($essai) {
        $e['x'] = 1;
    }
    suivi_ajouter($jour, $e);
}

/* Entretien : de temps en temps seulement, pour ne pas ralentir les visites. */
if (mt_rand(1, 150) === 1) {
    suivi_purger_anciens();
    suivi_nettoyer_temporaires();
}
suivi_terminer();
