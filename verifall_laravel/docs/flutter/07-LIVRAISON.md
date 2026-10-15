# 7. Livraison

## 7.1 Signature

### Android

- Un **magasin de clés de publication** distinct de la clé de débogage, conservé
  hors du dépôt. La perdre, c'est perdre la capacité de mettre à jour
  l'application — les utilisateurs installés ne recevront plus jamais de version.
  Elle se sauvegarde comme un secret d'infrastructure, pas comme un fichier de
  projet.
- Les identifiants de signature arrivent par variables d'environnement ou par un
  fichier de propriétés **non versionné**. Le §5 des règles du chantier —
  « jamais de secret en dur » — ne s'arrête pas à PHP.
- **Signature d'application Play** activée : Google conserve la clé de
  distribution, on ne détient que la clé de téléversement, et une clé de
  téléversement perdue se remplace. C'est le seul filet contre l'accident
  ci-dessus.
- Format de publication : **App Bundle**, pas APK.

### iOS

- Identifiants et profils gérés automatiquement quand c'est possible ; sinon un
  profil de distribution dédié.
- Les certificats vivent dans un trousseau partagé chiffré, jamais dans le dépôt.
- **Aucune capacité déclarée qui ne soit utilisée.** Chaque droit demandé est un
  motif de rejet et une question de plus à la revue.

## 7.2 Permissions et ce qu'il faut en dire

L'application n'a besoin que de ce que ses écrans emploient. Toute permission
supplémentaire est un refus d'installation en puissance — et, sur un produit de
fact-checking, un soupçon.

| Permission | Écran qui la justifie | Formulation du motif |
|---|---|---|
| Appareil photo | 09 — prendre une photo à vérifier | « Pour photographier un contenu à vérifier » |
| Photothèque | 09 — choisir dans la galerie | « Pour choisir une image ou une vidéo à vérifier » |
| Microphone | 09 — enregistrer une note vocale | « Pour enregistrer un audio à vérifier » |
| Notifications | 08 — verdicts, rappels | « Pour vous prévenir quand un verdict est prêt » — **à ne pas demander tant que rien n'émet de notification** (réserve C.2) : une permission demandée pour rien se refuse, et ne se redemande pas |

