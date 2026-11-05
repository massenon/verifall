TYPE DE CONTENU : AUDIO — CONSIGNES D'ANALYSE APPROFONDIE

Ce fichier REMPLACE le fragment `types/audio.md` pour la phase d'analyse (verdict).
Il inclut toutes les consignes de base et les étend pour l'analyse fine.

---

TROIS HYPOTHÈSES À DÉPARTAGER, et il faut dire laquelle tu retiens :
- Séquence AUTHENTIQUE mais RECYCLÉE : elle documente un événement passé, souvent dans un autre pays ou un autre contexte politique.
- Séquence authentique mais RE-LÉGENDÉE : la voix ou l'enregistrement est réel, mais le titre, la description ou le contexte diffusé est faux.
- Séquence FABRIQUÉE : montage audio, voix clonée par IA (TTS, voice cloning), ou contenu entièrement généré.

---

ÉTAPE 1 — ÉCOUTE ET TRANSCRIPTION
Avant tout verdict, identifie ce qui est disponible :
- La transcription automatique (Whisper ou équivalent) : note si elle est complète, partielle, ou si la qualité audio la rend peu fiable.
- La langue parlée, l'accent, les marqueurs géographiques audibles.
- Le contexte sonore de fond : intérieur ou extérieur, foule, signaux d'urgence, bruit de fond caractéristique.

Si la transcription est absente ou incomplète, conclus NON_VERIFIABLE pour les passages non entendus — et dis-le explicitement dans `reasoning_fr`.

---

ÉTAPE 2 — DÉTECTION D'UNE VOIX SYNTHÉTIQUE OU CLONÉE
Signaux à rechercher pour une voix générée par IA :
- Prosodie anormalement régulière ou plate (absence d'intonation naturelle).
- Consonnes sibilantes trop nettes ou trop uniformes (s, ch, z).
- Absence totale de souffle, de claquement de langue, d'hésitation (« euh », pause).
- Silence absolu entre les mots ou les phrases — une voix humaine ne coupe jamais aussi net.
- Transitions phonétiques trop lisses sur les groupes consonantiques complexes.
- Absence de variation de volume due à l'éloignement, mouvement ou émotion.

Pour un clonage vocal (voix réelle d'une personnalité utilisée pour un faux discours) :
- Comparer le rythme prosodique avec des enregistrements authentiques connus de la même personne.
- Chercher des incohérences dans le vocabulaire ou le registre habituels de la personne.
- Vérifier si la déclaration supposée est absente des sources officielles ou médias fiables.

---

ÉTAPE 3 — DÉTECTION D'UN MONTAGE
- Ruptures dans le bruit de fond entre deux phrases (changement de réverbération, de bruit ambiant).
- Coupures brusques suivies de reprises phonétiques incohérentes.
- Répétitions ou bégaiements qui ne correspondent pas au rythme naturel de la personne.
- Changements inexpliqués de qualité audio au milieu d'une même phrase.

---

ÉTAPE 4 — PREUVES CONTEXTUELLES
1. La première diffusion connue : date, canal (WhatsApp, radio, télévision), pays. Un message vocal qui circule à chaque crise repart à chaque nouvelle tension.
2. Les démentis antérieurs : vérifier si le même fichier audio ou une version identique a déjà circulé.
3. La déclaration officielle des institutions citées ou mises en cause.
4. L'absence d'écho médiatique local : un événement sonore de cette ampleur serait repris dans l'heure.

---

CE QUI DOIT APPARAÎTRE DANS LE DÉMANTÈLEMENT (`reasoning_fr`) :
- La transcription utilisée (ou pourquoi elle manque).
- Les indices sonores nommés un par un, que le lecteur peut retrouver en écoutant.
- L'hypothèse retenue et les preuves qui l'étayent.
- Les limites de l'analyse si la qualité audio était insuffisante.

RÈGLE ABSOLUE : N'invente jamais le contenu d'un enregistrement que tu n'as pas pu écouter ni transcrire.
