<?php
/*
 * Mise en service de la mesure d'audience (à faire une seule fois sur le serveur, voir suivi/LISEZ-MOI.txt).
 *
 * Envoyer une requête POST avec le champ « code » (code d'installation, connu du seul propriétaire du site) :
 * le serveur crée le dossier des données, fabrique la clé secrète du tableau de bord et la renvoie UNE seule fois.
 * Seule son empreinte est gardée. Pour changer la clé plus tard, renvoyer le code avec « regenerer=1 ».
 *
 * Le dépôt Git ne contient que l'empreinte du code d'installation, pas le code lui-même.
 */

require_once __DIR__ . '/lib.php';

define('SUIVI_EMPREINTE_INSTALLATION', '8915ca041b413654a812ed47fb1440c59cebf534a582e3a65161f0e452f3ac63');

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

function suivi_repondre($statut, $contenu)
{
    http_response_code($statut);
    echo json_encode($contenu, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    suivi_repondre(404, array('ok' => false));
}
$code = isset($_POST['code']) && is_string($_POST['code']) ? $_POST['code'] : '';
$attendu = SUIVI_EMPREINTE_INSTALLATION;
if ($code === '' || !hash_equals($attendu, hash('sha256', 'msis-installation|' . $code))) {
    usleep(400000);
    suivi_repondre(403, array('ok' => false, 'erreur' => 'code refusé'));
}

$dossier = suivi_dossier();
$etat = array(
    'php' => PHP_VERSION,
    'dossier' => $dossier,
    'hors_dossier_public' => $dossier !== '' && strpos(str_replace('\\', '/', $dossier), '/suivi/donnees') === false,
    'ecriture' => $dossier !== '' && is_writable($dossier),
    'random_bytes' => function_exists('random_bytes'),
    'json' => function_exists('json_encode'),
    /* Diagnostic réservé à celui qui connaît le code : quelle adresse le serveur voit-il derrière le réseau de diffusion ? */
    'adresse_utilisee' => suivi_ip(),
    'remote_addr' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
    'x_forwarded_for' => isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '',
    'autres_entetes' => array_values(array_filter(array('HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_FORWARDED_PROTO'), function ($k) {
        return isset($_SERVER[$k]);
    })),
);
if ($dossier === '') {
    suivi_repondre(500, array('ok' => false, 'erreur' => 'aucun dossier de données accessible en écriture', 'etat' => $etat));
}

$existante = suivi_config();
if ($existante !== null && empty($_POST['regenerer'])) {
    suivi_repondre(409, array('ok' => false, 'erreur' => 'déjà installé (ajouter regenerer=1 pour changer la clé)', 'etat' => $etat));
}

$cle = bin2hex(random_bytes(16));
$config = array('cle_hash' => suivi_empreinte_cle($cle), 'cree' => $existante !== null && isset($existante['cree']) ? $existante['cree'] : gmdate('Y-m-d\TH:i:s\Z'), 'cle_changee' => gmdate('Y-m-d\TH:i:s\Z'));
if (!suivi_ecrire_config($config)) {
    suivi_repondre(500, array('ok' => false, 'erreur' => 'écriture de la configuration impossible', 'etat' => $etat));
}
suivi_repondre(200, array('ok' => true, 'cle' => $cle, 'etat' => $etat));
