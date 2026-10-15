# Mise en ligne sur cPanel — et le piège qui a coûté la première tentative

## Ce qui s'est passé

Le site en ligne renvoyait :

```
SQLSTATE[HY000] [1045] Access denied for user 'root'@'localhost' (using password: NO)
(Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: verifall, ...)
```

alors que le `.env` du serveur déclarait un tout autre utilisateur, un mot de
passe, et une autre base.

**Cause : le dossier `bootstrap/cache/` avait été téléversé avec le reste.**

`php artisan config:cache` écrit `bootstrap/cache/config.php`, un fichier PHP
qui contient toutes les valeurs **déjà résolues**. À partir du moment où il
existe, **Laravel ne lit plus `.env` du tout** — c'est le principe même de ce
cache, et c'est ce qui le rend rapide.

Le fichier téléversé venait du poste de développement. Il portait donc
`database => 'verifall'`, `username => 'root'`, mot de passe vide — les valeurs
par défaut de `config/database.php` quand `DB_USERNAME` et `DB_PASSWORD` sont
absents. C'est mot pour mot ce qu'affichait l'erreur.

Deux effets de bord, plus discrets que la panne mais plus graves :

- `APP_DEBUG` valait `true` : chaque visiteur voyait la trace complète, les
  chemins du serveur et les requêtes SQL.
- `APP_ENV` valait `local` : or plusieurs garde-fous de ce projet interrogent
  l'environnement pour se désactiver en production — le seeder de démonstration
  et la commande `verifall:code-2fa`, notamment. Un site en ligne qui se déclare
  local a ces protections **désarmées**.

## La correction, dans l'ordre

### 1. Supprimer les caches sur le serveur

Depuis le gestionnaire de fichiers cPanel, ou en SSH :

```bash
rm -f bootstrap/cache/config.php
rm -f bootstrap/cache/routes-v7.php
rm -f bootstrap/cache/events.php
```

Ne touchez pas à `packages.php` ni `services.php` : ceux-là sont engendrés par
Composer et ne contiennent aucune valeur d'environnement.

Le site doit déjà refonctionner à ce stade : sans cache, `.env` est relu.

