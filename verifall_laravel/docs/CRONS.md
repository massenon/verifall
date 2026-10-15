# Tâches planifiées

*État vérifié le 25 août 2026, par lecture de `routes/console.php`.*

---

## La seule ligne qui compte

Laravel n'a **pas** un cron par tâche. Il en a **un seul**, qui réveille son
propre planificateur ; c'est ce dernier qui décide, à chaque réveil, ce qui doit
tourner.

```cron
* * * * * cd /home/UTILISATEUR/verifall && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Tant que cette ligne n'existe pas, **aucune** des sept tâches ci-dessous ne
s'exécute. Elles ne produisent aucune erreur : elles ne se produisent
simplement pas. C'est le mode de panne le plus coûteux du lot, parce qu'il est
silencieux — une obligation de rétention confiée à un planificateur débranché
n'est pas une obligation remplie.

> **Sur cPanel**, `php` en ligne de commande n'est pas forcément le même
> exécutable que celui du site. Voir `DEPLOIEMENT_CPANEL.md`, section « le
> chemin du PHP ».

---

## Les sept tâches, et pourquoi chacune a sa cadence

### `verifall:purger-donnees-expirees` — toutes les heures

Applique les durées de conservation : comptes en suppression au-delà du délai de
rétractation, archives arrivées à échéance, fichiers téléversés hors délai.

*Pourquoi toutes les heures* — assez fréquent pour qu'une donnée expirée ne
traîne jamais longtemps, assez espacé pour ne rien coûter. C'est la tâche qui
porte la conformité RGPD : elle ne doit jamais être désactivée « le temps de
voir ».

`withoutOverlapping()` : deux passages simultanés tenteraient d'anonymiser le
même compte, et le second écraserait la date posée par le premier.

### `verifall:reconcilier-paiements` — toutes les 10 minutes

Réinterroge l'opérateur sur les paiements restés en attente.

*Pourquoi dix minutes* — un rappel d'opérateur peut se perdre. Quelqu'un qui a
payé et ne voit pas ses unités arriver écrit au support en quelques minutes, pas
en une heure. **C'est la tâche dont la panne se voit le plus vite**, et c'est une
bonne chose : elle sert de témoin. Si les réclamations « j'ai payé et je n'ai
rien reçu » augmentent, vérifier le cron avant toute autre chose.

### `verifall:attribuer-dotation-mensuelle` — le 1ᵉʳ à 00h05

Les 10 unités offertes chaque mois, comme le prototype l'annonce.

*Pourquoi 00h05 et pas minuit* — cinq minutes de marge pour ne pas courir contre
le changement de date. Une tâche déclenchée à `00:00:00` pile peut, selon la
seconde où le planificateur se réveille, se croire encore la veille.

**Le point de vigilance** : si le serveur est éteint ou le cron interrompu la
nuit du 1ᵉʳ, la dotation du mois **est perdue** — la tâche ne rattrape pas. À
surveiller ce jour-là, ou à rendre rattrapable si le besoin se confirme.

### `verifall:fermer-opportunites-expirees` — chaque jour à 03h20

Retire du Hub les offres dépassées depuis plus d'une semaine.

*Pourquoi la nuit, et pourquoi une seule fois par jour* — une date limite n'a
pas d'heure ; la granularité du jour suffit. 03h20 plutôt que 03h00 : les heures
rondes sont encombrées sur un mutualisé, où tout le monde planifie au même
moment.

*Pourquoi une semaine de grâce et non zéro* — une offre dont la date limite est
passée hier peut encore accepter des dossiers. Fermer à la seconde ferait perdre
des candidatures ; le délai est un choix éditorial consigné aux arbitrages.

### `verifall:pontage` — toutes les heures

Recalcule le score de pontage des notes de communauté et publie celles qui
franchissent le seuil.

*Pourquoi l'heure, et pas la minute* — le calcul est quadratique en nombre de
votants. Chaque minute ferait payer un travail dont le résultat ne bouge qu'au
rythme des votes.

*Pourquoi l'heure, et pas le jour* — une note qui apporte du contexte à une
rumeur arrive après elle si on attend la nuit. Le contexte arrivé le lendemain
ne sert plus à personne.

*La panne à connaître* — **sans cette tâche, aucune note ne se publie jamais.**
Les votes s'accumulent, les notes restent « en attente d'évaluation », et rien
sur l'écran ne signale que le calcul ne tourne plus. C'est le premier point à
vérifier si le mécanisme paraît inerte.

### `verifall:infolettre-hebdomadaire` — lundi 07h10, heure de Cotonou

Envoie le récapitulatif des fact-checks publiés dans les sept derniers jours.

*Pourquoi le fuseau est écrit en toutes lettres* — `config/app.php` est en UTC.
Sans `->timezone('Africa/Porto-Novo')`, la lettre du lundi matin partirait à
08h10 locale, et le nom du rendez-vous deviendrait faux. C'est le genre d'écart
que personne ne remarque avant plusieurs semaines.

*Pourquoi `runInBackground`* — l'envoi est synchrone par choix (arbitrage 157 :
mis en file, le compteur d'échecs resterait nul quoi qu'il arrive). Il peut
durer plusieurs minutes, et retiendrait sinon tout le planificateur.

*Ce qui se passe une semaine sans publication* — la commande n'envoie rien,
journalise un avertissement et sort en **code 2** : ni succès, ni panne. Une
lettre vide coûte des désabonnements sans rien apporter.

*Le garde-fou contre l'oubli* — au-delà de huit jours sans envoi réel, l'écran
d'administration affiche un bandeau rouge. C'est le seul dispositif qui rende
visible un cron débranché.

---

## La file d'attente : le second cron, celui qu'on oublie

Les tâches planifiées ne sont **pas** la file d'attente. Ce sont deux mécanismes
distincts, et il en faut deux.

`QUEUE_CONNECTION=database` : les analyses de vérification, les courriels et les
appels aux moteurs externes sont mis en file. Sans processus qui la consomme,
**ils restent en file indéfiniment** — l'écran affiche « analyse en cours » pour
toujours, et personne ne comprend pourquoi.

En production classique, un `queue:work` tourne en permanence sous supervision.
**Sur un mutualisé, c'est généralement interdit** : les processus longs y sont
tués. La parade :

```cron
* * * * * cd /home/UTILISATEUR/verifall && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=55 --tries=3 >> storage/logs/queue.log 2>&1
```

Chaque option répond à une contrainte précise :

- `--stop-when-empty` — le processus rend la main dès qu'il n'y a plus rien.
  C'est ce qui le rend acceptable sur un hébergement qui interdit les démons.
- `--max-time=55` — il s'arrête de lui-même avant le cycle suivant. Sans cela,
  deux exécutions finiraient par se chevaucher et par traiter deux fois le même
  travail.
- `--tries=3` — un moteur externe indisponible mérite trois essais, pas un.
  Au-delà, le travail part en `failed_jobs` et n'y retourne pas seul.

### `retry_after` : l'autre chevauchement, celui que `--max-time` n'empêche pas

`--max-time` évite que deux processus `queue:work` se chevauchent. Il
n'empêche pas qu'un **même job** soit donné deux fois.

`retry_after`, dans `config/queue.php`, est la durée pendant laquelle la file
garde un job **réservé**. Passé ce délai, elle le considère abandonné et le rend
au worker suivant — sans vérifier que le premier a fini. Un job dont le
`$timeout` dépasse cette réservation finit donc par tourner deux fois en
parallèle.

La valeur livrée par Laravel est **90 secondes**. `AnalyserVerification` déclare
`$timeout = 180`, `DiffuserInfolettre` va jusqu'à 3600. Avec 90, la production a
rendu un `Duplicate entry for key analysis_reports_verification_id_unique` : deux
passages avaient interrogé les moteurs, deux passages ont écrit le rapport, le
second s'est écrasé sur la contrainte — après avoir payé les moteurs deux fois.

La règle est simple : **`retry_after` doit dépasser le `$timeout` du job le plus
long**. Le dépôt le fixe à 3900 (3600 + 5 minutes de marge), surchargeable par
`DB_QUEUE_RETRY_AFTER`. `php artisan verifall:diagnostic` le vérifie et vire au
rouge si l'invariant est rompu ; un test le tient aussi
(`tests/Feature/ReservationDeLaFileTest.php`).

**Chaque minute, et non toutes les cinq.** Ce fichier a longtemps prescrit
`*/5`, en contradiction avec `CLAUDE.md`. Ce n'est pas une préférence de style :
le cycle de la file est le délai que subit **le code d'inscription**. À cinq
minutes, un code valable vingt en perd un quart avant même de partir, et la
personne qui recopie six chiffres reçus il y a un instant s'entend répondre
qu'ils ont expiré. Le produit paraît cassé alors que seule la cadence l'est.

**Le prix à payer, et il faut le connaître** : une analyse attend jusqu'à une
minute avant de commencer. Ce n'est pas la même expérience qu'un démon. Si ce
délai devient gênant, c'est le signal qu'il faut quitter le mutualisé — pas
qu'il faut bricoler le cron.

---

## Vérifier que ça tourne vraiment

```bash
# Ce que le planificateur croit devoir faire, et quand.
php artisan schedule:list

