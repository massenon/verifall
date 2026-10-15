# Ce que la documentation des fournisseurs apprend au module de vérification

Relevé le 10 septembre 2026 en dépouillant `C:\Projects\verifall\adaptateurs\`.
Chaque affirmation est adossée à une citation dans les rapports de
dépouillement ; ce document ne garde que les conséquences.

> **Le document a DEUX passes, et la seconde complète la première.** La partie
> ci-dessous porte sur les quatorze premiers fichiers (~420 Ko, Gemini et Custom
> Search). Le dossier est ensuite passé à quarante-trois fichiers (~2 Mo) : AI or
> Not, Winston AI, OpenAI, DeepSeek, Google Fact Check. Cette seconde passe est
> en fin de document, et elle **contredit un point** de la première — la retouche
> d'image, que Gemini ne sait pas juger mais qu'un autre fournisseur sait.

---

## Le constat qui domine tous les autres

**Cette documentation décrit une API que notre code n'appelle pas.**

Les quatorze fichiers décrivent l'**Interactions API** :

```
POST https://generativelanguage.googleapis.com/v1beta/interactions
en-tête  Api-Revision: 2026-05-20
corps    { model, input, tools, generation_config }
réponse  { steps: [ thought | google_search_call | model_output ] }
```

Notre `GeminiAdapter` appelle `generateContent` :

```
POST https://generativelanguage.googleapis.com/v1beta/models/{modele}:generateContent?key=…
corps    { contents, tools, generationConfig }
réponse  { candidates[0].content.parts[0].text, candidates[0].groundingMetadata }
```

Les mots `generateContent`, `candidates`, `groundingMetadata`, `groundingChunks`,
`response_mime_type` et `responseSchema` **n'apparaissent dans aucun des
quatorze fichiers**. La documentation ne dit donc ni que notre appel est juste,
ni qu'il est faux : elle parle d'autre chose.

Ce qu'il faut en retenir, et qui n'est pas une nuance : **rien de ce qui suit ne
justifie de réécrire l'adaptateur sur l'Interactions API sans un appel réel pour
l'éprouver.** Une migration décidée sur lecture seule remplacerait un code qui
fonctionne par un code qu'on croit juste.

---

## Ce qui a été corrigé, parce que c'était vérifiable dans NOTRE code

### 1. Le modèle de repli était mort, et muet

`gemini-1.5-flash` **n'existe plus**. Il ne figure nulle part au catalogue — pas
même dans « Previous models », qui recense pourtant la génération *suivante*
(2.0 Flash et 2.0 Flash-Lite, marqués « Shut down »). Un appel rend
`404 model_not_found`.

Le chemin exact du silence, tracé dans le code : le 404 n'était dans aucune liste
de codes traités, `successful()` valait faux, la boucle passait au modèle suivant
puis à la clé suivante — où le même 404 se reproduisait —, et l'adaptateur rendait
« rien » **sans une ligne de journal**. Le repli n'a jamais pris le relais et ne
le pouvait pas.

Corrigé : `gemini-2.5-flash-lite` (même génération que le principal, donc même
accès pour une clé qui fonctionne déjà), et `signalerSiModeleInconnu()` journalise
désormais tout 404 de modèle. La prochaine disparition se verra le jour même.

### 2. Une clé morte était confondue avec une clé saturée

`refuserSiQuota()` levait la même exception sur 401, 403 et 429. La documentation
pose la règle inverse : « Do not retry on client errors (like 400 or 403) as they
indicate issues like invalid API keys. »

Conséquence : une clé révoquée était mise au repos cinq minutes, puis réessayée,
indéfiniment, en étant comptée comme saine dans la rotation — et le journal disait
la même chose que pour un quota, donc personne ne la remplaçait.

Corrigé : `CleRefusee` distincte, repos de six heures, journal en `error` avec ce
qu'il faut faire.

### 3. Une réponse bloquée était un échec muet

Gemini rend **200 avec zéro candidat** quand il refuse de répondre pour raison de
sécurité (`blockReason`). Sur une plateforme de fact-checking, dont le métier est
précisément de soumettre des propos haineux ou violents à l'analyse, ce n'est pas
un cas limite : c'est un cas ordinaire. Il est désormais journalisé avec son
motif.

---

## Ce qui appartient au porteur du projet

### A. Custom Search ferme le 1er janvier 2027

> « The Custom Search JSON API is closed to new customers. »
> « Existing Custom Search JSON API customers have until **January 1, 2027** to
> transition to an alternative solution. »

Deux conséquences, et la seconde est la plus gênante :

1. `GoogleCustomSearchAdapter` a une date de péremption. Les alternatives
   nommées par Google sont Vertex AI Search (jusqu'à 50 domaines) ou une
   demande de contact pour la recherche web complète.
2. **L'API est fermée aux nouveaux clients.** Si la plateforme n'a pas déjà un
   compte Custom Search actif, ce moteur ne pourra pas être mis en service du
   tout — `.env.example` livre des clés vides pour un service auquel on ne peut
   plus s'inscrire.

À trancher avant d'investir davantage dans ce moteur.

### B. La transcription est réalisable, et rouvrirait deux types de contenu

C'est le manque le plus visible du produit : les boutons **Vidéo** et **Audio**
de l'écran citoyen sont inactifs, faute d'adaptateur `Transcription`.

Ce que la documentation établit :

| Point | Valeur |
|---|---|
| Formats audio | 13 types MIME : `audio/wav`, `mp3`, `aiff`, `aac`, `ogg`, `flac`, `mpeg`, `m4a`, `l16`, `opus`, `alaw`, `mulaw`, `webm` |
| Durée | 9,5 h par prompt sur un modèle généraliste ; 1 h sur le modèle dédié, ramenée à 30 min avec horodatages ou diarisation |
| Taille | 20 Mo pour la requête ENTIÈRE en inline ; Files API au-delà |
| Coût | 32 tokens par seconde d'audio, soit 1 920 par minute |
| Langue | détection automatique, ou imposée en BCP-47 |

Le mécanisme d'ouverture existe déjà et n'est pas à écrire :
`TypeContenu::moteurIndispensable()` rend `Transcription` pour vidéo et audio, et
`Verifier::moduleDisponible()` teste `estAllume()` **et**
`adaptateurReel() !== null`. Écrire l'adaptateur et allumer l'interrupteur rouvre
les deux boutons, sans toucher à l'écran.

**Deux obstacles, à ne pas découvrir en route :**

- **La vidéo n'est pas couverte.** Aucun type MIME vidéo n'est listé pour la
  transcription ; seule une URL YouTube l'est, sur le modèle généraliste. Pour
  un MP4 téléversé par un citoyen, il faudra soit vérifier le comportement réel,
  soit **extraire la piste audio côté serveur** (ffmpeg) avant l'envoi — ce qui
  ajoute une dépendance système sur un hébergement mutualisé.
- **Aucune langue nationale béninoise n'est listée.** Le français est présent en
  `fr-FR` seulement ; le yoruba est absent. La documentation ne dit rien du
  français d'Afrique de l'Ouest. Pour une plateforme béninoise, c'est une
  information de cadrage : la transcription servira le français standard, et ses
  limites sur les autres langues du pays sont inconnues.

### C. Deux promesses qu'il ne faut PAS faire

Ces deux points sont des garde-fous produit, pas des détails techniques.

**Gemini ne certifie ni la retouche, ni la provenance d'une image.** Recherche
faite sur les quatorze fichiers pour `manipulat|retouch|provenance|authentic|
deepfake|forensic|C2PA` : aucune occurrence pertinente. Le modèle décrit ce
qu'une image *montre*. Lui faire dire « cette photo a été retouchée » serait une
opinion présentée comme un constat — exactement ce que le §35 de la directive
éditoriale interdit, et ce que le produit combat.

**SynthID ne se lit pas depuis l'API publique.** Les deux seules mentions du
filigrane disent que les images *engendrées* en portent un. Aucune API, aucun
paramètre, aucun verbe `detect` ou `verify`. Vérif'All ne peut donc pas afficher
« générée par IA : oui/non » sur la foi de cette documentation.

### D. La migration vers l'Interactions API

Elle apporterait des citations liées au texte (`annotations` de type
`url_citation` avec index de début et de fin), là où notre lecture actuelle de
`groundingMetadata` rend une liste de sources sans savoir quelle phrase chacune
appuie. Pour un produit de fact-checking, c'est un gain réel.

Elle demande en revanche de réécrire l'envoi, la lecture, la gestion d'erreurs
et les tests de l'adaptateur, **et de le faire contre une API qu'on ne peut
éprouver qu'en appelant**. À décider, pas à improviser.

Un point à vérifier au passage, parce qu'il n'est tranché nulle part : l'outil
`google_search` combiné à une sortie JSON forcée
(`response_mime_type: application/json`), que notre code envoie ensemble. La
documentation ne l'autorise ni ne l'interdit ; elle décrit la réponse ancrée
comme de la **prose annotée**, ce qui laisse craindre que le JSON forcé soit
ignoré en silence — et notre `lireJson()` rendrait alors `null` sans erreur HTTP.
Un seul appel réel le dirait.

---

## Une erreur trouvée dans NOS propres documents

`docs/ADAPTATEURS_ORCHESTRATEUR_RAPPORT.md` et
`docs/instructions/ENV_EXAMPLE_ANNOTE.md` annoncent une variable
**`GOOGLE_CSE_KEY`**. Le code lit **`GOOGLE_CUSTOM_SEARCH_KEY`**
(`config/services.php`), et `.env.example` déclare bien celle-là.

Quelqu'un qui suit la documentation renseigne donc une variable que le code ne
lit jamais, et le moteur reste silencieusement non configuré — sans erreur, sans
message. Corrigé dans les deux documents.

---

# Seconde passe — 10 septembre 2026, 29 fichiers de plus

Le dossier est passé de 14 à 43 fichiers (~2 Mo). Cette section ne reprend pas
la première : elle ajoute ce que les nouveaux documents apprennent.

> **Un piège de méthode, à connaître avant d'ouvrir le dossier.** Plusieurs
> fichiers nommés « gemini … » contiennent en réalité de la documentation
> **OpenAI** — `gemini API Overview.txt`, `gemini Get a model response.txt`,
> `gemini Background mode.txt`, `gemini Conversation state.txt`,
> `gemini Streaming API responses.txt`. Deux paires sont par ailleurs des
> doublons exacts (`Get a model response` / `gemini Get a model response`, et
> les deux `Create image edit`). Se fier aux noms de fichiers ferait attribuer à
> Gemini des contrats qui sont ceux d'OpenAI.

## Ce qui a été livré

### Le détecteur d'IA existe enfin — `AiOrNotAdapter`

C'était le fournisseur déjà retenu par le projet, et sa clé attendait dans
`.env.example` depuis l'origine. La documentation le spécifie entièrement :
`https://api.aiornot.com`, `Authorization: Bearer`, trois endpoints
**synchrones** — `/v2/text/sync`, `/v2/image/sync`, `/v2/video/sync`.

