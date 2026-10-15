/*
 | Service worker de Vérif'All, engendré par ServiceWorkerController.
 |
 | ================================================================
 |  LA RÈGLE QUI PRIME SUR TOUTES LES AUTRES
 | ================================================================
 |
 | Rien de ce qui appartient à quelqu'un ne va dans le cache.
 |
 | Ce n'est pas de la prudence abstraite. Un rapport de vérification porte ce
 | que la personne a soumis ; sur un téléphone partagé, la norme, pas
 | l'exception, dans le public visé, un cache mal réglé rend ce rapport
 | lisible par le suivant, hors ligne, sans mot de passe, après déconnexion.
 |
 | La règle n'est PAS une liste d'adresses interdites. Une liste s'oublie à la
 | route suivante. On lit l'en-tête que le serveur pose déjà : le middleware
 | `SecureHeaders` répond `Cache-Control: no-store` sur toute réponse
 | authentifiée. Le serveur dit ce qui est privé ; le service worker obéit.
 |
 | Version : {{ $version }}, change dès qu'une ressource du socle change.
 */

const VERSION = '{{ $version }}';
const CACHE_SOCLE = `verifall-socle-${VERSION}`;
const CACHE_PAGES = `verifall-pages-${VERSION}`;
const PAGE_HORS_LIGNE = '{{ $pageHorsLigne }}';

const SOCLE = @json($socle, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).concat([PAGE_HORS_LIGNE]);

/* Combien de pages on garde. Au-delà, on jette la plus ancienne : un cache
   sans plafond finit par occuper des centaines de mégaoctets sur un téléphone
   qui n'en a pas. */
const PAGES_MAX = 40;

self.addEventListener('install', (evenement) => {
  evenement.waitUntil(
    caches.open(CACHE_SOCLE)
      /* `addAll` échoue en bloc si UNE ressource manque, et l'installation
         entière est perdue. On ajoute donc une par une, et un manque n'empêche
         pas le reste de fonctionner. */
      .then((cache) => Promise.all(
        SOCLE.map((url) => cache.add(url).catch(() => null)),
      ))
      .then(() => self.skipWaiting()),
  );
});

self.addEventListener('activate', (evenement) => {
  evenement.waitUntil(
    caches.keys()
      .then((noms) => Promise.all(
        noms
          .filter((nom) => nom.startsWith('verifall-') && !nom.endsWith(VERSION))
          .map((nom) => caches.delete(nom)),
      ))
      .then(() => self.clients.claim()),
  );
});

/**
 * Une réponse a-t-elle le droit d'être conservée ?
 *
 * Quatre conditions, toutes nécessaires. La troisième est celle qui protège
 * la vie privée, et elle vient du serveur, pas d'une liste tenue ici.
 */
function conservable(reponse) {
  if (!reponse || !reponse.ok) return false;
  /* Une réponse obtenue au bout d'une redirection n'est pas celle de l'adresse
     demandée : `/mon-espace/…` sans session aboutit à la page de connexion.
     La ranger sous l'adresse d'origine servirait, hors ligne, la connexion à
     la place du tableau de bord. */
  if (reponse.redirected) return false;
  /* `basic` = même origine. On ne conserve rien d'un tiers : on ne maîtrise ni
     sa durée de validité, ni ce qu'il renvoie. */
  if (reponse.type !== 'basic') return false;

  const controle = reponse.headers.get('Cache-Control') || '';
  if (controle.includes('no-store') || controle.includes('private')) return false;

  /* Une page qui pose ou renouvelle un cookie de session est, par définition,
     propre à quelqu'un. */
  if (reponse.headers.has('Set-Cookie')) return false;

  return true;
}

/**
 * Range une copie dans un cache, sans jamais faire échouer la réponse.
 *
 * Un cache plein (quota du téléphone) ou indisponible fait rejeter `put`.
 * Ce rejet ne doit ni remonter en « promesse non gérée », ni — pire — faire
 * croire à un échec réseau alors que le serveur a bien répondu.
 */
async function conserver(nomDuCache, requete, reponse) {
  try {
    const cache = await caches.open(nomDuCache);
    await cache.put(requete, reponse);
  } catch (echec) {
    /* Pas de cache cette fois-ci : la réponse, elle, part quand même. */
  }
}

/** Limite la taille d'un cache, en jetant les entrées les plus anciennes. */
async function plafonner(nomDuCache, maximum) {
  const cache = await caches.open(nomDuCache);
  const clefs = await cache.keys();
  if (clefs.length <= maximum) return;
  await Promise.all(clefs.slice(0, clefs.length - maximum).map((c) => cache.delete(c)));
}

