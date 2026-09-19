/*
 * Configuration du site vitrine — les DEUX seules valeurs à adapter au déploiement.
 *
 * espaceUrl : adresse de l'espace privé « Mon Espace » (application Laravel), sans « / » final.
 *   En développement : http://localhost:8010
 *   En production    : l'adresse publique de l'espace, par exemple https://espace.exemple.com
 *   Les liens « Mon Espace », « Devenir partenaire » et l'API du formulaire GSN s'y rattachent
 *   automatiquement (attribut data-espace, voir js/site.js).
 *
 * turnstileSiteKey : clé PUBLIQUE Cloudflare Turnstile (anti-robot du formulaire GSN).
 *   Laisser vide tant que Turnstile n'est pas activé côté espace (TURNSTILE_SECRET_KEY).
 */
window.SOLAVIH_CONFIG = {
    espaceUrl: 'http://localhost:8010',
    turnstileSiteKey: ''
};