Quatre choses ont décidé de l'écriture de cet adaptateur :

1. **Il ne rend JAMAIS de verdict.** `porteUnVerdict()` valait déjà `false` pour
   `DetecteurIA` ; l'adaptateur rend `verdict: null` et un score. Un détecteur
   dit qu'un texte *ressemble* à une production automatique, pas qu'il est faux.
2. **Le fournisseur ne publie aucun taux de faux positifs**, et ne dit **rien**
   des langues prises en charge — pas un mot sur le français. On ne peut donc
   pas le calibrer ; on peut seulement refuser de lui faire dire plus qu'il ne
   sait. C'est écrit dans le raisonnement que lit l'analyste.
3. **Le plancher de 250 caractères** est vérifié AVANT l'appel. La plupart des
   affirmations soumises tiennent en une phrase : sans cette garde, le cas
   ordinaire produisait un 422, une clé comptée en échec et un journal qui
   parlait de panne.
4. **Le score est un flottant 0–1**, pas 0–100. Lu tel quel, `0.42` devenait
   `0` — le signal le plus fort passait pour une absence de signal.

Pas de `sonde()` : le seul endpoint gratuit (`/v1/system/live`) **n'exige pas la
clé** et la documentation déconseille de l'appeler souvent. Il répond « le
service est allumé », pas « votre clé est acceptée ». Mieux vaut un état inconnu
qu'un état faux — c'est la leçon du `etat()` retiré le 1er septembre.

