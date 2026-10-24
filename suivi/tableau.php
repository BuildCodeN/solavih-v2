<?php
/*
 * Tableau de bord de fréquentation du site Groupe MSIS-CI (privé : nécessite la clé créée par installer.php).
 * Voir suivi/LISEZ-MOI.txt. Aucune bibliothèque externe : graphiques en SVG fabriqués ici.
 */

require_once __DIR__ . '/analyse.php';

suivi_entetes_prives();
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; img-src data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

function h($texte)
{
    return htmlspecialchars((string) $texte, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function nb($n)
{
    return number_format((float) $n, 0, ',', "\xC2\xA0");
}

function pct($n)
{
    return (int) $n . "\xC2\xA0%";
}

function date_courte($ymd)
{
    return substr($ymd, 8, 2) . '/' . substr($ymd, 5, 2);
}

function date_longue($ymd)
{
    return substr($ymd, 8, 2) . '/' . substr($ymd, 5, 2) . '/' . substr($ymd, 0, 4);
}

function jours_ecoules($du, $au)
{
    return count(suivi_liste_jours($du, $au));
}

/* ---------- Accès ---------- */

$config = suivi_config();
$cleRecue = suivi_cle_recue();
$connecte = $config !== null && $cleRecue !== '' && suivi_cle_valide($cleRecue);

if ($connecte && (isset($_GET['cle']) || isset($_POST['cle']))) {
    /* Accès avec la clé : on la mémorise dans un cookie réservé à ce dossier (visible du seul propriétaire),
       puis on recharge la page sans la clé dans l'adresse. */
    if (!isset($_COOKIE['msis_suivi']) || $_COOKIE['msis_suivi'] !== $cleRecue) {
        header('Set-Cookie: msis_suivi=' . rawurlencode($cleRecue) . '; Max-Age=' . (90 * 86400) . '; Path=/suivi/; HttpOnly; SameSite=Strict' . (suivi_https() ? '; Secure' : ''));
    }
    $reste = $_GET;
    unset($reste['cle']);
    header('Location: tableau.php' . (count($reste) ? '?' . http_build_query($reste) : ''), true, 303);
    exit;
}

if ($connecte && isset($_POST['action']) && $_POST['action'] === 'deconnexion') {
    header('Set-Cookie: msis_suivi=; Max-Age=0; Path=/suivi/; HttpOnly; SameSite=Strict');
    header('Location: tableau.php');
    exit;
}

if (!$connecte) {
    http_response_code($config === null ? 503 : 200);
    ?><!DOCTYPE html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Fréquentation — accès</title>
<style>
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#F8FAFC;color:#1E293B;font:16px/1.5 "Segoe UI",system-ui,-apple-system,Roboto,sans-serif}
form{background:#fff;border:1px solid #E2E8F0;border-radius:14px;padding:32px;width:min(92vw,380px);box-shadow:0 18px 40px -16px rgba(2,6,23,.22)}
h1{margin:0 0 6px;font-size:1.3rem;color:#0F172A}p{margin:0 0 18px;color:#64748B;font-size:.95rem}
input{width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid #CBD5E1;border-radius:8px;font:inherit;margin-bottom:14px}
button{width:100%;padding:12px;border:0;border-radius:8px;background:#EF7618;color:#fff;font:inherit;font-weight:600;cursor:pointer}
.err{color:#B91C1C;font-size:.9rem;margin-bottom:12px}
</style></head><body>
<form method="post" action="tableau.php" autocomplete="off">
<h1>Fréquentation du site</h1>
<?php if ($config === null) { ?>
<p>La mesure d’audience n’est pas encore installée sur ce serveur.</p>
<?php } else { ?>
<p>Espace réservé à l’équipe du Groupe MSIS-CI. Saisissez votre clé d’accès.</p>
<?php if ($cleRecue !== '') { ?><div class="err">Clé refusée.</div><?php } ?>
<input type="password" name="cle" placeholder="Clé d’accès" autofocus required>
<button type="submit">Ouvrir le tableau de bord</button>
<?php } ?>
</form></body></html>
<?php
    exit;
}

/* Jeton contre les envois forcés depuis un autre site (en plus du cookie SameSite=Strict). */
$jeton = hash('sha256', 'jeton|' . $config['cle_hash']);

/* ---------- Choix de la période ---------- */

$aujourdhui = gmdate('Y-m-d');
$choix = isset($_GET['p']) ? (string) $_GET['p'] : '30';
$du = gmdate('Y-m-d', time() - 29 * 86400);
$au = $aujourdhui;
if ($choix === '7') {
    $du = gmdate('Y-m-d', time() - 6 * 86400);
} elseif ($choix === '90') {
    $du = gmdate('Y-m-d', time() - 89 * 86400);
} elseif ($choix === 'mois') {
    $du = gmdate('Y-m-01');
} elseif ($choix === 'mois-1') {
    $premier = strtotime(gmdate('Y-m-01') . ' 00:00:00 UTC');
    $du = gmdate('Y-m-01', $premier - 86400);
    $au = gmdate('Y-m-d', $premier - 86400);
} elseif ($choix === 'perso') {
    $d1 = isset($_GET['du']) ? (string) $_GET['du'] : '';
    $d2 = isset($_GET['au']) ? (string) $_GET['au'] : '';
    if (count(suivi_liste_jours($d1, $d2)) > 0) {
        $du = $d1;
        $au = $d2;
    } else {
        $choix = '30';
    }
} else {
    $choix = '30';
}
$tests = !empty($_GET['tests']);

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'purger_essais') {
    if (isset($_POST['jeton']) && hash_equals($jeton, (string) $_POST['jeton'])) {
        $retires = suivi_purger_essais(gmdate('Y-m-d', time() - 800 * 86400), $aujourdhui);
        $message = $retires . ' événement(s) d’essai effacé(s).';
    } else {
        $message = 'Action refusée (jeton invalide).';
    }
}

$agg = suivi_agreger($du, $au, $tests);
list($pdu, $pau) = suivi_periode_precedente($du, $au);
$prec = suivi_agreger($pdu, $pau, $tests);
$t = $agg['total'];
$tp = $prec['total'];
$suggestions = suivi_suggestions($agg, $prec);
$parametresExport = http_build_query(array('du' => $du, 'au' => $au, 'tests' => $tests ? 1 : 0));
$sansDonnees = $t['evenements'] === 0;
$demo = is_file(suivi_dossier() . '/DEMO.txt'); /* présent seulement dans le dossier de démonstration, jamais en ligne */

/* ---------- Graphiques en SVG ---------- */

function svg_jours($jours)
{
    $n = count($jours);
    if ($n === 0) {
        return '';
    }
    $L = 760;
    $H = 230;
    $gauche = 40;
    $droite = 10;
    $haut = 14;
    $bas = 30;
    $max = 1;
    foreach ($jours as $j) {
        $max = max($max, $j['visites'], $j['vues']);
    }
    $pas = max(1, (int) ceil($max / 4));
    $maxA = $pas * 4;
    $zoneL = $L - $gauche - $droite;
    $zoneH = $H - $haut - $bas;
    $larg = $zoneL / $n;
    $barre = max(2, $larg * 0.62);
    $s = '<svg viewBox="0 0 ' . $L . ' ' . $H . '" role="img" aria-label="Visites et pages vues par jour" class="graph">';
    for ($i = 0; $i <= 4; $i++) {
        $y = $haut + $zoneH - ($zoneH * $i / 4);
        $s .= '<line x1="' . $gauche . '" x2="' . ($L - $droite) . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '" class="grille"/>';
        $s .= '<text x="' . ($gauche - 6) . '" y="' . round($y + 4, 1) . '" class="axe" text-anchor="end">' . ($pas * $i) . '</text>';
    }
    $points = array();
    $saut = max(1, (int) ceil($n / 10));
    foreach ($jours as $i => $j) {
        $x = $gauche + $larg * $i + ($larg - $barre) / 2;
        $hv = $zoneH * $j['visites'] / $maxA;
        $s .= '<rect x="' . round($x, 1) . '" y="' . round($haut + $zoneH - $hv, 1) . '" width="' . round($barre, 1) . '" height="' . round($hv, 1) . '" rx="2" class="barre"><title>'
            . h(date_longue($j['jour']) . ' : ' . $j['visites'] . ' visite(s), ' . $j['vues'] . ' page(s) vue(s)') . '</title></rect>';
        $points[] = round($gauche + $larg * $i + $larg / 2, 1) . ',' . round($haut + $zoneH - $zoneH * $j['vues'] / $maxA, 1);
        if ($i % $saut === 0) {
            $s .= '<text x="' . round($gauche + $larg * $i + $larg / 2, 1) . '" y="' . ($H - 10) . '" class="axe" text-anchor="middle">' . h(date_courte($j['jour'])) . '</text>';
        }
    }
    if ($n > 1) {
        $s .= '<polyline points="' . implode(' ', $points) . '" class="courbe"/>';
    }
    return $s . '</svg>';
}

function svg_colonnes($valeurs, $etiquettes, $titre, $pasEtiquette)
{
    $n = count($valeurs);
    $L = 760;
    $H = 170;
    $gauche = 34;
    $haut = 10;
    $bas = 26;
    $max = max(1, max($valeurs));
    $zoneL = $L - $gauche - 8;
    $zoneH = $H - $haut - $bas;
    $larg = $zoneL / $n;
    $barre = $larg * 0.66;
    $s = '<svg viewBox="0 0 ' . $L . ' ' . $H . '" role="img" aria-label="' . h($titre) . '" class="graph">';
    $s .= '<line x1="' . $gauche . '" x2="' . ($L - 8) . '" y1="' . ($haut + $zoneH) . '" y2="' . ($haut + $zoneH) . '" class="grille"/>';
    $s .= '<text x="' . ($gauche - 6) . '" y="' . ($haut + 8) . '" class="axe" text-anchor="end">' . $max . '</text>';
    $s .= '<text x="' . ($gauche - 6) . '" y="' . ($haut + $zoneH + 4) . '" class="axe" text-anchor="end">0</text>';
    foreach ($valeurs as $i => $v) {
        $hv = $zoneH * $v / $max;
        $x = $gauche + $larg * $i + ($larg - $barre) / 2;
        $s .= '<rect x="' . round($x, 1) . '" y="' . round($haut + $zoneH - $hv, 1) . '" width="' . round($barre, 1) . '" height="' . round($hv, 1) . '" rx="2" class="barre"><title>'
            . h($etiquettes[$i] . ' : ' . $v) . '</title></rect>';
        if ($i % $pasEtiquette === 0) {
            $s .= '<text x="' . round($gauche + $larg * $i + $larg / 2, 1) . '" y="' . ($H - 8) . '" class="axe" text-anchor="middle">' . h($etiquettes[$i]) . '</text>';
        }
    }
    return $s . '</svg>';
}

function liste_barres($lignes, $unite)
{
    /* $lignes : tableau de [libellé, valeur, détail facultatif] ; la plus grande valeur donne 100 % de la largeur. */
    if (!count($lignes)) {
        return '<p class="vide">Pas encore de données.</p>';
    }
    $max = 1;
    foreach ($lignes as $l) {
        $max = max($max, $l[1]);
    }
    $s = '<ul class="barres">';
    foreach ($lignes as $l) {
        $s .= '<li><span class="lib">' . h($l[0]) . (isset($l[2]) && $l[2] !== '' ? '<small>' . h($l[2]) . '</small>' : '') . '</span>'
            . '<span class="piste"><span class="rempli" style="width:' . round(100 * $l[1] / $max) . '%"></span></span>'
            . '<span class="val">' . nb($l[1]) . ($unite !== '' ? ' ' . h($unite) : '') . '</span></li>';
    }
    return $s . '</ul>';
}

function badge($actuel, $avant, $bonSens = 1)
{
    $v = suivi_variation($actuel, $avant);
    if ($v === null) {
        return ''; /* pas de période précédente à comparer (site récent) */
    }
    if ($v === 0) {
        return '<span class="delta neutre">= période précédente</span>';
    }
    $bon = ($v > 0) === ($bonSens > 0);
    return '<span class="delta ' . ($bon ? 'bon' : 'mauvais') . '">' . ($v > 0 ? '▲ +' : '▼ ') . $v . "\xC2\xA0% vs période précédente</span>";
}

$libellesClics = array('whatsapp' => 'WhatsApp', 'tel' => 'Appel', 'mail' => 'E-mail', 'pdf' => 'Document', 'menu' => 'Menu', 'pied' => 'Bas de page', 'interne' => 'Lien du contenu',
    'ancre' => 'Lien dans la page', 'externe' => 'Site externe', 'bouton' => 'Bouton');
$jourSemaine = array('Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam');
$heuresEtiq = array();
for ($i = 0; $i < 24; $i++) {
    $heuresEtiq[] = $i . ' h';
}
$visitesTotal = max(1, $t['visites']);
$sommeContacts = array_sum($agg['contacts']);

?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Fréquentation du site — Groupe MSIS-CI</title>
<style>
:root{--marine:#0F172A;--marine2:#1E293B;--orange:#EF7618;--orange-fonce:#C4600F;--fond:#F8FAFC;--carte:#fff;--bord:#E2E8F0;--texte:#1E293B;--doux:#64748B;--bon:#15803D;--mauvais:#B91C1C}
*{box-sizing:border-box}
body{margin:0;background:var(--fond);color:var(--texte);font:15px/1.55 "Segoe UI",system-ui,-apple-system,Roboto,sans-serif}
a{color:var(--orange-fonce)}
header.haut{background:var(--marine);color:#F8FAFC;padding:16px 0}
.cadre{width:min(1180px,calc(100% - 32px));margin:0 auto}
.haut .cadre{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.haut h1{margin:0;font-size:1.25rem;font-weight:600}.haut h1 small{display:block;font-size:.8rem;font-weight:400;color:#94A3B8}
.haut nav{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.haut a,.haut button{color:#F8FAFC;font:inherit;font-size:.88rem;background:rgba(248,250,252,.1);border:1px solid rgba(248,250,252,.2);border-radius:8px;padding:7px 12px;text-decoration:none;cursor:pointer}
.haut a:hover,.haut button:hover{background:rgba(248,250,252,.2)}
.filtre{background:#fff;border-bottom:1px solid var(--bord);padding:12px 0}
.filtre .cadre{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
.filtre a.p{padding:7px 14px;border:1px solid var(--bord);border-radius:999px;text-decoration:none;color:var(--texte);font-size:.9rem;background:#fff}
.filtre a.p.on{background:var(--marine);color:#fff;border-color:var(--marine)}
.filtre form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-left:auto}
.filtre input[type=date]{padding:6px 8px;border:1px solid var(--bord);border-radius:8px;font:inherit;font-size:.88rem}
.filtre button{padding:7px 14px;border:0;border-radius:8px;background:var(--orange);color:#fff;font:inherit;font-size:.9rem;font-weight:600;cursor:pointer}
.filtre label{font-size:.85rem;color:var(--doux);display:flex;gap:6px;align-items:center}
main.cadre{padding:22px 0 60px}
.info{background:#FFF7ED;border:1px solid #FED7AA;border-radius:10px;padding:12px 16px;margin:0 0 18px;font-size:.92rem}
.periode{color:var(--doux);margin:0 0 14px;font-size:.92rem}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(165px,100%),1fr));gap:14px;margin-bottom:22px}
.kpi{background:var(--carte);border:1px solid var(--bord);border-radius:14px;padding:16px 18px}
.kpi .nom{color:var(--doux);font-size:.82rem;text-transform:uppercase;letter-spacing:.05em}
.kpi .valeur{font-size:1.7rem;font-weight:700;color:var(--marine);line-height:1.15;margin:4px 0;white-space:nowrap}
.kpi .aide{color:var(--doux);font-size:.82rem}
.delta{display:block;font-size:.8rem;margin-top:2px}.delta.bon{color:var(--bon)}.delta.mauvais{color:var(--mauvais)}.delta.neutre{color:var(--doux)}
.grille2{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(440px,100%),1fr));gap:18px;margin-bottom:22px}
section.bloc{background:var(--carte);border:1px solid var(--bord);border-radius:14px;padding:18px 20px;margin-bottom:22px}
.grille2 section.bloc{margin-bottom:0}
section.bloc h2{margin:0 0 4px;font-size:1.1rem;color:var(--marine)}
section.bloc .sous{margin:0 0 14px;color:var(--doux);font-size:.88rem}
.titre-section{margin:34px 0 12px;font-size:1.3rem;color:var(--marine);border-left:4px solid var(--orange);padding-left:12px}
.graph{width:100%;height:auto;display:block}
.graph .grille{stroke:#E2E8F0;stroke-width:1}.graph .axe{fill:#64748B;font-size:11px}
.graph .barre{fill:var(--orange)}.graph .courbe{fill:none;stroke:var(--marine);stroke-width:2;stroke-linejoin:round}
.legende{display:flex;gap:18px;font-size:.82rem;color:var(--doux);margin-top:6px}
.legende i{display:inline-block;width:12px;height:12px;border-radius:3px;margin-right:6px;vertical-align:-1px}
ul.barres{list-style:none;margin:0;padding:0;display:grid;gap:9px}
ul.barres li{display:grid;grid-template-columns:minmax(120px,42%) 1fr auto;gap:10px;align-items:center;font-size:.9rem}
ul.barres .lib small{display:block;color:var(--doux);font-size:.76rem}
ul.barres .piste{background:#F1F5F9;border-radius:999px;height:10px;overflow:hidden}
ul.barres .rempli{display:block;height:100%;background:var(--orange);border-radius:999px}
ul.barres .val{font-variant-numeric:tabular-nums;font-weight:600;color:var(--marine)}
table{width:100%;border-collapse:collapse;font-size:.88rem}
th,td{padding:8px 10px;border-bottom:1px solid var(--bord);text-align:left;vertical-align:top}
th{color:var(--doux);font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;white-space:nowrap}
td.n,th.n{text-align:right;font-variant-numeric:tabular-nums}
.defiler{overflow-x:auto}
.vide{color:var(--doux);margin:6px 0;font-size:.9rem}
.pistes{display:grid;gap:12px}
.piste-carte{background:#fff;border:1px solid var(--bord);border-left:5px solid #94A3B8;border-radius:10px;padding:12px 16px}
.piste-carte.important{border-left-color:var(--mauvais)}.piste-carte.conseil{border-left-color:var(--orange)}.piste-carte.info{border-left-color:#3B82F6}
.piste-carte strong{display:block;color:var(--marine)}.piste-carte p{margin:4px 0 0;color:var(--texte);font-size:.92rem}
.etiquette{display:inline-block;font-size:.7rem;text-transform:uppercase;letter-spacing:.06em;padding:1px 8px;border-radius:999px;background:#F1F5F9;color:var(--doux);margin-bottom:4px}
.empile{display:flex;height:16px;border-radius:999px;overflow:hidden;background:#F1F5F9;margin:8px 0}
.empile span{display:block;height:100%}
.puces{display:flex;gap:16px;flex-wrap:wrap;font-size:.88rem}
.puces i{display:inline-block;width:11px;height:11px;border-radius:50%;margin-right:6px}
details.aide{background:#fff;border:1px solid var(--bord);border-radius:10px;padding:10px 16px;margin-bottom:18px;font-size:.9rem}
details.aide summary{cursor:pointer;font-weight:600;color:var(--marine)}
details.aide dt{font-weight:600;margin-top:8px}details.aide dd{margin:0;color:var(--doux)}
.outils label{display:block;font-size:.82rem;color:var(--doux);margin:10px 0 4px}
.outils select,.outils input[type=text]{width:100%;padding:9px 10px;border:1px solid #CBD5E1;border-radius:8px;font:inherit}
.outils .lien{margin-top:12px;padding:10px 12px;background:#F1F5F9;border-radius:8px;word-break:break-all;font-family:Consolas,monospace;font-size:.82rem}
.outils button,.bouton{margin-top:10px;padding:9px 16px;border:0;border-radius:8px;background:var(--orange);color:#fff;font:inherit;font-weight:600;cursor:pointer;text-decoration:none;display:inline-block}
.bouton.clair{background:#fff;color:var(--marine);border:1px solid var(--bord)}
.pied{color:var(--doux);font-size:.82rem;margin-top:30px}
@media(max-width:640px){ul.barres li{grid-template-columns:1fr auto}ul.barres .piste{grid-column:1/-1;order:3}.kpi .valeur{font-size:1.5rem}.filtre form{margin-left:0}}
@media print{.haut nav,.filtre,.outils,.nonimprime{display:none!important}body{background:#fff}section.bloc,.kpi{break-inside:avoid}}
</style>
</head>
<body>
<header class="haut"><div class="cadre">
<h1>Fréquentation du site<small>Groupe MSIS-CI · msis-ci.com · mesure anonyme, sans cookie</small></h1>
<nav>
<a href="export.php?<?= h($parametresExport) ?>&amp;format=json">Exporter (JSON)</a>
<a href="export.php?<?= h($parametresExport) ?>&amp;format=csv&amp;table=jours">Exporter (CSV)</a>
<a href="../Confidentialite.html?mesure=non" title="Les visites faites depuis cet appareil ne seront plus comptées">Ne pas me compter</a>
<form method="post" action="tableau.php" style="margin:0"><input type="hidden" name="action" value="deconnexion"><button type="submit">Se déconnecter</button></form>
</nav>
</div></header>

<div class="filtre"><div class="cadre">
<?php
$liens = array('7' => '7 jours', '30' => '30 jours', '90' => '90 jours', 'mois' => 'Ce mois', 'mois-1' => 'Mois dernier');
foreach ($liens as $cle => $nom) {
    echo '<a class="p' . ($choix === (string) $cle ? ' on' : '') . '" href="tableau.php?p=' . h($cle) . ($tests ? '&amp;tests=1' : '') . '">' . h($nom) . '</a>';
}
?>
<form method="get" action="tableau.php">
<input type="hidden" name="p" value="perso">
<input type="date" name="du" value="<?= h($du) ?>" aria-label="Du"> <span>→</span> <input type="date" name="au" value="<?= h($au) ?>" aria-label="Au">
<label><input type="checkbox" name="tests" value="1"<?= $tests ? ' checked' : '' ?>> avec les essais</label>
<button type="submit">Afficher</button>
</form>
</div></div>

<main class="cadre">
<?php if ($demo) { ?><div class="info" style="background:#FEF2F2;border-color:#FECACA"><strong>DONNÉES DE DÉMONSTRATION</strong> : ces chiffres sont fictifs, fabriqués pour montrer le fonctionnement du tableau. Ils ne correspondent à aucune vraie visite.</div><?php } ?>
<?php if ($message !== '') { ?><div class="info"><?= h($message) ?></div><?php } ?>
<p class="periode">Du <strong><?= h(date_longue($du)) ?></strong> au <strong><?= h(date_longue($au)) ?></strong> (<?= (int) $agg['periode']['jours'] ?> jours)<?= $t['dernier_evenement'] !== '' ? ' · dernière mesure reçue le ' . h(date_longue(substr($t['dernier_evenement'], 0, 10)) . ' à ' . substr($t['dernier_evenement'], 11, 5)) . ' (heure d’Abidjan)' : '' ?><?= $tests ? ' · <strong>essais inclus</strong>' : '' ?></p>

<?php if ($sansDonnees) { ?>
<div class="info"><strong>Aucune visite enregistrée sur cette période.</strong> Si le site vient d’être mis en ligne, les premières visites apparaissent ici dans la minute qui suit. Les visites de robots, les navigateurs qui envoient « Ne pas me suivre » et les appareils exclus ne sont pas comptés.</div>
<?php } ?>

<details class="aide"><summary>Comment lire ces chiffres ?</summary>
<dl>
<dt>Visiteur</dt><dd>Un appareil qui a ouvert le site dans la journée. Aucun cookie : un visiteur qui revient un autre jour est compté de nouveau, et deux personnes qui partagent la même connexion et le même modèle de téléphone peuvent être comptées pour une seule. Le chiffre est donc une estimation prudente.</dd>
<dt>Visite</dt><dd>Une série de pages consultées par un visiteur. Elle se termine après 30 minutes sans action.</dd>
<dt>Rebond</dt><dd>Visite d’une seule page, de moins de 15 secondes, sans aucun clic. Plus ce chiffre est bas, mieux c’est.</dd>
<dt>Contact</dt><dd>Visite pendant laquelle la personne a cliqué sur WhatsApp, sur le téléphone, sur l’e-mail ou a envoyé un formulaire. C’est la mesure la plus importante : elle montre ce qui conduit à une demande.</dd>
<dt>Lecture</dt><dd>Part de la page que le visiteur a fait défiler en moyenne (100 % = jusqu’en bas).</dd>
</dl></details>

<div class="kpis">
<div class="kpi"><div class="nom">Visiteurs</div><div class="valeur"><?= nb($t['visiteurs']) ?></div><?= badge($t['visiteurs'], $tp['visiteurs']) ?></div>
<div class="kpi"><div class="nom">Visites</div><div class="valeur"><?= nb($t['visites']) ?></div><?= badge($t['visites'], $tp['visites']) ?></div>
<div class="kpi"><div class="nom">Pages consultées</div><div class="valeur"><?= nb($t['pages_vues']) ?></div><?= badge($t['pages_vues'], $tp['pages_vues']) ?><div class="aide"><?= str_replace('.', ',', (string) $t['pages_par_visite']) ?> par visite</div></div>
<div class="kpi"><div class="nom">Visites avec contact</div><div class="valeur"><?= nb($t['visites_avec_contact']) ?></div><?= badge($t['visites_avec_contact'], $tp['visites_avec_contact']) ?><div class="aide"><?= pct($t['taux_contact']) ?> des visites</div></div>
<div class="kpi"><div class="nom">Durée d’une visite</div><div class="valeur"><?= h(suivi_duree($t['duree_visite_moyenne_s'])) ?></div><div class="aide">temps passé par page : <?= h(suivi_duree($t['temps_page_moyen_s'])) ?></div></div>
<div class="kpi"><div class="nom">Taux de rebond</div><div class="valeur"><?= $t['visites'] > 0 ? pct($t['taux_rebond']) : '—' ?></div><?= badge($t['taux_rebond'], $tp['taux_rebond'], -1) ?><div class="aide">lecture moyenne : <?= $t['defilement_moyen'] > 0 ? pct($t['defilement_moyen']) : '—' ?></div></div>
</div>

<section class="bloc">
<h2>Visites par jour</h2>
<p class="sous">Les barres montrent les visites, la ligne les pages consultées.</p>
<?= svg_jours($agg['par_jour']) ?>
<div class="legende"><span><i style="background:var(--orange)"></i>Visites</span><span><i style="background:var(--marine)"></i>Pages consultées</span></div>
</section>

<h2 class="titre-section">Ce que vos visiteurs recherchent</h2>
<div class="grille2">
<section class="bloc"><h2>Pages les plus consultées</h2><p class="sous">Nombre de fois où chaque page a été ouverte.</p>
<?php
$lignes = array();
foreach (array_slice($agg['pages'], 0, 10) as $p) {
    $lignes[] = array($p['nom'], $p['vues'], $p['visiteurs'] . ' visiteur(s)');
}
echo liste_barres($lignes, '');
?></section>
<section class="bloc"><h2>Rubriques les plus lues</h2><p class="sous">Titres restés à l’écran plus d’une seconde.</p>
<?php
$lignes = array();
foreach (array_slice($agg['sections'], 0, 10) as $s) {
    $lignes[] = array($s['section'], $s['n'], $s['nom_page']);
}
echo liste_barres($lignes, '');
?></section>
<section class="bloc"><h2>Boutons et liens les plus utilisés</h2><p class="sous">Ce sur quoi les visiteurs cliquent.</p>
<?php if (count($agg['clics'])) { ?>
<div class="defiler"><table><thead><tr><th>Type</th><th>Libellé</th><th>Page</th><th class="n">Clics</th></tr></thead><tbody>
<?php foreach (array_slice($agg['clics'], 0, 12) as $c) { ?>
<tr><td><?= h(isset($libellesClics[$c['cat']]) ? $libellesClics[$c['cat']] : $c['cat']) ?></td><td><?= h($c['nom'] !== '' ? $c['nom'] : $c['cible']) ?></td><td><?= h(suivi_nom_page($c['page'])) ?></td><td class="n"><?= nb($c['n']) ?></td></tr>
<?php } ?>
</tbody></table></div>
<?php } else { ?><p class="vide">Pas encore de données.</p><?php } ?>
</section>
<section class="bloc"><h2>Documents ouverts et mots recherchés</h2><p class="sous">Brochures et PDF ouverts ; termes saisis dans la recherche du site (si elle est activée).</p>
<?php
$lignes = array();
foreach ($agg['telechargements'] as $fichier => $n) {
    $lignes[] = array($fichier, $n);
}
echo liste_barres($lignes, '');
if (count($agg['recherches'])) {
    $lignes = array();
    foreach ($agg['recherches'] as $mot => $n) {
        $lignes[] = array($mot, $n);
    }
    echo '<h3 style="margin:16px 0 8px;font-size:.95rem">Mots recherchés</h3>' . liste_barres($lignes, '');
}
?></section>
</div>

<h2 class="titre-section">Comment ils vous trouvent</h2>
<div class="grille2">
<section class="bloc"><h2>Sources des visites</h2><p class="sous">D’où viennent les visiteurs, et combien finissent par vous contacter.</p>
<?php if (count($agg['sources'])) { ?>
<div class="defiler"><table><thead><tr><th>Source</th><th class="n">Visites</th><th class="n">Part</th><th class="n">Avec contact</th></tr></thead><tbody>
<?php foreach ($agg['sources'] as $s) { ?>
<tr><td><?= h($s['source']) ?></td><td class="n"><?= nb($s['visites']) ?></td><td class="n"><?= pct(suivi_taux($s['visites'], $visitesTotal)) ?></td><td class="n"><?= nb($s['contacts']) ?> (<?= pct(suivi_taux($s['contacts'], $s['visites'])) ?>)</td></tr>
<?php } ?>
</tbody></table></div>
<?php } else { ?><p class="vide">Pas encore de données.</p><?php } ?>
</section>
<section class="bloc"><h2>Liens suivis (campagnes)</h2><p class="sous">Visites arrivées par un lien créé avec le générateur ci-dessous (source / support / campagne).</p>
<?php
$lignes = array();
foreach ($agg['campagnes'] as $nom => $n) {
    $lignes[] = array($nom, $n);
}
echo liste_barres($lignes, 'visite(s)');
?></section>
<section class="bloc"><h2>Appareils et langues</h2><p class="sous">Sur quoi le site est consulté.</p>
<?php
$couleurs = array('mobile' => '#EF7618', 'ordinateur' => '#0F172A', 'tablette' => '#3B82F6', 'inconnu' => '#94A3B8');
$noms = array('mobile' => 'Téléphone', 'ordinateur' => 'Ordinateur', 'tablette' => 'Tablette', 'inconnu' => 'Inconnu');
$totalApp = max(1, array_sum($agg['appareils']));
if (count($agg['appareils'])) {
    echo '<div class="empile">';
    foreach ($agg['appareils'] as $k => $n) {
        echo '<span style="width:' . round(100 * $n / $totalApp, 1) . '%;background:' . (isset($couleurs[$k]) ? $couleurs[$k] : '#94A3B8') . '" title="' . h((isset($noms[$k]) ? $noms[$k] : $k) . ' : ' . $n) . '"></span>';
    }
    echo '</div><div class="puces">';
    foreach ($agg['appareils'] as $k => $n) {
        echo '<span><i style="background:' . (isset($couleurs[$k]) ? $couleurs[$k] : '#94A3B8') . '"></i>' . h(isset($noms[$k]) ? $noms[$k] : $k) . ' ' . pct(suivi_taux($n, $totalApp)) . '</span>';
    }
    echo '</div>';
} else {
    echo '<p class="vide">Pas encore de données.</p>';
}
if (count($agg['langues'])) {
    $parts = array();
    foreach ($agg['langues'] as $l => $n) {
        $parts[] = strtoupper($l) . ' ' . $n;
    }
    echo '<p class="sous" style="margin-top:14px">Langue du navigateur : ' . h(implode(' · ', $parts)) . '</p>';
}
?></section>
<section class="bloc"><h2>Pages d’entrée et de sortie</h2><p class="sous">Par où les visites commencent et finissent.</p>
<?php
$lignes = array();
foreach ($agg['entrees'] as $p => $n) {
    $lignes[] = array(suivi_nom_page($p), $n, 'entrée');
}
echo liste_barres(array_slice($lignes, 0, 5), 'visite(s)');
$lignes = array();
foreach ($agg['sorties'] as $p => $n) {
    $lignes[] = array(suivi_nom_page($p), $n, 'sortie');
}
echo '<div style="height:12px"></div>' . liste_barres(array_slice($lignes, 0, 5), 'visite(s)');
?></section>
</div>

<h2 class="titre-section">Quand ils viennent</h2>
<div class="grille2">
<section class="bloc"><h2>Pages consultées par heure</h2><p class="sous">Heure d’Abidjan : le bon moment pour publier un statut WhatsApp ou un flyer numérique.</p>
<?= svg_colonnes($agg['heures'], $heuresEtiq, 'Pages consultées par heure de la journée', 3) ?></section>
<section class="bloc"><h2>Pages consultées par jour de la semaine</h2><p class="sous">Les jours les plus actifs.</p>
<?= svg_colonnes($agg['jours_semaine'], $jourSemaine, 'Pages consultées par jour de la semaine', 1) ?></section>
</div>

<h2 class="titre-section">Contacts et formulaires</h2>
<div class="grille2">
<section class="bloc"><h2>Demandes de contact</h2><p class="sous"><?= nb($sommeContacts) ?> action(s) de contact sur la période.</p>
<?php
echo liste_barres(array(
    array('Clics WhatsApp', $agg['contacts']['whatsapp']),
    array('Appels (clic sur le téléphone)', $agg['contacts']['tel']),
    array('E-mails (clic sur l’adresse)', $agg['contacts']['mail']),
    array('Formulaires envoyés', $agg['contacts']['formulaire']),
), '');
?></section>
<section class="bloc"><h2>Formulaires</h2><p class="sous">Commencés, envoyés, et part abandonnée.</p>
<?php if (count($agg['formulaires'])) { ?>
<div class="defiler"><table><thead><tr><th>Formulaire</th><th class="n">Commencés</th><th class="n">Envoyés</th><th class="n">Abandon</th></tr></thead><tbody>
<?php foreach ($agg['formulaires'] as $f) { ?>
<tr><td><?= h($f['nom']) ?></td><td class="n"><?= nb($f['debuts']) ?></td><td class="n"><?= nb($f['envois']) ?></td><td class="n"><?= $f['debuts'] > 0 ? pct(max(0, 100 - suivi_taux($f['envois'], $f['debuts']))) : '—' ?></td></tr>
<?php } ?>
</tbody></table></div>
<?php } else { ?><p class="vide">Pas encore de données.</p><?php } ?>
</section>
</div>

<section class="bloc" style="margin-top:22px"><h2>Chaque page en détail</h2><p class="sous">Temps moyen de lecture, part de la page parcourue, entrées, sorties et contacts obtenus.</p>
<?php if (count($agg['pages'])) { ?>
<div class="defiler"><table><thead><tr><th>Page</th><th class="n">Vues</th><th class="n">Visiteurs</th><th class="n">Temps moyen</th><th class="n">Lecture</th><th class="n">Entrées</th><th class="n">Sorties</th><th class="n">Contacts</th></tr></thead><tbody>
<?php foreach ($agg['pages'] as $p) { ?>
<tr><td><?= h($p['nom']) ?><br><small style="color:var(--doux)"><?= h($p['page']) ?></small></td><td class="n"><?= nb($p['vues']) ?></td><td class="n"><?= nb($p['visiteurs']) ?></td><td class="n"><?= h($p['temps_moyen_s'] !== null ? suivi_duree($p['temps_moyen_s']) : '—') ?></td><td class="n"><?= $p['defilement_moyen'] !== null ? pct($p['defilement_moyen']) : '—' ?></td><td class="n"><?= nb($p['entrees']) ?></td><td class="n"><?= nb($p['sorties']) ?></td><td class="n"><?= nb($p['contacts']) ?></td></tr>
<?php } ?>
</tbody></table></div>
<?php } else { ?><p class="vide">Pas encore de données.</p><?php } ?>
</section>

<h2 class="titre-section">Pistes d’amélioration</h2>
<section class="bloc">
<p class="sous">Déduites automatiquement des chiffres ci-dessus ; chaque piste cite les valeurs sur lesquelles elle repose. Plus il y a de visites, plus elles sont fiables.</p>
<div class="pistes">
<?php
$etiquettes = array('important' => 'À traiter en priorité', 'conseil' => 'Conseil', 'info' => 'À savoir');
if (count($suggestions)) {
    foreach ($suggestions as $s) {
        echo '<div class="piste-carte ' . h($s['niveau']) . '"><span class="etiquette">' . h($etiquettes[$s['niveau']]) . '</span><strong>' . h($s['titre']) . '</strong><p>' . h($s['detail']) . '</p></div>';
    }
} else {
    echo '<p class="vide">Rien à signaler pour le moment.</p>';
}
?>
</div></section>

<h2 class="titre-section nonimprime">Outils</h2>
<div class="grille2 nonimprime">
<section class="bloc outils"><h2>Créer un lien suivi</h2><p class="sous">À mettre sur chaque flyer (QR code), statut WhatsApp ou publication : le tableau dira alors lequel amène des visiteurs.</p>
<label for="lsPage">Page à ouvrir</label>
<select id="lsPage">
<?php foreach (suivi_pages_connues() as $chemin => $nom) { if ($chemin === '/Confidentialite.html') { continue; } echo '<option value="' . h($chemin) . '">' . h($nom) . '</option>'; } ?>
</select>
<label for="lsSource">Où sera diffusé le lien ?</label>
<select id="lsSource">
<option value="whatsapp">WhatsApp</option><option value="facebook">Facebook</option><option value="instagram">Instagram</option><option value="linkedin">LinkedIn</option>
<option value="tiktok">TikTok</option><option value="flyer">Flyer ou affiche (QR code)</option><option value="sms">SMS</option><option value="email">E-mail</option><option value="autre">Autre</option>
</select>
<label for="lsMedium">Type de publication</label>
<select id="lsMedium"><option value="statut">Statut / story</option><option value="publication">Publication</option><option value="message">Message envoyé</option><option value="qr">QR code imprimé</option><option value="bio">Lien dans le profil</option></select>
<label for="lsCampagne">Nom de la campagne</label>
<input type="text" id="lsCampagne" placeholder="ex. formations-novembre-2026" value="">
<div class="lien" id="lsResultat" aria-live="polite"></div>
<button type="button" id="lsCopier">Copier le lien</button>
</section>
<section class="bloc outils"><h2>Exports et entretien</h2><p class="sous">Les données restent sur votre hébergement, rangées hors du dossier public. Conservation : 24 mois, puis effacement automatique.</p>
<p><a class="bouton clair" href="export.php?<?= h($parametresExport) ?>&amp;format=csv&amp;table=pages">Pages (CSV)</a>
<a class="bouton clair" href="export.php?<?= h($parametresExport) ?>&amp;format=csv&amp;table=sources">Sources (CSV)</a>
<a class="bouton clair" href="export.php?<?= h($parametresExport) ?>&amp;format=csv&amp;table=clics">Clics (CSV)</a>
<a class="bouton clair" href="export.php?<?= h($parametresExport) ?>&amp;format=csv&amp;table=sections">Rubriques (CSV)</a></p>
<form method="post" action="tableau.php?<?= h(http_build_query(array('p' => $choix, 'du' => $du, 'au' => $au, 'tests' => $tests ? 1 : 0))) ?>" onsubmit="return confirm('Effacer définitivement tous les événements marqués « essai » ?');">
<input type="hidden" name="action" value="purger_essais"><input type="hidden" name="jeton" value="<?= h($jeton) ?>">
<button type="submit" class="bouton clair">Effacer les essais</button>
</form>
<p class="sous" style="margin-top:14px">Pour qu’un essai ne compte pas, ouvrez le site avec <code>?mesure=essai</code> à la fin de l’adresse.</p>
</section>
</div>

<p class="pied">Mesure anonyme : aucun cookie, aucune adresse IP conservée, aucun contenu de formulaire lu. Les visiteurs sont informés sur la page « Confidentialité ».</p>
</main>

<script>
(function () {
    var base = <?= json_encode((isset($_SERVER['HTTP_HOST']) && PHP_SAPI !== 'cli-server' ? 'https://' . $_SERVER['HTTP_HOST'] : (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'https://msis-ci.com'))) ?>;
    var champs = ['lsPage', 'lsSource', 'lsMedium', 'lsCampagne'];
    function bout(v) { return String(v || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, ''); }
    function maj() {
        var page = document.getElementById('lsPage').value;
        var camp = bout(document.getElementById('lsCampagne').value) || 'campagne';
        var lien = base + (page === '/' ? '/' : page) + '?utm_source=' + bout(document.getElementById('lsSource').value)
            + '&utm_medium=' + bout(document.getElementById('lsMedium').value) + '&utm_campaign=' + camp;
        document.getElementById('lsResultat').textContent = lien;
    }
    for (var i = 0; i < champs.length; i++) {
        var el = document.getElementById(champs[i]);
        el.addEventListener('input', maj);
        el.addEventListener('change', maj);
    }
    document.getElementById('lsCopier').addEventListener('click', function () {
        var b = this, texte = document.getElementById('lsResultat').textContent;
        var ok = function () { b.textContent = 'Lien copié ✔'; setTimeout(function () { b.textContent = 'Copier le lien'; }, 2000); };
        if (navigator.clipboard && navigator.clipboard.writeText) { navigator.clipboard.writeText(texte).then(ok); return; }
        var z = document.createElement('textarea'); z.value = texte; document.body.appendChild(z); z.select();
        try { document.execCommand('copy'); ok(); } catch (e) { /* copie manuelle */ }
        document.body.removeChild(z);
    });
    maj();
})();
</script>
</body>
</html>
