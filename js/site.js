/* SOLAVIH V2 — comportements du site (en-tête, menu mobile, apparitions
   au défilement). Chaque section isolée dans son propre try/catch. */
(function () {
    "use strict";

    var prefersReducedMotion = window.matchMedia &&
        window.matchMedia('(prefers-reduced-motion: reduce)').matches;

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

            gsnForm.addEventListener('submit', function (evt) {
                evt.preventDefault();

                if (!gsnForm.reportValidity()) return;

                var lignes = [
                    "Bonjour SOLAVIH, je souhaite m'inscrire à un contrat GSN.",
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

                var texte = encodeURIComponent(lignes.join('\n'));
                window.open('https://wa.me/225100063355?text=' + texte, '_blank', 'noopener');
            });
        }
    } catch (e) { /* le formulaire reste consultable même si l'envoi échoue */ }
})();
