<?php
/*
 * Mesure d'audience du site Groupe MSIS-CI : fonctions communes (voir suivi/LISEZ-MOI.txt).
 *
 * Principes : mesure ANONYME. Aucun cookie pour les visiteurs, aucune adresse IP conservée, aucun texte saisi
 * par le visiteur. Un fichier par jour, une ligne JSON par événement, pas de base de données. Les données sont
 * rangées HORS du dossier public quand le serveur le permet ; chaque fichier commence par une ligne PHP qui
 * l'empêche d'être lu depuis Internet. Aucun secret ici : la clé du tableau de bord est créée sur le serveur
 * par installer.php (seule son empreinte y est gardée) et ne figure pas dans le dépôt Git.
 *
 * Ce fichier est chargé à chaque mesure : il ne contient que le strict nécessaire (stockage, anonymat,
 * contrôles). Les calculs du tableau de bord sont dans analyse.php.
 *
 * Écrit pour PHP 7.2 et plus (pas de fonctions plus récentes).
 */

date_default_timezone_set('UTC'); /* UTC = heure d'Abidjan */

define('SUIVI_HOTES', 'msis-ci.com,www.msis-ci.com');      /* sites autorisés à envoyer des mesures */
define('SUIVI_GARDE', "<?php exit; ?>\n");                 /* première ligne de chaque fichier de données */
define('SUIVI_CONSERVATION_JOURS', 730);                   /* 24 mois */
define('SUIVI_LIMITE_VISITEUR_JOUR', 400);                 /* événements par visiteur et par jour */
define('SUIVI_LIMITE_GLOBALE_JOUR', 150000);               /* événements par jour, tous visiteurs */
define('SUIVI_OCTETS_MAX', 24000);                         /* taille maximale d'un envoi */

/* ---------- Stockage ---------- */

