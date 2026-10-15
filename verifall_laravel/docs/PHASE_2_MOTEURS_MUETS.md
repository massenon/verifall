# Phase 2 — pourquoi les analyses ne concluent pas

Diagnostic du 10 septembre 2026, obtenu **depuis le poste de développement**,
sans accès au serveur : les clés du `.env` local sont celles qui ont servi en
production. `php artisan verifall:sonder-les-moteurs` interroge chaque
fournisseur pour de bon.

---

## Le symptôme

Le journal de production porte, sur deux vérifications, trois tentatives
chacune :

```
Analyse reprogrammée … « Trop peu de moteurs ont pu répondre pour conclure honnêtement. »
```

Le comportement du code est le bon : pas de rapport creux, unités remboursées,
reprise programmée. Ce sont les moteurs qui ne répondent pas.

---

## Ce que la sonde rend

| Moteur | État | Précision |
|---|---|---|
| Analyse sémantique (Gemini) | **Opérationnel** | 5 clés sur 5 acceptées |
| Contre-analyse (DeepSeek) | **Opérationnel** | |
| Raisonnement (OpenAI) | **Opérationnel** | |
| Recherche web citée (Perplexity) | **Clé absente** | |
| Fact-checks antérieurs (Google) | **Injoignable** | clé refusée (INVALID_ARGUMENT) |
| Presse béninoise et sources officielles (Google) | **Injoignable** | clé refusée (INVALID_ARGUMENT) |
| Vérification des entités (Knowledge Graph) | Non porté | |
| Analyse technique du domaine | Configuré | |
| Antériorité de l'image (TinEye) | Éteint | service payant, non souscrit |
| Lecture de l'image (Vision) | Éteint | clé Google refusée |
| Détection de contenu généré (AI or Not) | **Configuré** | écrit le 10 septembre |
| Transcription | Éteint | aucun adaptateur |

---

## La cause, et elle est UNE

**Une seule clé Google, morte, alimente quatre moteurs.**

`.env` la câble ainsi :

```
GOOGLE_API_KEY=<la clé>
GOOGLE_FACT_CHECK_KEY=<la même valeur, recopiée>
GOOGLE_CUSTOM_SEARCH_KEY=${GOOGLE_API_KEY}
```

Interrogée directement, elle rend :

```
400  API key not found. Please pass a valid API key.
     status = API_KEY_INVALID
```

Elle éteint donc, d'un seul coup : **Fact Check**, **Custom Search**,
**Knowledge Graph** et **Vision**.

### Le projet le savait à moitié

`MoteurAnalyse::raisonDeLEtatParDefaut()` porte, pour Vision :

> « ÉTEINT : dépend de `GOOGLE_API_KEY`, que Google refuse actuellement.
> Rallumez-le dès que la clé est régénérée. »

Le refus était donc connu. Ce que personne n'avait relié, c'est que **la même
clé alimente aussi Fact Check et Custom Search**, tous deux allumés par défaut.
Vision avait été éteint et documenté ; les deux autres ont continué d'être
appelés, d'échouer, et de ne rien dire.

### Pourquoi ça ne se voyait pas

Google rend **400** pour une clé invalide, quand tout le monde rend 401 ou 403.
Le 400 ne figurait dans aucune garde : `refuserSiQuota()` ne le voyait pas, la
clé n'était jamais marquée refusée, et l'échec ressemblait à n'importe quel
autre. Le diagnostic lui-même disait « le fournisseur refuse la clé **ou** ne
répond pas » — les deux cas mêlés, alors qu'ils n'appellent pas le même geste.

C'est corrigé : la sonde distingue désormais les deux et nomme le motif. Avec la
garde qui va avec, et son inverse — **un 400 ordinaire n'accuse pas la clé**,
sans quoi une requête maladroite mettrait une clé saine au repos six heures.

---

## Ce que ça change pour les rapports

Les trois moteurs qui répondent — Gemini, DeepSeek, OpenAI — sont des moteurs de
**raisonnement**. Les trois qui sont morts sont ceux qui apportent des
**sources** : fact-checks antérieurs, presse et portails officiels, recherche web
citée.

