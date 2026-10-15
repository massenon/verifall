# Alternatives aux API payantes — image, audio, vidéo, texte généré

*Relevé du 25 août 2026. Les tarifs bougent ; chaque chiffre porte sa source.*

---

## La contrainte qui commande tout le reste

Avant de comparer quoi que ce soit, il faut poser la contrainte, parce qu'elle
élimine à elle seule la moitié des réponses qu'on trouve en ligne.

**La cible de déploiement est un hébergement mutualisé cPanel.** Pas de GPU, pas
de conteneur, pas de service long, souvent pas de processus en arrière-plan
au-delà d'une tâche planifiée. Voir `docs/DEPLOIEMENT_CPANEL.md`.

Conséquence : **« il suffit d'auto-héberger le modèle » n'est pas une option.**
La quasi-totalité des recommandations publiques — CLIP + base vectorielle,
EfficientNetV2 pour la vidéo, Whisper local, Ollama, LocalAI, BentoML — supposent
une machine qu'on administre et une carte graphique. Rien de cela n'existe ici.

Ce document ne retient donc que ce qui est **appelable par HTTP depuis PHP**, et
signale explicitement quand la seule réponse honnête est « pas sur cette
infrastructure ».

Deuxième contrainte, tout aussi structurante : **une analyse ne doit jamais
bloquer une requête web.** Un mutualisé coupe à 30 ou 60 secondes. Tout appel
externe passe par la file (`QUEUE_CONNECTION=database`) et un `queue:work`
déclenché par cron — c'est déjà l'architecture en place.

---

## 1. TinEye — antériorité d'une image

**Ce qu'on lui demande** : « cette photo est-elle apparue avant la date qu'on lui
prête ? » C'est LA question du fact-checking d'image. Une photo de 2018
republiée comme une actualité de 2026 est le mode de désinformation le plus
courant, et le prototype en donne lui-même l'exemple — la bagarre au Sénat
nigérian.

