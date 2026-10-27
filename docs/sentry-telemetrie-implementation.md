# Sentry — Implémentation Télémétrie v1

**Vérifall Mobile Flutter · octobre 2026**

---

## Résumé

Intégration de Sentry (`sentry_flutter ^8.11.0`) avec consentement RGPD opt-in, conforme aux principes RGPD art. 7. La transmission n'a lieu que si l'utilisateur a explicitement donné son consentement **ET** que le DSN est configuré.

---

## Fichiers créés

### `lib/core/telemetry/consentement_telemetrie_store.dart` (NOUVEAU)

Persiste le consentement de télémétrie localement dans `flutter_secure_storage`.

- Clé : `vf_telemetrie_consent`
- **Complètement séparé** de `AuthConsentements` (qui part vers l'API)
- Méthodes : `lire()`, `ecrire(bool)`, `effacer()`

### `lib/core/telemetry/sentry_puits_de_telemetrie.dart` (NOUVEAU)

Implémente l'interface `PuitsDeTelemetrie` en utilisant le SDK Sentry.

- Reçoit un `Incident` déjà expurgé par `LogRedactor`
- Appelle `Sentry.captureEvent()` avec les champs mappés

---

## Fichiers modifiés

### `pubspec.yaml`

```yaml
# Télémétrie de plantage
sentry_flutter: ^8.11.0
```

### `lib/core/telemetry/telemetry_providers.dart`

Ajout de deux providers :

```dart
final Provider<ConsentementTelemetrieStore> consentementTelemetrieStoreProvider
final FutureProvider<bool> consentementTelemetrieLuProvider
```

### `lib/main.dart`

1. Lit le consentement depuis `ConsentementTelemetrieStore` au démarrage
2. Si consentement + DSN configuré → `SentryFlutter.init()` avec `attachFlutterErrorHandler: false`
3. Override des providers `consentementTelemetrieProvider` et `puitsDeTelemetrieProvider`

### `lib/presentation/features/auth/providers/signup_form_provider.dart`

- `EtatInscription` : nouveau champ `consentementTelemetrie: bool = false`
- `majConsentement()` : paramètre `telemetrie` ajouté
- `soumettre()` : persistance locale si consentement coché (ne voyage **pas** vers l'API)

### `lib/presentation/features/auth/pages/signup_page.dart`

4e case de consentement dans `_CarteConsentements` :

> « J'autorise l'envoi de rapports de diagnostic anonymes pour améliorer la stabilité de l'application. » *(facultatif)*

### `lib/presentation/features/profile/pages/settings_page.dart`

Section "Données personnelles" — interrupteur **Rapports de diagnostic** :

- Lit `consentementTelemetrieLuProvider`
- Révocation : `Sentry.flush()` + `Sentry.close()` + invalidation du provider

---

## Décisions d'architecture

| Décision | Raison |
|---|---|
| `attachFlutterErrorHandler: false` | `RapporteurDePlantage` est le seul handler — évite double capture et contourne l'expurgation `LogRedactor` |
| Consentement séparé de `AuthConsentements` | La décision télémétrie est locale à l'appareil, elle ne voyage pas vers le serveur |
| `SentryFlutter.init()` avant `ProviderContainer` | Le `RapporteurDePlantage.brancher()` s'installe juste après ; Sentry doit être prêt avant le premier incident possible |
| Opt-in non coché par défaut | RGPD art. 7 — consentement libre et éclairé |
| `enableAutoSessionTracking: false` | On ne track pas les sessions utilisateur — seuls les incidents anonymes partent |

---

## Variables d'environnement

| Variable | Utilisation |
|---|---|
| `VF_TELEMETRY_DSN` | DSN Sentry. Vide = Sentry désactivé même avec consentement. |

Injecté à la build :

```bash
flutter build appbundle --release \
  --dart-define=VF_TELEMETRY_DSN=https://xxx@sentry.io/yyy
```
