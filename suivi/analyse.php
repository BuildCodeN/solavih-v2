<?php
/*
 * Mesure d'audience du site Groupe MSIS-CI : calculs du tableau de bord, de l'export et des pistes d'amélioration.
 * Chargé uniquement par tableau.php et export.php (jamais à chaque visite). Voir lib.php pour le stockage.
 */

require_once __DIR__ . '/lib.php';

define('SUIVI_VISITE_INACTIVITE', 1800); /* une visite se termine après 30 minutes sans action */

/* Noms lisibles des pages du site (le titre envoyé par le navigateur sert de repli). */
function suivi_pages_connues()
{
    return array(
        '/' => 'Accueil',
        '/Nous.html' => 'À propos',
        '/ServcINFOR.html' => 'Informatique & solutions numériques',
        '/ServcELECT.html' => 'Sécurité & équipements électroniques',
        '/ServcIMPRI.html' => 'Communication visuelle & imprimerie',
        '/ServcMATIN.html' => 'Matériel informatique',
        '/ServcFBURO.html' => 'Mobilier & fournitures de bureau',
        '/ServcMATCO.html' => 'Matériaux de construction & ménagement',
        '/ServcFPROF.html' => 'Formations professionnelles',
        '/Inscription.html' => 'Inscription GSN',
        '/Partenaire-GSN.html' => 'Devenir partenaire GSN',
        '/Partenaires.html' => 'Nos partenaires',
        '/Partenaire-Commercial.html' => 'Devenir partenaire commercial',
        '/Carriere.html' => 'Carrières',
        '/Candidature-Spontanee.html' => 'Candidature spontanée',
        '/Confidentialite.html' => 'Confidentialité',
    );
}

function suivi_nom_page($page, $titre = '')
{
    $connues = suivi_pages_connues();
    if (isset($connues[$page])) {
        return $connues[$page];
    }
    return $titre !== '' ? $titre : $page;
}

/* ---------- Lecture ---------- */

function suivi_lire_jour($jour)
{
    $chemin = suivi_fichier_jour($jour);
    if (!is_file($chemin)) {
        return array();
    }
    $lignes = @file($chemin, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lignes) {
        return array();
    }
    $sortie = array();
    foreach ($lignes as $ligne) {
        if ($ligne === '' || $ligne[0] !== '{') {
            continue;
        }
        $e = json_decode($ligne, true);
        if (is_array($e) && isset($e['e'], $e['p'])) {
            $sortie[] = $e;
        }
    }
    return $sortie;
}

function suivi_liste_jours($du, $au)
{
    $jours = array();
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $du) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $au)) {
        return $jours;
    }
    $debut = strtotime($du . ' 00:00:00 UTC');
    $fin = strtotime($au . ' 00:00:00 UTC');
    if ($debut === false || $fin === false || $fin < $debut || ($fin - $debut) > 800 * 86400) {
        return $jours;
    }
    for ($t = $debut; $t <= $fin; $t += 86400) {
        $jours[] = gmdate('Y-m-d', $t);
    }
    return $jours;
}

/* Efface les événements marqués « essai » dans une période (essais faits à la mise en service, par exemple). */
function suivi_purger_essais($du, $au)
{
    $retires = 0;
    foreach (suivi_liste_jours($du, $au) as $jour) {
        $chemin = suivi_fichier_jour($jour);
        if (!is_file($chemin)) {
            continue;
        }
        $lignes = @file($chemin, FILE_IGNORE_NEW_LINES);
        if (!$lignes) {
            continue;
        }
        $gardes = array();
        foreach ($lignes as $ligne) {
            if ($ligne !== '' && $ligne[0] === '{') {
                $e = json_decode($ligne, true);
                if (is_array($e) && !empty($e['x'])) {
                    $retires++;
                    continue;
                }
            }
            $gardes[] = $ligne;
        }
        if (count($gardes) !== count($lignes)) {
            @file_put_contents($chemin, implode("\n", $gardes) . "\n", LOCK_EX);
        }
    }
    return $retires;
}

/* ---------- Calculs ---------- */