Ce qui n'est **pas** demandé, et qu'il ne faut pas ajouter : localisation
(l'arbitrage n° 9 refuse explicitement de géolocaliser les sessions), contacts,
stockage externe, calendrier.

Les permissions sont demandées **au moment de l'usage**, jamais au lancement.
Une demande d'appareil photo au premier écran, avant toute explication, se refuse
et ne se redemande pas.

## 7.3 Versionnement

- **Version sémantique** pour la partie visible (`MAJEUR.MINEUR.CORRECTIF`), et
  un **numéro de compilation strictement croissant**, jamais réutilisé — les deux
  magasins refusent un numéro déjà vu, et découvrir cela au moment de publier un
  correctif urgent coûte une heure.
- La version affichée en pied de l'écran Réglages (30) est **lue depuis le paquet
  installé**, pas écrite en dur. Une version figée dans une chaîne de caractères
  finit toujours par mentir, et c'est la première chose qu'on demande à
  quelqu'un qui signale un défaut.
- **Correspondance avec l'API.** Le contrat fixe son versionnement : version
  majeure **dans l'URL** (`/api/v1`), ajouts compatibles sans préavis, ruptures
  en v2. Côté client, deux mécanismes en découlent et se prévoient dès la
  première version :
  - la version de l'application part dans **`X-Client-Version`** à chaque
    requête. L'en-tête est facultatif au contrat — un client qui n'annonce rien
    est servi — mais l'envoyer systématiquement est ce qui rend le plancher
    utilisable le jour où il faudra couper une version dangereuse ;
  - sous le plancher `API_MINIMUM_CLIENT`, le serveur répond **426
    `client_obsolete`** avec `X-Minimum-Client-Version`. Le client affiche un
    **écran bloquant** qui explique et mène au magasin. 426 et non 400 : *« c'est
    la différence entre quelqu'un qui agit et quelqu'un qui désinstalle. »*
    Sans ce mécanisme dès le départ, on ne peut jamais retirer un ancien
    comportement — il faut attendre que le dernier utilisateur mette à jour, ce
    qui n'arrive pas.
- **Une note de version en français**, qui dit ce qui change pour la personne, pas
  ce qui change dans le code.

## 7.4 Diffusion

Trois canaux, dans cet ordre :

1. **Interne** — l'équipe, à chaque compilation de la branche principale.
2. **Recette / test fermé** — la saveur de recette, sur un groupe restreint, avec
   les cinq parcours de la section 8 à valider.
3. **Production** — **déploiement progressif**. Commencer à une petite fraction
   des utilisateurs et suivre le taux de plantage avant d'élargir. C'est le seul
   moyen d'arrêter une régression avant qu'elle touche tout le monde ; une fois
   à 100 %, il faut publier un correctif et attendre la revue.

## 7.5 Ce que les magasins vont demander

Trois points valent d'être préparés avant la première soumission, parce qu'ils
provoquent des rejets et que chaque rejet coûte plusieurs jours :

- **La politique de confidentialité** doit être joignable depuis une adresse
  publique et correspondre à ce que l'application fait réellement. L'écran 30 y
  renvoie déjà.
- **Le formulaire de collecte de données** doit être rempli honnêtement : compte,
  contenus soumis, identifiants d'appareil pour les notifications. Sous-déclarer
  est un motif de retrait.
- **La suppression de compte doit être accessible depuis l'application** — c'est
  une exigence des deux magasins, et l'écran 30 la porte déjà. Vérifier que le
  chemin est atteignable sans support.

Et un point propre au produit : une application de fact-checking sera examinée
sur ce qu'elle affirme. L'écran 11 porte déjà l'avertissement sur l'usage de
l'IA ; il ne faut pas le retirer pour gagner de la place.

## 7.6 Télémétrie de plantage

### Ce qu'on collecte

Plantages, erreurs non capturées, images perdues (*jank*), et un petit nombre
d'événements de diagnostic : échec de rejeu de la file, échec de renouvellement
de jeton, échec d'envoi de fichier. Ces trois-là expliquent la majorité des
incidents réels.

### Ce qu'on ne collecte jamais

La liste est courte et absolue :

- **le contenu soumis à vérification** — c'est du texte utilisateur, souvent
  nominatif, et c'est le cœur de ce que la plateforme promet de protéger ;
- **l'adresse e-mail, le téléphone, le nom** ;
- **les jetons, mots de passe, codes à usage unique** ;
- **l'adresse IP en clair** — le back impose déjà `IdentityHasher` (règles du
  chantier §5), et une télémétrie qui garde des IP en clair contournerait la
  règle par la porte de service.

Un identifiant d'installation anonyme suffit à regrouper les incidents d'un même
appareil. Il se régénère à la désinstallation, et **il se purge à la
déconnexion** — comme le reste.

### Ce qui en découle côté produit

- La collecte est soumise au **consentement**, cohérente avec la bannière
  cookies du web et avec les consentements granulaires de l'écran 03.
- Les traces sont conservées le temps utile au diagnostic, pas indéfiniment.
- **Aucun écran d'échec sans explication.** Un plantage remonté à la télémétrie
  n'excuse pas un message vide chez l'utilisateur : la règle du chantier §7
  demande que l'écran dise ce qui se passe, y compris quand ça se passe mal.

## 7.7 Journal de bord de version

À chaque publication, consigner — comme le back consigne ses arbitrages :

- ce qui est livré, et ce qui ne l'est pas ;
- les écarts au simulateur, avec leur raison, dans `docs/ARBITRAGES.md` ;
- la dette laissée, nommée. `docs/RESTE_A_FAIRE.md` montre la forme : chaque
  chiffre se revérifie par une commande, aucun n'est estimé.

