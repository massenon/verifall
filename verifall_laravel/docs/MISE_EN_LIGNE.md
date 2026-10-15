# Mise en ligne de verifall.com

Écrit le 2 septembre 2026. Ce document couvre les gestes qui ne se font **pas**
depuis le poste de développement : ils demandent l'accès à cPanel, aux consoles
des fournisseurs, et à la base de production.

Chaque section dit ce qui a été préparé ici, et ce qui vous revient.

---

## 0. L'ordre est imposé, et une seule inversion coûte cher

**`APP_DEBUG=false` AVANT de renouveler les clés.**

Avec `APP_DEBUG=true`, la page d'erreur de Laravel affiche la pile d'appels
**et l'environnement** : chaque clé d'API, le mot de passe de la base, le secret
de la passerelle de paiement. Il suffit d'une requête qui rend 500 — et il y en
aura une.

Renouveler les clés puis déployer avec `APP_DEBUG=true` **divulgue les nouvelles
le jour même**, et l'on croit le problème réglé. C'est la seule erreur d'ordre
qui annule tout le reste.

`php artisan verifall:diagnostic` porte désormais une section « Mise en ligne »
qui refuse cette configuration en rouge.

---

## 1. Le fichier `.env` de production

L'état constaté sur le poste de développement, et ce qu'il doit devenir :

| Clé | Aujourd'hui | En production |
|---|---|---|
| `APP_ENV` | `local` | **`production`** |
| `APP_DEBUG` | `true` | **`false`** |
| `APP_URL` | `https://verifall.com` | inchangé — déjà juste |
| `SESSION_DOMAIN` | `null` | `.verifall.com` si des sous-domaines partagent la session, `null` sinon |
| `MAIL_MAILER` | `smtp` | inchangé |

`APP_URL` désignait déjà la production alors qu'`APP_ENV` valait `local` :
**l'un des deux était faux, et rien ne le disait.** Le diagnostic le signale
maintenant.

Après toute modification :

```bash
php artisan config:clear
php artisan verifall:diagnostic
```

---

## 2. Renouveler les identifiants exposés

### Ce qui s'est passé

Des identifiants de production ont été collés dans une conversation d'assistance.
**Tout secret présent dans ce fichier à ce moment-là doit être considéré comme
divulgué** — pas seulement ceux qu'on a remarqués.

### Les trois à traiter en premier

Ce sont ceux dont l'usage est facturé ou donne accès à un compte :

| Secret | Où le renouveler | Pourquoi en premier |
|---|---|---|
| `OPENAI_API_KEY` | platform.openai.com → API keys | facturé à l'usage : une clé divulguée coûte de l'argent immédiatement |
| `GOOGLE_CLIENT_SECRET` | console.cloud.google.com → Identifiants OAuth | permet de se faire passer pour l'application auprès de Google |
| `WINSTON_AI_API_KEY` | tableau de bord Winston AI | jeton JWT, facturé à l'usage |

### Les autres

Les 22 restants sont déclarés dans `.env` et lus par `config/`. Aucun n'est
écrit en dur dans le code — la règle 5 du chantier tient, vérifiée. Les
renouveler est donc **une modification de `.env` et rien d'autre** : aucun
déploiement de code, aucune migration.

Une exception à noter : `ZOOM_CLIENT_SECRET` est déclaré dans `.env` mais
**aucun fichier de `config/` ne le lit**. Il ne sert à rien aujourd'hui ; le
renouveler est inutile, le supprimer est plus propre.

### Ce que je ne peux pas faire

Me connecter à vos consoles de fournisseurs. Cela demande vos identifiants, et
je n'entre jamais de mot de passe ni ne crée de compte. Les trois liens
ci-dessus mènent aux pages exactes.

---

## 3. Les deux crons cPanel

Le détail est dans `docs/CRONS.md`. Les deux lignes, à recopier en remplaçant
`UTILISATEUR` par votre nom de compte cPanel et en vérifiant le chemin de `php` :

```
* * * * * cd /home/UTILISATEUR/verifall && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
* * * * * cd /home/UTILISATEUR/verifall && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> storage/logs/queue.log 2>&1
```

### Ce que chacune porte

**La première** déclenche les **sept** tâches planifiées — le document en
annonçait six jusqu'à aujourd'hui ; `verifall:sonder-les-moteurs` y manquait.
La commande qui dit la vérité est `php artisan schedule:list`.

**La seconde** est celle qu'on oublie, et c'est la plus grave à oublier. Sans
worker :

