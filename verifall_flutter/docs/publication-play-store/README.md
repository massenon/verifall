# Publication sur le Google Play Store — Vérif'All

Ce dossier rassemble ce qu'il faut préparer pour soumettre l'application Android au
Play Store. Il ne remplace pas `docs/flutter/07-LIVRAISON.md` du dépôt
`verifall_laravel` — qui reste la référence éditoriale sur la signature, les
permissions, le versionnement et ce que les deux magasins vont demander — il le
**complète** avec des commandes exactes, des valeurs vérifiées dans ce dépôt au
9 octobre 2026, et des documents prêts à remplir ou à coller dans la Console Play.

**Principe directeur, identique à celui du reste du projet : on documente ce qui
est vrai aujourd'hui, pas ce qui est prévu.** Chaque affirmation de ce dossier a
été vérifiée dans le code au moment de l'écrire (manifeste, `build.gradle.kts`,
`pubspec.yaml`, dépendances) — pas recopiée d'un gabarit générique.

## Les fichiers

| Fichier | Contenu |
|---|---|
| `01-SIGNATURE-ET-KEYSTORE.md` | Générer le magasin de clés, activer la signature d'app Play, ce qui est déjà câblé dans `build.gradle.kts` |
| `02-VERSION-ET-BUILD-AAB.md` | Le numéro de version, comment le faire avancer, la commande exacte pour produire le bundle de publication |
| `03-PERMISSIONS.md` | Les permissions réellement déclarées aujourd'hui, pourquoi ce ne sont que deux, et ce qui changera le jour où l'audio sera câblé |
| `04-DATA-SAFETY.md` | De quoi remplir le formulaire « Sécurité des données » de la Console Play, point par point |
| `05-POLITIQUE-DE-CONFIDENTIALITE.md` | Brouillon de politique de confidentialité, à faire relire par un juriste avant publication — **ce n'est pas un document juridique validé** |
| `06-CAPTURES-ECRAN.md` | Les formats exigés par le Play Store, et l'état réel de ce qui peut être produit dans cet environnement aujourd'hui |
| `07-PUBLICATION.md` | La checklist de soumission, dans l'ordre |

## Ce qui est déjà prêt, vérifié dans ce dépôt

- `applicationId` : `com.verifall.verifall_app` (`android/app/build.gradle.kts:54`)
- Le mécanisme de signature (`android/key.properties`, non versionné) est câblé et
  **refuse de construire un bundle de publication sans magasin de clés** —
  `android/app/build.gradle.kts:116-147`. Il ne manque que le magasin de clés
  lui-même, qui est un secret d'infrastructure à générer et conserver hors du
  dépôt (voir `01-SIGNATURE-ET-KEYSTORE.md`).
- `config/prod.json` pointe déjà vers `https://verifall.com` — pas de problème de
  `localhost`/`10.0.2.2` à résoudre pour un build de publication, cette
  configuration n'étant utilisée qu'en développement local (`config/exemple.json`).
- Aucun SDK publicitaire, aucun SDK d'analytique tiers (Firebase, Sentry,
  Facebook, AdMob…) n'est présent dans `pubspec.yaml` — vérifié par recherche
  textuelle le 9 octobre 2026.
- Permissions déclarées dans le manifeste principal
  (`android/app/src/main/AndroidManifest.xml`) : **`INTERNET` et
  `ACCESS_NETWORK_STATE`, et rien d'autre.** Voir `03-PERMISSIONS.md` pour le
  détail, notamment pourquoi la capture photo/vidéo (déjà codée dans
  `verify_page.dart`) n'exige pas la permission `CAMERA`.
- Icône de l'application : remplacée le 9 octobre 2026 — l'icône par défaut du
  gabarit Flutter (le logo bleu « F ») a été remplacée partout
  (`mipmap-{m,h,xh,xxh,xxxh}dpi/ic_launcher.png`, plus une icône adaptative
  Android 8+ complète : `ic_launcher_foreground.png` par densité,
  `values/colors.xml`, `mipmap-anydpi-v26/ic_launcher.xml`) par le vrai
  logo-icône Vérif'All, tiré de `verifall_laravel/public/assets/images/logo-icon.png`.
- **Les 6 visuels de la fiche (5 captures téléphone + feature graphic) sont
  livrés** dans `visuels/` — voir `06-CAPTURES-ECRAN.md` pour leur provenance
  exacte et comment les régénérer.

## Ce qui reste à faire, nommé

- **Le magasin de clés n'existe pas encore.** Personne ne peut produire de
  bundle de publication tant qu'il n'est pas généré — commande exacte dans
  `01-SIGNATURE-ET-KEYSTORE.md`.
- **La politique de confidentialité est un brouillon**, pas un texte validé —
  elle doit être relue par quelqu'un d'habilité à engager l'organisation avant
  d'être publiée à une adresse réelle et déclarée dans la Console Play.
- **Le formulaire « Sécurité des données »** de `04-DATA-SAFETY.md` reflète ce
  que le code fait réellement aujourd'hui ; il devra être revérifié à chaque
  fois qu'une nouvelle donnée personnelle est collectée (audio, paiement
  mobile money, notifications).
