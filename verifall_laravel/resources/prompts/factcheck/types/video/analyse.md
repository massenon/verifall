TYPE DE CONTENU : VIDÉO — CONSIGNES D'ANALYSE APPROFONDIE

Ce fichier REMPLACE le fragment `types/video.md` pour la phase d'analyse (verdict).
Il inclut toutes les consignes de base et les étend pour l'analyse fine audiovisuelle.

---

TROIS HYPOTHÈSES À DÉPARTAGER, et il faut dire laquelle tu retiens :
- Séquence AUTHENTIQUE mais RECYCLÉE : elle documente un événement passé, souvent dans un autre pays ou un autre contexte.
- Séquence authentique mais RE-LÉGENDÉE : les images sont réelles, le commentaire ou le titre diffusé est faux.
- Séquence FABRIQUÉE : montage vidéo, images générées, deepfake (remplacement de visage ou de voix), ou incrustations modifiées.

---

ÉTAPE 1 — ANALYSE VISUELLE
Identifie les indices permettant de situer la séquence dans l'espace et le temps :
- Enseignes et panneaux : langue, alphabet, noms de rues ou d'entreprises.
- Véhicules : marques, modèles, couleurs, plaques d'immatriculation (format et pays).
- Tenues et uniformes : emblèmes, couleurs, équipements caractéristiques d'un corps ou d'un pays.
- Architecture : style constructif, matériaux, végétation environnante, topographie.
- Conditions météo et luminosité : saison, heure estimée, conditions climatiques.
- Monnaie visible, affiches, journaux ou écrans dans le cadre.

---

ÉTAPE 2 — DÉTECTION DEEPFAKE (visage synthétisé)
Signaux visuels à rechercher :
- Synchronisation des lèvres imparfaite ou légèrement décalée par rapport à la parole.
- Clignements des yeux anormaux : trop rares, trop fréquents, ou non naturels.
- Contour du visage flou ou instable, particulièrement aux mouvements rapides.
- Cheveux, oreilles ou arrière du crâne qui manquent de netteté ou « fondent » dans l'arrière-plan.
- Teint ou éclairage facial incohérent avec le reste de la scène (ombres qui ne correspondent pas).
- Mouvements de tête rigides ou mécaniques.
- Dents ou yeux mal définis lors de gros plans.

Pour une voix clonée accompagnant la vidéo : appliquer les mêmes critères que `audio/analyse.md`.

---

ÉTAPE 3 — DÉTECTION D'UN MONTAGE VIDÉO
- Coupures brusques sans transition logique (lieu, lumière, foule qui change instantanément).
- Sons de fond incohérents entre deux plans censément continus.
- Métadonnées de compression incohérentes (qualité qui varie anormalement dans une séquence prétendue continue).
- Incrustations textuelles ou graphiques ajoutées après coup (typographie différente du reste de l'image).
- Plans « trop parfaits » pour une captation amateur (angle, stabilité, cadrage incompatibles avec le contexte décrit).

---

ÉTAPE 4 — PREUVES CONTEXTUELLES
1. La première diffusion connue : date, canal, pays. Recherche par image inversée ou correspondance avec d'autres archives.
2. Les démentis antérieurs : la même séquence a souvent déjà servi ailleurs.
3. Le communiqué officiel des forces de sécurité ou de l'institution concernée sur le lieu prétendu.
4. L'absence d'écho médiatique local : un événement de cette ampleur serait couvert dans l'heure.

---

CE QUI DOIT APPARAÎTRE DANS LE DÉMANTÈLEMENT (`reasoning_fr`) :
- LES INDICES VISUELS, nommés un par un : le lecteur doit pouvoir les retrouver sur la séquence.
- Les indices de deepfake ou de montage observés, avec leur localisation dans la vidéo si possible.
- L'hypothèse retenue et les preuves qui l'étayent.
- Les limites de l'analyse si la vidéo n'a pas pu être examinée visuellement.

RÈGLE ABSOLUE : N'invente jamais le contenu d'une séquence que tu n'as pas pu examiner.