function suivi_canal($referent, $utmSource)
{
    if ($utmSource !== '') {
        $s = strtolower($utmSource);
        $noms = array('whatsapp' => 'WhatsApp', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok',
            'youtube' => 'YouTube', 'google' => 'Google', 'flyer' => 'Flyer / affiche (QR code)', 'qr' => 'Flyer / affiche (QR code)', 'sms' => 'SMS', 'mail' => 'E-mail', 'email' => 'E-mail');
        foreach ($noms as $motif => $nom) {
            if (strpos($s, $motif) !== false) {
                return $nom;
            }
        }
        return 'Campagne : ' . $utmSource;
    }
    $r = strtolower((string) $referent);
    if ($r === '') {
        return 'Accès direct (lien partagé, WhatsApp, SMS, favoris)';
    }
    $table = array('google.' => 'Google', 'bing.' => 'Bing', 'duckduckgo' => 'DuckDuckGo', 'yahoo.' => 'Yahoo', 'facebook.' => 'Facebook', 'fb.com' => 'Facebook',
        'instagram.' => 'Instagram', 'linkedin.' => 'LinkedIn', 'tiktok.' => 'TikTok', 'youtube.' => 'YouTube', 't.co' => 'X (Twitter)', 'twitter.' => 'X (Twitter)',
        'x.com' => 'X (Twitter)', 'wa.me' => 'WhatsApp', 'whatsapp.' => 'WhatsApp', 'github.io' => 'Ancienne adresse (GitHub)', 'isca-adjame' => 'Site ISCA');
    foreach ($table as $motif => $nom) {
        if (strpos($r, $motif) !== false) {
            return $nom;
        }
    }
    return 'Site : ' . $r;
}

function suivi_ajouter_compteur(&$tableau, $cle, $n = 1)
{
    $tableau[$cle] = (isset($tableau[$cle]) ? $tableau[$cle] : 0) + $n;
}

function suivi_taux($a, $b)
{
    return $b > 0 ? (int) round(100 * $a / $b) : 0;
}

function suivi_variation($actuel, $avant)
{
    return $avant > 0 ? (int) round(100 * ($actuel - $avant) / $avant) : null;
}

function suivi_trier_top($tableau, $limite)
{
    arsort($tableau);
    return array_slice($tableau, 0, $limite, true);
}

/* Agrège les événements d'une période : visiteurs, visites, pages, contenus consultés, actions, sources, appareils.
   Un « visiteur » est compté une fois par jour (rien ne le suit d'un jour à l'autre) ; une « visite » s'arrête après
   30 minutes sans action. */
