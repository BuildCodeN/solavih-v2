/* Mesure d'audience anonyme du site Groupe MSIS-CI (voir Confidentialite.html et suivi/LISEZ-MOI.txt).
   Sans cookie ni stockage d'identifiant : le navigateur envoie des événements courts (page vue, temps passé,
   clic sur un contact, rubrique lue, formulaire commencé ou envoyé) à suivi/mesure.php. Jamais le contenu d'un
   champ, jamais d'adresse e-mail ni de numéro saisi. « Ne pas me suivre » (DNT / GPC) est respecté. */
(function () {
    "use strict";

    var HOTES = ['msis-ci.com', 'www.msis-ci.com'];
    var ADRESSE = '/suivi/mesure.php';
    var CLE_REFUS = 'msis_mesure';       /* « non » : cet appareil n'est pas compté */
    var CLE_ESSAI = 'msis_essai';        /* « 1 » : les événements sont marqués « essai » (onglet courant) */

    var hote = window.location.hostname;
    var enLocal = /^(localhost|127\.0\.0\.1|\[::1\])$/.test(hote);

    /* Lecture / écriture prudentes : le stockage peut être bloqué (navigation privée, réglages). */
    function lire(stock, cle) { try { return window[stock].getItem(cle); } catch (e) { return null; } }
    function ecrire(stock, cle, valeur) {
        try {
            if (valeur === null) { window[stock].removeItem(cle); } else { window[stock].setItem(cle, valeur); }
        } catch (e) { /* sans effet */ }
    }

    /* ---------- Réglages demandés par l'adresse (?mesure=non | oui | essai) ---------- */
    try {
        var recherche = window.location.search || '';
        var m = /[?&]mesure=(non|oui|essai)(&|$)/.exec(recherche);
        if (m) {
            if (m[1] === 'non') { ecrire('localStorage', CLE_REFUS, 'non'); }
            if (m[1] === 'oui') { ecrire('localStorage', CLE_REFUS, null); }
            if (m[1] === 'essai') { ecrire('sessionStorage', CLE_ESSAI, '1'); }
            if (window.history && window.history.replaceState) {
                var propre = recherche.replace(/([?&])mesure=(non|oui|essai)(&|$)/, function (tout, avant, val, apres) { return apres === '&' ? avant : ''; });
                window.history.replaceState(null, '', window.location.pathname + (propre === '?' ? '' : propre) + window.location.hash);
            }
        }
    } catch (e) { /* sans effet */ }

    var refuse = lire('localStorage', CLE_REFUS) === 'non';
    var nePasSuivre = navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.msDoNotTrack === '1' || navigator.globalPrivacyControl === true;
    var autorise = (HOTES.indexOf(hote) !== -1 || enLocal);
    var essai = enLocal || lire('sessionStorage', CLE_ESSAI) === '1';
    var actif = autorise && !refuse && !nePasSuivre;

    var arrete = false; /* devient vrai dès que le visiteur refuse : plus rien n'est envoyé, même par cette page */

    /* Interface publique : utilisée par site.js (envoi des formulaires) et par Confidentialite.html (refus). */
    var api = {
        actif: actif,
        formulaire: function () { /* remplacé plus bas si la mesure est active */ },
        refuser: function () { arrete = true; ecrire('localStorage', CLE_REFUS, 'non'); },
        accepter: function () { ecrire('localStorage', CLE_REFUS, null); },
        refuse: function () { return lire('localStorage', CLE_REFUS) === 'non'; },
        gpc: nePasSuivre
    };
    window.MSIS_MESURE = api;

    /* ---------- Bouton de la page Confidentialité ---------- */
    try {
        var bascule = document.getElementById('mesureBascule');
        var etat = document.getElementById('mesureEtat');
        if (bascule) {
            var majBascule = function () {
                var off = api.refuse();
                if (etat) {
                    etat.textContent = nePasSuivre
                        ? 'Votre navigateur envoie « Ne pas me suivre » : vos visites ne sont jamais comptées.'
                        : (off ? 'Vos visites ne sont pas comptées sur cet appareil.' : 'Vos visites sont comptées de façon anonyme.');
                }
                bascule.textContent = off ? 'Recommencer à compter mes visites' : 'Ne plus compter mes visites sur cet appareil';
                bascule.setAttribute('aria-pressed', off ? 'true' : 'false');
            };
            bascule.addEventListener('click', function () {
                if (api.refuse()) { api.accepter(); } else { api.refuser(); }
                majBascule();
            });
            majBascule();
        }
    } catch (e) { /* la page reste lisible */ }

    if (!actif) { return; }

    /* ---------- Outils ---------- */
    var identifiantPage = Math.random().toString(36).slice(2, 10);
    var chemin = (function () {
        var p = window.location.pathname || '/';
        p = p.replace(/\/index\.html$/i, '/');
        if (p.length > 1) { p = p.replace(/\/+$/, ''); }
        return p || '/';
    })();

    function net(texte, max) {
        return String(texte || '').replace(/\s+/g, ' ').replace(/^\s+|\s+$/g, '').slice(0, max);
    }

    /* Texte tel qu'affiché : innerText transforme un <br> en espace (textContent collerait les mots). */
    function texteVisible(el) {
        return (typeof el.innerText === 'string' && el.innerText) ? el.innerText : el.textContent;
    }

    var file = [];
    var minuteur = null;

    function envoyer(corps) {
        if (arrete) { return; }
        var texte = JSON.stringify(corps);
        try {
            if (navigator.sendBeacon && navigator.sendBeacon(ADRESSE, new Blob([texte], { type: 'text/plain;charset=UTF-8' }))) { return; }
        } catch (e) { /* repli ci-dessous */ }
        try {
            if (window.fetch) {
                window.fetch(ADRESSE, { method: 'POST', body: texte, headers: { 'Content-Type': 'text/plain;charset=UTF-8' }, keepalive: true, credentials: 'omit' })
                    .catch(function () { /* la mesure ne doit jamais gêner la visite */ });
            }
        } catch (e) { /* idem */ }
    }

    function vider() {
        if (minuteur) { window.clearTimeout(minuteur); minuteur = null; }
        if (!file.length) { return; }
        var lot = file.splice(0, file.length);
        envoyer(essai ? { x: 1, evts: lot } : { evts: lot });
    }

    function empiler(evenement, immediat) {
        evenement.p = chemin;
        evenement.k = identifiantPage;
        file.push(evenement);
        if (file.length >= 20 || immediat) { vider(); return; }
        if (!minuteur) { minuteur = window.setTimeout(vider, 1500); }
    }

    /* ---------- Page vue ---------- */
    var vueEnvoyee = false;
    function envoyerVue() {
        if (vueEnvoyee) { return; }
        vueEnvoyee = true;
        var e = { e: 'vue', ti: net(document.title, 90), w: window.innerWidth || 0 };
        try {
            var langue = (navigator.language || '').slice(0, 2).toLowerCase();
            if (langue) { e.l = langue; }
            if (document.referrer) {
                var lien = document.createElement('a');
                lien.href = document.referrer;
                var hr = (lien.hostname || '').replace(/^www\./, '');
                if (hr && hr !== hote.replace(/^www\./, '')) { e.r = hr; }
            }
            var q = window.location.search || '';
            var utm = /[?&]utm_(source|medium|campaign)=([^&#]*)/g;
            var t;
            while ((t = utm.exec(q)) !== null) {
                var valeur = '';
                try { valeur = decodeURIComponent(t[2].replace(/\+/g, ' ')); } catch (err) { valeur = t[2]; }
                e[{ source: 'us', medium: 'um', campaign: 'uc' }[t[1]]] = net(valeur, 60);
            }
        } catch (err) { /* la page vue est envoyée même sans ces détails */ }
        empiler(e, false);
    }

    /* ---------- Temps passé et défilement (envoyés quand la page est quittée ou cachée) ---------- */
    var debutVisible = document.visibilityState === 'hidden' ? 0 : Date.now();
    var secondesCumulees = 0;
    var secondesEnvoyees = 0;
    var defilementMax = 0;
    var defilementEnvoye = -1;

    function mesurerDefilement() {
        try {
            var haut = window.pageYOffset || document.documentElement.scrollTop || 0;
            var total = Math.max(document.documentElement.scrollHeight, document.body ? document.body.scrollHeight : 0);
            if (total > 0) {
                var pct = Math.min(100, Math.round(100 * (haut + (window.innerHeight || 0)) / total));
                if (pct > defilementMax) { defilementMax = pct; }
            }
        } catch (e) { /* sans effet */ }
    }
    var enAttenteDefil = false;
    window.addEventListener('scroll', function () {
        if (enAttenteDefil) { return; }
        enAttenteDefil = true;
        window.setTimeout(function () { enAttenteDefil = false; mesurerDefilement(); }, 250);
    }, { passive: true });

    function envoyerTemps() {
        if (debutVisible) { secondesCumulees += (Date.now() - debutVisible) / 1000; debutVisible = 0; }
        mesurerDefilement();
        var entier = Math.round(secondesCumulees);
        var delta = entier - secondesEnvoyees;
        if (delta > 0 || defilementMax !== defilementEnvoye) {
            secondesEnvoyees = entier;
            defilementEnvoye = defilementMax;
            empiler({ e: 'temps', sec: Math.max(0, delta), defil: defilementMax }, false);
        }
        vider(); /* envoie aussi ce qui attendait encore dans la file (rubrique lue, clic) */
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            envoyerTemps();
        } else {
            if (!vueEnvoyee) { envoyerVue(); }
            if (!debutVisible) { debutVisible = Date.now(); }
        }
    });
    window.addEventListener('pagehide', envoyerTemps);

    /* ---------- Rubriques lues : un titre resté visible au moins 1,2 s ---------- */
    function demarrerRubriques() {
        if (!('IntersectionObserver' in window)) { return; }
        var titres = document.querySelectorAll('main h2, main h3');
        var dejaVus = {};
        var total = 0;
        var attentes = new Map();
        var observateur = new IntersectionObserver(function (entrees) {
            for (var i = 0; i < entrees.length; i++) {
                (function (entree) {
                    var el = entree.target;
                    if (entree.isIntersecting && entree.intersectionRatio >= 0.5) {
                        if (attentes.has(el)) { return; }
                        attentes.set(el, window.setTimeout(function () {
                            attentes.delete(el);
                            var nom = net(el.getAttribute('data-suivi') || texteVisible(el), 80);
                            if (!nom || dejaVus[nom] || total >= 40) { return; }
                            dejaVus[nom] = true;
                            total++;
                            empiler({ e: 'section', n: nom }, false);
                        }, 1200));
                    } else if (attentes.has(el)) {
                        window.clearTimeout(attentes.get(el));
                        attentes.delete(el);
                    }
                })(entrees[i]);
            }
        }, { threshold: [0, 0.5] });
        for (var i = 0; i < titres.length; i++) { observateur.observe(titres[i]); }
    }

    /* ---------- Clics : contacts, documents, menus, liens ---------- */
    function libelle(el) {
        var l = el.getAttribute('data-suivi') || el.getAttribute('aria-label') || el.getAttribute('title') || texteVisible(el);
        if (!net(l, 80)) {
            var img = el.querySelector ? el.querySelector('img[alt]') : null;
            l = img ? img.getAttribute('alt') : '';
        }
        return net(l, 80);
    }

    document.addEventListener('click', function (evt) {
        try {
            var el = evt.target;
            while (el && el !== document && !(el.tagName === 'A' || el.tagName === 'BUTTON')) { el = el.parentNode; }
            if (!el || el === document) { return; }
            if (el.tagName === 'BUTTON') {
                if (el.type === 'submit' || el.form) { return; } /* les formulaires sont suivis à part */
                empiler({ e: 'clic', c: 'bouton', n: libelle(el) }, false);
                return;
            }
            var href = el.getAttribute('href') || '';
            if (!href || href === '#') { return; }
            var e = { e: 'clic', n: libelle(el) };
            if (/^tel:/i.test(href)) {
                e.c = 'tel';
            } else if (/^mailto:/i.test(href)) {
                e.c = 'mail';
            } else {
                var a = document.createElement('a');
                a.href = href;
                var hoteLien = (a.hostname || '').replace(/^www\./, '');
                var chemLien = a.pathname || '';
                if (/(^|\.)(wa\.me|whatsapp\.com)$/i.test(hoteLien)) {
                    e.c = 'whatsapp';
                    e.cible = net(hoteLien + chemLien, 100); /* jamais la partie « ?text= » du lien */
                } else if (/\.pdf$/i.test(chemLien)) {
                    e.c = 'pdf';
                    e.cible = net(chemLien.split('/').pop(), 100);
                } else if (hoteLien && hoteLien !== hote.replace(/^www\./, '')) {
                    e.c = 'externe';
                    e.cible = net(hoteLien, 100);
                } else if (href.charAt(0) === '#') {
                    e.c = 'ancre';
                    e.cible = net(href, 60);
                } else {
                    e.c = el.closest && el.closest('footer') ? 'pied' : (el.closest && el.closest('header, nav') ? 'menu' : 'interne');
                    e.cible = net(chemLien.replace(/\/index\.html$/i, '/'), 100);
                }
            }
            empiler(e, e.c === 'whatsapp' || e.c === 'tel' || e.c === 'mail' || e.c === 'pdf' || e.c === 'externe');
        } catch (err) { /* la mesure ne doit jamais gêner un clic */ }
    }, true);

    /* ---------- Formulaires : commencé (premier champ touché) et envoyé (appelé par site.js) ---------- */
    var NOMS_FORMULAIRES = {
        gsnForm: 'Inscription GSN',
        candidatureForm: 'Candidature spontanée',
        partenaireGsnForm: 'Devenir partenaire GSN'
    };
    var formulairesCommences = {};

    document.addEventListener('focusin', function (evt) {
        try {
            var form = evt.target && evt.target.form;
            if (!form) { return; }
            var nom = NOMS_FORMULAIRES[form.id] || net(form.id || form.getAttribute('name') || 'Formulaire', 60);
            if (formulairesCommences[nom]) { return; }
            formulairesCommences[nom] = true;
            empiler({ e: 'form', c: 'debut', n: nom }, false);
        } catch (err) { /* sans effet */ }
    });

    api.formulaire = function (nom, phase) {
        try {
            empiler({ e: 'form', c: phase === 'envoi' ? 'envoi' : 'debut', n: net(NOMS_FORMULAIRES[nom] || nom, 60) }, true);
        } catch (err) { /* sans effet */ }
    };

    /* ---------- Démarrage ---------- */
    function demarrer() {
        if (document.visibilityState === 'prerender') {
            document.addEventListener('visibilitychange', function attendre() {
                if (document.visibilityState !== 'prerender') {
                    document.removeEventListener('visibilitychange', attendre);
                    demarrer();
                }
            });
            return;
        }
        if (document.visibilityState === 'visible') { envoyerVue(); }
        try { demarrerRubriques(); } catch (err) { /* sans effet */ }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', demarrer);
    } else {
        demarrer();
    }
})();