/* Dossier des données : au-dessus du dossier public si possible, sinon dans suivi/donnees. */
function suivi_dossier()
{
    static $dossier = null;
    if ($dossier !== null) {
        return $dossier;
    }
    $candidats = array();
    $test = getenv('SUIVI_DOSSIER_TEST');
    if ($test) {
        $candidats[] = rtrim($test, '/\\');
    } elseif (PHP_SAPI === 'cli-server') {
        $candidats[] = rtrim(sys_get_temp_dir(), '/\\') . '/suivi_test';
    } else {
        $racine = isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/\\') : '';
        if ($racine !== '') {
            $candidats[] = dirname($racine) . '/suivi_donnees';
            $candidats[] = $racine . '/suivi/donnees';
        }
    }
    foreach ($candidats as $c) {
        if ((is_dir($c) || @mkdir($c, 0750, true)) && is_writable($c)) {
            $dossier = $c;
            if (strpos(str_replace('\\', '/', $c), '/suivi/donnees') !== false) {
                /* Repli dans le dossier public : on le ferme aux visiteurs (chaque fichier se protège déjà par sa première ligne). */
                if (!is_file($c . '/index.html')) {
                    @file_put_contents($c . '/index.html', '');
                }
                if (!is_file($c . '/.htaccess')) {
                    @file_put_contents($c . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
                }
            }
            return $dossier;
        }
    }
    $dossier = '';
    return $dossier;
}

function suivi_fichier_jour($jour)
{
    return suivi_dossier() . '/evenements-' . $jour . '.php';
}

function suivi_ajouter($jour, $evenement)
{
    if (suivi_dossier() === '') {
        return false;
    }
    $chemin = suivi_fichier_jour($jour);
    $f = @fopen($chemin, 'ab');
    if (!$f) {
        return false;
    }
    if (flock($f, LOCK_EX)) {
        $etat = fstat($f);
        if ($etat && (int) $etat['size'] === 0) {
            fwrite($f, SUIVI_GARDE); /* nouveau fichier : la première ligne le protège contre une lecture depuis Internet */
        }
        fwrite($f, json_encode($evenement, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        fflush($f);
        flock($f, LOCK_UN);
    }
    fclose($f);
    return true;
}

/* Efface les fichiers d'événements plus vieux que la durée de conservation. */
function suivi_purger_anciens()
{
    $d = suivi_dossier();
    if ($d === '') {
        return;
    }
    $limite = gmdate('Y-m-d', time() - SUIVI_CONSERVATION_JOURS * 86400);
    $fichiers = glob($d . '/evenements-*.php');
    if (!$fichiers) {
        return;
    }
    foreach ($fichiers as $f) {
        if (preg_match('/evenements-(\d{4}-\d{2}-\d{2})\.php$/', $f, $m) && $m[1] < $limite) {
            @unlink($f);
        }
    }
}

/* Supprime les fichiers temporaires (sels, quotas, essais de clé) de plus de deux jours. */
function suivi_nettoyer_temporaires()
{
    $d = suivi_dossier();
    if ($d === '') {
        return;
    }
    foreach (array('sel-*.php', 'quotas-*.php', 'echecs-*.php') as $motif) {
        $fichiers = glob($d . '/' . $motif);
        if (!$fichiers) {
            continue;
        }
        foreach ($fichiers as $f) {
            if (filemtime($f) < time() - 2 * 86400) {
                @unlink($f);
            }
        }
    }
}

/* Configuration créée par installer.php (empreinte de la clé d'accès). */
function suivi_config()
{
    $d = suivi_dossier();
    if ($d === '' || !is_file($d . '/config.php')) {
        return null;
    }
    $c = @include $d . '/config.php';
    return is_array($c) ? $c : null;
}

function suivi_ecrire_config($config)
{
    $d = suivi_dossier();
    if ($d === '') {
        return false;
    }
    return @file_put_contents($d . '/config.php', "<?php\nreturn " . var_export($config, true) . ";\n", LOCK_EX) !== false;
}

/* ---------- Anonymat ---------- */

function suivi_ip()
{
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $morceaux = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($morceaux[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
}

/* Sel du jour : tiré au hasard, jamais conservé plus de deux jours. Sans lui, un identifiant ne peut plus être
   recoupé avec une adresse : un même visiteur est compté une fois par jour, jamais suivi d'un jour à l'autre. */
function suivi_sel($jour)
{
    $f = suivi_dossier() . '/sel-' . $jour . '.php';
    for ($i = 0; $i < 4; $i++) {
        $c = @file_get_contents($f);
        if ($c !== false && preg_match('/([0-9a-f]{32})/', $c, $m)) {
            return $m[1];
        }
        $sel = bin2hex(random_bytes(16));
        $h = @fopen($f, 'x'); /* création exclusive : deux demandes simultanées ne produisent qu'un seul sel */
        if ($h) {
            fwrite($h, SUIVI_GARDE . $sel . "\n");
            fclose($h);
            return $sel;
        }
        usleep(20000);
    }
    return hash('sha256', $jour . '|' . __FILE__);
}

function suivi_visiteur($jour)
{
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    return substr(hash('sha256', suivi_sel($jour) . '|' . suivi_ip() . '|' . $ua), 0, 16);
}

/* ---------- Contrôles ---------- */

function suivi_hote_autorise()
{
    if (PHP_SAPI === 'cli-server') {
        return true; /* essais en local */
    }
    $origine = '';
    if (!empty($_SERVER['HTTP_ORIGIN'])) {
        $origine = $_SERVER['HTTP_ORIGIN'];
    } elseif (!empty($_SERVER['HTTP_REFERER'])) {
        $origine = $_SERVER['HTTP_REFERER'];
    }
    $hote = strtolower((string) parse_url($origine, PHP_URL_HOST));
    return $hote !== '' && in_array($hote, explode(',', SUIVI_HOTES), true);
}

function suivi_est_robot($ua)
{
    if ($ua === '') {
        return true;
    }
    return (bool) preg_match('/(^|[^a-z])bot($|[^a-z])|[a-z]bot[\/;)]|crawl|spider|slurp|headless|lighthouse|pagespeed|gtmetrix|facebookexternalhit|monitor|uptime|curl\/|wget|python-requests|httpclient|scrapy|phantomjs|java\/|go-http|libwww/i', $ua);
}

function suivi_appareil($ua, $largeur)
{
    if (preg_match('/iPad|Tablet|PlayBook|Silk/i', $ua) || (preg_match('/Android/i', $ua) && !preg_match('/Mobile/i', $ua))) {
        return 'tablette';
    }
    if (preg_match('/Mobile|iPhone|iPod|Android|Windows Phone|Opera Mini/i', $ua)) {
        return 'mobile';
    }
    if ($largeur > 0 && $largeur < 600) {
        return 'mobile';
    }
    return 'ordinateur';
}

function suivi_nettoyer($valeur, $max)
{
    $v = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $valeur);
    $v = str_replace(array('<', '>'), '', $v);
    $v = trim(preg_replace('/\s+/', ' ', $v));
    return function_exists('mb_substr') ? mb_substr($v, 0, $max, 'UTF-8') : substr($v, 0, $max);
}

/* Contrôle et nettoie un événement reçu du navigateur ; renvoie null s'il est invalide. */
function suivi_valider($e)
{
    if (!is_array($e) || !isset($e['e'], $e['p'])) {
        return null;
    }
    $types = array('vue', 'clic', 'form', 'section', 'temps', 'recherche');
    if (!in_array($e['e'], $types, true)) {
        return null;
    }
    $p = (string) $e['p'];
    if (!preg_match('#^/[A-Za-z0-9_\-./]{0,120}$#', $p) || strpos($p, '..') !== false) {
        return null;
    }
    $p = preg_replace('#/index\.html$#i', '/', $p); /* « / » et « /index.html » sont la même page */
    if (strlen($p) > 1) {
        $p = rtrim($p, '/');
    }
    $sortie = array('e' => $e['e'], 'p' => $p);
    $textes = array('ti' => 90, 'r' => 80, 'us' => 40, 'um' => 40, 'uc' => 60, 'n' => 80, 'c' => 20, 'cible' => 100, 'l' => 5);
    foreach ($textes as $cle => $max) {
        if (isset($e[$cle]) && $e[$cle] !== '' && is_scalar($e[$cle])) {
            $sortie[$cle] = suivi_nettoyer($e[$cle], $max);
        }
    }
    if (isset($e['k']) && preg_match('/^[a-z0-9]{4,12}$/', (string) $e['k'])) {
        $sortie['k'] = (string) $e['k']; /* identifiant de page, tiré au hasard à chaque chargement, jamais conservé côté visiteur */
    }
    $nombres = array('sec' => 1800, 'defil' => 100, 'w' => 10000);
    foreach ($nombres as $cle => $max) {
        if (isset($e[$cle]) && is_numeric($e[$cle])) {
            $sortie[$cle] = max(0, min($max, (int) $e[$cle]));
        }
    }
    if ($sortie['e'] === 'recherche') {
        if (!isset($sortie['n'])) {
            return null;
        }
        /* jamais de numéro de téléphone ni d'adresse e-mail dans une recherche */
        if (strpos($sortie['n'], '@') !== false) {
            return null;
        }
        $sortie['n'] = preg_replace('/\d{5,}/', '…', $sortie['n']);
    }
    return $sortie;
}

/* Limite le nombre d'événements par visiteur et par jour (protège le disque contre un envoi en boucle). */
function suivi_quota_ok($jour, $visiteur)
{
    $f = suivi_dossier() . '/quotas-' . $jour . '.php';
    $h = @fopen($f, 'c+');
    if (!$h) {
        return true;
    }
    $ok = true;
    if (flock($h, LOCK_EX)) {
        $contenu = stream_get_contents($h);
        $donnees = array();
        if ($contenu !== false && strlen($contenu) > strlen(SUIVI_GARDE)) {
            $tmp = json_decode(substr($contenu, strlen(SUIVI_GARDE)), true);
            if (is_array($tmp)) {
                $donnees = $tmp;
            }
        }
        $total = isset($donnees['_total']) ? (int) $donnees['_total'] : 0;
        $mien = isset($donnees[$visiteur]) ? (int) $donnees[$visiteur] : 0;
        if ($total >= SUIVI_LIMITE_GLOBALE_JOUR || $mien >= SUIVI_LIMITE_VISITEUR_JOUR) {
            $ok = false;
        } else {
            $donnees['_total'] = $total + 1;
            $donnees[$visiteur] = $mien + 1;
            ftruncate($h, 0);
            rewind($h);
            fwrite($h, SUIVI_GARDE . json_encode($donnees));
        }
        flock($h, LOCK_UN);
    }
    fclose($h);
    return $ok;
}

/* ---------- Accès au tableau de bord ---------- */

function suivi_empreinte_cle($cle)
{
    return hash('sha256', 'msis-ci|' . $cle);
}

/* Clé d'accès : comparaison à temps constant, 20 essais ratés par jour et par appareil au maximum. */
function suivi_cle_valide($cle)
{
    $config = suivi_config();
    if (!$config || empty($config['cle_hash']) || !is_string($cle) || $cle === '' || strlen($cle) > 200) {
        return false;
    }
    $fichier = suivi_dossier() . '/echecs-' . gmdate('Y-m-d') . '.php';
    $empreinte = substr(hash('sha256', suivi_ip()), 0, 12);
    $echecs = array();
    if (is_file($fichier)) {
        $tmp = json_decode(substr((string) @file_get_contents($fichier), strlen(SUIVI_GARDE)), true);
        if (is_array($tmp)) {
            $echecs = $tmp;
        }
    }
    if (isset($echecs[$empreinte]) && $echecs[$empreinte] >= 20) {
        return false;
    }
    if (hash_equals($config['cle_hash'], suivi_empreinte_cle($cle))) {
        return true;
    }
    $echecs[$empreinte] = (isset($echecs[$empreinte]) ? $echecs[$empreinte] : 0) + 1;
    @file_put_contents($fichier, SUIVI_GARDE . json_encode($echecs), LOCK_EX);
    return false;
}

/* La clé peut arriver par l'en-tête X-Suivi-Cle (rapport automatique), le paramètre cle (premier accès) ou le cookie
   posé au premier accès (consultations suivantes, sans clé dans l'adresse). */
function suivi_cle_recue()
{
    if (!empty($_SERVER['HTTP_X_SUIVI_CLE'])) {
        return (string) $_SERVER['HTTP_X_SUIVI_CLE'];
    }
    if (isset($_POST['cle']) && is_string($_POST['cle'])) {
        return $_POST['cle'];
    }
    if (isset($_GET['cle']) && is_string($_GET['cle'])) {
        return $_GET['cle'];
    }
    if (isset($_COOKIE['msis_suivi']) && is_string($_COOKIE['msis_suivi'])) {
        return $_COOKIE['msis_suivi'];
    }
    return '';
}

function suivi_https()
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
}

function suivi_entetes_prives()
{
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('X-Content-Type-Options: nosniff');
}
