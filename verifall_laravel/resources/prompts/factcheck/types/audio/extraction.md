TYPE DE CONTENU : AUDIO — CONSIGNES D'EXTRACTION DES AFFIRMATIONS

Ce fichier REMPLACE le comportement d'extraction générique pour les contenus audio.
Il guide la décomposition d'un enregistrement en affirmations vérifiables.

---

CONTEXTE DU FICHIER
Tu reçois la transcription automatique d'un enregistrement audio (message vocal, discours, extrait sonore).
La transcription peut être partielle ou approximative — travaille avec ce qui est disponible.

---

COMMENT EXTRAIRE LES AFFIRMATIONS D'UN AUDIO

1. BASE-TOI SUR CE QUI EST DIT, pas sur les métadonnées ou le titre accompagnant le fichier.
   Le titre d'un message WhatsApp est souvent la partie manipulée — la transcription est la source première.

2. DISTINGUE les types d'affirmations présents dans l'audio :
   - Ce que la voix affirme explicitement (faits, chiffres, accusations, annonces).
   - Ce que le contexte sonore sous-entend (lieu, événement, moment).
   - Ce que la description ou le titre du message prétend, en dehors de ce qui est dit.

3. SI LA TRANSCRIPTION EST PARTIELLE : indique-le dans `texte_original` en précisant le segment disponible.
   Ne construis pas d'affirmation à partir d'un passage inaudible ou absent de la transcription.

4. PORTÉE TEMPORELLE : les déclarations audio ont souvent une portée récente ou urgente (« il vient de se passer »).
   Classe-les en RECENT_VERIFIABLE si elles portent sur des faits récents susceptibles de vérification.

5. MAXIMUM 5 AFFIRMATIONS. Privilégie les plus précises et les plus vérifiables :
   - Une affirmation avec un lieu, une date ou un nom est plus vérifiable qu'une affirmation vague.
   - Extrais l'affirmation principale du discours, pas tous les sous-points.

---

FORMAT DE SORTIE IDENTIQUE AU STANDARD :
{
  "claims": [
    {
      "texte": "Affirmation reformulée clairement",
      "texte_original": "Extrait exact de la transcription dont elle est tirée",
      "acte_de_langage": "AFFIRMATION | QUESTION | OPINION | PREDICTION | NON_FACTUEL",
      "portee_temporelle": "RECENT_VERIFIABLE | HISTORIQUE | EVERGREEN | PREDICTION | NON_TEMPOREL",
      "claim_implicite": false
    }
  ]
}

Si la transcription est vide ou totalement illisible, retourne {"claims": []} sans inventer de contenu.