- l'analyse d'une vérification ne démarre pas — c'est le cœur du produit ;
- **aucun code OTP ne part** ;
- aucun courriel ne part, y compris ceux de la reprise de l'ancienne base.

Rien ne casse : pas d'exception, pas de journal d'erreur, la page répond 200. Le
job attend simplement en table `jobs`.

> **Conséquence directe sur la reprise des anciens comptes :** 16 des 133 comptes
> importés devront vérifier leur adresse par OTP à leur première connexion. Sans
> ce cron, ils attendront un courriel qui ne partira jamais — et le formulaire
> n'aura aucun défaut.

### Vérifier que ça tourne vraiment

```bash
php artisan verifall:diagnostic
```

Elle dit l'état de la file, du transport de courriel, du cache, de la session,
du planificateur et de la mise en ligne, ne modifie rien, et **sort en échec dès
qu'un point est rouge** — donc utilisable dans une surveillance.

---

## 4. Répéter la reprise sur une COPIE de la production

Le raisonnement complet, les deux incidents rencontrés et la procédure sont dans
`docs/MIGRATION_ANCIENNE_BASE.md` (§11). Ce qui compte ici :

1. **Exporter un dump FRAIS** de l'ancienne base. Celui traité le 2 septembre
   n'est plus à jour, et un dump plus récent peut contenir des statuts ou des
   verdicts absents des tables de traduction.
2. **Restaurer ce dump dans une base d'essai**, pas dans la production.
3. **Lancer l'essai à blanc** — il compte, et il **refuse avant toute écriture**
   toute valeur qu'il ne sait pas traduire, en la nommant avec son nombre
   d'occurrences.
4. Import réel seulement si l'essai est vert.

```bash
mysqldump --single-transaction --routines -u UTILISATEUR -p BASE > avant-import.sql
php artisan verifall:importer-ancienne-base --essai --fichier=/chemin/dump.sql
php artisan verifall:importer-ancienne-base --fichier=/chemin/dump.sql
php artisan verifall:diagnostic
```

### Les contrôles d'après-import, ceux qui prouvent quelque chose

Ce ne sont pas les volumes qui comptent, mais l'utilisabilité :

| Contrôle | Ce qu'il prouve |
|---|---|
| Empreintes identiques à l'octet près entre ancienne et nouvelle base | personne n'a à réinitialiser son mot de passe |
| Relecture Eloquent complète sans faute de cast | les données sont lisibles, pas seulement présentes |
| `SUM(unit_logs.delta)` = `SUM(users.units)` sur les comptes importés | l'invariant de l'économie tient |
| Aucun `claim` commençant par « Analyse Invité » | les URL sont complètes, pas tronquées à 60 caractères |

Sur le dump du 2 septembre, les quatre sont passés.

### Ce qu'il faut dire, et ne pas promettre

**226 vérifications sur 524 (43 %) sont anonymes** — `created_by` était nul dans
l'ancienne base. Elles n'apparaîtront dans aucun tableau de bord.

Une communication annonçant « retrouvez toutes vos vérifications » ferait ouvrir
un tableau de bord vide à une bonne partie des 133 comptes, qui en concluraient
que la migration a échoué. « Votre historique vous attend » est vrai pour les
**61 comptes** qui en ont un.

---

## 5. L'application mobile

L'APK se construit avec la configuration de production :

```bash
flutter build apk --release --dart-define-from-file=config/prod.json
```

`config/prod.json` a été créé et pointe sur `https://verifall.com`. Il est
**non versionné** (`config/*.json` est dans `.gitignore`), comme les deux autres
environnements.

`AppConfig` refuse désormais de démarrer une application de production sans
adresse d'API, ou avec une adresse qui n'est pas en HTTPS : sans cette garde,
oublier `--dart-define-from-file` produisait un paquet d'apparence normale dont
chaque appel échouait sans rien expliquer.

### Ce qui reste avant de distribuer

**L'APK produit est signé avec la clé de DÉBOGAGE.** Il sert à essayer, pas à
distribuer : un artefact ainsi signé ne pourra jamais être remplacé par une mise
à jour correctement signée. Voir `android/key.properties.exemple` — la commande
`keytool` y est écrite, et `flutter build appbundle` refuse de produire un bundle
sans magasin de clés.

La clé de signature Android est **définitive**. Activez la « signature
d'application Play » : Google conserve alors la clé de distribution, et une clé
de téléversement perdue se remplace.