**Pourquoi c'est bloquant** : l'API TinEye démarre à **200 $/mois pour 5 000
recherches** ([Mixpeek](https://mixpeek.com/curated-lists/best-reverse-image-search-apis)).
À 40 F CFA la recherche, c'est plus cher que le pack d'unités qu'on vend.

### Ce qui existe

| Piste | Antériorité ? | Praticable sur mutualisé | Coût |
|---|---|---|---|
| **Google Custom Search — recherche d'image** | Partielle | **Oui** — déjà branché | 100 requêtes/jour gratuites, puis 5 $/1000 |
| **Bing Visual Search** | Partielle | Oui | Palier gratuit, puis à la requête |
| **Yandex** | Bonne sur les photos réelles | Non officiellement | — |
| **CLIP + base vectorielle** | Non (similarité, pas datation) | **Non** — GPU requis | — |

### Ce que je recommande, et ce que ça coûte en honnêteté

**Étendre `GoogleCustomSearchAdapter` à la recherche d'image plutôt qu'écrire un
`TinEyeAdapter`.** L'adaptateur existe, la clé existe, le quota gratuit de 100
requêtes par jour couvre l'usage réel d'un lancement.

**Mais il faut dire ce qu'on perd, et le dire à l'écran.** TinEye répond « cette
image existe depuis le 14 mars 2018 » ; Google répond « voici des pages où elle
apparaît ». La date de première apparition n'est **pas** donnée — elle se déduit
au mieux de la plus ancienne page trouvée, ce qui n'est pas la même chose.

Le moteur doit donc rendre un signal honnête : *« l'image apparaît sur N pages,
la plus ancienne datée du … »*, et non *« première apparition le … »*. Sur une
plateforme dont le métier est la rigueur, cette nuance n'est pas cosmétique.

> **Réserve à trancher par le porteur.** Si l'antériorité datée devient un
> argument commercial, TinEye n'a pas d'équivalent gratuit. C'est un arbitrage
> budgétaire, pas technique.

---

## 2. GPTZero — texte généré par IA

**Tarifs 2026** : offre gratuite à **10 000 mots/mois**, plans à partir de
**10 $/mois**, l'accès API arrivant au plan Professional à **45,99 $/mois**
([SpotSaaS](https://www.spotsaas.com/product/gptzero/pricing),
[EyeSift](https://www.eyesift.com/blog/ai-detector-pricing-2026/)).

### La question préalable : en a-t-on besoin ?

Il faut la poser avant de chercher un remplaçant, parce que la réponse change le
problème.

La documentation d'architecture pondère déjà les détecteurs d'IA à **0,5 —
« signal, pas verdict »** (`ADAPTATEURS_ORCHESTRATEUR_RAPPORT.md`). C'est la
bonne pondération, et elle est même généreuse : la détection de texte généré est
**peu fiable**, et son mode d'échec est asymétrique. Elle produit des faux
positifs sur les textes de locuteurs non natifs et sur les écrits scolaires —
c'est-à-dire, ici, sur une bonne partie du public visé. Accuser à tort un
étudiant béninois d'avoir fait écrire son texte par une machine est un dommage
concret, causé par un outil qu'on présentait comme une aide.

### Ce qui existe

- **ZeroGPT** — gratuit, sans compte, mais pas d'API publique documentée.
- **AI Text Detector** — jusqu'à 50 000 caractères par analyse, sans compte
  ([aitextdetector.ai](https://aitextdetector.ai/is-gptzero-free/)).
- **Modèles ouverts** (RoBERTa fine-tuné, Binoculars) — **hors de portée** sur
  mutualisé.

### Ce que je recommande

**Ne pas remplacer GPTZero. Le retirer du périmètre v1, et l'écrire.**

Trois raisons qui tiennent ensemble : la fiabilité est faible, le mode d'échec
frappe précisément notre public, et aucune alternative gratuite n'expose d'API
stable. Un moteur à 0,5 de poids qui coûte 46 $/mois et se trompe sur les bons
élèves n'a pas sa place dans une v1.

Ce que l'écran doit dire, en revanche : **« nous ne nous prononçons pas sur
l'origine du texte »**. C'est la règle §2 du chantier — ne jamais afficher un
indicateur qu'on ne mesure pas.

---

## 3. Hugging Face — le socle des modèles

**Tarifs 2026** : le palier gratuit donne **0,10 $/mois de crédit** sur les
fournisseurs d'inférence — soit quelques dizaines d'appels. Les *Inference
Endpoints* démarrent à **0,03 $/h en CPU** et **0,50 $/h en GPU**
([documentation officielle](https://huggingface.co/docs/inference-providers/en/pricing),
[eesel](https://www.eesel.ai/blog/hugging-face-pricing)).

**Lecture** : 0,50 $/h en continu font ~360 $/mois. Mais un *endpoint* sait
**redescendre à zéro** quand il ne sert pas. Pour un volume de lancement — cent
analyses par jour, quelques secondes chacune — la facture réelle se compte en
dollars, pas en centaines.

### Ce que je recommande

**Garder Hugging Face, mais en *Inference Endpoint* à l'échelle zéro, et non en
API serverless.** Le palier gratuit n'est pas un palier de production : 0,10 $
de crédit ne tient pas une journée.

Et surtout : **c'est le seul chemin qui rend l'audio et la vidéo possibles sans
GPU chez nous.** On déporte le calcul chez quelqu'un qui en a un, à la seconde.

---

## 4. Audio — ce qu'on peut vraiment faire

Deux questions différentes, qu'on confond souvent :

**(a) Que dit cet enregistrement ?** — la transcription. C'est le besoin réel
sur un audio WhatsApp : une fois transcrit, le texte repart dans toute la chaîne
de vérification déjà en place. **C'est de loin le meilleur rapport
valeur/effort du lot.**

| Piste | Praticable | Coût |
|---|---|---|
| **Whisper via Hugging Face Endpoint** | Oui | à la seconde de calcul |
| **Groq (Whisper large v3)** | Oui, très rapide | palier gratuit généreux |
| **Whisper local** | **Non** — pas de GPU | — |

**(b) Cette voix est-elle synthétique ?** — la détection d'hypertrucage audio.

Les outils existent et sont sérieux — DETECT-2B annonce 94–98 % sur 30 langues
([Resemble AI](https://www.resemble.ai/resources/audio-deepfake-detection-tools)),
DeepFake-O-Meter v2.0 est une plateforme ouverte
([arXiv](https://arxiv.org/pdf/2404.13146)). Mais ils supposent tous une machine
à soi.

**Recommandation** : **transcrire, oui ; détecter la synthèse, pas en v1.** Et
le dire à l'écran plutôt que de le laisser croire.

---

## 5. Vidéo — la réponse honnête est « pas encore »

**Aucune option praticable sur mutualisé.**

La détection d'hypertrucage vidéo demande une analyse image par image —
EfficientNetV2 + MTCNN dans les implémentations ouvertes
([dépôt de référence](https://github.com/Pranesh-2005/AI-Generated-Video-Detector)).
C'est du calcul lourd, sur un fichier lourd, avec un temps de traitement qui se
compte en minutes. Rien de tout cela n'entre dans un mutualisé, ni dans une file
qui doit rendre la main.

### Ce qui reste faisable, et qui est loin d'être négligeable

1. **Extraire des vignettes et les passer en recherche inversée.** Une vidéo
   « d'actualité » est très souvent une vidéo ancienne recontextualisée — pas un
   hypertrucage. C'est le cas majoritaire, et il se traite avec l'outillage
   image déjà décrit.
2. **Transcrire la bande son** et vérifier les affirmations. Même chaîne qu'en
   (a) ci-dessus.
3. **Lire les métadonnées** du conteneur, quand elles survivent au partage.

**Recommandation** : traiter la vidéo comme *image + audio*, et **écrire
noir sur blanc que l'hypertrucage vidéo n'est pas détecté**. C'est une limite
réelle ; l'annoncer vaut mieux qu'un verdict rassurant qui n'a rien vérifié.

---

## Récapitulatif des décisions proposées

| Service | Décision | Effet sur le produit |
|---|---|---|
| TinEye | **Remplacé** par Google Custom Search (image) | Pages où l'image apparaît, sans date de première apparition |
| GPTZero | **Retiré de la v1** | L'écran dit qu'on ne se prononce pas sur l'origine du texte |
| Hugging Face | **Gardé**, en *Endpoint* à l'échelle zéro | Ouvre l'audio ; coût à l'usage réel |
| Audio — transcription | **À faire** (Whisper via Groq ou HF) | Meilleur rapport valeur/effort du lot |
| Audio — hypertrucage | **Reporté** | Annoncé comme non couvert |
| Vidéo — hypertrucage | **Reporté** | Traitée comme image + audio |

**Aucune de ces décisions n'est technique au fond.** Ce sont des arbitrages
entre un budget, une infrastructure et ce qu'on promet à l'écran. Elles
appartiennent au porteur ; ce document sert à les prendre en connaissance de
cause, pas à les prendre à sa place.

Ce qui n'est pas négociable, en revanche : **ce qu'on ne mesure pas ne
s'affiche pas.** Un moteur retiré doit laisser à l'écran une phrase qui le dit,
pas un blanc qu'on interprète comme un feu vert.

---

## Sources

- [Best Reverse Image Search APIs in 2026 — Mixpeek](https://mixpeek.com/curated-lists/best-reverse-image-search-apis)
- [3 Best TinEye alternatives — lenso.ai](https://lenso.ai/en/blog/news/3-best-tineye-alternatives-for-reverse-image-search-in-2025)
- [GPTZero Pricing 2026 — SpotSaaS](https://www.spotsaas.com/product/gptzero/pricing)
- [AI Detector Pricing 2026 — EyeSift](https://www.eyesift.com/blog/ai-detector-pricing-2026/)
- [Is GPTZero Free? — AI Text Detector](https://aitextdetector.ai/is-gptzero-free/)
- [Pricing and Billing — Hugging Face](https://huggingface.co/docs/inference-providers/en/pricing)
- [Hugging Face pricing explained — eesel AI](https://www.eesel.ai/blog/hugging-face-pricing)
- [Top Deepfake Audio Detection Tools — Resemble AI](https://www.resemble.ai/resources/audio-deepfake-detection-tools)
- [DeepFake-O-Meter v2.0 — arXiv](https://arxiv.org/pdf/2404.13146)
- [AI-Generated-Video-Detector — GitHub](https://github.com/Pranesh-2005/AI-Generated-Video-Detector)