function suivi_agreger($du, $au, $avecTests = false)
{
    $jours = suivi_liste_jours($du, $au);
    $agg = array(
        'periode' => array('du' => $du, 'au' => $au, 'jours' => count($jours)),
        'total' => array('visiteurs' => 0, 'visites' => 0, 'pages_vues' => 0, 'pages_par_visite' => 0, 'rebonds' => 0, 'taux_rebond' => 0,
            'visites_avec_contact' => 0, 'taux_contact' => 0, 'duree_visite_moyenne_s' => 0, 'temps_page_moyen_s' => 0, 'defilement_moyen' => 0,
            'evenements' => 0, 'dernier_evenement' => ''),
        'par_jour' => array(), 'pages' => array(), 'sections' => array(), 'clics' => array(),
        'contacts' => array('whatsapp' => 0, 'tel' => 0, 'mail' => 0, 'formulaire' => 0),
        'telechargements' => array(), 'formulaires' => array(), 'sources' => array(), 'campagnes' => array(), 'appareils' => array(),
        'langues' => array(), 'heures' => array_fill(0, 24, 0), 'jours_semaine' => array_fill(0, 7, 0),
        'entrees' => array(), 'sorties' => array(), 'recherches' => array(),
    );
    $pages = array();
    $sections = array();
    $clics = array();
    $formulaires = array();
    $sources = array();
    $dureeSomme = 0;
    $dureeN = 0;
    $tempsSomme = 0;
    $tempsN = 0;
    $defilSomme = 0;
    $defilN = 0;
    $dernierTs = 0;

    foreach ($jours as $jour) {
        $visiteursJ = array();
        $visitesJ = array();
        $vuesJ = 0;
        $contactsJ = 0;
        $visites = array(); /* clé visiteur|numéro de visite => détail */
        $suivi = array();   /* visiteur => dernière activité et numéro de visite en cours */
        $vuesK = array();   /* identifiant de page => durée et défilement */
        $anonyme = 0;

        foreach (suivi_lire_jour($jour) as $e) {
            if (!$avecTests && !empty($e['x'])) {
                continue;
            }
            $agg['total']['evenements']++;
            $type = $e['e'];
            $p = $e['p'];
            $v = isset($e['v']) ? $e['v'] : 'inconnu';
            $ts = isset($e['t']) ? (int) strtotime($e['t']) : 0;
            if ($ts > $dernierTs) {
                $dernierTs = $ts;
            }

            if (!isset($suivi[$v])) {
                $suivi[$v] = array('ts' => $ts, 'n' => 1);
            } elseif ($type !== 'temps') {
                if ($ts - $suivi[$v]['ts'] > SUIVI_VISITE_INACTIVITE) {
                    $suivi[$v]['n']++;
                }
                $suivi[$v]['ts'] = $ts;
            }
            $cle = $v . '|' . $suivi[$v]['n'];
            if (!isset($visites[$cle])) {
                $visites[$cle] = array('vues' => 0, 'interactions' => 0, 'sec' => 0, 'chrono' => false, 'contact' => false, 'entree' => '', 'sortie' => '',
                    'source' => '', 'appareil' => '');
            }

            if ($type === 'vue') {
                $vuesJ++;
                $visiteursJ[$v] = true;
                $visitesJ[$cle] = true;
                $visites[$cle]['vues']++;
                $visites[$cle]['sortie'] = $p;
                if ($visites[$cle]['entree'] === '') {
                    $visites[$cle]['entree'] = $p;
                    $visites[$cle]['source'] = suivi_canal(isset($e['r']) ? $e['r'] : '', isset($e['us']) ? $e['us'] : '');
                    $visites[$cle]['appareil'] = isset($e['d']) ? $e['d'] : 'inconnu';
                    if (!empty($e['uc'])) {
                        suivi_ajouter_compteur($agg['campagnes'], (isset($e['us']) ? $e['us'] : '?') . ' / ' . (isset($e['um']) ? $e['um'] : '?') . ' / ' . $e['uc']);
                    }
                    if (!empty($e['l'])) {
                        suivi_ajouter_compteur($agg['langues'], strtolower($e['l']));
                    }
                }
                if (!isset($pages[$p])) {
                    $pages[$p] = array('titre' => '', 'vues' => 0, 'visiteurs' => array(), 'temps' => 0, 'temps_n' => 0, 'defil' => 0, 'defil_n' => 0,
                        'entrees' => 0, 'rebonds' => 0, 'sorties' => 0, 'contacts' => 0);
                }
                $pages[$p]['vues']++;
                $pages[$p]['visiteurs'][$v] = true;
                if (!empty($e['ti'])) {
                    $pages[$p]['titre'] = $e['ti'];
                }
                if ($ts > 0) {
                    $agg['heures'][(int) gmdate('G', $ts)]++;
                    $agg['jours_semaine'][(int) gmdate('w', $ts)]++;
                }
                $k = isset($e['k']) ? $e['k'] : 'sans' . (++$anonyme);
                $vuesK[$k] = array('p' => $p, 'sec' => 0, 'defil' => null);
            } elseif ($type === 'temps') {
                $k = isset($e['k']) ? $e['k'] : 'sans' . (++$anonyme);
                if (!isset($vuesK[$k])) {
                    $vuesK[$k] = array('p' => $p, 'sec' => 0, 'defil' => null);
                }
                if (isset($e['sec'])) {
                    $vuesK[$k]['sec'] += (int) $e['sec'];
                    $visites[$cle]['sec'] += (int) $e['sec'];
                    $visites[$cle]['chrono'] = true;
                }
                if (isset($e['defil'])) {
                    $vuesK[$k]['defil'] = max((int) $vuesK[$k]['defil'], (int) $e['defil']);
                }
            } elseif ($type === 'clic') {
                $visites[$cle]['interactions']++;
                $cat = isset($e['c']) ? $e['c'] : 'lien';
                $nom = isset($e['n']) ? $e['n'] : '';
                $cible = isset($e['cible']) ? $e['cible'] : '';
                if (in_array($cat, array('whatsapp', 'tel', 'mail'), true)) {
                    $agg['contacts'][$cat]++;
                    $visites[$cle]['contact'] = true;
                    $contactsJ++;
                    if (isset($pages[$p])) {
                        $pages[$p]['contacts']++;
                    }
                } elseif ($cat === 'pdf') {
                    suivi_ajouter_compteur($agg['telechargements'], $cible !== '' ? $cible : $nom);
                }
                $kc = $cat . '|' . $nom . '|' . $cible . '|' . $p;
                if (!isset($clics[$kc])) {
                    $clics[$kc] = array('cat' => $cat, 'nom' => $nom, 'cible' => $cible, 'page' => $p, 'n' => 0);
                }
                $clics[$kc]['n']++;
            } elseif ($type === 'form') {
                $visites[$cle]['interactions']++;
                $nom = isset($e['n']) ? $e['n'] : '?';
                $phase = isset($e['c']) ? $e['c'] : 'debut';
                if (!isset($formulaires[$nom])) {
                    $formulaires[$nom] = array('nom' => $nom, 'debuts' => 0, 'envois' => 0);
                }
                if ($phase === 'envoi') {
                    $formulaires[$nom]['envois']++;
                    $agg['contacts']['formulaire']++;
                    $visites[$cle]['contact'] = true;
                    $contactsJ++;
                    if (isset($pages[$p])) {
                        $pages[$p]['contacts']++;
                    }
                } else {
                    $formulaires[$nom]['debuts']++;
                }
            } elseif ($type === 'section') {
                $nom = isset($e['n']) ? $e['n'] : '?';
                $ks = $p . '|' . $nom;
                if (!isset($sections[$ks])) {
                    $sections[$ks] = array('page' => $p, 'section' => $nom, 'n' => 0);
                }
                $sections[$ks]['n']++;
            } elseif ($type === 'recherche') {
                if (!empty($e['n'])) {
                    suivi_ajouter_compteur($agg['recherches'], strtolower($e['n']));
                }
            }
        }

        foreach ($visites as $vis) {
            if ($vis['vues'] < 1) {
                continue;
            }
            $agg['total']['visites']++;
            $rebond = ($vis['vues'] === 1 && $vis['interactions'] === 0 && $vis['sec'] < 15);
            if ($rebond) {
                $agg['total']['rebonds']++;
            }
            if ($vis['contact']) {
                $agg['total']['visites_avec_contact']++;
            }
            if ($vis['chrono']) {
                $dureeSomme += $vis['sec'];
                $dureeN++;
            }
            if ($vis['source'] !== '') {
                if (!isset($sources[$vis['source']])) {
                    $sources[$vis['source']] = array('source' => $vis['source'], 'visites' => 0, 'contacts' => 0);
                }
                $sources[$vis['source']]['visites']++;
                if ($vis['contact']) {
                    $sources[$vis['source']]['contacts']++;
                }
                suivi_ajouter_compteur($agg['appareils'], $vis['appareil']);
            }
            if ($vis['entree'] !== '') {
                suivi_ajouter_compteur($agg['entrees'], $vis['entree']);
                if (isset($pages[$vis['entree']])) {
                    $pages[$vis['entree']]['entrees']++;
                    if ($rebond) {
                        $pages[$vis['entree']]['rebonds']++;
                    }
                }
            }
            if ($vis['sortie'] !== '') {
                suivi_ajouter_compteur($agg['sorties'], $vis['sortie']);
                if (isset($pages[$vis['sortie']])) {
                    $pages[$vis['sortie']]['sorties']++;
                }
            }
        }
        foreach ($vuesK as $vk) {
            if ($vk['sec'] > 0) {
                $tempsSomme += $vk['sec'];
                $tempsN++;
                if (isset($pages[$vk['p']])) {
                    $pages[$vk['p']]['temps'] += $vk['sec'];
                    $pages[$vk['p']]['temps_n']++;
                }
            }
            if ($vk['defil'] !== null) {
                $defilSomme += $vk['defil'];
                $defilN++;
                if (isset($pages[$vk['p']])) {
                    $pages[$vk['p']]['defil'] += $vk['defil'];
                    $pages[$vk['p']]['defil_n']++;
                }
            }
        }
        $agg['par_jour'][] = array('jour' => $jour, 'visiteurs' => count($visiteursJ), 'visites' => count($visitesJ), 'vues' => $vuesJ, 'contacts' => $contactsJ);
        $agg['total']['visiteurs'] += count($visiteursJ);
        $agg['total']['pages_vues'] += $vuesJ;
    }

    $t = &$agg['total'];
    $t['pages_par_visite'] = $t['visites'] > 0 ? round($t['pages_vues'] / $t['visites'], 1) : 0;
    $t['taux_rebond'] = suivi_taux($t['rebonds'], $t['visites']);
    $t['taux_contact'] = suivi_taux($t['visites_avec_contact'], $t['visites']);
    $t['duree_visite_moyenne_s'] = $dureeN > 0 ? (int) round($dureeSomme / $dureeN) : 0;
    $t['temps_page_moyen_s'] = $tempsN > 0 ? (int) round($tempsSomme / $tempsN) : 0;
    $t['defilement_moyen'] = $defilN > 0 ? (int) round($defilSomme / $defilN) : 0;
    $t['dernier_evenement'] = $dernierTs > 0 ? gmdate('Y-m-d\TH:i:s\Z', $dernierTs) : '';
    unset($t);

    foreach ($pages as $p => $s) {
        $agg['pages'][] = array('page' => $p, 'nom' => suivi_nom_page($p, $s['titre']), 'vues' => $s['vues'], 'visiteurs' => count($s['visiteurs']),
            'temps_moyen_s' => $s['temps_n'] > 0 ? (int) round($s['temps'] / $s['temps_n']) : null,
            'defilement_moyen' => $s['defil_n'] > 0 ? (int) round($s['defil'] / $s['defil_n']) : null,
            'entrees' => $s['entrees'], 'rebonds' => $s['rebonds'], 'sorties' => $s['sorties'], 'contacts' => $s['contacts']);
    }
    usort($agg['pages'], function ($a, $b) {
        return $b['vues'] - $a['vues'];
    });

    $trier = function ($a, $b) {
        return $b['n'] - $a['n'];
    };
    $liste = array_values($sections);
    usort($liste, $trier);
    foreach ($liste as &$l) {
        $l['nom_page'] = suivi_nom_page($l['page']);
    }
    unset($l);
    $agg['sections'] = array_slice($liste, 0, 30);
    $liste = array_values($clics);
    usort($liste, $trier);
    $agg['clics'] = array_slice($liste, 0, 40);
    $agg['formulaires'] = array_values($formulaires);
    $liste = array_values($sources);
    usort($liste, function ($a, $b) {
        return $b['visites'] - $a['visites'];
    });
    $agg['sources'] = array_slice($liste, 0, 15);
    $agg['campagnes'] = suivi_trier_top($agg['campagnes'], 15);
    $agg['telechargements'] = suivi_trier_top($agg['telechargements'], 15);
    $agg['recherches'] = suivi_trier_top($agg['recherches'], 30);
    $agg['entrees'] = suivi_trier_top($agg['entrees'], 10);
    $agg['sorties'] = suivi_trier_top($agg['sorties'], 10);
    $agg['langues'] = suivi_trier_top($agg['langues'], 6);
    return $agg;
}