**Dire ce qui n'est pas fait.** Un rapport qui tait une réserve est pire qu'un
retard annoncé.

---

## 7.8 Lot du 2 septembre 2026 — ce qui est câblé, ce qui vous attend

Écrit après coup. Trois points de ce document étaient prescrits sans être
implémentés ; deux le sont désormais, le troisième est câblé jusqu'à la limite
de ce qui peut l'être sans une décision qui vous appartient.

### La migration de la base locale — corrigée

`onUpgrade` appelait `m.createAll()`, qui émet des `CREATE TABLE IF NOT EXISTS`.
Sur une installation existante, cela crée les tables absentes et **ne touche à
aucune table déjà présente**. Une colonne ajoutée à une table existante n'aurait
donc jamais été créée chez quelqu'un qui MET À JOUR : la requête suivante aurait
cherché une colonne que SQLite n'a pas, et l'application aurait planté à chaque
ouverture. Seule une désinstallation l'en aurait sorti, et `purgeAll()` n'aurait
rien rattrapé — c'est la FORME de la table qui est fausse, pas son contenu.

Le défaut était invisible : `schemaVersion` vaut 1 et n'a jamais été incrémenté,
donc `onUpgrade` n'avait **jamais tourné une seule fois**. Il se serait déclenché
à la première migration APRÈS mise en magasin, c'est-à-dire au moment où le parc
installé est le plus large.

La règle retenue suit la nature des données, telle que ce document la décrit
déjà : la base est « un cache de lecture jetable plus une file d'écriture ».

- **Le cache est refait à neuf** : il se rebâtit depuis le serveur, et une suite
  de migrations `stepByStep` sur six modules coûterait cher pour des données
  qu'on jette de toute façon à la déconnexion.
- **La file d'écriture est préservée** : `pending_ops` contient le travail fait
  hors ligne que le serveur n'a pas encore reçu. L'effacer ferait disparaître un
  message, un vote, une leçon terminée.

`test/data/local/migration_test.dart` éprouve les deux propriétés, en simulant la
future montée de version par une sous-classe à `schemaVersion = 2`. Le test a été
vérifié **dans les deux sens** : il échoue avec l'ancienne implémentation, il
passe avec la nouvelle.

Le jour où `pending_ops` change elle-même de forme, ce raccourci ne suffit plus :
il faudra une vraie étape de migration pour elle seule. C'est la demande adressée
au responsable du socle dont parle le §8.1, et elle ne concerne qu'une table.

### La signature Android — câblée, le magasin de clés vous revient

`signingConfig` lit désormais `android/key.properties`, non versionné, conforme
au §7.1 et à la règle §5 du chantier. Le modèle commenté est
`android/key.properties.exemple`, avec la commande `keytool`.

Ce qui a changé de comportement, et qui compte plus qu'il n'y paraît : la ligne
d'origine du gabarit Flutter signait la version *release* avec la clé de
**débogage**, en silence. Rien dans la sortie de construction ne le disait. Play
l'aurait refusé — le cas heureux ; le cas malheureux est un APK distribué à la
main, que plus aucune mise à jour ne pourrait jamais remplacer.

Désormais, `flutter build appbundle` **échoue** avec un message explicite quand
le magasin de clés est absent. `flutter run --release` continue de fonctionner
sans, pour ne pas gêner le travail quotidien — une règle qui empêche de
travailler finit contournée.

> **Non vérifié de bout en bout.** Ce poste n'a pas d'accès réseau vers
> `repo.maven.apache.org` : Gradle ne peut résoudre aucune dépendance, donc la
> construction ne va pas jusqu'au garde-fou. Le Kotlin a été relu à la main —
> une erreur de compilation y a d'ailleurs été trouvée et corrigée (un `String?`
> passé à `Project.file(Any)`) — mais **la première construction réelle reste à
> faire sur un poste connecté**.

> **Au passage** : le dossier `sdk/ndk/28.2.13676358` était une installation
> interrompue, entièrement vide hormis son marqueur `.installer`. Elle bloquait
> toute configuration Gradle (`did not have a source.properties file`). Elle a
> été **déplacée** — non supprimée — vers `sdk/ndk-installation-interrompue-28.2.13676358`.

