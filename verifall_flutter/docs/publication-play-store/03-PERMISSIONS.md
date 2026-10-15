# Permissions Android

Référence éditoriale : `docs/flutter/07-LIVRAISON.md` §7.2, dépôt `verifall_laravel`
(« toute permission supplémentaire est un refus d'installation en puissance — et,
sur un produit de fact-checking, un soupçon »). Ce document vérifie l'état RÉEL,
pas l'état visé.

## Ce qui est déclaré aujourd'hui — et rien de plus

`android/app/src/main/AndroidManifest.xml` (manifeste de release, celui qui compte
pour le Play Store) :

```xml
<uses-permission android:name="android.permission.INTERNET"/>
<uses-permission android:name="android.permission.ACCESS_NETWORK_STATE"/>
```

- **`INTERNET`** : obligatoire, posée explicitement dans le manifeste principal
  (pas seulement `debug`/`profile` où Flutter la pose par défaut) — toute la
  donnée de l'app vient de l'API. Le commentaire en place dans le manifeste
  explique pourquoi cette ligne ne peut pas être oubliée : sans elle, un APK de
  release se lancerait normalement et échouerait sur **chaque** appel réseau,
  un mode de panne invisible en développement.
- **`ACCESS_NETWORK_STATE`** : pour `connectivity_plus`. Sert d'indice d'état
  réseau à l'interface, jamais de verrou — l'app n'empêche pas une tentative
  sans connexion, elle prévient que ça risque d'échouer.

Vérifié aussi : **aucun greffon du projet ne déclare de permission
supplémentaire dans son propre manifeste** — contrôlé le 9 octobre 2026 sur
`image_picker_android`, `video_player_android`, `share_plus`,
`url_launcher_android`, `device_info_plus`, `flutter_secure_storage`,
`connectivity_plus`. Le manifeste final (après fusion Gradle) ne contient donc
que les deux lignes ci-dessus.

## Le cas qui surprend : la caméra n'a pas besoin de permission `CAMERA`

`lib/presentation/features/verify/pages/verify_page.dart` appelle déjà
`ImagePicker().pickImage(source: ImageSource.camera)` et `pickVideo(...)` — la
capture photo/vidéo **est** codée, contrairement à ce qu'on pourrait supposer
en lisant seulement §7.2 de `07-LIVRAISON.md`, qui range cet usage sous
« écran 09, pas encore câblé ».

Et pourtant, aucune permission `CAMERA` n'apparaît nulle part. Ce n'est pas un
oubli : `image_picker` sur Android délègue la capture à l'application Appareil
photo du système par un **intent implicite**
(`MediaStore.ACTION_IMAGE_CAPTURE` / `ACTION_VIDEO_CAPTURE`) — c'est
l'application Caméra qui détient la permission, jamais l'appelant. C'est
exactement pourquoi le manifeste du plugin ne déclare rien : vérifié
directement dans son code, pas supposé.

**Conséquence pratique : ne pas ajouter `android.permission.CAMERA` à la main.**
L'ajouter sans l'utiliser directement (ex. via le greffon natif Android
`Camera2`) ferait échouer la revue Google sur la règle citée plus haut — une
permission déclarée doit être justifiable par un usage réel dans l'app.

## Ce qui N'EST PAS déclaré, et pourquoi c'est correct aujourd'hui

| Permission | Écran qui la justifierait (§7.2) | État du code au 9 octobre 2026 |
|---|---|---|
| `RECORD_AUDIO` (microphone) | Enregistrer une note vocale à vérifier | **Non câblé.** `verify_page.dart` affiche une boîte de dialogue « Import audio indisponible » dès que `TypeContenu.audio` est choisi — aucun code n'accède au microphone. |
| `POST_NOTIFICATIONS` | Prévenir qu'un verdict est prêt | **Non câblé.** `VF_FEATURE_NOTIFICATIONS=false` dans toutes les configurations (`config/*.json`) ; aucun appel à un plugin de notifications dans `pubspec.yaml`. |

**Chacune de ces deux lignes redevient à écrire le jour où l'écran
correspondant est livré** — ni avant (une permission inutilisée se refuse),
ni après coup dans la précipitation (une demande de permission ajoutée après
la première publication doit être justifiée dans la fiche du magasin au
prochain envoi).

## Ce qui ne doit jamais être ajouté

Repris de `07-LIVRAISON.md` §7.2, toujours vrai aujourd'hui : **localisation**
(arbitrage n° 9, refus explicite de géolocaliser les sessions), **contacts**,
**stockage externe**, **calendrier**. Aucun de ces quatre n'a de justification
dans le produit tel qu'il est écrit.

## La règle qui ne se vérifie pas dans un manifeste

« Les permissions sont demandées **au moment de l'usage**, jamais au
lancement » (§7.2) est un comportement d'exécution, pas une déclaration XML —
rien dans ce document ne le garantit statiquement. À revérifier manuellement à
l'écran au moment où une permission runtime sera effectivement demandée (ce
qui n'est pas encore le cas aujourd'hui, caméra comprise, puisqu'elle ne
demande rien directement).
