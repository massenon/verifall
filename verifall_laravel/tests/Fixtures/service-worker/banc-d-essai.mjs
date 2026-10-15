/*
 | Banc d'essai du service worker — exécute le VRAI `/sw.js`, tel que Laravel le
 | rend, dans Node (≥ 18), avec `fetch`, `caches` et `self` simulés.
 |
 | Lancé par tests/Feature/Public/ServiceWorkerComportementTest.php ; à la main :
 |   node tests/Fixtures/service-worker/banc-d-essai.mjs <sw.js> [origine]
 |
 | Il couvre ce qu'une assertion sur le texte du fichier ne peut pas voir : le
 | chemin réellement emprunté par une requête, les rejets de promesse, ce qui
 | finit — ou non — dans le cache.
 */
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const ORIGINE = process.argv[3] || 'https://verifall.com';
const source = readFileSync(process.argv[2], 'utf8');

let rejetsNonGeres = 0;
process.on('unhandledRejection', (e) => { rejetsNonGeres++; console.log('   ! rejet non géré :', String(e)); });

function fabriquer() {
  const stores = new Map(); // nom -> Map(url -> Response)
  let quotaPlein = false;
  const ouvrir = (nom) => {
    if (!stores.has(nom)) stores.set(nom, new Map());
    const m = stores.get(nom);
    const clef = (r) => (typeof r === 'string' ? new URL(r, ORIGINE).href : r.url);
    return {
      async match(r) { const x = m.get(clef(r)); return x ? x.clone() : undefined; },
      async put(r, rep) { if (quotaPlein) throw new DOMException('quota', 'QuotaExceededError'); m.set(clef(r), rep); },
      async add(u) { const rep = await sandbox.fetch(new Request(new URL(u, ORIGINE))); if (!rep.ok) throw new TypeError('add'); m.set(clef(u), rep); },
      async keys() { return [...m.keys()].map((u) => new Request(u)); },
      async delete(r) { return m.delete(clef(r)); },
    };
  };
  const caches = {
    async open(n) { return ouvrir(n); },
    async keys() { return [...stores.keys()]; },
    async delete(n) { return stores.delete(n); },
    async match(r) { for (const n of stores.keys()) { const x = await ouvrir(n).match(r); if (x) return x; } return undefined; },
  };
  const ecouteurs = {};
  let reseau = async () => { throw new TypeError('Failed to fetch'); };
  const sandbox = {
    console: { warn() {}, log: console.log }, URL, Request, Response, Headers, DOMException, Promise,
    caches,
    fetch: (r) => reseau(r),
    self: {
      location: new URL(ORIGINE + '/sw.js'),
      addEventListener: (t, f) => { ecouteurs[t] = f; },
      skipWaiting: async () => {}, clients: { claim: async () => {} },
    },
  };
  vm.createContext(sandbox);
  vm.runInContext(source, sandbox);

  return {
    stores, caches,
    setReseau(f) { reseau = f; },
    setQuotaPlein(v) { quotaPlein = v; },
    async evenement(type, extra = {}) {
      let p; const ev = { ...extra, waitUntil: (x) => { p = x; } };
      ecouteurs[type](ev); await p;
    },
    async requete(req) {
      let promesse = null;
      ecouteurs.fetch({ request: req, respondWith: (p) => { promesse = Promise.resolve(p); } });
      if (!promesse) return { intercepte: false };
      try { return { intercepte: true, reponse: await promesse }; }
      catch (e) { return { intercepte: true, rejet: e }; }
    },
  };
}

/** Réponse « serveur » de même origine (type basic), éventuellement redirigée. */
function serveur(corps, { status = 200, headers = {}, redirige = false } = {}) {
  const r = new Response(corps, { status, headers });
  Object.defineProperty(r, 'type', { value: 'basic' });
  Object.defineProperty(r, 'redirected', { value: redirige });
  const cloner = r.clone.bind(r);
  r.clone = () => { const c = cloner(); Object.defineProperty(c, 'type', { value: 'basic' }); Object.defineProperty(c, 'redirected', { value: redirige }); return c; };
  return r;
}

const PRIVE = { 'Content-Type': 'text/html', 'Cache-Control': 'no-store, no-cache, must-revalidate, private' };
const PUBLIC = { 'Content-Type': 'text/html', 'Cache-Control': 'max-age=60' };
const DASH = ORIGINE + '/mon-espace/tableau-de-bord';
const livewire = (u) => new Request(u, { headers: { 'X-Livewire-Navigate': '' } }); // = performFetch de Livewire
const navigation = (u) => { const r = new Request(u, { headers: { Accept: 'text/html' } }); Object.defineProperty(r, 'mode', { value: 'navigate' }); return r; };

