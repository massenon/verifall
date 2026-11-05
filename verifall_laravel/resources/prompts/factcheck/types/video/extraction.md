TYPE DE CONTENU : VIDÉO — CONSIGNES D'EXTRACTION DES AFFIRMATIONS

Ce fichier REMPLACE le comportement d'extraction générique pour les contenus vidéo.
Il guide la décomposition d'une séquence en affirmations vérifiables, en tenant compte
des deux canaux : le visuel et l'audio.

---

CONTEXTE DU FICHIER
Tu reçois la transcription automatique de la bande audio d'une vidéo (ce qui est dit),
et tu peux aussi t'appuyer sur l'analyse visuelle si elle est disponible.

---

COMMENT EXTRAIRE LES AFFIRMATIONS D'UNE VIDÉO

1. DISTINGUE LES DEUX SOURCES D'AFFIRMATIONS :
   a) Ce qui est DIT dans la vidéo (transcription) : discours, commentaire, narration.
   b) Ce qui est MONTRÉ dans la vidéo : lieu, événement, personnes, actions représentées.
   Ces deux sources peuvent se contredire — c'est souvent là que réside la manipulation.

2. LA DESCRIPTION OU LE TITRE n'est pas une source primaire : il peut être la partie manipulée.
   Mentionne-le comme `claim_implicite: true` si l'affirmation vient uniquement du titre ou de la légende.

3. COHÉRENCE AUDIO-VISUELLE : si ce qui est dit ne correspond pas à ce qui est montré,
   extrais les deux affirmations séparément — elles formeront deux claims distincts.

4. PORTÉE TEMPORELLE : les vidéos partagées ont souvent une portée récente ou urgente.
   Classe-les en RECENT_VERIFIABLE si elles portent sur des faits récents susceptibles de vérification.

5. MAXIMUM 5 AFFIRMATIONS. Priorité aux plus précises et vérifiables :
   - Affirmations avec lieu identifiable, date, personnalité nommée.
   - Affirmations sur des faits (événement, décision, chiffre) plutôt que des opinions.

---

FORMAT DE SORTIE IDENTIQUE AU STANDARD :
{
  "claims": [
    {
      "texte": "Affirmation reformulée clairement",
      "texte_original": "Extrait exact de la transcription ou description de ce qui est montré",
      "acte_de_langage": "AFFIRMATION | QUESTION | OPINION | PREDICTION | NON_FACTUEL",
      "portee_temporelle": "RECENT_VERIFIABLE | HISTORIQUE | EVERGREEN | PREDICTION | NON_TEMPOREL",
      "claim_implicite": false
    }
  ]
}

Si la vidéo n'a pas pu être analysée du tout, retourne {"claims": []} sans inventer de contenu.