/* ---------- Pistes d'amélioration ---------- */

/* Chaque piste cite les chiffres sur lesquels elle repose. $prec = période précédente (peut être null). */
function suivi_suggestions($agg, $prec)
{
    $s = array();
    $t = $agg['total'];
    $v = $t['visites'];
    if ($v < 30) {
        $s[] = array('niveau' => 'info', 'code' => 'peu-de-visites', 'titre' => 'Encore peu de visites pour conclure (' . $v . ' sur la période)',
            'detail' => 'Les tendances sont à prendre avec prudence. Ajoutez le lien suivi du générateur (en bas du tableau de bord) sur chaque flyer, chaque statut WhatsApp et chaque publication : on saura ce qui amène les visiteurs.');
    }
    if ($v >= 1) {
        $mobile = isset($agg['appareils']['mobile']) ? $agg['appareils']['mobile'] : 0;
        $partMobile = suivi_taux($mobile, $v);
        if ($v >= 20 && $partMobile >= 65) {
            $s[] = array('niveau' => 'conseil', 'code' => 'mobile', 'titre' => 'Votre public est surtout sur téléphone (' . $partMobile . ' % des visites)',
                'detail' => 'Vérifier chaque page sur un téléphone, alléger les grandes images (le chargement consomme des données mobiles) et garder le bouton WhatsApp visible sans défiler.');
        }
        $direct = 0;
        foreach ($agg['sources'] as $src) {
            if (strpos($src['source'], 'Accès direct') === 0) {
                $direct += $src['visites'];
            }
        }
        if ($v >= 20 && suivi_taux($direct, $v) >= 70) {
            $s[] = array('niveau' => 'conseil', 'code' => 'direct', 'titre' => 'On ne sait pas d\'où vient la plupart des visites (' . suivi_taux($direct, $v) . ' % en accès direct)',
                'detail' => 'Un lien partagé sur WhatsApp ou SMS apparaît toujours « en direct ». Ajoutez un lien suivi (utm_source, utm_medium, utm_campaign) à chaque support : le tableau dira alors lequel amène des visiteurs.');
        }
        if ($v >= 20 && $t['taux_rebond'] >= 60) {
            $pires = array();
            foreach ($agg['pages'] as $pg) {
                if ($pg['entrees'] >= 5 && suivi_taux($pg['rebonds'], $pg['entrees']) >= 60) {
                    $pires[] = $pg['nom'] . ' (' . suivi_taux($pg['rebonds'], $pg['entrees']) . ' %)';
                }
            }
            $s[] = array('niveau' => 'important', 'code' => 'rebond', 'titre' => 'Beaucoup de visiteurs repartent aussitôt (' . $t['taux_rebond'] . ' % des visites)',
                'detail' => 'Le haut de page doit dire en une phrase ce que vous proposez et montrer un bouton d\'action. Vérifier aussi la vitesse de chargement.'
                    . (count($pires) ? ' Pages d\'entrée les plus touchées : ' . implode(', ', array_slice($pires, 0, 4)) . '.' : ''));
        }
        if ($v >= 20 && $t['taux_contact'] < 8) {
            $s[] = array('niveau' => 'important', 'code' => 'contact', 'titre' => 'Peu de visiteurs vous contactent (' . $t['taux_contact'] . ' % des visites)',
                'detail' => 'Répéter le bouton WhatsApp au milieu et en bas de chaque page de service, avec une action simple (« Demander un devis », « Réserver ma place »).');
        }
        if ($v >= 20 && $t['temps_page_moyen_s'] > 0 && $t['temps_page_moyen_s'] < 20) {
            $s[] = array('niveau' => 'conseil', 'code' => 'lecture-rapide', 'titre' => 'Les pages sont lues très vite (' . $t['temps_page_moyen_s'] . ' s en moyenne)',
                'detail' => 'Soit le contenu n\'accroche pas, soit la page est lente. Raccourcir les textes d\'introduction et placer l\'essentiel en haut.');
        }
        if ($v >= 20 && $t['defilement_moyen'] > 0 && $t['defilement_moyen'] < 40) {
            $s[] = array('niveau' => 'conseil', 'code' => 'defilement', 'titre' => 'Les visiteurs vont rarement au bas des pages (' . $t['defilement_moyen'] . ' % en moyenne)',
                'detail' => 'Remonter les informations utiles (prix, formations, contact) dans la première moitié de chaque page.');
        }
    }
    $sansContact = array();
    foreach (array_slice($agg['pages'], 0, 8) as $pg) {
        if ($pg['vues'] >= 15 && $pg['contacts'] === 0 && !in_array($pg['page'], array('/Confidentialite.html', '/connexion.html'), true)) {
            $sansContact[] = $pg['nom'] . ' (' . $pg['vues'] . ' vues)';
        }
    }
    if (count($sansContact)) {
        $s[] = array('niveau' => 'conseil', 'code' => 'pages-sans-contact', 'titre' => 'Des pages très consultées n\'amènent aucun contact',
            'detail' => implode(' ; ', $sansContact) . '. Y ajouter un bouton d\'action bien visible (WhatsApp, devis) en haut et en bas.');
    }
    foreach ($agg['formulaires'] as $f) {
        if ($f['debuts'] >= 5 && suivi_taux($f['envois'], $f['debuts']) < 40) {
            $s[] = array('niveau' => 'important', 'code' => 'formulaire', 'titre' => 'Le formulaire « ' . $f['nom'] . ' » est commencé mais rarement envoyé (' . $f['envois'] . ' sur ' . $f['debuts'] . ')',
                'detail' => 'Réduire le nombre de champs obligatoires ou d\'étapes, et rappeler qu\'il suffit d\'envoyer un message WhatsApp pour commencer.');
        }
    }
    $pdf = array_sum($agg['telechargements']);
    if ($pdf >= 5) {
        $s[] = array('niveau' => 'info', 'code' => 'pdf', 'titre' => 'Les documents PDF intéressent (' . $pdf . ' ouvertures)',
            'detail' => 'Les mettre en avant sur la page d\'accueil et dans le menu des formations ; vérifier qu\'ils sont à jour.');
    }
    if (count($agg['recherches']) > 0) {
        $premiers = array_slice(array_keys($agg['recherches']), 0, 5);
        $s[] = array('niveau' => 'info', 'code' => 'recherches', 'titre' => 'Ce que les visiteurs cherchent',
            'detail' => 'Mots les plus saisis : ' . implode(', ', $premiers) . '. S\'ils ne trouvent pas de réponse, créer la page ou la rubrique correspondante.');
    }
    if ($v >= 50) {
        $vues = array();
        foreach ($agg['pages'] as $pg) {
            $vues[$pg['page']] = true;
        }
        $jamais = array();
        foreach (suivi_pages_connues() as $chemin => $nom) {
            if (!isset($vues[$chemin]) && $chemin !== '/Confidentialite.html') {
                $jamais[] = $nom;
            }
        }
        if (count($jamais)) {
            $s[] = array('niveau' => 'conseil', 'code' => 'pages-jamais-vues', 'titre' => 'Pages jamais consultées sur la période',
                'detail' => implode(', ', $jamais) . '. Les rendre plus visibles (lien dans le menu ou sur l\'accueil) ou les regrouper avec une page plus consultée.');
        }
    }
    $meilleure = null;
    foreach ($agg['sources'] as $src) {
        if ($src['visites'] >= 10 && strpos($src['source'], 'Accès direct') !== 0) {
            $taux = suivi_taux($src['contacts'], $src['visites']);
            if ($meilleure === null || $taux > $meilleure['taux']) {
                $meilleure = array('nom' => $src['source'], 'taux' => $taux, 'visites' => $src['visites']);
            }
        }
    }
    if ($meilleure !== null && $meilleure['taux'] > 0) {
        $s[] = array('niveau' => 'info', 'code' => 'meilleure-source', 'titre' => 'La meilleure source : ' . $meilleure['nom'] . ' (' . $meilleure['taux'] . ' % de visites avec contact)',
            'detail' => $meilleure['visites'] . ' visites viennent de cette source. La renforcer en priorité.');
    }
    if ($prec && $prec['total']['visites'] >= 10 && $t['visites'] > 0) {
        $delta = suivi_variation($t['visites'], $prec['total']['visites']);
        if ($delta !== null && $delta <= -25) {
            $s[] = array('niveau' => 'important', 'code' => 'baisse', 'titre' => 'La fréquentation baisse (' . $delta . ' % de visites par rapport à la période précédente)',
                'detail' => 'Relancer la diffusion : une publication ou un statut WhatsApp avec lien suivi, et un rappel aux contacts.');
        } elseif ($delta !== null && $delta >= 25) {
            $s[] = array('niveau' => 'info', 'code' => 'hausse', 'titre' => 'La fréquentation progresse (+' . $delta . ' % de visites par rapport à la période précédente)',
                'detail' => 'Repérer la source qui progresse (tableau « Sources ») et la renforcer.');
        }
    }
    $maxH = 0;
    $heureMax = -1;
    foreach ($agg['heures'] as $h => $n) {
        if ($n > $maxH) {
            $maxH = $n;
            $heureMax = $h;
        }
    }
    if ($maxH >= 10) {
        $s[] = array('niveau' => 'info', 'code' => 'heure', 'titre' => 'Les visites sont les plus nombreuses vers ' . $heureMax . ' h',
            'detail' => 'Publier flyers et statuts WhatsApp juste avant ce créneau pour toucher le plus de monde.');
    }
    $ordre = array('important' => 0, 'conseil' => 1, 'info' => 2);
    usort($s, function ($a, $b) use ($ordre) {
        return $ordre[$a['niveau']] - $ordre[$b['niveau']];
    });
    return $s;
}

/* Période précédente de même durée, juste avant celle demandée. */
function suivi_periode_precedente($du, $au)
{
    $debut = strtotime($du . ' 00:00:00 UTC');
    $fin = strtotime($au . ' 00:00:00 UTC');
    $jours = (int) round(($fin - $debut) / 86400) + 1;
    return array(gmdate('Y-m-d', $debut - $jours * 86400), gmdate('Y-m-d', $debut - 86400));
}

/* Formate une durée en secondes : « 2 min 05 s ». */
function suivi_duree($s)
{
    $s = (int) $s;
    if ($s <= 0) {
        return '—';
    }
    if ($s < 60) {
        return $s . ' s';
    }
    return floor($s / 60) . ' min ' . str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT) . ' s';
}