Le plancher de deux moteurs est tenu, donc un rapport sort. Mais il sort adossé
au raisonnement de trois modèles de langue, avec pour seul ancrage documentaire
la recherche intégrée de Gemini. Sur une plateforme dont la promesse est « un
verdict structuré, sourcé et explicable », c'est le sourçage qui manque.

En production, où la sortie réseau d'un mutualisé peut en écarter d'autres, le
plancher a cédé — d'où les reprises.

---

## Ce qu'il faut faire, dans l'ordre

0. **`APP_DEBUG=false` sur le serveur AVANT de régénérer quoi que ce soit.**

   Ce n'est pas une précaution de forme, et l'ordre n'est pas interchangeable.
   Avec `APP_DEBUG=true`, la page d'erreur de Laravel affiche la pile d'appels
   **et l'environnement** : chaque clé d'API, le mot de passe de la base, le
   secret de la passerelle de paiement. Régénérer d'abord et déployer ensuite
   **divulgue les nouvelles clés le jour même**, et l'on croit le problème
   réglé.

   Le point est ici, et pas seulement dans `MISE_EN_LIGNE.md` §0, à cause d'un
   fait précis : `/sitemap.xml` a rendu **500 neuf fois** en production. Si
   `APP_DEBUG` y valait `true`, ces neuf pages ont montré l'environnement à qui
   les demandait — ce qui donnerait au passage une explication à une clé Google
   qu'il faut renouveler.

   `php artisan verifall:diagnostic` refuse cette configuration en rouge.

1. **Régénérer la clé Google**, dans la console Cloud, et vérifier que les
   quatre API sont activées sur le projet qui la porte : Fact Check Tools,
   Custom Search, Knowledge Graph, Vision. Une clé valide dont l'API n'est pas
   activée rend le même 400.

   Pensez à la remplacer **aux deux endroits** : `GOOGLE_API_KEY` et
   `GOOGLE_FACT_CHECK_KEY`, cette dernière portant aujourd'hui une copie
   littérale de la même valeur. `GOOGLE_CUSTOM_SEARCH_KEY` suit toute seule,
   puisqu'elle vaut `${GOOGLE_API_KEY}`.
2. **Décider pour Perplexity** : la clé est vide et le moteur pèse 0,75 — autant
   que Gemini. Soit on souscrit, soit on l'éteint dans les réglages pour qu'il
   cesse de compter comme absent.
3. **Rejouer la sonde** après chaque changement :
   `php artisan verifall:sonder-les-moteurs`. Elle sort en échec tant qu'un
   moteur est injoignable, et nomme désormais le motif.

   Puis **`php artisan verifall:diagnostic`** pour le reste. Depuis le
   10 septembre 2026, il porte une section « Moteurs d'analyse » : il lit la
   dernière mesure — sans re-sonder, pour ne pas épuiser le quota qu'il
   surveille —, affiche son ÂGE, et fait **échouer** la commande si une clé est
   refusée ou si le plancher n'est plus tenu.

   Une seule commande dit donc désormais l'état de `APP_DEBUG`, de la file, du
   courriel, du planificateur ET des douze moteurs. C'est délibéré : la clé
   Google morte a survécu des semaines parce que son état vivait dans une
   seconde commande, que personne ne lançait.
4. **La rejouer SUR LE SERVEUR**, une fois le déploiement fait. Une clé peut
   être valide ici et refusée là-bas — restriction par adresse IP ou par
   référent, sortie réseau fermée. Ce diagnostic-ci vaut pour les clés, pas pour
   le réseau de l'hébergeur.

> **Rappel qui prime sur les quatre points ci-dessus.** Rien de tout cela
> n'atteint la production tant que la branche `chantier/lots-5-14` n'est pas
> fusionnée et déployée. Le serveur tourne une version antérieure au
> 2 septembre : il n'a ni les correctifs, ni ce diagnostic, ni la sonde qui le
> produit.
