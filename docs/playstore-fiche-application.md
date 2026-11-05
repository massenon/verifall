# Fiche Play Store — Vérif All

> Document de référence pour la publication sur Google Play Console.
> Tous les textes ci-dessous sont prêts à copier-coller.

---

## Informations générales

| Champ | Valeur |
|---|---|
| **Nom de l'application** | `Vérif All` |
| **Package / applicationId** | `com.verifall.mobile` *(immuable après publication)* |
| **Catégorie principale** | Actualités et magazines |
| **Catégorie secondaire** | Éducation |
| **E-mail de contact** | `support@verifall.com` *(à créer)* |
| **URL politique de confidentialité** | `https://verifall.com/confidentialite` *(à mettre en ligne avant soumission)* |
| **Note de contenu** | Tout public (généré par IARC) |
| **Pays ciblés** | Bénin, Côte d'Ivoire, Sénégal, Togo, Niger, Burkina Faso, Cameroun — Afrique de l'Ouest francophone |

---

## Tags (5 maximum)

```
vérification  fact-checking  information  Bénin  citoyenneté
```

---

## Description courte *(80 caractères max.)*

```
Vérifiez l'information en un instant. La boussole citoyenne du Bénin.
```

*(69 caractères — dans la limite)*

---

## Description complète *(4 000 caractères max.)*

```
🛡️ VÉRIF ALL — LA BOUSSOLE CITOYENNE DE L'INFORMATION

Dans un monde saturé de rumeurs et de fausses nouvelles, Vérif All vous donne les outils pour distinguer le vrai du faux — rapidement, sans expertise requise.

✅ VÉRIFICATION INSTANTANÉE
Soumettez une affirmation, une photo ou un article. Nos équipes de vérification analysent et publient un verdict clair : Vrai, Faux ou Trompeur, avec les sources citées.

📚 COMPRENEZ LA DÉSINFORMATION
Accédez à des explications pédagogiques sur les mécanismes de la désinformation. Chaque vérification explique pourquoi une affirmation est fausse ou trompeuse.

🤝 UNE COMMUNAUTÉ ENGAGÉE
Rejoignez des milliers de citoyens béninois qui s'informent mieux. Partagez les vérifications, signalez les rumeurs, contribuez à un espace public plus sain.

🔒 VOS DONNÉES VOUS APPARTIENNENT
Vérif All respecte votre vie privée. Aucune donnée personnelle n'est vendue. Vous contrôlez vos consentements depuis les réglages. Conforme RGPD.

🌍 CONÇU POUR L'AFRIQUE DE L'OUEST
Interface pensée pour les connexions lentes. Fonctionne partiellement hors ligne. Adapté aux contextes béninois et ouest-africains.

✨ FONCTIONNALITÉS
• Vérifications en temps réel par des fact-checkers certifiés
• Historique personnel de vos recherches
• Notifications pour les rumeurs virales de votre région
• Partage facile vers WhatsApp et les réseaux sociaux
• Mode sombre inclus
• Application légère (< 20 Mo)

📲 COMMENCEZ DÈS MAINTENANT
Créez votre compte citoyen gratuit en moins de 2 minutes. 10 vérifications offertes à l'inscription.

---
Vérif All est développé par une équipe indépendante engagée pour la qualité de l'information en Afrique francophone. Nos sources et méthodologies sont publiques et auditables.
```

*(Longueur : ~1 750 caractères — dans la limite de 4 000)*

---

## Visuels requis

Voir `docs/visuals/specs-visuels-playstore.md` pour les spécifications détaillées.

| Visuel | Taille | Statut |
|---|---|---|
| Icône haute résolution | 512 × 512 px, PNG 32-bit | ⬜ À créer |
| **Feature Graphic (bannière)** | **1 024 × 500 px, JPEG/PNG** | ✅ `docs/visuals/banner-playstore.html` |
| Capture téléphone 1 — Accueil | 1 080 × 1 920 px | ⬜ À capturer |
| Capture téléphone 2 — Résultat VRAI | 1 080 × 1 920 px | ⬜ À capturer |
| Capture téléphone 3 — Résultat FAUX | 1 080 × 1 920 px | ⬜ À capturer |
| Capture téléphone 4 — Réglages | 1 080 × 1 920 px | ⬜ À capturer |

---

## Déclaration sécurité des données (Data Safety)

À remplir dans Play Console → onglet **Sécurité des données**.

### Données collectées

| Donnée | Type | Pourquoi | Chiffrement | Suppression |
|---|---|---|---|---|
| Identifiant d'installation | Identifiant d'appareil | Stabilité, diagnostic (opt-in) | ✅ Oui | ✅ À la déconnexion |
| Rapports de diagnostic | Données d'application | Amélioration de l'app (opt-in RGPD) | ✅ Oui | ✅ Sur demande |
| E-mail | Coordonnées | Authentification | ✅ Oui | ✅ Suppression de compte |
| Nom + prénom | Informations personnelles | Profil utilisateur | ✅ Oui | ✅ Suppression de compte |

### Données NON collectées

- ❌ Localisation précise ou approximative
- ❌ Contacts
- ❌ Messages
- ❌ Informations financières
- ❌ Données biométriques
- ❌ Données de santé

### Réponses au questionnaire

- Chiffrement en transit : **Oui** (HTTPS/TLS)
- Suppression des données possible : **Oui** (suppression de compte dans l'app)
- Partage avec des tiers : **Non** (sauf Sentry pour les diagnostics, opt-in uniquement)
- Contenu généré par l'utilisateur : **Oui** (vérifications soumises — modérées)

---

## Classification du contenu (IARC)

| Question | Réponse |
|---|---|
| Violence | Aucune |
| Contenu sexuel | Aucun |
| Grossièretés | Non |
| Substances contrôlées | Non |
| Jeux d'argent | Non |
| Contenu généré par les utilisateurs | Oui (texte uniquement, modéré) |
| **Classification attendue** | **Tout public (3+)** |

---

## Notes de publication (Changelog)

### v1.0.0 — Lancement initial

```
🎉 Vérif All est maintenant disponible !

• Inscription et connexion sécurisées
• Soumettez vos vérifications et recevez un verdict
• Historique de vos recherches
• Réglages RGPD complets (consentements granulaires)
• Mode sombre
```