### La télémétrie de plantage — le socle est là, deux décisions vous restent

L'application n'installait **aucun** gestionnaire d'erreur global :
`FlutterError.onError`, `PlatformDispatcher.instance.onError` et
`runZonedGuarded` étaient absents du code. Une exception non capturée
s'imprimait dans la console de développement et disparaissait ; sur le téléphone
de quelqu'un, elle ne laissait aucune trace nulle part. Le champ `telemetryDsn`
était lu depuis l'environnement et **aucun fichier ne le consommait**.

Ce qui est désormais en place, dans `lib/core/telemetry/` :

| Pièce | Rôle |
|---|---|
| `RapporteurDePlantage` | branche les deux gestionnaires globaux, expurge, journalise, transmet |
| `Incident` | la forme transportée, aux clés closes |
| `PuitsDeTelemetrie` | l'interface d'un fournisseur — aucun n'est branché |
| `IdentifiantDInstallation` | un UUID v4 anonyme, **purgé à la déconnexion** |

**Deux gestes distincts, et il ne faut pas les confondre.** *Journaliser* est
local : rien ne quitte l'appareil, aucun consentement n'est en jeu, et cela se
fait toujours — c'est précisément ce qui manquait. *Transmettre* exige les deux
portes : le consentement, et un puits configuré.

Ce qui **vous** revient, parce que ce ne sont pas des décisions techniques :

1. **Le consentement de télémétrie n'existe pas.** L'écran 03 en porte trois —
   CGU, confidentialité, infolettre — et aucun ne couvre les traces, alors que
   ce §7.6 exige que la collecte y soit soumise. Ajouter la case est un
   changement de produit : une ligne de plus à l'inscription, et un champ côté
   serveur. La porte est donc **fermée par défaut**, ce qui est le seul défaut
   défendable pour un produit qui reproche aux plateformes de collecter sans
   demander.
2. **Aucun fournisseur n'est choisi.** Ce document n'en nomme aucun, et
   l'écrire dans le cœur de l'application ferait choisir par défaut d'écriture.
   Brancher Sentry — ou tout autre — consiste à écrire une classe qui implémente
   `PuitsDeTelemetrie` et à redéfinir un provider dans `main.dart`.

Tant que ces deux points ne sont pas tranchés, **aucun plantage ne quitte
l'appareil** — mais aucun ne disparaît plus non plus.

`test/core/telemetry/rapporteur_de_plantage_test.dart` verrouille la frontière :
ce qui reste local, ce qui peut sortir, et la liste absolue de ce qui n'y figure
jamais (adresse, jeton, suites de six chiffres et plus).

#### Où se branche la purge, et pourquoi pas dans `signOut`

Premier essai : un appel à l'effacement ajouté directement dans `signOut`. Le
parcours M1 s'est alors arrêté sur un **délai de dix minutes** — pas sur une
assertion. La déconnexion attendait `flutter_secure_storage`, dont le coffre ne
répond pas hors appareil, et rien ne l'avait substitué : la classe fabriquait
son propre stockage au lieu de le recevoir.

L'identifiant passe donc par `LocalDataPurger`, qui est déjà « ce qui efface
tout ce qui est écrit sur l'appareil », déjà injecté, et déjà substitué par les
bancs d'essai. La racine de composition assemble les deux purgeurs — base locale
et identifiant — via `PurgeurCompose`, qui les exécute **tous** même si l'un
échoue : « la moitié oubliée d'une purge est une fuite de données sur un
téléphone partagé », et un échec sur la base locale ne doit pas laisser
l'identifiant en place.

La leçon dépasse ce lot : **un effacement ajouté à la main dans `signOut` est la
moitié qu'on oubliera** le jour où un troisième chemin de déconnexion
apparaîtra. Il n'y a qu'un point d'effacement, et c'est le purgeur.