let echecs = 0;
const verifier = (nom, ok, detail = '') => { console.log(`${ok ? 'PASS' : 'FAIL'}  ${nom}${detail ? ' — ' + detail : ''}`); if (!ok) echecs++; };
const contenu = async (sw) => [...sw.stores.values()].flatMap((m) => [...m.keys()]);

async function preparer() {
  const sw = fabriquer();
  sw.setReseau(async (r) => {
    const u = new URL(r.url).pathname;
    if (u === '/hors-ligne') return serveur('<h1>Pas de connexion</h1>', { headers: PUBLIC });
    if (u.startsWith('/assets/')) return serveur('/* css */', { headers: { 'Content-Type': 'text/css' } });
    throw new TypeError('Failed to fetch');
  });
  await sw.evenement('install');
  await sw.evenement('activate');
  return sw;
}

// --- 1. Session valide, réseau OK, chargement Livewire (connexion -> tableau de bord)
{
  const sw = await preparer();
  // Une vieille entrée sous la même adresse ne doit PAS être servie (réseau d'abord).
  (await sw.caches.open('verifall-pages-ancienne')).put(DASH, serveur('ANCIEN', { headers: PUBLIC }));
  sw.setReseau(async () => serveur('DASHBOARD-FRAIS', { headers: PRIVE }));
  const r = await sw.requete(livewire(DASH));
  const corps = r.reponse && await r.reponse.text();
  verifier('1. tableau de bord via wire:navigate : réseau d\'abord, réponse fraîche', corps === 'DASHBOARD-FRAIS', String(corps));
  const pages = [...sw.stores.entries()].filter(([n]) => !n.endsWith('ancienne')).flatMap(([, m]) => [...m.keys()]);
  verifier('1c. aucune page privée dans les caches du SW', !pages.includes(DASH), pages.join(','));
}
// --- 1d. Navigation classique
{
  const sw = await preparer();
  sw.setReseau(async () => serveur('DASHBOARD', { headers: PRIVE }));
  const r = await sw.requete(navigation(DASH));
  verifier('1d. navigation classique vers le tableau de bord', r.reponse?.status === 200 && !(await contenu(sw)).includes(DASH));
}
// --- 2/3. Sans session : redirection suivie vers la connexion (fetch Livewire)
{
  const sw = await preparer();
  sw.setReseau(async () => serveur('<form>connexion</form>', { headers: PUBLIC, redirige: true }));
  const r = await sw.requete(livewire(DASH));
  verifier('2. sans session : la page de connexion est rendue', (await r.reponse.text()).includes('connexion'));
  verifier('2b. la connexion n\'est pas rangée sous l\'adresse du tableau de bord', !(await contenu(sw)).includes(DASH));
}
// --- 3/4/5. 401, 403, 500 : préservés, jamais conservés
for (const status of [401, 403, 500]) {
  const sw = await preparer();
  sw.setReseau(async () => serveur(`E${status}`, { status, headers: PUBLIC }));
  for (const [nom, req] of [['navigation', navigation(DASH)], ['livewire', livewire(DASH)], ['ressource', new Request(ORIGINE + '/assets/x.css')]]) {
    const r = await sw.requete(req);
    verifier(`${status} (${nom}) : statut préservé, non conservé`, r.reponse?.status === status && !(await contenu(sw)).includes(req.url), String(r.reponse?.status ?? r.rejet));
  }
}
// --- 6. Échec réseau réel
{
  const sw = await preparer();
  sw.setReseau(async () => { throw new TypeError('Failed to fetch'); });
  const nav = await sw.requete(navigation(DASH));
  verifier('6a. navigation hors ligne : page hors ligne, statut 503 (pas de 200 simulé)', nav.reponse?.status === 503 && (await nav.reponse.text()).includes('Pas de connexion'), String(nav.reponse?.status ?? nav.rejet));
  const lw = await sw.requete(livewire(DASH));
  verifier('6b. wire:navigate hors ligne : pas de rejet, 503', !lw.rejet && lw.reponse?.status === 503, String(lw.reponse?.status ?? lw.rejet));
  const res = await sw.requete(new Request(ORIGINE + '/assets/images/inconnue.png'));
  verifier('6c. ressource hors ligne non en cache : 503 contrôlée, pas de rejet', !res.rejet && res.reponse?.status === 503, String(res.reponse?.status ?? res.rejet));
  const css = await sw.requete(new Request(ORIGINE + '/assets/css/verifall.css'));
  verifier('6d. ressource du socle servie hors ligne depuis le cache', css.reponse?.status === 200);
  // Pas de page hors ligne en cache du tout
  const sw2 = fabriquer();
  const nu = await sw2.requete(navigation(DASH));
  verifier('6e. sans aucun fallback : 503 texte, no-store', nu.reponse?.status === 503 && nu.reponse.headers.get('Cache-Control') === 'no-store', String(nu.reponse?.status ?? nu.rejet));
}
// --- 7. Ressources statiques
{
  const sw = await preparer();
  let appels = 0;
  sw.setReseau(async (r) => { appels++; return new URL(r.url).pathname.endsWith('404.png') ? serveur('', { status: 404 }) : serveur('img', { headers: { 'Content-Type': 'image/png' } }); });
  await sw.requete(new Request(ORIGINE + '/assets/images/a.png'));
  const deux = await sw.requete(new Request(ORIGINE + '/assets/images/a.png'));
  verifier('7a. image : cache d\'abord au second passage', appels === 1 && deux.reponse.status === 200);
  const nf = await sw.requete(new Request(ORIGINE + '/assets/images/404.png'));
  verifier('7b. 404 préservée et non conservée', nf.reponse.status === 404 && !(await contenu(sw)).includes(ORIGINE + '/assets/images/404.png'));
  sw.setQuotaPlein(true);
  const q = await sw.requete(new Request(ORIGINE + '/assets/js/b.js'));
  verifier('7c. quota plein : la ressource est servie quand même', q.reponse?.status === 200, String(q.reponse?.status ?? q.rejet));
  const qp = await sw.requete(new Request(ORIGINE + '/actualites', { headers: { Accept: 'text/html' } }));
  const corpsQp = qp.reponse ? await qp.reponse.text() : '';
  verifier('7d. quota plein sur une page publique : réponse réseau, pas la page hors ligne', qp.reponse?.status === 200 && corpsQp === 'img', corpsQp);
}
// --- 8. API, écritures, URL signées : non interceptées
{
  const sw = await preparer();
  for (const [nom, req] of [['API', new Request(ORIGINE + '/api/v1/me')], ['POST', new Request(ORIGINE + '/livewire/update', { method: 'POST', body: '{}' })], ['signée', new Request(ORIGINE + '/mon-espace/supports/x?signature=abc')]]) {
    verifier(`8. ${nom} non interceptée`, (await sw.requete(req)).intercepte === false);
  }
}
// --- 8b. Les autres requêtes Livewire gardent leur traitement ordinaire
{
  const sw = await preparer();
  let appels = 0;
  sw.setReseau(async () => { appels++; return serveur('/* livewire */', { headers: { 'Content-Type': 'application/javascript' } }); });
  const script = ORIGINE + '/livewire/livewire.js?id=abc';
  await sw.requete(new Request(script));
  const second = await sw.requete(new Request(script));
  verifier('8b. livewire.js (sans X-Livewire-Navigate) : ressource, cache d\'abord', appels === 1 && second.reponse?.status === 200, `${appels} appel(s) réseau`);
}
// --- 2c. Navigation classique sans session : la redirection opaque repart telle quelle
{
  const sw = await preparer();
  sw.setReseau(async () => { const r = new Response(null, { status: 200 }); Object.defineProperty(r, 'type', { value: 'opaqueredirect' }); return r; });
  const r = await sw.requete(navigation(DASH));
  verifier('2c. redirection de navigation (opaqueredirect) transmise, jamais conservée', r.reponse?.type === 'opaqueredirect' && !(await contenu(sw)).includes(DASH));
}
// --- 9. Fuite entre sessions : purge à la déconnexion
{
  const sw = await preparer();
  sw.setReseau(async () => serveur('PUBLIQUE', { headers: PUBLIC }));
  await sw.requete(navigation(ORIGINE + '/actualites'));
  const avant = (await sw.caches.keys()).some((n) => n.startsWith('verifall-pages-'));
  await sw.evenement('message', { data: 'purge' });
  await new Promise((r) => setTimeout(r, 10));
  const apres = (await sw.caches.keys()).some((n) => n.startsWith('verifall-pages-'));
  verifier('9. purge à la déconnexion efface les pages', avant && !apres);
}
// --- 11. Mise à jour : seuls les caches d'une autre version sont supprimés
{
  const sw = fabriquer();
  await sw.caches.open('verifall-socle-ancienne0000');
  await sw.caches.open('autre-appli');
  await sw.evenement('activate');
  const noms = await sw.caches.keys();
  verifier('11. activate : anciens caches Vérif\'All supprimés, les autres gardés', !noms.includes('verifall-socle-ancienne0000') && noms.includes('autre-appli'), noms.join(','));
}

await new Promise((r) => setTimeout(r, 20));
verifier('10. aucun rejet de promesse non géré', rejetsNonGeres === 0, `${rejetsNonGeres} rejet(s)`);
console.log(echecs ? `\n${echecs} échec(s)` : '\nTout est vert');
process.exit(echecs ? 1 : 0);