### 2. Corriger le `.env` du serveur

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://verifall.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=massenon_verifalltest
DB_USERNAME=massenon_root
DB_PASSWORD=<le nouveau mot de passe>
```

`APP_DEBUG=false` est **la ligne la plus importante de ce fichier**. Elle décide
si une erreur montre une page neutre ou l'intérieur du serveur.

### 3. Une clé d'application PROPRE au serveur

```bash
php artisan key:generate
```

Ne recopiez pas l'`APP_KEY` de votre poste. Elle chiffre les sessions, les
cookies et — dans ce projet — **les secrets de double authentification**. Deux
environnements qui partagent une clé partagent une compromission.

> **Attention si la base contient déjà des comptes.** Changer l'`APP_KEY` rend
> illisibles les secrets 2FA déjà enregistrés. Le code prévoit ce cas
> (`TwoFactorService::estActive()` vérifie que le secret est *déchiffrable* et
> renvoie vers l'enrôlement plutôt que d'enfermer les gens dehors), mais les
> personnes concernées devront réenrôler leur application d'authentification.
> Sur une base neuve, la question ne se pose pas.

### 4. Reconstruire les caches, SUR LE SERVEUR

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

C'est un gain de performance réel en production. La règle qui l'accompagne est
simple : **on reconstruit ces caches sur la machine qui les utilisera, jamais
ailleurs**, et on les efface après chaque modification du `.env`.

### 5. Vérifier

```bash
php artisan about --only=environment
```

Doit afficher `production` et `Debug Mode: OFF`. Puis :

```bash
php artisan migrate --force
```

`--force` est exigé en production : c'est un garde-fou contre une migration
lancée par mégarde.

## Ce qu'il ne faut jamais téléverser

| Chemin | Pourquoi |
|---|---|
| `bootstrap/cache/*.php` | Fige la configuration d'une autre machine — la panne ci-dessus |
| `.env` local | Contient les identifiants de développement |
| `storage/logs/*` | Journaux d'un autre environnement |
| `node_modules/`, `tests/` | Inutiles en production |
| `database/*.sqlite` | Base de développement |

Ajoutez `bootstrap/cache/*.php` à ce qui est exclu de votre transfert. Le
dossier lui-même doit exister et rester **inscriptible** ; seul son contenu ne
doit pas voyager.

## Après la mise en ligne

- **Changer le mot de passe de la base.** Celui de la première tentative a
  circulé en clair ; le considérer comme compromis coûte cinq minutes, l'ignorer
  peut coûter la base.
- `storage/` et `bootstrap/cache/` doivent être inscriptibles (755, propriétaire
  cPanel).
- La racine du domaine doit pointer sur `public/`, jamais sur la racine du
  projet. Autrement `.env` devient téléchargeable par n'importe qui.
- `php artisan storage:link` pour les fichiers publics.

## Le point qui reste ouvert

`bootstrap/app.php` déclare `trustProxies(at: '*')` : l'application fait
confiance à n'importe quel proxy pour lui annoncer l'adresse du visiteur. Cette
adresse peut donc être falsifiée, et toute limitation de débit assise dessus
contournée. La valeur juste est la plage d'adresses du répartiteur de charge de
l'hébergeur — à demander au support cPanel avant l'ouverture au public.

---

# Le courriel refusé en « high-probability spam »

## Le symptôme

```
Expected response code "250" but got code "550",
with message "550 Message discarded as high-probability spam".
```

## Ce que la trace dit, et ce qu'elle disculpe

L'échec survient dans `executeCommand('\r\n.\r\n')` — le point final qui clôt le
corps du message. Autrement dit : la connexion a été acceptée, **les
identifiants ont été acceptés**, le contenu a été transmis en entier, et c'est
seulement à la fin que le serveur l'a jeté.

Ce n'est donc pas un problème de `MAIL_HOST`, de port, ni de mot de passe. C'est
le filtre sortant qui a jugé le message.

## Cause n°1 : le serveur s'annonce sous un faux nom

`config/mail.php`, ligne 49 :

```php
'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
```

Le nom annoncé à la commande SMTP `EHLO` est **déduit d'`APP_URL`**. Si `APP_URL`
est resté sur sa valeur par défaut, le serveur se présente comme `localhost` —
ce qu'aucun serveur légitime ne fait. Les filtres sortants pénalisent lourdement
ce signal.

Le même `APP_URL` alimente le lien de l'en-tête de chaque courriel
(`resources/views/vendor/mail/html/message.blade.php`) : tous vos messages
contiennent alors un lien vers `http://localhost`.

**Correction :**

```dotenv
APP_URL=https://verifall.com
MAIL_EHLO_DOMAIN=verifall.com
```

`MAIL_EHLO_DOMAIN` est posé explicitement plutôt que déduit. Une valeur
importante ne devrait pas dépendre du fait que quelqu'un ait pensé à corriger
une autre variable.

Puis, impérativement :

```bash
php artisan config:clear && php artisan config:cache
php artisan queue:restart
```

`queue:restart` n'est pas optionnel : un worker déjà lancé garde en mémoire
l'ancienne configuration. Sans lui, la correction ne s'appliquera qu'au
prochain redémarrage — c'est-à-dire jamais, tant que le worker tourne.

## Cause n°2 : l'authentification du domaine

Un domaine récent qui envoie du courriel sans SPF, DKIM ni DMARC est traité
comme suspect, y compris par son propre hébergeur.

À vérifier dans cPanel, section **Email Deliverability** :

| Enregistrement | Ce qu'il prouve | État attendu |
|---|---|---|
| SPF | Que ce serveur a le droit d'émettre pour verifall.com | Valide |
| DKIM | Que le message n'a pas été altéré en route | Valide |
| DMARC | Ce qu'il faut faire d'un message qui échoue aux deux | Présent |

cPanel les propose en un clic (« Repair »). **Si le DNS du domaine n'est pas
géré par cPanel mais chez le registrar, cPanel affichera les enregistrements
sans pouvoir les poser : il faut alors les recopier à la main chez le
registrar.** C'est le cas le plus fréquent, et celui qu'on oublie.

## Cause n°3 : ce que le filtre lit dans le message

Par ordre de poids, une fois les deux causes ci-dessus écartées :

- **Un lien vers un domaine sans rapport avec l'expéditeur.** Vérifié dans ce
  dépôt : le gabarit n'affiche le logo distant de Laravel que si le nom de
  l'application vaut exactement `Laravel`. Le nôtre vaut `Vérif'All`, donc
  **aucune image distante n'est chargée**. Ce point est sain.
- **Un message court, presque uniquement composé d'un code.** C'est le cas du
  courriel de code à usage unique, et c'est inévitable : sa brièveté est une
  qualité. Si le filtre s'obstine après correction du SPF et de l'EHLO, la voie
  est un service d'envoi transactionnel (voir plus bas), pas l'allongement
  artificiel du texte.
- **L'absence d'alternative texte.** Non concernée ici : les courriels de ce
  dépôt sont des mailables Markdown, qui produisent HTML **et** texte.

## Une clé qui ne sert à rien dans votre `.env`

```dotenv
MAIL_ENCRYPTION=tls   # ← ignoré par Laravel 12
```

Cette clé n'existe plus. `config/mail.php` ne lit que `MAIL_SCHEME`. La ligne
peut rester, elle n'a aucun effet — mais ne comptez pas sur elle pour avoir
réglé le chiffrement. Sur le port 587, STARTTLS est négocié automatiquement ;
il n'y a rien à déclarer.

Pour un chiffrement implicite sur le port 465, il faudrait `MAIL_SCHEME=smtps`.

## Si le problème persiste après tout cela

Le courriel transactionnel envoyé depuis un hébergement mutualisé reste fragile :
l'adresse IP est partagée avec des centaines de sites, et la réputation ne vous
appartient pas.

Pour un service dont **la connexion des utilisateurs dépend d'un code envoyé par
courriel**, c'est un point de défaillance à ne pas laisser au hasard. Un service
d'envoi transactionnel (Brevo, Mailgun, Postmark) résout la question en changeant
deux lignes du `.env`, sans toucher au code :

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=<hôte du service>
MAIL_PORT=587
MAIL_USERNAME=<identifiant fourni>
MAIL_PASSWORD=<clé fournie>
MAIL_FROM_ADDRESS="contact@verifall.com"
MAIL_EHLO_DOMAIN=verifall.com
```

Le domaine expéditeur reste le vôtre ; seule la route change.

## Vérifier sans attendre un vrai utilisateur

```bash
php artisan tinker
>>> Mail::raw('Test de délivrabilité Vérif\'All', fn ($m) => $m->to('votre@adresse.com')->subject('Test'));
```

Puis relire `storage/logs/laravel.log`. Un envoi réussi n'y laisse aucune trace ;
c'est le silence qui est bon signe.

---

# Les trois gestes serveur de la phase 1

*Écrit le 10 septembre 2026, en exécutant `docs/PLAN_FINALISATION.md`. Ces trois
points ne se font PAS depuis le poste de développement : ils demandent l'accès à
cPanel. Le code, lui, est prêt et vérifié ici.*

## 1. Déployer le correctif du plan du site

`/sitemap.xml` rend 500 en production — neuf fois au journal du 2 septembre :

```
syntax error, unexpected identifier "version"
(View: resources/views/public/plan-du-site.blade.php)
```

**Le correctif est écrit depuis longtemps et n'a jamais été déployé.** Il tient
en une ligne : la déclaration XML est ÉMISE par Blade au lieu d'être écrite en
clair, si bien que le `?>` qu'elle contient devient le texte d'une chaîne.

Il a été prouvé, et pas seulement relu — le gabarit compilé passe
`php -d short_open_tag=1 -l`, alors que l'ancienne forme rend exactement
l'erreur du journal. `tests/Feature/Public/PlanDuSiteTest.php` garde la
propriété : il compile la vue et la fait analyser par un second processus PHP
réglé comme la production.

Après le déploiement :

```bash
php artisan view:clear
curl -s https://verifall.com/sitemap.xml | head -1
```

La première ligne doit être la déclaration XML, pas une trace d'exception.

> **`view:clear` n'est pas optionnel.** Les gabarits compilés sont mis en cache
> dans `storage/framework/views` ; sans purge, la version fautive resterait
> servie après un déploiement parfaitement correct.

## 2. Purger le planificateur

Le journal porte aussi :

```
There are no commands defined in the "activitylog" namespace.
```

`activitylog` est **absent de tout le code déployé** — `grep` sur `app/`,
`config/`, `routes/` et `composer.json` : zéro occurrence. C'est un reste de
l'ANCIENNE application, qui l'employait : le dump de reprise contient une table
`activity_log`.

L'appel vient donc soit d'une entrée cron autonome dans cPanel, soit d'un
`routes/console.php` périmé resté sur le serveur.

À faire, dans cet ordre :

1. Dans cPanel → *Cron Jobs*, retirer toute entrée appelant `activitylog:*`.
2. Comparer le `routes/console.php` du serveur à celui d'ici — **7 tâches** —, et
   remplacer le fichier si besoin.
3. Vérifier : `php artisan schedule:list` ne doit lister que ces 7 tâches.

Ne restent que les deux entrées cron du produit, décrites dans `docs/CRONS.md` :
`schedule:run` et `queue:work --stop-when-empty --max-time=55`, chaque minute.

## 3. Réencoder les secrets de double authentification

```
Secret de double authentification illisible : clé d'application changée ? {"user_id":1}
```

L'`APP_KEY` de production a tourné sans que les secrets 2FA soient réencodés.
Ils étaient chiffrés avec l'ancienne clé et ne se déchiffrent plus.

**Personne n'est verrouillé dehors** : le code traite un secret illisible comme
un facteur ABSENT, ce qui renvoie vers l'enrôlement — c'est l'arbitrage du
26 août, pris après qu'un `DecryptException` a rendu 500 jusqu'à l'écran de
connexion. Mais l'avertissement restera au journal tant que la ligne vit, et la
personne concernée devra réenrôler son téléphone.

Le geste est donc simple, et il vaut mieux qu'un réencodage : faire remettre à
zéro le second facteur du compte concerné, qui le réenrôlera à sa prochaine
connexion.

> **La cause mérite d'être retenue, parce qu'elle se reproduira.** Renouveler
> l'`APP_KEY` invalide TOUT ce que l'application a chiffré : secrets 2FA,
> colonnes `encrypted`, cookies de session. `docs/MISE_EN_LIGNE.md` §2 impose
> déjà l'ordre des opérations pour le renouvellement des identifiants ; ce point
> en fait partie.
