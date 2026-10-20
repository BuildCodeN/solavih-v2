/*
 * Configuration du site vitrine — les DEUX seules valeurs à adapter au déploiement.
 *
 * espaceUrl : adresse de l'espace privé « Mon Espace » (application Laravel), sans « / » final.
 *   En développement (site ouvert depuis localhost) : http://localhost:8010, automatiquement.
 *   En production : VIDE tant que l'application n'est pas en ligne. Les liens « Mon Espace »,
 *   « Devenir partenaire » et « Se connecter » sont alors masqués (js/site.js) et le formulaire
 *   GSN envoie directement sur WhatsApp. Une fois l'application déployée, remplacer l'expression
 *   par son adresse publique, par exemple 'https://espace.msis-ci.com' : les liens et l'API du
 *   formulaire GSN s'y rattachent automatiquement (attribut data-espace, voir js/site.js).
 *
 * turnstileSiteKey : clé PUBLIQUE Cloudflare Turnstile (anti-robot du formulaire GSN).
 *   Laisser vide tant que Turnstile n'est pas activé côté espace (TURNSTILE_SECRET_KEY).
 */
window.SOLAVIH_CONFIG = {
    espaceUrl: /^(localhost|127\.0\.0\.1|\[::1\])$/.test(window.location.hostname) ? 'http://localhost:8010' : '',
    turnstileSiteKey: ''
};