self.addEventListener('fetch', (evenement) => {
  const requete = evenement.request;

  /* Seules les lectures. Une soumission de vérification, une connexion, un
     paiement : jamais. Rejouer une écriture depuis un cache est un moyen sûr
     de débiter quelqu'un deux fois. */
  if (requete.method !== 'GET') return;

  const url = new URL(requete.url);
  if (url.origin !== self.location.origin) return;

  /* Ni l'API, ni les téléchargements signés. Une URL signée EXPIRE : la garder
     revient à garder un lien mort, et à faire croire à un échec réseau. */
  if (url.pathname.startsWith('/api/') || url.searchParams.has('signature')) return;

  /* `wire:navigate` et `redirectRoute(…, navigate: true)` — la connexion mène
     ainsi au tableau de bord — chargent la page par un `fetch()` ordinaire :
     ni `mode: 'navigate'`, ni `Accept: text/html`, seulement l'en-tête
     `X-Livewire-Navigate`. Sans ce troisième test, une page authentifiée
     passait pour une feuille de style, en cache d'abord. */
  const estUnePage = requete.mode === 'navigate'
    || requete.headers.has('X-Livewire-Navigate')
    || (requete.headers.get('Accept') || '').includes('text/html');

  evenement.respondWith(estUnePage ? servirUnePage(requete) : servirUneRessource(requete));
});

/**
 * Une page : le réseau d'abord.
 *
 * Sur un site de fact-checking, une version périmée n'est pas un désagrément
 * mineur, un verdict a pu changer, un article a pu être corrigé. On ne sert
 * donc le cache que lorsque le réseau a réellement échoué.
 */
async function servirUnePage(requete) {
  let reponse;
  try {
    reponse = await fetch(requete);
  } catch (echec) {
    /* Seul un échec RÉSEAU arrive ici. Une 401, une 403, une 500 sont des
       réponses du serveur : elles repartent telles quelles, plus bas. */
    return pageDeSecours(requete);
  }

  if (conservable(reponse)) {
    await conserver(CACHE_PAGES, requete, reponse.clone());
    plafonner(CACHE_PAGES, PAGES_MAX).catch(() => null);
  }

  return reponse;
}

/**
 * Ce qu'on sert quand le réseau a échoué sur une page.
 *
 * Une page privée n'est jamais en cache (`no-store`, voir `conservable`) :
 * le tableau de bord ne peut donc pas être remplacé par une version ancienne,
 * ni par celle d'une autre personne. Il reçoit la page hors ligne — avec un
 * statut 503, qui dit la vérité : ce n'est pas la page demandée.
 */
async function pageDeSecours(requete) {
  try {
    const enCache = await caches.match(requete);
    if (enCache) return enCache;

    const horsLigne = await caches.match(PAGE_HORS_LIGNE);
    if (horsLigne) {
      return new Response(horsLigne.body, {
        status: 503,
        statusText: 'Service Unavailable',
        headers: horsLigne.headers,
      });
    }
  } catch (echec) {
    /* Cache illisible : on tombe sur la réponse de dernier recours. */
  }

  return indisponible('Vous êtes hors ligne, et cette page n’a pas encore été consultée sur cet appareil.');
}

/** La réponse de dernier recours : jamais un succès simulé, jamais en cache. */
function indisponible(message) {
  return new Response(message, {
    status: 503,
    statusText: 'Service Unavailable',
    headers: { 'Content-Type': 'text/plain; charset=UTF-8', 'Cache-Control': 'no-store' },
  });
}

/**
 * Une ressource : le cache d'abord.
 *
 * Une feuille de style ou une police ne change pas entre deux visites, et son
 * URL change quand son contenu change. La servir depuis le cache économise le
 * forfait sans jamais servir de version périmée.
 */
async function servirUneRessource(requete) {
  try {
    const enCache = await caches.match(requete);
    if (enCache) return enCache;
  } catch (echec) {
    /* Cache illisible : le réseau prend le relais. */
  }

  let reponse;
  try {
    reponse = await fetch(requete);
  } catch (echec) {
    /* Sans ce `catch`, le rejet remontait jusqu'à `respondWith` : « Uncaught
       (in promise) TypeError: Failed to fetch », et une erreur réseau brute
       pour la page. On répond 503, sans réessayer : un nouvel essai est
       l'affaire de la page, pas d'une boucle ici. */
    console.warn('[Service Worker] Échec réseau', { url: requete.url });
    return indisponible('Ressource momentanément indisponible.');
  }

  if (conservable(reponse)) {
    await conserver(CACHE_SOCLE, requete, reponse.clone());
  }

  return reponse;
}

/* La déconnexion doit effacer ce qui a été vu. La page l'annonce par un
   message ; sans cela, se déconnecter laisserait les pages en cache. */
self.addEventListener('message', (evenement) => {
  if (evenement.data === 'purge') {
    caches.keys().then((noms) => Promise.all(
      noms.filter((n) => n.startsWith('verifall-pages-')).map((n) => caches.delete(n)),
    ));
  }
});
