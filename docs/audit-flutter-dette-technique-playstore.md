# Audit Flutter — Dette Technique & Play Store

**Vérifall Mobile · octobre 2026**

> Document de référence : <https://claude.ai/artifact/KDhXZ26osn9ibQ6t4yXVMg>

---

## 1. Résumé exécutif

Le codebase Flutter est en bonne santé pour une v1. L'architecture de composition (root pattern dans `main.dart`) est saine, les couches core/data/domain/presentation sont bien séparées, et les gardes de sécurité (expurgation, consentement RGPD, clés chiffrées) sont en place. La dette technique identifiée est principalement de l'ordre du raffinement — aucun blocage critique avant la publication Play Store.

---

## 2. Dette technique restante

### 2.1 Critique (avant Play Store)

| # | Fichier / zone | Problème | Effort |
|---|---|---|---|
| T-01 | `main.dart` | `flutter pub get` jamais exécuté en CI — le lockfile `pubspec.lock` n'est pas versionné. La reproductibilité de la build n'est pas garantie. | 1 h |
| T-02 | `lib/core/telemetry/` | `SentryFlutter.init()` n'est pas entouré d'un try/catch : une DSN malformée planterait le démarrage silencieusement. | 30 min |
| T-03 | `pubspec.yaml` | Version de `sentry_flutter` épinglée à `^8.11.0` sans date de révision — à revalider avant release. | 15 min |

### 2.2 Important (premier sprint post-v1)

| # | Fichier / zone | Problème | Effort |
|---|---|---|---|
| T-04 | `settings_page.dart` | `Sentry.close()` sur révocation ne vide pas le buffer en mémoire : des incidents en file peuvent partir après la révocation. Besoin de `Sentry.flush()` avant `close()`. | 1 h |
| T-05 | `signup_form_provider.dart` | Le `ConsentementTelemetrieStore().ecrire(true)` dans `soumettre()` est `unawaited` — une erreur de coffre sécurisé est silencieuse. Ajouter un log d'avertissement. | 30 min |
| T-06 | `rapporteur_de_plantage.dart` | Pas de limite sur la taille du message journalisé localement — un stack trace géant peut saturer le stockage sur appareil bas de gamme. | 2 h |
| T-07 | `lib/data/` | Aucune couverture de test unitaire sur les repositories. Les mappers JSON sont testés manuellement. | 4–8 h |

### 2.3 Raffinement (backlog)

| # | Fichier / zone | Problème | Effort |
|---|---|---|---|
| T-08 | Global | Pas de golden tests (screenshots) sur les widgets critiques — une régression visuelle passe inaperçue. | 4 h |
| T-09 | `app_config.dart` | `AppConfig.fromEnvironment()` ne valide pas le format de `telemetryDsn` : une URL non-Sentry serait acceptée silencieusement. | 1 h |
| T-10 | `identifiant_installation.dart` | L'identifiant UUID est recréé si `flutter_secure_storage` est vidé (désinstallation + réinstallation) — comportement attendu, mais non documenté. | 30 min |
| T-11 | `vf_colors.dart` | Tokens de couleur pour la section `id="resultat"` définis dans le PRD mais pas encore implémentés (`--vf-result-bg`, `--vf-card-*`). Bloquant pour le web Laravel uniquement. | 2 h |

---

## 3. Étapes Play Store (Android)

### Phase A — Prérequis compte (1 fois)

1. **Créer un compte Google Play Console** sur play.google.com/console — frais d'inscription uniques de 25 USD.
2. **Vérifier l'identité** du développeur (entreprise ou individu) — délai 1–3 jours ouvrés.
3. **Configurer le profil marchand** (coordonnées bancaires pour les futurs achats intégrés).

### Phase B — Préparation de l'application

4. **Définir `applicationId`** dans `android/app/build.gradle` : `com.verifall.mobile` (ou identifiant choisi) — **immuable après publication**.
5. **Générer le keystore de production** :
   ```bash
   keytool -genkey -v -keystore verifall-release.jks \
     -alias verifall -keyalg RSA -keysize 2048 -validity 10000
   ```
   Stocker dans un coffre (1Password, Bitwarden) — perte = perte de l'application.
6. **Configurer la signature** dans `android/key.properties` (hors git) et référencer dans `build.gradle`.
7. **Incrémenter `versionCode`** dans `pubspec.yaml` → propagé automatiquement par le build Flutter.
8. **Build de l'App Bundle** :
   ```bash
   flutter build appbundle --release \
     --dart-define=VF_API_BASE_URL=https://api.verifall.com \
     --dart-define=VF_TELEMETRY_DSN=https://xxx@sentry.io/yyy
   ```

### Phase C — Déclarations obligatoires (Play Console)

9. **Politique de confidentialité** : URL publique obligatoire. À créer sur le site web avant soumission.
10. **Déclaration de sécurité des données** (onglet "Sécurité des données") — à remplir pour :
    - Données collectées : identifiant d'installation anonyme, rapports de diagnostic (optionnels)
    - Données NON collectées : nom, e-mail, localisation, contacts
    - Chiffrement en transit : oui (HTTPS)
    - Suppression possible : oui (suppression de compte)
11. **Questionnaire contenu** : application destinée aux adultes, pas de contenu généré par l'utilisateur.
12. **Ciblage géographique** : Bénin + Afrique de l'Ouest → activer les pays voulus.

### Phase D — Soumission

13. **Créer la fiche Play Store** :
    - Nom : « Vérif All »
    - Description courte (80 car.) + description longue
    - Captures d'écran : 2 minimum par format (téléphone + tablette optionnel)
    - Icône 512×512 px, bannière 1024×500 px
14. **Uploader l'AAB** dans une piste de test interne ou fermée d'abord.
15. **Tester sur la piste interne** (jusqu'à 100 testeurs) — valider le parcours complet.
16. **Soumettre en production** — délai de révision : 1–7 jours.

### Phase E — Post-publication

17. **Activer Play App Signing** (Google gère le keystore de distribution) — recommandé pour simplifier les rotations futures.
18. **Configurer les alertes ANR/crash** dans Play Console → les relier à Sentry via le webhook si disponible.
19. **Planifier les mises à jour** : incrémenter `versionCode` à chaque release, conserver l'historique des changelogs.

---

## 4. Checklist de release v1

- [ ] T-01 : `pubspec.lock` versionné + CI flutter pub get
- [ ] T-02 : try/catch autour de `SentryFlutter.init()`
- [ ] T-03 : version sentry_flutter confirmée
- [ ] Keystore généré et sauvegardé hors dépôt
- [ ] `applicationId` définitif confirmé
- [ ] Politique de confidentialité en ligne
- [ ] Déclaration sécurité des données remplie
- [ ] Piste de test interne validée (parcours inscription → vérification → réglages)
- [ ] Captures d'écran Play Store préparées
- [ ] `versionCode: 1`, `versionName: 1.0.0`
