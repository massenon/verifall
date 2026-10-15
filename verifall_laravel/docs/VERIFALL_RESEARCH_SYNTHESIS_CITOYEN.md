# VÉRIF'ALL — SYNTHÈSE DE RECHERCHE · NOUVELLES FONCTIONNALITÉS COMPTE CITOYEN
> **Sources synthétisées** : personas et parcours de la base de connaissances (02_USER_PERSONAS, 03_USER_JOURNEYS, 00_PRODUCT_VISION), directives DA appliquées en vagues 1-11, signaux d'usage simulés dans les seeders du prototype (abandons LMS, file de signalements, référents du parrainage), et demandes produit exprimées en cours de projet (e-learning vidéo, gains de complétion, notification mensuelle). **Limite méthodologique assumée : aucun entretien utilisateur réel n'a encore été mené — cette synthèse structure les hypothèses à valider, elle ne les remplace pas.**

## 1. Thèmes extraits (fréquence × impact)

### T1 — « Prouvez-moi que ça vaut mon forfait data » (Sceptique Pressé) — fréquence ★★★ · impact ★★★
Signaux : persona primaire sur Android entrée de gamme et data facturée ; coût en unités par analyse ; leçons texte préférées à la vidéo dans les seeders. Douleur : chaque action doit justifier son coût (data + unités) *avant* d'être engagée.

### T2 — « Je veux une preuve de ma montée en compétence » (Apprenant Engagé) — ★★★ · ★★★
Signaux : demande explicite de simulation de gains post-cours ; certificats vérifiables déjà différenciants ; niveaux nommés (Novice→Gardien) très consultés dans recompenses. Douleur : la récompense arrive silencieusement — l'XP tombe sans cérémonie ni récapitulatif.

### T3 — « Aidez-moi à convaincre les miens » (Relais Communautaire) — ★★ · ★★★
Signaux : persona 3 partage dans des groupes WhatsApp familiaux ; le share center du parrainage est le composant le plus riche du compte ; le label VÉRIF'ALL CONFIRME est fait pour être montré. Douleur : un rapport de vérification ne s'exporte pas en format « transférable dans un groupe » (image compacte, hors-ligne).

### T4 — « Je décroche sans m'en rendre compte » — ★★ · ★★
Signaux : seeders d'abandon (progression <10 % après 14 j, « dernière activité il y a 19 j ») ; la série 🔥 récompense la présence mais rien ne rattrape l'absence. Douleur : aucun mécanisme de retour ciblé côté citoyen.

### T5 — « La vidéo m'apprend mieux, quand le réseau suit » — ★★ · ★★
Signaux : demande produit e-learning vidéo ; mix vidéo/texte dans les contenus mentor. Tension avec T1 : la vidéo doit rester optionnelle et annoncée (durée, poids).

## 2. Recommandations de fonctionnalités (priorisées RICE simplifié)

| # | Fonctionnalité | Thèmes | Effort | Priorité |
|---|---|---|---|---|
| F1 | **Cérémonie de complétion** : à 100 % cours + quiz réussis, écran récap « Vos gains » (+XP, +unités bonus, badge, certificat) avec confetti déjà existant et CTA « Cours suivant conseillé » | T2 | S | **P0** |
| F2 | **Rapport partageable** : export image 9:16 du verdict (logo, verdict coloré, ID de vérification, QR vers le rapport) via canvas — pensé pour WhatsApp | T3, T1 | M | **P0** |
| F3 | **Coût annoncé avant engagement** : sur chaque leçon vidéo « ▶ 11 min · ~18 Mo » + choix « Lire la version texte » ; sur la soumission d'analyse « il restera N unités » | T1, T5 | S | **P1** |
| F4 | **Cours e-learning vidéo** : player simulé (poster placeholder, progression de lecture comptant dans la complétion, reprise au timestamp) sur 2 nouveaux cours dont 1 premium | T5, T2 | M | **P1** |
| F5 | **Notification mensuelle « 1 cours gratuit »** : le 1er du mois, notification + carte dashboard « Votre cours offert du mois » (enrôlement 1 clic sur un cours gratuit mis en avant) | T2, T4 | S | **P1** |
| F6 | **Rappel de reprise intelligent** : après 7 j d'inactivité sur un cours entamé, notification « Reprenez à la leçon 2.3 (8 min restantes) » — lien profond Smart Resume existant | T4 | S | **P2** |
| F7 | **Mode économie de données** : réglage qui force texte d'abord, désactive l'autoplay et les covers haute résolution | T1 | M | **P2** |

## 3. Découpage de la prochaine vague prototype (F1+F4+F5 cohérentes ensemble)
1. Étendre `vf-store.js` : cours E (vidéo, gratuit) et F (vidéo, premium 12 u) avec `lessons[].type:'video'` + `duration` ; champ `rewards` par cours (+XP, +unités).
2. `lecon.html` : player simulé (poster = placeholder SVG, barre de progression cliquable, bouton « Marquer comme vue » alimentant la complétion, reprise du timestamp stocké).
3. `quiz.html` : au pass du dernier quiz d'un cours 100 %, redirection vers l'écran « Vos gains » (F1) au lieu du simple toast.
4. Dashboard + topbar : notification mensuelle simulée (date du store) « Votre cours offert d'août » → enrôlement direct (F5).
5. Garde-fous : les 56 tests du scénario C doivent passer inchangés ; nouveaux tests pour vidéo (progression, reprise) et cérémonie.

## 4. Questions ouvertes à trancher par de la vraie recherche (5 entretiens/persona suffisent)
- T1 : le double compteur (data + unités) est-il compris, ou faut-il fusionner la communication en « coût » unique ?
- T2 : la cérémonie doit-elle être bloquante (écran plein) ou célébrer sans interrompre (bandeau) ?
- T3 : l'image partagée doit-elle montrer le nom du citoyen vérificateur (fierté) ou rester anonyme (sécurité) ?
- F5 : « cours offert » choisi par l'équipe éditoriale ou personnalisé par l'historique ?