### Deux corrections de socle

- **402 « Payment Required »** est désormais traité comme un quota. Winston AI le
  rend quand les crédits sont épuisés ; il n'était couvert nulle part, et chaque
  analyse repayait un aller-retour pour se faire refuser de la même façon.
- **Le modèle DeepSeek n'est plus écrit en dur** (`DEEPSEEK_MODEL`). Il était le
  seul des moteurs dans ce cas.

## Ce qui appartient au porteur du projet

### `generateContent` est officiellement *legacy*

> « While it remains supported, the `generateContent` API is now considered
> legacy »

C'est l'API que notre `GeminiAdapter` appelle. Elle reste servie, **aucune date
de fin n'est donnée**, et rien n'oblige à bouger aujourd'hui. Mais la direction
est écrite : les ~20 exemples `curl` des documents Gemini appellent tous
`POST /v1beta/interactions`, avec `x-goog-api-key` et `Api-Revision: 2026-05-20`.

Deux gains à la migration, et ils ne sont pas cosmétiques :

- **Des citations liées au texte** — `annotations` de type `url_citation` avec
  index de début et de fin. Notre lecture actuelle rend une liste de sources
  sans savoir quelle phrase chacune appuie. Pour un produit de fact-checking,
  c'est la différence entre « voici des sources » et « voici ce qui appuie cette
  phrase-là ».