# Le forcer une fois, à la main, pour voir ce qui se déclenche.
php artisan schedule:run

# Une tâche seule, sans attendre son heure.
php artisan verifall:reconcilier-paiements

# La file : ce qui attend, et ce qui a échoué.
php artisan queue:monitor database
php artisan queue:failed
```

**Le test qui tranche vraiment**, parce qu'il ne dépend d'aucune interprétation :

```bash
php artisan tinker --execute="echo cache()->get('illuminate:schedule:last-run') ?? 'JAMAIS';"
```

Si la réponse est `JAMAIS` ou remonte à plusieurs minutes, le cron **n'est pas
branché**, quoi qu'affiche l'interface de l'hébergeur.

---

## En ajouter une

Dans `routes/console.php`, jamais dans le cron du serveur. Le serveur n'a qu'une
ligne, et il ne doit jamais en avoir deux : sinon la liste réelle des tâches
n'est plus lisible dans le dépôt, et personne ne sait ce qui tourne.

Trois règles, dans l'ordre d'importance :

1. **`withoutOverlapping()` sur toute tâche qui écrit.** Le cas d'école est
   l'anonymisation : deux passages concurrents sur le même compte se marchent
   dessus, et c'est le second qui gagne.
2. **Une heure décalée.** `03:20` plutôt que `03:00`. Sur un mutualisé, tout le
   monde planifie aux heures rondes ; s'y joindre, c'est se mettre en file.
3. **Un nom qui dit l'effet, pas le déclencheur.** `fermer-opportunites-expirees`
   se comprend dans un journal à 3 h du matin ; `tache-quotidienne-2`, non.

---

### `verifall:sonder-les-moteurs` — toutes les heures

**Absente de ce document jusqu'au 2 septembre 2026**, alors qu'elle est déclarée
et planifiée depuis plus longtemps. `schedule:list` en compte sept ; ce texte en
annonçait six. Un relevé écrit à la main finit toujours par diverger du code —
la commande qui dit la vérité est :

```bash
php artisan schedule:list
```

Elle interroge chaque moteur d'analyse sur son état et garde la réponse. Le
tableau de bord lit la DERNIÈRE mesure et affiche son âge, plutôt que de sonder
à l'ouverture : « le quota gratuit de Google Custom Search est de cent requêtes
par jour », et une sonde relancée à chaque affichage épuiserait avant midi le
quota du moteur qu'elle surveille. **Un dispositif de surveillance qui provoque
la panne qu'il cherche ne surveille rien.**

Conséquence pratique : sans le cron, la carte « santé des moteurs » du
back-office affiche des mesures qui vieillissent sans que rien ne le signale
comme une panne — elle indique justement leur âge pour cela.
