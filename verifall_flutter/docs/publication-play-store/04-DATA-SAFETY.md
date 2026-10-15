# Formulaire « Sécurité des données » (Google Play Console)

Référence éditoriale : `docs/flutter/07-LIVRAISON.md` §7.5 et §7.6, dépôt
`verifall_laravel` (« sous-déclarer est un motif de retrait »). Ce document
traduit ce que le code fait réellement, au 9 octobre 2026, dans le vocabulaire
exact des catégories du formulaire Play Console (section **App content → Data
safety**). Il ne remplace pas le jugement de la personne qui le remplit dans la
Console — c'est un relevé de faits, pas le formulaire rempli.

## Méthode

Pour chaque catégorie Play, trois questions : **la donnée est-elle collectée ?
Est-elle partagée avec un tiers ? Est-elle optionnelle ?** — et la preuve dans
le code.

## Informations personnelles

| Donnée | Collectée ? | Preuve dans le code | Partagée avec un tiers ? |
|---|---|---|---|
| **Adresse e-mail** | Oui | `auth_remote_datasource.dart` — connexion, inscription, réinitialisation de mot de passe | Non — reste sur l'API Vérif'All |
| Nom | Non constaté dans ce dépôt au-delà de ce que `lib/` échange avec `/auth/*` | — | — |
| Téléphone | Non constaté | — | — |

**Finalité à déclarer** : fonctionnalité de l'application (authentification du
compte). **Suppression** : l'écran `/compte-en-suppression` existe
(`lib/core/router/routes_profile.dart:123`, `vf_routes.dart:82`) — la
suppression de compte est bien **accessible depuis l'application**, exigence
explicite des deux magasins (§7.5).

## Contenu utilisateur

| Donnée | Collectée ? | Preuve | Note |
|---|---|---|---|
| **Texte soumis à vérification** (l'affirmation, le contexte) | Oui | Flux de soumission, `verify_page.dart` → API | C'est **le cœur du produit** ; `07-LIVRAISON.md` §7.6 l'exclut explicitement de toute télémétrie — seule l'API de vérification le reçoit, jamais un fournisseur de crash analytics |
| **Photos** | Oui, si l'utilisateur en soumet une | `ImagePicker().pickImage` dans `verify_page.dart` | Transmise au serveur pour analyse, pas conservée ailleurs côté client au-delà du cache de soumission hors ligne |
| **Vidéos** | Oui, si l'utilisateur en soumet une | `ImagePicker().pickVideo`, `video_player`/`chewie` pour la relecture | Idem |
| **Audio** | **Non** — fonctionnalité non câblée | `verify_page.dart` affiche « Import audio indisponible » | Rien à déclarer tant que ce n'est pas livré ; à revérifier à ce moment-là |

## Identifiants

| Donnée | Collectée ? | Preuve | Finalité |
|---|---|---|---|
| **Jeton d'authentification** | Oui, stocké | `lib/core/network/token_store.dart`, via `flutter_secure_storage` (coffre chiffré du système, jamais `SharedPreferences`) | Fonctionnalité de l'app — rester connecté |
| **Modèle de l'appareil** | Oui, envoyé au serveur | `lib/data/datasources/remote/auth/nom_appareil.dart`, via `device_info_plus` | Associé à l'émission d'un jeton (traçabilité des sessions actives, probablement affichée en Réglages) — **à confirmer côté produit avant de remplir le formulaire : vérifier si l'écran Réglages affiche une liste d'appareils connectés** |
| **Identifiant d'installation anonyme** | Généré, **mais pas transmis aujourd'hui** | `lib/core/telemetry/identifiant_installation.dart` | Pensé pour regrouper des incidents de plantage — `telemetryDsn` est vide dans toutes les configurations (`config/*.json`) et aucun fournisseur n'est branché (`PuitsDeTelemetrie` n'a aucune implémentation active, §7.6 du document de livraison). **Ne rien déclarer de transmis tant qu'aucun fournisseur n'est effectivement branché** ; ce tableau devra être corrigé le jour où `main.dart` en configure un |

## Ce qui n'est PAS collecté — vérifié, pas supposé

- **Localisation** : aucun package de géolocalisation dans `pubspec.yaml`, et
  l'arbitrage n° 9 du projet la refuse explicitement pour les sessions.
- **Contacts, calendrier, stockage externe** : aucun package correspondant.
- **Publicité / identifiants publicitaires** : aucun SDK publicitaire.
- **Analytique tierce** (Firebase Analytics, Facebook SDK, etc.) : absente de
  `pubspec.yaml`, vérifié le 9 octobre 2026.
- **Adresse IP en clair** : le back impose `IdentityHasher` côté serveur
  (§7.6) — rien à déclarer côté client, mais cette ligne concerne le
  dépôt `verifall_laravel`, pas celui-ci ; à confirmer que la pratique serveur
  tient toujours au moment de remplir le formulaire.

## Chiffrement en transit

`AppConfig.assertNoDeveloperSessionInProduction()`
(`lib/core/config/app_config.dart`) **refuse de démarrer l'application en
production si `apiBaseUrl` ne commence pas par `https://`** — ce n'est pas
une politique déclarée, c'est une assertion exécutée au démarrage. Répondre
« oui » à « les données sont-elles chiffrées en transit » est donc vérifiable
dans le code, pas une simple déclaration d'intention.

## Point à trancher avant de remplir le formulaire

**Paiement / mobile money.** `docs/flutter/07-LIVRAISON.md` et le reste de la
documentation du produit mentionnent un système d'unités et de recharge, mais
**aucun flux de paiement n'a été trouvé dans `lib/` de ce dépôt** au
9 octobre 2026 (aucune dépendance `in_app_purchase`, aucun datasource
mobile money côté Flutter). Si la recharge se fait aujourd'hui uniquement par
le site web (`verifall.com`), le formulaire de l'app n'a rien à déclarer sur
ce point ; si elle est prévue côté app avant la soumission, ce tableau devra
être complété avec les champs réellement envoyés (numéro de téléphone
opérateur, montant…) et une catégorie « Informations financières » s'ajoutera
au formulaire. **Cette décision n'est pas technique — elle revient à qui
pilote le périmètre de la première version publiée.**