- **Le mode « background »** : `"background": true` rend immédiatement
  `{id, status: "in_progress"}`, et l'on relève le résultat par
  `GET /v1beta/interactions/{id}`. **C'est taillé pour notre déploiement** :
  sur un hébergement mutualisé, la file tourne par cron d'une minute avec
  `--max-time=55`, et une analyse longue ne peut pas tenir la connexion. Un job
  créerait la tâche et stockerait l'identifiant ; le passage suivant relèverait
  le résultat.

Ce que la migration coûte : réécrire l'envoi, la lecture, la gestion d'erreurs
et les tests — **contre une API qu'on ne peut éprouver qu'en l'appelant**.

Nos modèles (`gemini-2.5-flash`, `gemini-2.5-flash-lite`) sont absents des
exemples ; le modèle par défaut de facto y est `gemini-3.8-flash`.

### Chat Completions n'est PAS déprécié — pas d'urgence côté OpenAI

> « While Chat Completions remains supported, Responses is recommended for all
> new projects. »

Aucune date de fin de vie. Notre `OpenAiAdapter` peut rester tel quel. À savoir
tout de même, si l'on migre un jour : sous Responses, le JSON se force par
`text.format` (et non `response_format`), et le texte se lit dans `output[]`
filtré sur `type: message` puis `type: output_text` — `choices.0.message.content`
n'existe plus. Et **`store` vaut vrai par défaut** : les requêtes y sont
conservées au moins trente jours, ce qui mérite une décision pour du contenu
citoyen.

### Winston AI sait ce qu'aucun autre ne sait, et reste hors de portée

`POST /v2/advanced-image-detection` rend un champ **`authenticity`** valant
`Authentic` ou `Manipulated`, obtenu par analyse ELA, bruit et CFA. C'est le
**seul verdict de retouche d'image** de tout le dossier — exactement ce que la
première passe déclarait impossible avec Gemini. Le fournisseur lit aussi les
métadonnées **C2PA**.

Deux obstacles, et le premier est structurel :

1. **Ses endpoints image exigent une URL PUBLIQUE.** Nos fichiers vivent sur le
   disque `private`, servis par route signée — un choix de sécurité délibéré.
   Les exposer publiquement, même temporairement, est une décision de
   confidentialité, pas un détail d'intégration.
2. **300 à 500 crédits par image**, quand le fact-checker en coûte 2 par mot.

### Les conditions d'utilisation des API Google, et notre base

> « build databases, or otherwise create permanent copies of such content, or
> keep cached copies longer than permitted by the cache header »

`Preuve` persiste un **extrait de 300 caractères** du contenu rendu par les API
Google. Une lecture stricte du §5e l'interdit. Garder l'URL, l'éditeur et la
note — qui sont des références, pas du contenu — serait au-dessus de tout
soupçon. À arbitrer avec le porteur du projet ; ce n'est pas une décision
technique.

Le §3d demande par ailleurs que notre politique de confidentialité mentionne
l'envoi du texte soumis à Google, et le §6c interdit de laisser croire à un aval
de Google.

### ClaimReview : Google retire le tapis

> « We're phasing out support for ClaimReview markup in Google Search »

Le rich result n'est plus garanti ; seul l'Explorer subsiste. Notre balisage
porte bien les trois propriétés obligatoires, mais le flux `/claimreview.json`
que nous servons **n'est un mécanisme d'ingestion documenté nulle part** :
Google ne connaît que le balisage sur page, son Markup Tool, et l'API `pages`.
Notre commentaire de route parle d'une déclaration « chez Google » qui n'a pas
de répondant.
