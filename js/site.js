/* SOLAVIH V2 — comportements du site (en-tête, menu mobile, apparitions
   au défilement). Chaque section isolée dans son propre try/catch. */
(function () {
    "use strict";

    var prefersReducedMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /* ---------- Liens vers l'espace privé « Mon Espace » ----------
       L'adresse vient de js/config.js (une seule valeur à changer au déploiement).
       Un lien porte data-espace="/chemin" (ou data-espace vide pour la racine) :
       son href est reconstruit ici ; s'il manque, le href écrit dans la page reste valable. */
    var espaceUrl = ((window.SOLAVIH_CONFIG && window.SOLAVIH_CONFIG.espaceUrl) || '').replace(/\/+$/, '');

    try {
        var liensEspace = document.querySelectorAll('[data-espace]');
        if (espaceUrl) {
            for (var e = 0; e < liensEspace.length; e++) {
                var chemin = liensEspace[e].getAttribute('data-espace') || '';
                liensEspace[e].setAttribute('href', espaceUrl + chemin);
            }
        } else {
            /* L'application n'est pas en ligne : on masque ses points d'entrée plutôt que de
               laisser des liens morts. Un conteneur dont tous les liens sont masqués l'est aussi
               (pas de bloc vide). Pour les réactiver : renseigner espaceUrl dans js/config.js. */
            for (var m = 0; m < liensEspace.length; m++) {
                liensEspace[m].style.display = 'none';
            }
            for (var p = 0; p < liensEspace.length; p++) {
                var parent = liensEspace[p].parentNode;
                if (!parent || parent === document.body) continue;
                var tousMasques = true;
                for (var c = 0; c < parent.children.length; c++) {
                    if (parent.children[c].style.display !== 'none') { tousMasques = false; break; }
                }
                if (tousMasques) parent.style.display = 'none';
            }
        }
    } catch (err) { /* les liens gardent leur adresse d'origine */ }

    /* ---------- En-tête : état au défilement ---------- */
    try {
        var header = document.getElementById('entete');
        if (header) {
            var updateHeaderState = function () {
                header.classList.toggle('is-scrolled', window.scrollY > 30);
            };
            updateHeaderState();
            window.addEventListener('scroll', updateHeaderState, { passive: true });
        }
    } catch (e) { /* n'empêche pas le reste de fonctionner */ }

    /* ---------- Lien de navigation actif ---------- */
    try {
        var currentPath = window.location.pathname.split('/').pop() || 'index.html';
        var navLinks = document.querySelectorAll('.site-nav .menu > li > a');
        for (var n = 0; n < navLinks.length; n++) {
            if (navLinks[n].getAttribute('href') === currentPath) {
                navLinks[n].classList.add('is-active');
            }
        }
    } catch (e) { /* purement cosmétique */ }

    /* ---------- Menu mobile ---------- */
    try {
        var navToggle = document.getElementById('navToggle');
        var siteNav = document.getElementById('mainNav');
        var navBackdrop = document.getElementById('navBackdrop');

        if (navToggle && siteNav) {
            var closeMenu = function () {
                siteNav.classList.remove('is-open');
                if (navBackdrop) navBackdrop.classList.remove('is-open');
                navToggle.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = '';
            };
            var openMenu = function () {
                siteNav.classList.add('is-open');
                if (navBackdrop) navBackdrop.classList.add('is-open');
                navToggle.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
            };

            navToggle.addEventListener('click', function () {
                if (siteNav.classList.contains('is-open')) { closeMenu(); } else { openMenu(); }
            });

            var navAnchors = siteNav.querySelectorAll('a');
            for (var a = 0; a < navAnchors.length; a++) {
                navAnchors[a].addEventListener('click', closeMenu);
            }
            if (navBackdrop) navBackdrop.addEventListener('click', closeMenu);
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeMenu();
            });
        }
    } catch (e) { /* le menu desktop reste utilisable */ }

    /* ---------- Apparition au défilement ---------- */
    try {
        var revealElements = document.querySelectorAll('[data-reveal]');
        if (revealElements.length) {
            if (prefersReducedMotion || !('IntersectionObserver' in window)) {
                for (var r = 0; r < revealElements.length; r++) {
                    revealElements[r].classList.add('is-visible');
                }
            } else {
                var observer = new IntersectionObserver(function (entries) {
                    entries.forEach(function (entry) {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

                for (var s = 0; s < revealElements.length; s++) {
                    observer.observe(revealElements[s]);
                }

                /* Filet de sécurité : un lien-ancre peut amener directement sur
                   une section sans jamais déclencher l'observer au passage. */
                var revealAllNow = function () {
                    for (var v = 0; v < revealElements.length; v++) {
                        revealElements[v].classList.add('is-visible');
                    }
                    observer.disconnect();
                };
                if (window.location.hash) {
                    window.setTimeout(revealAllNow, 50);
                }
                window.setTimeout(function () {
                    var stillHidden = document.querySelectorAll('[data-reveal]:not(.is-visible)');
                    for (var h = 0; h < stillHidden.length; h++) {
                        stillHidden[h].classList.add('is-visible');
                    }
                }, 4000);
            }
        }
    } catch (e) {
        var fallbackEls = document.querySelectorAll('[data-reveal]');
        for (var f = 0; f < fallbackEls.length; f++) {
            fallbackEls[f].classList.add('is-visible');
        }
    }

    /* ---------- Compteurs animés (bandeau de preuve) ---------- */
    try {
        var counters = document.querySelectorAll('[data-count-to]');
        if (counters.length && !prefersReducedMotion && ('IntersectionObserver' in window)) {
            var countObserver = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    var el = entry.target;
                    var target = parseInt(el.getAttribute('data-count-to'), 10) || 0;
                    var suffix = el.getAttribute('data-count-suffix') || '';
                    var duration = 1100;
                    var start = null;
                    var step = function (ts) {
                        if (!start) start = ts;
                        var progress = Math.min((ts - start) / duration, 1);
                        var eased = 1 - Math.pow(1 - progress, 3);
                        el.textContent = Math.round(eased * target) + suffix;
                        if (progress < 1) window.requestAnimationFrame(step);
                    };
                    window.requestAnimationFrame(step);
                    countObserver.unobserve(el);
                });
            }, { threshold: 0.4 });
            for (var c = 0; c < counters.length; c++) { countObserver.observe(counters[c]); }
        }
    } catch (e) { /* les chiffres restent affichés en statique */ }

    /* ---------- Héro : diaporama ---------- */
    try {
        var hero = document.getElementById('hero');
        if (hero) {
            var slides = hero.querySelectorAll('.hero-slide');
            var dotsWrap = hero.querySelector('.hero-controls');
            var prevBtn = hero.querySelector('.hero-arrow--prev');
            var nextBtn = hero.querySelector('.hero-arrow--next');
            var autoplayDelay = parseInt(hero.getAttribute('data-autoplay'), 10) || 7000;
            var current = 0;
            var timerId = null;

            if (dotsWrap && slides.length > 1) {
                for (var d = 0; d < slides.length; d++) {
                    var dot = document.createElement('button');
                    dot.type = 'button';
                    dot.className = 'hero-dot' + (d === 0 ? ' is-active' : '');
                    dot.setAttribute('aria-label', 'Aller à la diapositive ' + (d + 1));
                    (function (index) {
                        dot.addEventListener('click', function () { goTo(index); restart(); });
                    })(d);
                    dotsWrap.appendChild(dot);
                }
            }
            var dots = hero.querySelectorAll('.hero-dot');

            var goTo = function (index) {
                var total = slides.length;
                if (!total) return;
                var next = (index + total) % total;
                slides[current].classList.remove('is-active');
                if (dots[current]) dots[current].classList.remove('is-active');
                current = next;
                slides[current].classList.add('is-active');
                if (dots[current]) dots[current].classList.add('is-active');
            };

            var stop = function () { if (timerId) { window.clearInterval(timerId); timerId = null; } };
            var start = function () {
                if (prefersReducedMotion || slides.length < 2) return;
                stop();
                timerId = window.setInterval(function () { goTo(current + 1); }, autoplayDelay);
            };
            var restart = function () { start(); };

            if (nextBtn) nextBtn.addEventListener('click', function () { goTo(current + 1); restart(); });
            if (prevBtn) prevBtn.addEventListener('click', function () { goTo(current - 1); restart(); });

            hero.addEventListener('mouseenter', stop);
            hero.addEventListener('mouseleave', start);
            hero.addEventListener('focusin', stop);
            hero.addEventListener('focusout', start);
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) { stop(); } else { start(); }
            });

            start();
        }
    } catch (e) { /* la première diapositive reste affichée */ }

    /* ---------- Curseur suiveur, boutons magnétiques, cartes en relief ----------
       Réservé aux pointeurs fins avec survol réel (souris) ; jamais sur tactile,
       jamais si l'utilisateur a demandé moins de mouvement. */
    try {
        var isFinePointer = window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches;
        if (isFinePointer && !prefersReducedMotion) {

            /* Curseur suiveur */
            var follower = document.createElement('div');
            follower.className = 'cursor-follow';
            follower.setAttribute('aria-hidden', 'true');
            document.body.appendChild(follower);

            var fx = 0, fy = 0, tx = 0, ty = 0, followerStarted = false;
            document.addEventListener('mousemove', function (e) {
                tx = e.clientX; ty = e.clientY;
                follower.classList.add('is-active');
                if (!followerStarted) { fx = tx; fy = ty; followerStarted = true; }
            });
            document.addEventListener('mousedown', function () { follower.classList.add('is-press'); });
            document.addEventListener('mouseup', function () { follower.classList.remove('is-press'); });
            document.documentElement.addEventListener('mouseleave', function () { follower.classList.remove('is-active'); });

            var renderFollower = function () {
                fx += (tx - fx) * 0.18;
                fy += (ty - fy) * 0.18;
                follower.style.transform = 'translate(' + fx + 'px,' + fy + 'px) translate(-50%, -50%)';
                window.requestAnimationFrame(renderFollower);
            };
            window.requestAnimationFrame(renderFollower);

            var hoverTargets = document.querySelectorAll('a, button, .service-card, input, textarea, summary');
            for (var ht = 0; ht < hoverTargets.length; ht++) {
                hoverTargets[ht].addEventListener('mouseenter', function () { follower.classList.add('is-hover'); });
                hoverTargets[ht].addEventListener('mouseleave', function () { follower.classList.remove('is-hover'); });
            }

            /* Cartes en relief (suivent le curseur) */
            var tiltCards = document.querySelectorAll('.service-card');
            for (var tcI = 0; tcI < tiltCards.length; tcI++) {
                (function (card) {
                    card.addEventListener('mousemove', function (e) {
                        var r = card.getBoundingClientRect();
                        var px = (e.clientX - r.left) / r.width - 0.5;
                        var py = (e.clientY - r.top) / r.height - 0.5;
                        card.style.setProperty('--tilt-x', (py * -7) + 'deg');
                        card.style.setProperty('--tilt-y', (px * 7) + 'deg');
                    });
                    card.addEventListener('mouseleave', function () {
                        card.style.setProperty('--tilt-x', '0deg');
                        card.style.setProperty('--tilt-y', '0deg');
                    });
                })(tiltCards[tcI]);
            }
        }
    } catch (e) { /* le site reste pleinement utilisable sans ces effets */ }

    /* ---------- Diaporama miniature (visuels de chaque service) ---------- */
    try {
        var diapos = document.querySelectorAll('.media-diapo');
        for (var mdI = 0; mdI < diapos.length; mdI++) {
            (function (diapo) {
                var imgs = diapo.querySelectorAll('img');
                if (imgs.length < 2) return;

                var dotsWrap = diapo.querySelector('.media-diapo-dots');
                var index = 0, timerId = null;
                var duree = parseInt(diapo.getAttribute('data-autoplay'), 10) || 4500;
                var dots = [];

                var goTo = function (i) {
                    imgs[index].classList.remove('is-active');
                    if (dots[index]) dots[index].classList.remove('is-active');
                    index = i;
                    imgs[index].classList.add('is-active');
                    if (dots[index]) dots[index].classList.add('is-active');
                };

                var restart = function () {
                    if (timerId) window.clearInterval(timerId);
                    if (prefersReducedMotion) return;
                    timerId = window.setInterval(function () { goTo((index + 1) % imgs.length); }, duree);
                };

                if (dotsWrap) {
                    for (var di = 0; di < imgs.length; di++) {
                        (function (i) {
                            var dot = document.createElement('button');
                            dot.type = 'button';
                            dot.setAttribute('aria-label', 'Voir la photo ' + (i + 1));
                            if (i === 0) dot.className = 'is-active';
                            dot.addEventListener('click', function () { goTo(i); restart(); });
                            dotsWrap.appendChild(dot);
                        })(di);
                    }
                    dots = dotsWrap.children;
                }

                restart();
            })(diapos[mdI]);
        }
    } catch (e) { /* le site reste pleinement utilisable sans ce diaporama */ }

    /* ---------- Formulaire d'inscription GSN ---------- */
    try {
        var gsnForm = document.getElementById('gsnForm');
        if (gsnForm) {
            var champFormations = document.getElementById('champVeutFormations');
            var blocFormations = document.getElementById('blocFormations');
            if (champFormations && blocFormations) {
                var syncBlocFormations = function () {
                    blocFormations.hidden = !champFormations.checked;
                };
                champFormations.addEventListener('change', syncBlocFormations);
                syncBlocFormations();
            }

            var valeurChamp = function (id) {
                var el = document.getElementById(id);
                return el && el.value ? el.value.trim() : '';
            };
            var valeursCochees = function (name) {
                var cases = gsnForm.querySelectorAll('input[name="' + name + '"]:checked');
                var valeurs = [];
                for (var c = 0; c < cases.length; c++) { valeurs.push(cases[c].value); }
                return valeurs.join(', ');
            };
            var ouVide = function (v) { return v ? v : '—'; };

            var construireMessage = function (numero) {
                var lignes = [
                    "Bonjour MSIS-CI, je souhaite m'inscrire à un contrat GSN.",
                    numero ? 'Référence : ' + numero : '',
                    "",
                    "Nom : " + valeurChamp('champNom'),
                    "Fonction : " + ouVide(valeurChamp('champFonction')),
                    "Entreprise : " + ouVide(valeurChamp('champEntreprise')),
                    "Localisation : " + valeurChamp('champLocalisation'),
                    "Ville : " + valeurChamp('champVille'),
                    "Téléphone : " + valeurChamp('champTel'),
                    "Email : " + ouVide(valeurChamp('champEmail')),
                    "",
                    "Formule souhaitée : " + ouVide(valeursCochees('offre')),
                    "",
                    "Services qui m'intéressent : " + ouVide(valeursCochees('services')),
                    "Domaines de formation : " + ouVide(valeursCochees('formations')),
                    "",
                    "Postes à couvrir : " + ouVide(valeurChamp('champTaille')),
                    "Urgence : " + ouVide(valeurChamp('champUrgence')),
                    "",
                    "Besoin : " + ouVide(valeurChamp('champBesoin'))
                ];
                if (!numero) { lignes.splice(1, 1); }
                return lignes.join('\n');
            };

            /* API de l'espace client — enregistre l'inscription et fournit son
               numéro de référence. L'adresse vient de js/config.js, comme les
               liens « Mon Espace » de l'en-tête. */
            var API_INSCRIPTIONS_GSN = espaceUrl ? espaceUrl + '/api/inscriptions-gsn' : '';

            var boutonEnvoi = gsnForm.querySelector('.gsn-form-submit');

            /* Anti-robot : Cloudflare Turnstile, seulement si une clé publique est
               renseignée dans js/config.js (sinon, seul le champ piège protège). */
            var jetonTurnstile = '';
            try {
                var cleTurnstile = (window.SOLAVIH_CONFIG && window.SOLAVIH_CONFIG.turnstileSiteKey) || '';
                var zoneTurnstile = document.getElementById('gsnTurnstile');
                if (cleTurnstile && zoneTurnstile) {
                    window.solavihTurnstileReady = function () {
                        window.turnstile.render(zoneTurnstile, {
                            sitekey: cleTurnstile,
                            callback: function (jeton) { jetonTurnstile = jeton; },
                            'expired-callback': function () { jetonTurnstile = ''; }
                        });
                    };
                    var scriptTurnstile = document.createElement('script');
                    scriptTurnstile.src = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=solavihTurnstileReady';
                    scriptTurnstile.async = true;
                    document.head.appendChild(scriptTurnstile);
                }
            } catch (err) { /* le formulaire reste utilisable sans Turnstile */ }

            gsnForm.addEventListener('submit', function (evt) {
                evt.preventDefault();

                if (!gsnForm.reportValidity()) return;

                var fenetre = window.open('', '_blank');
                if (boutonEnvoi) boutonEnvoi.classList.add('is-loading');

                var ouvrirWhatsapp = function (numero) {
                    if (boutonEnvoi) boutonEnvoi.classList.remove('is-loading');
                    var texte = encodeURIComponent(construireMessage(numero));
                    var url = 'https://wa.me/2250100063355?text=' + texte;
                    if (fenetre) { fenetre.location.href = url; }
                    else { window.open(url, '_blank', 'noopener'); }
                };

                var donnees = {
                    nom: valeurChamp('champNom'),
                    fonction: valeurChamp('champFonction') || null,
                    telephone: valeurChamp('champTel'),
                    email: valeurChamp('champEmail') || null,
                    entreprise: valeurChamp('champEntreprise') || null,
                    localisation: valeurChamp('champLocalisation'),
                    ville: valeurChamp('champVille'),
                    taille: valeurChamp('champTaille') || null,
                    urgence: valeurChamp('champUrgence') || null,
                    besoin: valeurChamp('champBesoin') || null,
                    /* Champ piège : invisible pour une personne, rempli par les robots. */
                    website: valeurChamp('champSiteWeb') || null,
                    turnstile_token: jetonTurnstile || null
                };
                var offreCochee = gsnForm.querySelector('input[name="offre"]:checked');
                donnees.offre = offreCochee ? offreCochee.value : null;
                var servicesCoches = gsnForm.querySelectorAll('input[name="services"]:checked');
                donnees.services = servicesCoches.length ? Array.prototype.map.call(servicesCoches, function (c) { return c.value; }) : null;
                var formationsCochees = gsnForm.querySelectorAll('input[name="formations"]:checked');
                donnees.formations = formationsCochees.length ? Array.prototype.map.call(formationsCochees, function (c) { return c.value; }) : null;

                /* Sans application en ligne (espaceUrl vide), pas d'appel réseau : WhatsApp directement. */
                if (!window.fetch || !API_INSCRIPTIONS_GSN) { ouvrirWhatsapp(null); return; }

                var controleur = window.AbortController ? new AbortController() : null;
                var minuteur = controleur ? window.setTimeout(function () { controleur.abort(); }, 2500) : null;

                fetch(API_INSCRIPTIONS_GSN, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(donnees),
                    signal: controleur ? controleur.signal : undefined
                }).then(function (reponse) {
                    return reponse.ok ? reponse.json() : null;
                }).then(function (resultat) {
                    if (minuteur) window.clearTimeout(minuteur);
                    ouvrirWhatsapp(resultat && resultat.numero ? resultat.numero : null);
                }).catch(function () {
                    if (minuteur) window.clearTimeout(minuteur);
                    ouvrirWhatsapp(null);
                });
            });
        }
    } catch (e) { /* le formulaire reste consultable même si l'envoi échoue */ }

    /* ---------- Formulaire de candidature spontanée (Candidature-Spontanee.html) ----------
       Assistant en 4 étapes (coordonnées / profil / CV &amp; message / vérification) :
       un seul bloc .wizard-step visible à la fois, barre + puces de progression tenues
       à jour dans irVersEtape(), dernière étape = récapitulatif modifiable avant envoi.
       Comme le formulaire GSN ci-dessus, l'envoi final ouvre WhatsApp avec un message
       pré-rempli — sans appel à « Mon Espace » (pas d'équivalent de /api/inscriptions-gsn
       côté candidatures à ce jour ; à ajouter facilement sur le même modèle si besoin).
       CV : sur les appareils qui savent partager un fichier (Web Share API, surtout
       mobile), l'envoi ouvre le menu de partage avec le CV déjà joint. Ailleurs (la
       plupart des ordinateurs de bureau), aucune API web ne permet de joindre un fichier
       à un simple lien WhatsApp : la personne le joint elle-même, d'où le rappel dans
       .gsn-form-hint et dans construireMessageCandidature(). */
    try {
        var candidatureForm = document.getElementById('candidatureForm');
        if (candidatureForm) {
            var valeurChampCand = function (id) {
                var el = document.getElementById(id);
                return el && el.value ? el.value.trim() : '';
            };
            var ouVideCand = function (v) { return v ? v : '—'; };
            var champCv = document.getElementById('candCv');
            var indiceCv = document.getElementById('candCvHint');
            var indiceCvParDefaut = indiceCv ? indiceCv.textContent : '';
            var indiceEnvoi = document.getElementById('candSendHint');
            var texteEnvoiParDefaut = indiceEnvoi ? indiceEnvoi.textContent : '';

            /* fichierDejaJoint : true quand le message part via navigator.share avec le
               CV en pièce jointe (le fichier voyage déjà avec le texte) ; false quand il
               part par le simple lien wa.me (texte seul — la personne joindra son CV
               elle-même dans WhatsApp juste après). */
            var infoCvPourMessage = function (fichierDejaJoint) {
                var f = champCv ? champCv.files[0] : null;
                if (!f) return '—';
                return f.name + (fichierDejaJoint ? ' (joint à cet envoi)' : ' (à joindre vous-même dans WhatsApp)');
            };

            var construireMessageCandidature = function (fichierDejaJoint) {
                var lignes = [
                    "Bonjour MSIS-CI, je vous envoie une candidature spontanée.",
                    "",
                    "Nom : " + valeurChampCand('candNom'),
                    "Téléphone : " + valeurChampCand('candTel'),
                    "Email : " + ouVideCand(valeurChampCand('candEmail')),
                    "",
                    "Domaine qui m'intéresse : " + ouVideCand(valeurChampCand('candDomaine')),
                    "Niveau d'études / diplôme : " + ouVideCand(valeurChampCand('candDiplome')),
                    "Expérience : " + ouVideCand(valeurChampCand('candExperience')),
                    "Disponibilité : " + ouVideCand(valeurChampCand('candDisponibilite')),
                    "",
                    "CV : " + infoCvPourMessage(fichierDejaJoint),
                    "Message : " + ouVideCand(valeurChampCand('candMessage'))
                ];
                return lignes.join('\n');
            };

            /* ---- Assistant pas à pas ---- */
            var etapesEls = Array.prototype.slice.call(candidatureForm.querySelectorAll('.wizard-step'));
            var progressEls = Array.prototype.slice.call(candidatureForm.parentNode.querySelectorAll('.wizard-progress-step'));
            var fillEl = document.getElementById('wizardFill');
            var recapEl = document.getElementById('wizardRecap');
            var totalEtapes = etapesEls.length;
            var etapeActuelle = 1;

            /* Le bouton promet WhatsApp précisément — pas « une appli parmi d'autres ».
               Sur mobile (Android/iPhone), WhatsApp s'enregistre comme destination de
               partage et apparaît bien dans le menu natif : le raccourci tient sa
               promesse. Sur ordinateur, Chrome/Edge exposent aussi navigator.share,
               mais WhatsApp Desktop (hors version Store) ne s'enregistre pas comme
               destination : le menu qui s'ouvre ne propose alors pas WhatsApp, ce qui
               ne correspond pas au bouton — d'où ce garde-fou supplémentaire. */
            var estMobile = /Mobi|Android|iPhone|iPad|iPod/i.test(navigator.userAgent || '');

            var partageAvecFichierPossible = function (f) {
                return !!(estMobile && f && window.navigator && navigator.share && navigator.canShare && navigator.canShare({ files: [f] }));
            };

            var majIndiceEnvoi = function () {
                if (!indiceEnvoi) return;
                var possible = partageAvecFichierPossible(champCv ? champCv.files[0] : null);
                indiceEnvoi.textContent = possible
                    ? "En cliquant, un menu de partage s'ouvre avec votre CV déjà joint : choisissez WhatsApp pour l'envoyer."
                    : texteEnvoiParDefaut;
            };

            var remplirRecap = function () {
                if (!recapEl) return;
                recapEl.textContent = '';

                var creerSection = function (etape, paires) {
                    var section = document.createElement('div');
                    section.className = 'wizard-recap-section';
                    var dl = document.createElement('dl');
                    for (var i = 0; i < paires.length; i++) {
                        var dt = document.createElement('dt'); dt.textContent = paires[i][0];
                        var dd = document.createElement('dd'); dd.textContent = paires[i][1];
                        dl.appendChild(dt); dl.appendChild(dd);
                    }
                    var bouton = document.createElement('button');
                    bouton.type = 'button';
                    bouton.className = 'wizard-recap-edit';
                    bouton.textContent = 'Modifier';
                    bouton.addEventListener('click', function () { irVersEtape(etape); });
                    section.appendChild(dl);
                    section.appendChild(bouton);
                    recapEl.appendChild(section);
                };

                creerSection(1, [
                    ['Nom complet', valeurChampCand('candNom')],
                    ['Téléphone', valeurChampCand('candTel')],
                    ['Email', ouVideCand(valeurChampCand('candEmail'))]
                ]);
                creerSection(2, [
                    ['Domaine', ouVideCand(valeurChampCand('candDomaine'))],
                    ['Diplôme', ouVideCand(valeurChampCand('candDiplome'))],
                    ['Expérience', ouVideCand(valeurChampCand('candExperience'))],
                    ['Disponibilité', ouVideCand(valeurChampCand('candDisponibilite'))]
                ]);
                var fichierCv = champCv ? champCv.files[0] : null;
                creerSection(3, [
                    ['CV', fichierCv ? (fichierCv.name + ' (' + Math.ceil(fichierCv.size / 1024) + ' Ko)') : 'Non ajouté'],
                    ['Message', valeurChampCand('candMessage')]
                ]);

                majIndiceEnvoi();
            };

            var irVersEtape = function (n) {
                if (n < 1) n = 1;
                if (n > totalEtapes) n = totalEtapes;
                etapesEls.forEach(function (el) {
                    el.classList.toggle('is-active', Number(el.getAttribute('data-step')) === n);
                });
                progressEls.forEach(function (el) {
                    var s = Number(el.getAttribute('data-step'));
                    el.classList.toggle('is-active', s === n);
                    el.classList.toggle('is-done', s < n);
                });
                if (fillEl) fillEl.style.width = Math.round((n / totalEtapes) * 100) + '%';
                etapeActuelle = n;
                if (n === totalEtapes) remplirRecap();
                try {
                    var panel = candidatureForm.closest('.apply-panel');
                    if (panel) panel.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
                } catch (errScroll) { /* le changement d'étape reste fonctionnel sans le scroll */ }
                try {
                    var champ = etapesEls[n - 1] && etapesEls[n - 1].querySelector('input, select, textarea');
                    if (champ) champ.focus({ preventScroll: true });
                } catch (errFocus) { /* le focus automatique n'est qu'un confort */ }
            };

            var etapeEstValide = function (n) {
                var etapeEl = etapesEls[n - 1];
                if (!etapeEl) return true;
                var champs = etapeEl.querySelectorAll('input, select, textarea');
                for (var i = 0; i < champs.length; i++) {
                    if (!champs[i].checkValidity()) { champs[i].reportValidity(); return false; }
                }
                return true;
            };

            var boutonsSuivant = Array.prototype.slice.call(candidatureForm.querySelectorAll('.wizard-next'));
            for (var bs = 0; bs < boutonsSuivant.length; bs++) {
                boutonsSuivant[bs].addEventListener('click', function () {
                    if (etapeEstValide(etapeActuelle)) irVersEtape(etapeActuelle + 1);
                });
            }
            var boutonsPrecedent = Array.prototype.slice.call(candidatureForm.querySelectorAll('.wizard-back'));
            for (var bp = 0; bp < boutonsPrecedent.length; bp++) {
                boutonsPrecedent[bp].addEventListener('click', function () { irVersEtape(etapeActuelle - 1); });
            }

            /* Un formulaire avec un bouton de type submit réagit à la touche Entrée
               même depuis une étape non affichée : sans ce garde-fou, Entrée dans le
               champ « Nom » (étape 1) déclencherait l'envoi WhatsApp immédiatement. */
            candidatureForm.addEventListener('keydown', function (evt) {
                if (evt.key !== 'Enter' || (evt.target && evt.target.tagName === 'TEXTAREA')) return;
                if (etapeActuelle === totalEtapes) return;
                evt.preventDefault();
                if (etapeEstValide(etapeActuelle)) irVersEtape(etapeActuelle + 1);
            });

            if (champCv && indiceCv) {
                champCv.addEventListener('change', function () {
                    var f = champCv.files[0];
                    indiceCv.textContent = f ? ('Fichier sélectionné : ' + f.name + ' (' + Math.ceil(f.size / 1024) + ' Ko)') : indiceCvParDefaut;
                    majIndiceEnvoi();
                });
            }

            var boutonEnvoiCand = candidatureForm.querySelector('.gsn-form-submit');

            candidatureForm.addEventListener('submit', function (evt) {
                evt.preventDefault();

                if (!etapeEstValide(totalEtapes)) return;

                /* Champ piège anti-robot : si rempli, on abandonne silencieusement
                   (un humain ne le voit ni ne le remplit jamais). */
                if (valeurChampCand('candSiteWeb')) return;

                if (boutonEnvoiCand) boutonEnvoiCand.classList.add('is-loading');

                var fichierCv = champCv ? champCv.files[0] : null;
                var partageFichier = partageAvecFichierPossible(fichierCv);

                var ouvrirWhatsappTexte = function () {
                    if (boutonEnvoiCand) boutonEnvoiCand.classList.remove('is-loading');
                    window.open('https://wa.me/2250100063355?text=' + encodeURIComponent(construireMessageCandidature(false)), '_blank', 'noopener');
                };

                if (partageFichier) {
                    navigator.share({ files: [fichierCv], text: construireMessageCandidature(true), title: 'Candidature spontanée — MSIS-CI' })
                        .then(function () { if (boutonEnvoiCand) boutonEnvoiCand.classList.remove('is-loading'); })
                        .catch(function () { ouvrirWhatsappTexte(); });
                } else {
                    ouvrirWhatsappTexte();
                }
            });

            /* Pas d'appel à irVersEtape(1) ici : l'étape 1 est déjà active dans le HTML
               (classes is-active posées d'origine) et cet appel ferait défiler la page
               vers le formulaire dès le chargement, avant toute action de l'internaute. */
        }
    } catch (e) { /* le formulaire reste consultable même si l'assistant échoue */ }

    /* ---------- Formulaire « Devenir partenaire GSN » (Partenaire-GSN.html) ----------
       Même assistant par étapes que la candidature spontanée ci-dessus (variables
       suffixées Gp pour ne jamais entrer en collision avec celles des deux autres
       formulaires de ce fichier), plus deux mécaniques propres à ce formulaire :
       - les compteurs [ − ] n [ + ] de l'étape « Inventaire » (délégation de clic sur
         .stepper-btn) ;
       - les cases à cocher de l'étape « Autres équipements », qui ne font que
         synchroniser leur compteur associé (0 <-> 1) — le compteur reste la seule
         source de vérité pour le récapitulatif et le message WhatsApp. */
    try {
        var partenaireGsnForm = document.getElementById('partenaireGsnForm');
        if (partenaireGsnForm) {
            var valeurChampGp = function (id) {
                var el = document.getElementById(id);
                return el && el.value ? el.value.trim() : '';
            };
            var ouVideGp = function (v) { return v ? v : '—'; };
            var localisationSlashGp = function () {
                var parties = [valeurChampGp('gpVille'), valeurChampGp('gpCommune'), valeurChampGp('gpQuartier'), valeurChampGp('gpSousQuartier')];
                var nonVides = [];
                for (var i = 0; i < parties.length; i++) { if (parties[i]) nonVides.push(parties[i]); }
                return nonVides.length ? nonVides.join(' / ') : '—';
            };

            var champsInventaireGp = [
                ['gpOrdiBureau', 'Ordinateurs de bureau'],
                ['gpOrdiPortable', 'Ordinateurs portables'],
                ['gpImprimantes', 'Imprimante laser'],
                ['gpMultifonctions', "Imprimante à jet d'encre"],
                ['gpRouteurs', 'Routeurs'],
                ['gpSwitches', 'Switches'],
                ['gpWifi', "Points d'accès Wi-Fi"],
                ['gpBaies', 'Baies / coffrets réseau'],
                ['gpServeursPhysiques', 'Serveurs physiques'],
                ['gpServeursNas', 'Serveurs NAS / stockage réseau']
            ];
            var champsAutresGp = [
                ['gpUps', 'Onduleurs / UPS'],
                ['gpTelIp', 'Téléphones IP'],
                ['gpCaisse', 'Terminaux de caisse'],
                ['gpVideo', 'Vidéoprojecteurs']
            ];
            var casesEquipementGp = [
                ['gpUpsCoche', 'gpUps'],
                ['gpTelIpCoche', 'gpTelIp'],
                ['gpCaisseCoche', 'gpCaisse'],
                ['gpVideoCoche', 'gpVideo']
            ];

            var listeNonZeroGp = function (champs) {
                var lignes = [];
                for (var i = 0; i < champs.length; i++) {
                    var el = document.getElementById(champs[i][0]);
                    var val = el ? (parseInt(el.value, 10) || 0) : 0;
                    if (val > 0) lignes.push([champs[i][1], val]);
                }
                return lignes;
            };
            var inventaireInformatiqueGp = function () { return listeNonZeroGp(champsInventaireGp); };
            var autresEquipementsGp = function () {
                var lignes = listeNonZeroGp(champsAutresGp);
                var precision = valeurChampGp('gpAutrePrecision');
                var qte = parseInt((document.getElementById('gpAutreQte') || {}).value, 10) || 0;
                if (precision && qte > 0) lignes.push([precision, qte]);
                return lignes;
            };

            var construireMessageGp = function () {
                var contratCoche = partenaireGsnForm.querySelector('input[name="gpContrat"]:checked');
                var etatCoche = partenaireGsnForm.querySelector('input[name="gpEtat"]:checked');
                var zoneCoche = partenaireGsnForm.querySelector('input[name="gpZone"]:checked');
                var inv = inventaireInformatiqueGp().concat(autresEquipementsGp());

                var lignes = ["DEMANDE DE PARTENARIAT GSN", ""];
                lignes.push("Entreprise : " + valeurChampGp('gpEntreprise'));
                lignes.push("Secteur : " + valeurChampGp('gpSecteur'));
                lignes.push("Zone : " + (zoneCoche ? zoneCoche.value : 'Non précisé'));
                lignes.push("Adresse : " + valeurChampGp('gpAdresse'));
                lignes.push("Localisation : " + localisationSlashGp());
                var siteWeb = valeurChampGp('gpSiteWeb');
                if (siteWeb) lignes.push("Site web : " + siteWeb);
                var rccm = valeurChampGp('gpRccm');
                if (rccm) lignes.push("RCCM : " + rccm);
                lignes.push("");
                lignes.push("Contact : " + valeurChampGp('gpContactNom'));
                lignes.push("Fonction : " + valeurChampGp('gpFonction'));
                lignes.push("Téléphone : " + valeurChampGp('gpTel'));
                lignes.push("E-mail : " + valeurChampGp('gpEmail'));
                lignes.push("");
                lignes.push("Contrat GSN souhaité : " + (contratCoche ? contratCoche.value : 'Non précisé'));
                lignes.push("");
                lignes.push("INVENTAIRE DU PARC");
                if (inv.length) {
                    for (var i = 0; i < inv.length; i++) lignes.push(inv[i][0] + " : " + inv[i][1]);
                } else {
                    lignes.push("Aucun équipement renseigné");
                }
                lignes.push("");
                lignes.push("État général du parc : " + (etatCoche ? etatCoche.value : 'Non précisé'));
                lignes.push("");
                lignes.push("Je souhaite être contacté par le Groupe MSIS-CI afin d'échanger sur cette demande et recevoir une proposition adaptée.");
                return lignes.join('\n');
            };

            /* ---- Assistant pas à pas (mêmes principes que candidatureForm ci-dessus) ---- */
            var etapesElsGp = Array.prototype.slice.call(partenaireGsnForm.querySelectorAll('.wizard-step'));
            var progressElsGp = Array.prototype.slice.call(partenaireGsnForm.parentNode.querySelectorAll('.wizard-progress-step'));
            var fillElGp = document.getElementById('gpWizardFill');
            var recapElGp = document.getElementById('gpWizardRecap');
            var totalEtapesGp = etapesElsGp.length;
            var etapeActuelleGp = 1;

            var remplirRecapGp = function () {
                if (!recapElGp) return;
                recapElGp.textContent = '';

                var creerSection = function (etape, paires) {
                    var section = document.createElement('div');
                    section.className = 'wizard-recap-section';
                    var dl = document.createElement('dl');
                    for (var i = 0; i < paires.length; i++) {
                        var dt = document.createElement('dt'); dt.textContent = paires[i][0];
                        var dd = document.createElement('dd'); dd.textContent = paires[i][1];
                        dl.appendChild(dt); dl.appendChild(dd);
                    }
                    var bouton = document.createElement('button');
                    bouton.type = 'button';
                    bouton.className = 'wizard-recap-edit';
                    bouton.textContent = 'Modifier';
                    bouton.addEventListener('click', function () { irVersEtapeGp(etape); });
                    section.appendChild(dl);
                    section.appendChild(bouton);
                    recapElGp.appendChild(section);
                };

                var zoneCoche = partenaireGsnForm.querySelector('input[name="gpZone"]:checked');
                creerSection(1, [
                    ['Entreprise', valeurChampGp('gpEntreprise')],
                    ['Secteur', valeurChampGp('gpSecteur')],
                    ['Zone', zoneCoche ? zoneCoche.value : 'Non précisé'],
                    ['Adresse', valeurChampGp('gpAdresse')],
                    ['Localisation', localisationSlashGp()],
                    ['Site web', ouVideGp(valeurChampGp('gpSiteWeb'))],
                    ['RCCM', ouVideGp(valeurChampGp('gpRccm'))],
                    ['Contact', valeurChampGp('gpContactNom') + ' — ' + valeurChampGp('gpFonction')],
                    ['Téléphone', valeurChampGp('gpTel')],
                    ['Email', valeurChampGp('gpEmail')]
                ]);

                var contratCoche = partenaireGsnForm.querySelector('input[name="gpContrat"]:checked');
                creerSection(2, [['Contrat souhaité', contratCoche ? contratCoche.value : 'Non précisé']]);

                var inv = inventaireInformatiqueGp();
                creerSection(3, inv.length
                    ? inv.map(function (l) { return [l[0], String(l[1])]; })
                    : [['Inventaire informatique', 'Aucun équipement renseigné']]);

                var autres = autresEquipementsGp().map(function (l) { return [l[0], String(l[1])]; });
                var etatCoche = partenaireGsnForm.querySelector('input[name="gpEtat"]:checked');
                autres.push(['État du parc', etatCoche ? etatCoche.value : 'Non précisé']);
                creerSection(4, autres);
            };

            var irVersEtapeGp = function (n) {
                if (n < 1) n = 1;
                if (n > totalEtapesGp) n = totalEtapesGp;
                etapesElsGp.forEach(function (el) {
                    el.classList.toggle('is-active', Number(el.getAttribute('data-step')) === n);
                });
                progressElsGp.forEach(function (el) {
                    var s = Number(el.getAttribute('data-step'));
                    el.classList.toggle('is-active', s === n);
                    el.classList.toggle('is-done', s < n);
                });
                if (fillElGp) fillElGp.style.width = Math.round((n / totalEtapesGp) * 100) + '%';
                etapeActuelleGp = n;
                if (n === totalEtapesGp) remplirRecapGp();
                try {
                    var panel = partenaireGsnForm.closest('.apply-panel');
                    if (panel) panel.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
                } catch (errScroll) { /* le changement d'étape reste fonctionnel sans le scroll */ }
                try {
                    var champ = etapesElsGp[n - 1] && etapesElsGp[n - 1].querySelector('input, select, textarea');
                    if (champ) champ.focus({ preventScroll: true });
                } catch (errFocus) { /* le focus automatique n'est qu'un confort */ }
            };

            var etapeEstValideGp = function (n) {
                var etapeEl = etapesElsGp[n - 1];
                if (!etapeEl) return true;
                var champs = etapeEl.querySelectorAll('input, select, textarea');
                for (var i = 0; i < champs.length; i++) {
                    if (!champs[i].checkValidity()) { champs[i].reportValidity(); return false; }
                }
                return true;
            };

            var boutonsSuivantGp = Array.prototype.slice.call(partenaireGsnForm.querySelectorAll('.wizard-next'));
            for (var bsg = 0; bsg < boutonsSuivantGp.length; bsg++) {
                boutonsSuivantGp[bsg].addEventListener('click', function () {
                    if (etapeEstValideGp(etapeActuelleGp)) irVersEtapeGp(etapeActuelleGp + 1);
                });
            }
            var boutonsPrecedentGp = Array.prototype.slice.call(partenaireGsnForm.querySelectorAll('.wizard-back'));
            for (var bpg = 0; bpg < boutonsPrecedentGp.length; bpg++) {
                boutonsPrecedentGp[bpg].addEventListener('click', function () { irVersEtapeGp(etapeActuelleGp - 1); });
            }

            partenaireGsnForm.addEventListener('keydown', function (evt) {
                if (evt.key !== 'Enter' || (evt.target && evt.target.tagName === 'TEXTAREA')) return;
                if (etapeActuelleGp === totalEtapesGp) return;
                evt.preventDefault();
                if (etapeEstValideGp(etapeActuelleGp)) irVersEtapeGp(etapeActuelleGp + 1);
            });

            /* ---- Compteurs [ − ] n [ + ] ---- */
            var boutonsStepperGp = Array.prototype.slice.call(partenaireGsnForm.querySelectorAll('.stepper-btn'));
            for (var sg = 0; sg < boutonsStepperGp.length; sg++) {
                boutonsStepperGp[sg].addEventListener('click', function () {
                    var cible = document.getElementById(this.getAttribute('data-target'));
                    if (!cible) return;
                    var delta = parseInt(this.getAttribute('data-step'), 10) || 0;
                    var actuel = parseInt(cible.value, 10) || 0;
                    var min = parseInt(cible.min, 10) || 0;
                    var max = parseInt(cible.max, 10) || 999;
                    var nouveau = actuel + delta;
                    if (nouveau < min) nouveau = min;
                    if (nouveau > max) nouveau = max;
                    cible.value = nouveau;
                    for (var sc = 0; sc < casesEquipementGp.length; sc++) {
                        if (casesEquipementGp[sc][1] === cible.id) {
                            var caseAssociee = document.getElementById(casesEquipementGp[sc][0]);
                            if (caseAssociee) caseAssociee.checked = nouveau > 0;
                        }
                    }
                });
            }

            /* ---- Cases à cocher « Autres équipements » : ne font que synchroniser leur
               compteur (0 <-> 1) — le compteur reste la seule source de vérité. ---- */
            for (var ceg = 0; ceg < casesEquipementGp.length; ceg++) {
                (function (idCase, idCompteur) {
                    var caseEl = document.getElementById(idCase);
                    var compteurEl = document.getElementById(idCompteur);
                    if (!caseEl || !compteurEl) return;
                    caseEl.addEventListener('change', function () {
                        if (caseEl.checked) {
                            if ((parseInt(compteurEl.value, 10) || 0) === 0) compteurEl.value = 1;
                        } else {
                            compteurEl.value = 0;
                        }
                    });
                })(casesEquipementGp[ceg][0], casesEquipementGp[ceg][1]);
            }

            var boutonEnvoiGp = partenaireGsnForm.querySelector('.gsn-form-submit');

            partenaireGsnForm.addEventListener('submit', function (evt) {
                evt.preventDefault();

                if (!etapeEstValideGp(totalEtapesGp)) return;

                /* Champ piège anti-robot : si rempli, on abandonne silencieusement. */
                if (valeurChampGp('gpHoneypot')) return;

                if (boutonEnvoiGp) boutonEnvoiGp.classList.add('is-loading');
                window.open('https://wa.me/2250100063355?text=' + encodeURIComponent(construireMessageGp()), '_blank', 'noopener');
                if (boutonEnvoiGp) boutonEnvoiGp.classList.remove('is-loading');
            });

            /* Pas d'appel à irVersEtapeGp(1) ici, pour la même raison que le formulaire
               de candidature ci-dessus (l'étape 1 est déjà active dans le HTML). */
        }
    } catch (e) { /* le formulaire reste consultable même si l'assistant échoue */ }
})();
