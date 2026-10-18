# Arbitrages — décisions tranchées

Registre des points où le prototype, les instructions et les exigences de
sécurité se contredisaient. Chaque entrée dit ce qui a été décidé, par qui, et
**pourquoi** — pour qu'une relecture future ne défasse pas un choix délibéré en
le prenant pour un oubli.

| # | Sujet | Décision | Repère |
|---|-------|----------|------|
| 1 | SameSite vs OAuth | `lax` | 2026-08-24 |
| 2 | Matrice de permissions | validée par le porteur | 2026-08-20 |
| 3 | Consentements RGPD | ajoutés | 2026-08-20 |
| 4 | Mot de passe oublié | code OTP | 2026-08-20 |
| 5 | Position des toasts | prototype | 2026-08-20 |
| 6 | Modification de l'adresse e-mail | vérification obligatoire | 2026-08-20 |
| 7 | Badge « Identité vérifiée » | adossé à une date réelle | 2026-08-20 |
| 8 | Interrupteur 2FA | parcours complet, pas un clic | 2026-08-20 |
| 9 | Sessions actives | ni IP ni géolocalisation | 2026-08-20 |
| 10 | Longueur du mot de passe | 12, centralisée | 2026-08-20 |
| 11 | Lettre d'information | consentement tracé | 2026-08-20 |
| 12 | Suppression du compte | mot + mot de passe + code | 2026-08-20 |
| 13 | Effacement | 30 jours puis anonymisation | 2026-08-20 |
| 14 | Archive de portabilité | lien signé, jamais en pièce jointe | 2026-08-20 |
| 15 | Packs d'unités | tranché — voir l'entrée 76 | 2026-08-25 |
| 16 | Dotation mensuelle | crédite, ne remet pas à zéro | 2026-08-21 |
| 17 | Montant d'un paiement | serveur uniquement | 2026-08-21 |
| 18 | Rappel Mobile Money | 4 gardes, idempotent | 2026-08-21 |
| 19 | Moteurs d'analyse | simulés, contrat identique | 2026-08-21 |
| 20 | Rapport | sort toujours, réserve annoncée | 2026-08-21 |
| 21 | Preuves | dédupliquées par source | 2026-08-21 |
| 22 | Adaptateurs réels | 6 portés, contrat sans exception | 2026-08-21 |
| 23 | Prompt | versionné hors du code | 2026-08-21 |
| 24 | Poids des moteurs | tranché — voir l'entrée 34 | 2026-08-21 |
| 25 | Réponse déjà connue | rendue et non facturée | 2026-08-21 |
| 26 | Rapprochement de rumeurs | chevauchement + plancher | 2026-08-21 |
| 27 | Limitation de débit | 20 analyses/heure | 2026-08-21 |
| 28 | Barre de progression | pilotée par le pipeline | 2026-08-21 |
| 29 | Cohérence du rapport | bloquante avant publication | 2026-08-21 |
| 30 | Vérification sans compte | 2 par adresse, dossier réservé | 2026-08-21 |
| 31 | Historique | montre aussi les analyses en cours | 2026-08-21 |
| 32 | Barre de fiabilité | couleur du verdict | 2026-08-21 |
| 33 | Prix des packs d'unités | ceux du prototype | sprint 3 |
| 34 | Poids des moteurs | une preuve pèse plus qu'une inférence | sprint 3 |
| 35 | Rapport creux | l'attente est annoncée, pas subie | sprint 3 |
| 36 | Hauteur de la sidebar staff | corrigée | audit |
| 37 | Audit responsive | ce qu'il a réellement montré | audit |
| 38 | `match` sans défaut | tous les cas couverts, test paramétré | audit |
| 39 | CSS de page en ligne | extrait en feuilles dédiées | audit |
| 40 | Schéma Académie | ce qu'il tient, ce qu'il refuse | sprint 4 |
| 41 | « Recommandé pour vous » | réparé | sprint 4 |
| 42 | Contenu de l'Académie | extrait, pas retapé | sprint 4 |
| 43 | Forum | deux jugements, jamais mélangés | sprint 5 |
| 44 | Hub | qualifie plus qu'il ne relaie | sprint 5 |
| 45 | Résultat invité | à la suite du formulaire | sprint 5 |
| 46 | Gamification | on ne compte que ce qu'on observe | sprint 7 |
| 47 | Récompenses | un événement, pas une dépendance | sprint 7 |
| 48 | Parrainage | survit à l'effacement du parrain | sprint 7 |
| 49 | Front-end public | l'éditorial se transpose, les verdicts se lisent | sprint 7 |
| 50 | Blog du prototype | vide, et il le dit | sprint 7 |
| 51 | Offres du Hub | lues en base, jamais inventées | sprint 7 |
| 52 | Signalement | n'est pas un vote | sprint 5 |
| 53 | Modération | agit là où elle lit | sprint 5 |
| 54 | Hub d'opportunités | renommé, portails officiels retirés | 2026-08-23 |
| 55 | Cycle de vie d'une offre | classée, fermée après 7 jours de grâce | 2026-08-23 |
| 56 | Agents du chantier | quatre rôles, règles écrites | 2026-08-23 |
| 57 | Examen | n'est pas un quiz sérieux | 2026-08-24 |
| 58 | Schéma des examens | ce qu'il tient lui-même | 2026-08-24 |
| 59 | Se déconnecter | POST, jamais un lien | 2026-08-24 |
| 60 | Connexion sociale | rattachement sur preuve, jamais sur adresse | 2026-08-24 |
| 61 | Notes des cours | pas d'avis, pas de note | 2026-08-24 |
| 62 | Conformité au prototype | mesurée, écran par écran | 2026-08-24 |
| 63 | Écrans en attente | huit transposés, un reste | 2026-08-24 |
| 64 | Contenu de démonstration | extrait du prototype, jamais inventé | 2026-08-24 |
| 65 | Vérifier un certificat | page autonome, sans le menu du site | 2026-08-24 |
| 66 | Fiche publique d'un cours | route créée | 2026-08-24 |
| 67 | Chiffres du prototype | comptés, ou retirés | 2026-08-24 |
| 76 | Prix des packs | en base, avec son écran | 2026-08-25 |
| 77 | Connexion sociale | Google et Facebook en service | 2026-08-25 |
| 78 | Premier accès au back-office | l'enrôlement 2FA existe enfin | 2026-08-25 |
| 79 | Redirection du back-office | vers SA connexion, pas celle des citoyens | 2026-08-25 |
| 80 | Notifications | la cloche est décorative, et on cesse de le promettre | 2026-08-25 |
| 81 | Noms des paramètres | anglais dans l'URL, français dans le corps | 2026-08-25 |
| 82 | Curseur et filtres | le curseur ne les porte pas, et le contrat le dit | 2026-08-25 |
| 83 | Barre supérieure | elle montrait le compte d'un autre | 2026-08-25 |
| 84 | Deuxième facteur à la connexion | il n'existe pas ; l'écran de code sert deux usages | M1 |
| 85 | Champs d'inscription | le contrat l'emporte : prénom + nom + profession, sans ville | M1 |
| 86 | Les commandes qui ne pilotent rien | retirées ou jamais écrites | M1 |
| 87 | Badge de rôle des auteurs | retiré des cartes : l'API n'expose jamais le rôle d'un tiers | M4 |
| 88 | Le cœur du forum | aucun endpoint : compteur en lecture seule, jamais un bouton | M4 |
| 89 | Publier un sujet ou une réponse | jamais mis en file : refusé hors ligne, et l'écran le dit | M4 |
| 90 | Libellés des six motifs de signalement | écrits côté client, faute de source — à valider | M4 |
| 91 | Décomptes du Hub | les offres archivées ne se comptent plus ; le reste n'est pas un total | M4 |
| 92 | Ce que M4 demande au socle | six manques, six écarts visibles, aucun contournement | M4 |
| 100 | Corrigé d'un quiz | il se déroule sur l'écran de résultat, jamais pendant l'épreuve | M3 |
| 101 | Chronomètre d'une leçon | le serveur ne sert que les leçons ouvertes : le cours entier ne se télécharge pas | M3 |
| 102 | Verrou du quiz | aucun champ serveur : non transposé, jamais recalculé côté client | M3 |
| 103 | Quatre chiffres du catalogue | note, inscrits, XP, total : retirés faute de source | M3 |
| 104 | PDF du certificat | bouton retiré : partage d'image et code public à la place | M3 |
| 105 | Ce que M3 demande au socle | quatre demandes, deux écarts assumés, un défaut signalé | M3 |
| 110 | Suppression du compte sur mobile | deux temps — mot de confirmation + mot de passe, puis code | M5 |
| 111 | Opérateur Mobile Money | déduit du numéro : la feuille à trois opérateurs devient une saisie | M5 |
| 112 | Trois réglages de sécurité sans endpoint | 2FA retirée, mot de passe renvoyé au code, adresse en lecture seule | M5 |
| 113 | Chiffres non servis du compte | dotation, barème de coût, progression du prochain badge, appareils : retirés | M5 |
| 114 | Formulaire de profil | « Nom affiché » devient prénom + nom ; profession et téléphone restent ouverts | M5 |
| 115 | Réessai automatique de Riverpod | désactivé : la reprise est visible, jamais silencieuse | M5 |
| 116 | Ce que M5 demande au socle | quatre demandes, aucune contournée | M5 |
| 117 | Bareme de cout du simulateur | retire : aucun endpoint ne le publie, un test verrouille son absence | M2 |
| 118 | Verdict vers couleur | etablie contre `VerdictFactCheck` : quatre valeurs, quatre teintes du DS | M2 |
| 119 | Trois blocs du rapport | moteurs, preuves, retour d'utilite : retires, faute de donnees servies | M2 |
| 120 | Chronologie du suivi | cinq etapes editoriales, etat = fonction pure de `data.avancement` | M2 |
| 121 | Reprise programmee | ni rendez-vous ni bouton de relance : rien n'est servi | M2 |
| 122 | Trois blocs du tableau de bord | XP, « un verdict vous attend », Live Class : non servis | M2 |
| 123 | Partage d'un rapport | pas de lien — le rapport est prive ; texte, copie et image 9:16 | M2 |
| 124 | Filtre des outils | local : liste fermee, non paginee, et consultable hors ligne | M2 |
| 125 | Historique | pas de filtre « en cours », pas de total ; KPI depuis `GET /me/stats` | M2 |
| 126 | Reverb | non branche : ni endpoint d'authentification de canal, ni forme d'evenement | M2 |
| 127 | Ce que M2 demande au socle | cinq demandes, aucune contournee | M2 |
| 128 | Accents des libelles mobiles | rendus a 545 chaines ; les jetons techniques restent nus | Integration |
| 129 | Bandeau « Données du <date> » | trois versions fusionnées ; le ton et la reprise gardent leurs deux raisons | Intégration |
| 130 | Dates, durées, tailles | une seule table ; les DEUX anciennetés conservées | Intégration |
| 131 | Verdict vers ton | une seule correspondance ; `unverifiable` prend le bleu que le serveur publie | Intégration |
| 132 | Réessai de Riverpod | tranché au conteneur, pour les cinq ; la doc rejoint le code | Intégration |
| 133 | `VfButton.expanded` | ne peut plus forcer une largeur infinie ; le `LayoutBuilder` était le mauvais remède | Intégration |
| 134 | Hauteur de `VfCover` | libre, sans effacer la variante brève | Intégration |
| 135 | Solde d'unités | une seule source ; quatre écrans en affichaient quatre | Intégration |
| 136 | Les deux `403` de compte | traités au routeur, sans fabriquer de date | Intégration |
| 137 | Tiroir et déconnexion | le tiroir a un hôte ; `deconnexionProvider` monte au socle | Intégration |
| 138 | Adresse du site public | `VF_WEB_BASE_URL`, et les chemins que le serveur sert vraiment | Intégration |
| 139 | Bouton WhatsApp | variante nommée du socle ; le bouton de vote reste local | Intégration |
| 140 | Purge de la déconnexion | elle oubliait les médias copiés | Intégration |
| 141 | `device_info_plus` | accepté : le point d'injection l'attendait | Intégration |
| 142 | `riverpod` | déclaré, il n'était que transitif | Intégration |
| 143 | Ce qui est refusé et reporté | cinq refus motivés, trois reports, un recensement | Intégration |
| 144 | Captures et `integration_test/` | ce qui manque, exactement | Intégration |
| 145 | Qui porte le verdict corrigé | table polymorphe, deux propriétaires | Lot A |
| 146 | Raison de correction obligatoire | refusée vide, transaction annulée | Lot A |
| 147 | Trois pages hors prototype | transposées avec les classes du build figé | Lot A |
| 148 | Un seul montant publiable | 800 €, et rien d'autre | Lot A |
| 149 | L'auteur d'une correction | conservé, jamais affiché | Lot A |
| 150 | Format de la carte de démenti | 1200 × 630 — le seul rapport que personne ne recadre | Lot B |
| 151 | Le mot du verdict sur la carte | l'enum, jamais « EN COURS » | Lot B |
| 152 | Polices serveur | trois `.ttf` convertis : GD ne lit pas le `.woff2` | Lot B |
| 153 | Diffusion de la carte | une route, jamais `public/storage` | Lot B |
| 154 | Empreinte dans le nom du fichier | contre le cache d'`og:image` de WhatsApp | Lot B |
| 155 | Ouvertures et clics de l'infolettre | retirés : rien ne les mesure | Lot C |
| 156 | Pas de bouton « envoyer maintenant » | l'édition part par la tâche planifiée | Lot C |
| 157 | Le récapitulatif n'est PAS mis en file | sinon les trois compteurs mentent | Lot C |
| 158 | Un dépôt par cas d'usage | une liaison, pas trois, dans un fichier verrouillé | Lot C |
| 159 | `edition_key` hors `$fillable` | `forceFill` plutôt que toucher `Campaign` | Lot C |
| 160 | Notes de communauté | publiées par pontage, jamais à la majorité | Lot D |
| 161 | Algorithme retenu | accord par paires, pas de factorisation | Lot D |
| 162 | Note en attente | invisible comme contexte, pas comme file d'évaluation | Lot D |
| 163 | Score de pontage | jamais affiché — ni celui-là ni la réputation des auteurs | Lot D |
| 164 | Réputation citoyenne | calculée depuis les vérifications, jamais saisie | Lot D |
| 165 | Escalade en fact-check | ouverte à l'auteur du sujet, et tracée | Lot D |
| 166 | Bloc d'infolettre | 7 pages éditoriales, pas le gabarit — et ancre absolue | Intégration |
| 167 | Carte de démenti | câblée par un observateur de modèle, pas par l'écran | Intégration |
| 168 | Formulaire de contact | rendu réel ; deux écrans de lecture ajoutés au prototype | Finalisation |
| 169 | Classes Tailwind « manquantes » | NON déclarées — le prototype s'affiche déjà sans elles | Finalisation |
| 170 | Secret 2FA illisible | compte comme facteur absent, et renvoie à l'enrôlement | Finalisation |

---

## 1. `SameSite=Strict` contre la connexion sociale — TRANCHÉ : `lax`

`SECURITE_LOGIN` demandait `strict`. En strict, le cookie de session n'est pas
renvoyé lors d'une navigation venue d'un autre site — donc jamais au retour d'un
callback OAuth. Google et Facebook échouaient sur « invalid state », l'état
anti-CSRF étant justement rangé dans la session perdue.

Le même piège vaut pour tout retour externe : lien de confirmation reçu par
courriel, retour d'un prestataire de paiement, invitation partagée sur WhatsApp.
Autant de navigations qui arriveraient déconnectées, sans que rien ne l'explique
à la personne.

`lax` reste protecteur là où ça compte : il n'envoie le cookie que sur les
navigations de PREMIER NIVEAU en GET. Un POST cross-site n'emporte rien, et
c'est le vecteur CSRF réel. Les formulaires du produit sont tous en POST, tous
porteurs d'un jeton, et la déconnexion elle-même est en POST exprès. C'est aussi
le défaut de Laravel et le comportement des navigateurs quand l'attribut est
absent.

La réserve était ouverte depuis le sprint 1. Elle bloquait Socialite ; elle ne
bloque plus rien.

## 2. Matrice de permissions — VALIDÉE

`docs/kb/ROLE_ET_PERMISSIONS.md` n'a jamais été fourni. La matrice
(`app/Support/Rbac/MatricePermissions.php`) a été **dérivée** des 46 pages de la
sidebar admin et des 12 pages mentor du prototype : 106 permissions, 34 modules.
Admin 106 · Mentor 26 · Modérateur 12 · Citoyen 0.

Les trois frontières fixées faute de spécification sont **validées** :

1. **Le Mentor ne publie pas.** `course.publish`, `course.archive` et
   `course.delete` restent à l'administrateur : publier un cours engage la
   plateforme.
2. **Le Modérateur ne supprime pas un signalement.** Il assigne et rend un
   verdict ; effacer une trace de désinformation reste une décision
   d'administrateur, traçable.
3. **Le module `student`** (`view`, `progress`) est conservé bien qu'absent de la
   sidebar admin : les pages mentor en ont besoin, il fallait une portée pour la
   porter.

Les Policies restent le contrôle définitif : masquer un bouton n'est pas une
sécurité.

## 3. Consentements RGPD — AJOUTÉS

L'inscription du prototype ne comporte **aucune** case de consentement ; le RGPD
en exige. Deux cases obligatoires (CGU, politique de confidentialité) et une
optionnelle (newsletter), **jamais pré-cochées**, horodatées avec la version du
document acceptée.

C'est la **seule addition structurelle** faite au prototype : la conformité
l'emporte sur la fidélité visuelle, et sur ce point seulement.

## 4. Mot de passe oublié — CODE OTP

Le prototype annonce un **lien** de réinitialisation ; les documents de sécurité
imposent le même contrat OTP que l'inscription — 6 chiffres, TTL 10 minutes,
usage unique, 5 tentatives. **Le code l'emporte** ; la formulation de l'écran a
été adaptée, sa structure non.

## 5. Position des toasts — CELLE DU PROTOTYPE

`TOAST_INSTRUCTION` demandait bas-droite partout. Le prototype place le toast
**citoyen en haut à droite** (`.vf-toast`) et le toast **staff en bas à droite**
(`.st-toast`, recentré sous 640 px). **Le prototype l'emporte** : « on transpose,
on n'invente pas ».

L'hôte est donc positionné par variante (`data-toast-variant`), jamais
globalement — une règle de position sur l'hôte nu s'appliquerait aux deux
espaces. Verrouillé par `tests/Feature/ToastTest.php`.

## 6. Modification de l'adresse e-mail — VÉRIFICATION OBLIGATOIRE

La page « Mon profil » du prototype place l'adresse e-mail au milieu des autres
champs, modifiable et enregistrée avec le reste. Or elle est **l'identifiant de
connexion ET le canal de récupération du mot de passe** : la changer sans
vérification donnerait le compte à quiconque emprunte une session laissée
ouverte, sans jamais connaître le mot de passe.

Elle sort donc du formulaire commun. La modifier envoie un code à la **nouvelle**
adresse ; tant qu'il n'est pas validé, l'ancienne reste seule active. L'adresse
en attente est retenue **en session** et non dans une propriété Livewire : ces
dernières font l'aller-retour par le navigateur, et une valeur qui décide d'un
changement de compte ne doit pas être modifiable entre l'envoi du code et sa
validation. Le cas est testé.

Le bloc reste à sa place dans la page ; seul son fonctionnement change.

## 7. Badge « Identité vérifiée (KYC) » — ADOSSÉ À UNE DATE RÉELLE

Le prototype affiche ce badge en dur, pour tous. Une page qui affirme une
vérification que rien ne soutient est pire qu'une page qui ne l'affiche pas : la
colonne `kyc_verified_at` en est désormais la source, et le badge bascule sur
« Identité non vérifiée » tant qu'elle est vide.

Le parcours de vérification lui-même relève du back-office, au Sprint 6.

---

## Fragilité connue, à trancher plus tard

La règle `email:rfc,dns` (inscription, changement d'adresse) interroge le DNS :
la suite de tests échoue donc hors ligne. Le bénéfice réel est modeste — `dns`
vérifie seulement que le domaine existe, ce qui n'écarte pas les fautes de frappe
sur des domaines squattés. À arbitrer si la CI doit tourner sans réseau.

## 8. Interrupteur 2FA — un clic ne suffit pas

Le prototype fait basculer la double authentification comme n'importe quelle
préférence. Deux raisons l'interdisent :

- **L'activer** suppose d'enrôler une application d'authentification. Un clic ne
  peut pas produire un secret partagé, et activer avant d'avoir la preuve que
  l'application a bien enregistré le secret enfermerait dehors. Le premier code
  valide est cette preuve.
- **La désactiver** affaiblit le compte. Si un clic suffisait, une session
  laissée ouverte suffirait à retirer la protection puis à s'emparer du compte à
  loisir. Mot de passe **et** code en cours sont exigés, et le refus ne dit pas
  lequel des deux est faux.

L'interrupteur reste à sa place et garde son apparence ; c'est ce qu'il
déclenche qui change.

## 9. Sessions actives — ni IP, ni géolocalisation inventée

Le prototype affiche « Android · Cotonou ». Nous ne disposons d'aucune
géolocalisation : l'inventer serait mentir. Afficher l'IP brute à la place ferait
de la page un annuaire d'adresses à la portée de qui emprunte l'écran quelques
secondes.

Seuls le navigateur, le système et l'ancienneté sont montrés — assez pour
reconnaître ses propres appareils, et rien de plus. La liste est **réelle** : le
pilote de session est la base de données, ce qui donne son sens au bouton
« Déconnecter ».

## 10. Longueur du mot de passe — 12, et à un seul endroit

Le prototype annonce « 8 caractères min. » ; `SECURITE_LOGIN` impose 12 avec
casse mixte, chiffres, symboles et vérification des fuites connues. La règle de
sécurité l'emporte, et l'étiquette de l'écran a été alignée : un formulaire ne
doit pas promettre moins que ce qu'il exigera.

Découvert à cette occasion : `Password::defaults()` n'avait jamais été
configuré et retombait sur le minimum de Laravel — huit caractères.
L'inscription y échappait en répétant la règle en dur. La politique est
désormais définie **une seule fois**, dans `AppServiceProvider`.

## 11. Lettre d'information — consentement, pas préférence

Placée parmi les interrupteurs de notification dans le prototype, elle est
enregistrée dans `consents` et non dans la colonne de préférences : le RGPD exige
un accord horodaté, rattaché à une version de document, et un retrait aussi
simple que l'accord. Chaque bascule **ajoute une ligne** plutôt que d'en modifier
une — l'historique des accords et des retraits est exactement ce qu'il faut
pouvoir produire en cas de contrôle.

## 12. Suppression du compte — le mot du prototype ne suffit pas

Le prototype demande de taper « SUPPRIMER » et efface. Le geste est conservé,
mais il prouve l'intention, pas l'identité : un écran laissé déverrouillé
suffirait à détruire le compte de quelqu'un d'autre. S'y ajoutent donc le **mot
de passe** puis un **code envoyé par courriel**, chacun couvrant ce que l'autre
ne couvre pas — le mot de passe prouve la connaissance, le code prouve la
maîtrise de la boîte.

L'état « premier temps franchi » vit en **session** : une propriété Livewire
revient du navigateur à chaque requête, et celle-ci décide si un code seul peut
détruire un compte.

## 13. Effacement — trente jours, puis anonymisation

Un effacement immédiat ne laisse aucune place au regret. Le compte est fermé
sur-le-champ — plus aucune connexion possible — mais les données ne
disparaissent qu'au bout de **30 jours**.

La rétractation passe par un **lien signé envoyé par courriel**, et c'est le
cœur du dispositif : le compte étant fermé, il n'est plus possible de se
connecter pour changer d'avis. Ce courriel est aussi ce qui alerte la vraie
personne quand la demande vient d'un écran resté ouvert.

À l'échéance, l'effacement prend la forme d'une **anonymisation** et non d'un
`DELETE` : détruire la ligne romprait les journaux d'audit, les certificats émis
et les contributions au forum — une demande d'effacement deviendrait une
corruption de l'historique d'autres personnes. Ce qui part : nom, adresse,
téléphone, ville, bio, secrets, préférences, rôles, jetons d'API, sessions. Ce
qui reste : la ligne, ses compteurs agrégés, et la date de son anonymisation.

Les archives d'export, elles, partent **immédiatement** : ce sont des copies
complètes des données de quelqu'un qui en demande l'effacement.

## 14. Archive de portabilité — jamais en pièce jointe

Le courriel ne porte qu'un **lien signé valable 24 heures**. Un courriel se
relaie, se transfère et se conserve indéfiniment sur des serveurs que nous ne
maîtrisons pas ; l'archive, elle, rassemble en un seul fichier tout ce qui
concerne une personne.

Le téléchargement exige **trois** conditions, aucune redondante : la signature
(le lien vient de nous), la session (une boîte mail se compromet), et la
vérification du propriétaire (un lien transféré ne doit pas livrer les données
d'autrui à qui se trouve connecté).

---

## Piège de configuration : APP_URL

Les liens signés sont calculés sur `APP_URL`. Une valeur qui ne correspond pas à
l'URL réellement servie les rend **tous** invalides — y compris le lien
d'annulation de suppression, seule voie de retour d'un compte fermé. Découvert en
vérifiant le parcours de bout en bout : `APP_URL` valait `http://localhost` alors
que le serveur de développement écoutait sur un autre port.

## 15. Packs d'unités — DÉCISION COMMERCIALE OUVERTE

Le prototype et `PAIEMENT_MOBILE_MONEY.md` n'annoncent pas les mêmes quantités :

| prix | prototype | instruction |
|---|---|---|
| 500 F | 10 unités | 25 unités |
| 1 000 F | 25 unités | 55 unités |
| 2 000 F | 60 unités | 120 unités |

Les valeurs du **prototype** sont appliquées — c'est la référence visuelle
validée, et « on transpose, on n'invente pas ». Mais l'écart est un facteur 2 à
2,5 sur la valeur vendue : **c'est une décision commerciale, pas technique**.
Elle se change sur une ligne dans `config/economy.php`, sans toucher au code.

## 16. Dotation mensuelle — elle AJOUTE, elle ne remet pas à zéro

La feuille de route nomme la tâche « ResetMonthlyUnits ». Le prototype dit
« 10 unités offertes chaque 1ᵉʳ du mois ». Une remise à zéro ferait perdre à un
compte ayant acheté 210 unités tout ce qu'il a payé : la dotation **crédite**.

Si l'intention est que le gratuit ne s'accumule pas, il faut **deux compteurs**
— offert et acheté — et non un plafond sur le total. À trancher avec les packs.

La commande est idempotente par mois civil : une reprise après incident ou un
planificateur qui double ne crédite pas deux fois.

## 17. Le montant vient du serveur, toujours

Le client n'envoie qu'un **identifiant de pack**. Le prix et la quantité sont
lus dans la configuration à l'initiation, figés sur la ligne de paiement, et
relus **de cette ligne** au moment de créditer — jamais de la charge du rappel,
même signée. Un client modifié qui enverrait « 99 999 unités » n'obtient rien de
plus. Testé.

## 18. Rappel Mobile Money — la surface la plus exposée

Une route **publique** dont l'effet est de créditer des unités. Quatre gardes :
signature HMAC à temps constant, paiement connu, transition verrouillée en base,
montant lu sur notre ligne. La référence opérateur est **unique** en base : un
rappel rejoué — par l'opérateur qui n'a pas reçu notre accusé, ou par un
attaquant qui l'a intercepté — ne crédite jamais deux fois.

Sans secret configuré, **tout** rappel est rejeté : accepter par défaut ouvrirait
le crédit d'unités à qui devine l'URL. C'est la seule route exclue de la
protection CSRF, l'opérateur étant un serveur tiers qui ne peut pas porter notre
jeton de formulaire.

Un rappel rejoué reçoit **200** et non une erreur : sans accusé, l'opérateur
rejouerait indéfiniment. Le champ `duplicate` dit ce qui s'est réellement passé.

---

## Dette assumée : la couche Repository

`CLAUDE.md` impose « aucune requête Eloquent hors Repository ». Le code des
Sprints 1 et 2 interroge Eloquent directement depuis les services. La couche est
posée à partir du domaine Économie (`UnitLogRepositoryInterface` +
`EloquentUnitLogRepository`) ; les domaines antérieurs restent à rattraper.

Le rattrapage est mécanique mais large — chaque service touché, chaque test
touché. À planifier comme un lot, pas à disperser.

## 19. Moteurs simulés — le pipeline complet, sans clé d'API

Aucune clé n'est disponible pour les 19 adaptateurs du catalogue. Le pipeline
tourne donc avec un adaptateur **simulé** qui respecte le MÊME contrat : le jour
où les clés arrivent, on écrit les implémentations réelles et l'on bascule
`FAKE_ADAPTERS` — l'orchestrateur, le scoring, le rapport et l'écran ne bougent
pas d'une ligne.

Ses réponses sont **déterministes**, dérivées de l'affirmation : la même phrase
donne toujours le même verdict. Un simulateur aléatoire rendrait la
démonstration incompréhensible et les tests ingouvernables. Un moteur sur onze
échoue, de façon reproductible : l'orchestrateur doit être exercé sur le cas
« un service ne répond pas », qui est le cas courant, pas l'exception.

Il **refuse de tourner en production** : rendre des verdicts inventés sur une
plateforme de fact-checking serait exactement ce qu'elle combat.

## 20. Le rapport sort toujours

Un moteur en panne, deux moteurs qui se contredisent, aucune synthèse rédigée :
rien de tout cela ne produit un écran vide. Ce qui change, c'est ce que le
rapport **annonce**.

- **Contradiction** (écart < 25 points) → statut « arbitrage humain », rapport
  non publié mais consultable par son auteur, les deux thèses affichées. Publier
  un faux consensus serait exactement ce que la plateforme combat.
- **Moins de deux moteurs exploitables** → verdict « non vérifiable », bandeau
  d'analyse partielle, **remboursement automatique** des unités. Une personne qui
  a dépensé doit repartir avec un rapport ou avec ses unités — jamais ni l'un ni
  l'autre.
- **Synthèse rédigée absente** → repli sur une synthèse composée côté serveur.
  La rédaction est un enrichissement, jamais un bloquant.
- **Verdict incohérent avec les moteurs** → blocage de publication et arbitrage.
  Un rapport dont les sources contredisent son propre verdict détruirait la
  confiance plus sûrement qu'une erreur franche.

Le score est **normalisé** : sans cela, il dépendrait du nombre de moteurs
interrogés, et une analyse plus large paraîtrait toujours plus sûre.

Les détecteurs d'IA et la transcription ne portent **pas** de verdict : ils
disent qu'un texte ressemble à une production automatique, pas qu'il est faux.
Confondre les deux ferait condamner une information vraie sur son seul style.

## 21. Preuves dédupliquées par source

Une même source citée par cinq moteurs apparaîtrait cinq fois et donnerait
l'illusion de cinq confirmations indépendantes — le biais même que le
fact-checking défait. Les preuves sont regroupées par URL ; quand deux moteurs
les lisent en sens opposé, elles sont marquées **contestées** et leur poids
divisé, plutôt qu'arbitrées en silence.

## 22. Adaptateurs réels — six moteurs portés sur dix-neuf

Le catalogue en compte dix-neuf ; six sont portés, choisis par leur poids dans
la décision : **Gemini**, **DeepSeek**, **Google Fact Check**, **Perplexity**,
**Google Custom Search**, **Analyse technique**. Restent à porter : OpenAI,
Knowledge Graph, TinEye, Vision, détecteurs d'IA, transcription.

Un moteur non porté est **absent**, pas en erreur : l'analyse tourne avec ceux
qui existent plutôt que d'attendre les autres.

Le socle `AdaptateurHttp` tient à lui seul la promesse du contrat —
`analyse()` **ne lève jamais**. Clé absente, service éteint, JSON malformé,
exception inattendue : la méthode rend toujours un verdict de moteur. Sans
cela, un fournisseur en panne ferait tomber l'analyse entière alors que onze
autres ont répondu, et la personne perdrait ses unités pour rien.

Le message d'exception ne remonte **jamais** dans le rapport : il peut contenir
une URL de service, un identifiant, voire une clé recopiée par une bibliothèque
tierce. Il va au journal, le rapport reçoit un motif neutre. Testé.

### Ce que chaque moteur a le droit de faire

- **Google Fact Check** porte le poids le plus fort parce qu'il ne raisonne
  pas : il RETROUVE. Un démenti publié par l'AFP vaut mieux que l'intuition
  d'un modèle. Il **s'abstient** quand il ne trouve rien — beaucoup de rumeurs
  béninoises n'ont jamais été vérifiées, et traiter cette absence comme un
  signal ferait peser sur chacune le simple fait que personne ne s'en est
  encore occupé.
- **Google Custom Search** ne tranche pas : il apporte des sources, avec un
  verdict nul. Il interroge aussi la formulation **adverse** (« X démenti ») —
  ne chercher que l'affirmation ne trouve que ce qui la répète, et c'est ainsi
  qu'une rumeur se confirme elle-même.
- **Analyse technique** qualifie le SUPPORT, pas l'affirmation : elle rend
  « trompeur » au pire, jamais « faux ». Un site douteux peut relayer une
  information exacte. Sa confiance est plafonnée à 70.
- **DeepSeek** n'a pas d'accès web : ses « sources » sont des souvenirs
  d'entraînement. Fiabilité volontairement basse — pistes à recouper, pas
  preuves. Sa raison d'être n'est pas d'être meilleur que Gemini, c'est d'être
  AUTRE : deux modèles de familles différentes hallucinent rarement la même
  chose.
- **Gemini** et **Perplexity** citent les pages qu'ils ont RÉELLEMENT ouvertes.
  Ces citations priment sur celles que le modèle déclare — c'est la différence
  entre une source vérifiable et une source plausible.

### Robustesse de lecture

La lecture des verdicts est délibérément tolérante : les modèles rendent
« FAUX », « false », « faux » selon la version. Un parseur strict
transformerait chaque mise à jour de modèle en panne silencieuse. Ce qui n'est
pas reconnu devient « non vérifiable », jamais un verdict par défaut. Les
blocs markdown enrobant le JSON sont retirés — ne pas le prévoir coûterait un
moteur qui échoue une fois sur trois. Les confiances aberrantes sont bornées.

## 23. Le prompt vit hors du code

`resources/prompts/factcheck/analyse.md`. C'est le texte qui décide de la
qualité des verdicts : il doit pouvoir être relu, comparé et corrigé sans
toucher à PHP, et son historique doit être lisible dans le dépôt. Un prompt en
dur dans une classe ne se relit pas.

Il demande verdict ET indice de haine dans une seule réponse : deux appels
coûteraient deux fois plus pour une information que le modèle a déjà sous les
yeux.

---

## Divergence à trancher : les poids des moteurs

| Moteur | Documentation | Moteur de juillet |
|---|---|---|
| Google Fact Check | **1,0** | 3,0 |
| Gemini | 0,7 | **5,0** |
| DeepSeek | 0,7 | 3,0 |
| Perplexity | 0,6 | 4,0 |

Les deux barèmes s'opposent sur le fond : la documentation fait davantage
confiance aux vérifications déjà publiées, le code de juillet aux modèles de
langage. Les valeurs de la **documentation** sont appliquées — un fact-check
signé par une rédaction accréditée vaut mieux qu'une intuition de modèle — mais
le code de juillet reflète l'expérience réelle du terrain. À trancher.

À vérifier aussi : le moteur de juillet visait `gemini-3.5-flash`, un nom qui
n'existe pas. Chaque appel tombait vraisemblablement en repli silencieux sur
`gemini-1.5-flash`. La configuration vise désormais `gemini-2.5-flash`, à
confirmer contre le catalogue courant.

## 25. Une réponse déjà connue n'est pas facturée

Une rumeur virale est signalée des centaines de fois. Avant d'appeler douze
moteurs payants, la plateforme regarde si la réponse existe déjà — dans la
**base de faits tranchés par l'équipe**, puis parmi les **analyses récentes du
même contenu**.

Quand elle la trouve, **les unités sont rendues**. L'écran a bien annoncé un
coût, il ne mentait pas ; mais faire payer une réponse qui n'a rien coûté serait
indéfendable, et une plateforme qui combat la désinformation a tout intérêt à ce
que la même rumeur soit signalée par le plus de monde possible. Le journal des
unités porte la mention « non facturée ».

Les preuves sont **recopiées**, pas partagées : le rapport doit rester lisible
si l'analyse d'origine disparaît — compte effacé, par exemple — et personne ne
doit dépendre de la demande d'un autre pour consulter sa propre réponse.

Trois garde-fous sur la reprise :
- **sept jours** de fraîcheur au plus. Une information peut être démentie la
  semaine suivante ; servir un verdict périmé serait pire que de ne rien servir ;
- jamais une analyse **remboursée** : elle était incomplète, c'est précisément
  pourquoi elle l'a été ;
- jamais une analyse **en arbitrage** : son verdict n'engage pas encore la
  plateforme.

## 26. Rapprocher deux rumeurs sans les confondre

L'empreinte est calculée sur le texte **normalisé** — casse, accents,
ponctuation et espaces écartés. Une rumeur circule en se déformant au fil des
partages ; hacher le texte brut ferait de chaque variante une rumeur
différente, et l'économie disparaîtrait au premier transfert WhatsApp.

Le rapprochement approximatif utilise le **coefficient de chevauchement** et non
l'indice de Jaccard : comparer une phrase entière à une liste de mots-clés par
recouvrement mutuel pénalise la phrase pour chaque mot qu'elle contient en plus.
S'y ajoute un **plancher de trois mots significatifs communs** — un ratio seul
se laisse tromper par un fait aux mots-clés très courts, où deux mots partagés
sur deux font 100 %.

Le seuil est délibérément haut. Servir le verdict d'une rumeur pour une autre
serait l'erreur la plus grave possible sur un fact-check.

Le calcul se fait **en PHP**, pas en SQL : une recherche plein texte serait plus
fine mais dépendrait du moteur de base de données et devrait être réécrite à la
première migration. Elle serait surtout inexplicable — or quand la plateforme
dit « cette rumeur a déjà été tranchée », elle doit pouvoir montrer pourquoi.

## 27. Limitation de débit — distincte du coût en unités

Les deux ne protègent pas de la même chose. Les **unités** bornent la dépense
d'une personne ; le **plafond horaire** borne la charge sur des services tiers
facturés à l'appel. Un compte rechargé pourrait sinon lancer mille analyses en
une minute et épuiser les quotas de toute la plateforme.

Vingt analyses par heure et par compte. Le compteur n'est incrémenté qu'après
une soumission réussie : un refus faute de solde ne doit pas grignoter le quota.

## 28. La barre de progression ne ment pas

Le prototype fait défiler ses six étapes sur des minuteurs aléatoires. Ici elles
sont posées par l'orchestrateur **au moment où il les franchit** : si l'analyse
cale sur les moteurs, l'affichage cale avec elle.

Une barre qui avance toute seule pendant qu'un service est en panne ment à qui
la regarde, et le fait attendre un rapport qui n'arrivera pas. Passé un délai
raisonnable, l'écran le dit et propose de revenir plus tard.

La chronologie horodatée est conservée dans le rapport : elle explique après
coup où le temps est passé.

## 29. Verdict, synthèse et détails ne peuvent pas se contredire

Les trois sont produits séparément et peuvent diverger. Un rapport qui titre
« FAUX » et dont la synthèse commence par « cette information est exacte »
détruit la confiance plus sûrement qu'une erreur franche : le lecteur ne sait
plus quoi croire, et il a raison.

`CoherenceRapport` confronte trois niveaux avant publication :

- le **verdict** contre l'avis majoritaire des moteurs ;
- la **synthèse** contre le verdict — on cherche l'affirmation CONTRAIRE, pas la
  confirmation : « les sources concordent » vaut « vrai » sans répéter le mot ;
- les **preuves** contre le verdict, avec tolérance : un dossier honnête contient
  des sources des deux bords, seul un renversement net vaut alerte.

Toute incohérence bloque la publication et bascule en arbitrage humain. Le
contrôle prendra tout son sens le jour où la synthèse sera rédigée par un modèle
de langage — c'est exactement là qu'un rapport commencera à dire autre chose que
son propre verdict.

Corollaire trouvé en navigateur : la synthèse annonçait « 14 sources
consultées » sous un rapport qui en montrait 13, parce qu'elle comptait les
retours bruts et le rapport les sources dédupliquées. Le comptage se fait
désormais après déduplication — comme le lecteur les verra.

## 30. Vérification sans compte — la porte d'entrée

Quelqu'un qui reçoit une rumeur sur WhatsApp doit pouvoir la soumettre en dix
secondes. Lui opposer un formulaire d'inscription, c'est le laisser partager
sans vérifier.

Trois différences avec l'espace citoyen, toutes assumées :

- **deux vérifications par adresse**, sur 24 heures. Sans compte, rien d'autre
  ne borne l'appel à des services facturés. L'adresse n'est jamais stockée en
  clair : la clé de quota porte sur son empreinte, comme partout ailleurs — ce
  garde-fou ne doit pas devenir un fichier de connexions ;
- **texte et lien seulement**. Un média coûte cinq unités et mobilise des
  moteurs lourds ;
- le **verdict et la synthèse sont montrés**, le dossier demande un compte. On
  ne cache pas la RÉPONSE, seulement les moteurs, les sources et l'indice de
  haine — et l'écran les nomme précisément. Une promesse vague ne convaincrait
  personne et laisserait croire qu'on retient le verdict.

L'analyse est mise en **file**, pas exécutée dans la requête : l'exécution
synchrone gelait la page plusieurs secondes en simulation, et l'aurait gelée une
minute avec de vrais moteurs. Le visiteur suit l'avancement sur place, et on lui
dit franchement que sans compte son rapport ne sera pas conservé.

Détail relevé à l'écran : le shell public ne charge pas `citizen.css`, où vivent
les classes `vbadge` — la pastille de verdict y sortait grise, sans distinguer
un « vrai » d'un « faux ». Elle utilise désormais les utilitaires présents dans
le build public.

## 31. L'historique montre aussi ce qui n'est pas fini

« Mes Vérifications » est la page où l'on revient chercher un rapport fermé trop
vite. Elle affiche donc les analyses **en cours** au même titre que les
terminées, avec leur état à la place du verdict — un tiret laisserait croire à
un échec. Le lien mène à l'écran qui convient : le rapport s'il existe, le suivi
sinon.

Le filtre mélange verdicts et état « en cours », comme le prototype. Ce n'est pas
une confusion de catégories mais le point de vue juste : quelqu'un qui ouvre
cette page cherche « où en est ma demande », pas une taxonomie.

« Intox détectées » réunit **faux et trompeur** : du point de vue de quelqu'un
qui a failli partager, les deux sont des pièges.

Les indicateurs portent sur **tout l'historique**, jamais sur la page filtrée :
ce sont des repères sur son activité, et ils changeraient de sens en suivant les
filtres.

Deux états vides distincts — « aucun résultat » et « votre laboratoire est
encore vide » — parce qu'ils n'appellent pas la même action : réinitialiser les
filtres d'un côté, lancer une première vérification de l'autre.

## 32. La barre de fiabilité prend la couleur du verdict

Une barre **verte** sous un verdict « non vérifiable » dit « tout va bien » là
où il n'y a précisément aucune assurance ; sous un « faux », elle féliciterait
le lecteur d'avoir trouvé une intox.

Le prototype le fait déjà (`cb.style.background = t.color`) : la barre laissée
verte était un écart de transposition, repéré à l'écran. Corrigé sur le rapport
citoyen comme sur le résultat invité, et verrouillé par un test.

## 33. Prix des packs d'unités — le prototype l'emporte

`unites.html` et `PAIEMENT_MOBILE_MONEY.md` n'annonçaient pas les mêmes
quantités : 10/25/60 unités contre 25/55/120, pour 500/1 000/2 000 F.

Retenu : **le prototype** (10/25/60), pour trois raisons.

C'est ce qui a été validé et **affiché** — revenir dessus, c'est décider d'un
prix, pas transposer. À sept moteurs par vérification, 20 F l'unité ne
couvrirait probablement pas le coût réel des API, et vendre à perte est le moyen
le plus sûr de n'avoir plus de plateforme du tout. Enfin, l'accessibilité — le
vrai sujet derrière le barème généreux — est déjà servie autrement : dotation
mensuelle de 10 unités, et gratuité des réponses déjà connues, si bien qu'une
rumeur virale ne coûte rien à revérifier.

Décision **révisable sur une ligne** de `config/economy.php`, sans toucher au
code : `units` est la seule source du montant crédité, le client n'envoie qu'un
identifiant de pack.

## 34. Poids des moteurs — une preuve pèse plus qu'une inférence

Deux barèmes s'opposaient : la documentation fait davantage confiance aux
vérifications **déjà publiées**, le moteur écrit en juillet aux **modèles de
langage**. Retenu : celui de la documentation, avec une nuance qu'aucun des deux
ne portait.

Un fact-check publié par une rédaction est une **preuve** : quelqu'un a fait le
travail, l'a signé, en répond. Un verdict de modèle est une **inférence** :
plausible, non tracée, parfois inventée. Une plateforme de fact-checking qui
ferait peser l'avis d'un modèle plus lourd qu'un démenti de l'AFP se
contredirait elle-même.

La nuance : tous les modèles ne se valent pas selon qu'ils **citent ce qu'ils
ont ouvert** ou jugent de mémoire. Gemini et Perplexity, ancrés sur des pages
réelles, sont à 0,75 ; DeepSeek, sans accès web, à 0,55. Ce n'est pas une
préférence de marque mais une différence de nature : l'un rapporte, l'autre se
souvient.

Barème complet dans `config/factcheck.php`, commenté ligne à ligne.

## 35. Aucun rapport creux : l'attente est annoncée, pas subie

Rendre « Verdict : synthèse indisponible » quand les moteurs sont saturés est la
**pire** réponse possible. La personne repart sans rien, persuadée que le
produit ne marche pas — alors que le problème est passager et que ses unités
sont prélevées.

Nouvelle règle : quand trop peu de moteurs répondent, l'analyse n'échoue pas,
elle est **reprogrammée**. Statut dédié `EnAttenteDeReprise` (« Reprise
programmée »), rendez-vous à **12 minutes** — dans la fourchette de dix à quinze
annoncée à l'écran — et **3 tentatives** au maximum.

Ce que voit la personne : ce qui se passe (« nos moteurs sont momentanément
saturés »), quand ça reprend, ce que deviennent ses unités. La reprise part
d'elle-même ; un bouton « Relancer maintenant » apparaît une fois le délai
écoulé, pour ne pas obliger à attendre un job programmé à la minute près.

Trois conséquences qui ne se voient pas mais comptent autant :

- Un **échec technique** (exception dans le pipeline) suit le même chemin :
  reprogrammé tant qu'il reste des tentatives. L'ancien code marquait « échec »
  **sans rembourser** — la personne avait payé et repartait les mains vides.
- Au bout des tentatives, on **renonce franchement** et les unités reviennent.
  Entretenir indéfiniment un espoir serait pire que de renoncer.
- Le **visiteur sans compte** y a droit aussi. « Revenez dans dix minutes » est
  une promesse creuse s'il retombe sur un formulaire vide : sa demande est
  retenue en **session** — et non sur l'empreinte de son adresse, car derrière
  un même NAT deux visiteurs partagent l'adresse mais pas la session, et
  personne ne doit voir passer la demande d'un autre.

## 36. La sidebar staff sortait à moitié de sa hauteur

Les rubriques repliables de l'administration (`FACT-CHECKING`, `ACADÉMIE`,
`EXAMENS`…) mesuraient **24 px au lieu de 42**, et la sidebar était noire au
lieu du vert profond `#052E16`. Défaut relevé à l'écran, en comparant au
prototype, puis mesuré : `.st-group summary` sans `padding`, `.st-sub` sans
retrait, `.sidebar` sans fond.

Cause : le prototype ne met pas ces règles dans un fichier. Il **répète un bloc
`<style>` en ligne dans chacune de ses 59 pages** admin et mentor — vérifié :
les 59 blocs sont identiques au caractère près. L'extraction programmatique du
shell avait pris le balisage et laissé le style derrière.

Corrigé par `public/assets/css/staff-shell.css`, extraction **verbatim** de ce
bloc, chargée **après tailwind.css** — la place exacte qu'il occupe dans le
prototype, et d'où il tire sa spécificité. Aucune règle ajoutée ni retouchée.
La cascade est verrouillée par un test.

Après correction, `<summary>` mesure 213 × 42 avec `padding: 10.4px 12px`, la
`.st-sub` retombe à `padding-left: 16px` — au pixel près le prototype.

## 37. Ce que l'audit responsive a réellement montré

Vérifié sur **cinq largeurs** — 390, 768, 1024, 1280, 1512 — pour les trois
shells (public, citoyen, staff), en pilotant Chrome par CDP. Deux détours à
connaître, car ils font conclure faux :

- `--window-size` de Chrome subit la mise à l'échelle Windows : demander 390 px
  donne une mise en page de **548 px**, et la capture semble déborder alors que
  rien ne déborde. Seul `Emulation.setDeviceMetricsOverride` donne la largeur
  demandée.
- L'application envoie `X-Frame-Options: DENY` — un harnais par `<iframe>` ne
  peut pas la mesurer. C'est la bonne réponse du serveur ; c'est le harnais
  qu'il fallait changer.

Résultat : **aucun débordement de page** nulle part. Les tableaux dépassent leur
colonne mais défilent dans leur propre conteneur, ce qui est le comportement
voulu. Sidebar hors-champ sous 1024 px, barre du bas sur mobile et tablette,
sidebar fixe au-delà — identique au prototype.

Le balisage des trois shells a été diffé ligne à ligne contre le prototype : la
seule différence est l'état actif, calculé à l'exécution. Ce n'est pas une
ressemblance, c'est une identité.

## 38. Un `match` sans défaut est un piège à retardement

Ajouter `EnAttenteDeReprise` à `StatutVerification` a fait planter « Mes
Vérifications » — `libelle()` n'avait pas la nouvelle branche, et PHP lève
`UnhandledMatchError` à l'affichage, pas à la compilation. La page entière
tombait ; les tests ne l'ont pas vue, le navigateur si.

Deux mesures, pas une :

- Un test paramétré sur `StatutVerification::cases()` exige un libellé et une
  pastille pour **chaque** état. Le prochain cas ajouté fera échouer un test au
  lieu d'une page.
- `valeursEnCours()` remplace les deux listes `[EnFile, EnCours]` écrites à la
  main dans « Mes Vérifications » — l'une pour le filtre, l'autre pour le
  compteur. Elles auraient divergé, et c'est le compteur qui aurait menti en
  premier : une analyse en attente de reprise n'apparaissait ni dans le filtre
  « en cours », ni dans le total.

## 39. Le CSS de page du prototype vivait en ligne — 46 règles perdues

Après la sidebar staff (§36), le même défaut, en plus large : le prototype
déclare des règles dans un bloc `<style>` **à l'intérieur de chaque page**.
L'extraction du shell prend le balisage et laisse le style. Relevé à l'écran :
les pastilles de catégorie du catalogue sortaient en **texte brut**.

Inventaire fait par script, en comparant chaque sélecteur en ligne au CSS
réellement chargé : **46 règles absentes**, réparties sur 13 pages —
`hub-pill`, `hub-card`, `hub-badge-*`, `no-scrollbar`, `fact-box`,
`content-style`, `featured-banner`, `course-tab`, `icon-act`…

Une feuille **par page** dans `public/assets/css/pages/`, poussée par la vue via
`@push('styles')`, donc après `tailwind.css` — la place qu'occupe le bloc dans
le prototype. Pas un fichier commun : `catalogue.html` et `hub-infos.html`
définissent `.hub-pill` et `.no-scrollbar` **différemment**, et les fusionner en
écraserait une.

## 40. Académie — ce que le schéma tient, et ce qu'il refuse

Le prototype range ses leçons **à plat**, sans sections, alors que le
dictionnaire de données mentionne une table `sections`. Aucun des dix écrans de
l'Académie ne l'affiche : une table vide traversée à chaque affichage serait de
la dette pure. La position ordonne les leçons ; des chapitres s'ajouteront sans
toucher à l'existant.

À l'inverse, `answers` est bien une table, comme le dictionnaire le demande, et
non un tableau JSON d'options. La raison est concrète : `picked_count` par
réponse dit au mentor **quelle mauvaise réponse attire**, donc où sa leçon
n'accroche pas. Un JSON ne se compte pas.

Quatre règles sont tenues par le SCHÉMA et non par le code, parce qu'une règle
qui ne vit que dans le code finit contournée par un job ou un import :

- **une** inscription par personne et par cours — sinon un double-clic débite
  deux fois ;
- **une** complétion par leçon — sinon relire fait dépasser 100 % ;
- **un** avis par cours — sinon une note se noie sous les doublons de son
  auteur ;
- les **tentatives de quiz**, elles, sont volontairement multiples : garder
  l'historique est ce qui permet de dire « vous progressez » plutôt que « vous
  avez échoué ».

Deux colonnes figent ce qui ne doit pas bouger : `paid_units` sur l'inscription
(le prix change, ce qui a été déboursé non) et `holder_name` / `course_title`
sur le certificat — un diplôme atteste de ce qui a été suivi CE jour-là, et ne
doit ni mentir après un renommage, ni redevenir nominatif après un effacement
RGPD.

Le « cours offert du mois » est une **date de fin**, pas un drapeau : un drapeau
qu'il faut penser à retirer reste allumé, et le cours resterait gratuit
indéfiniment.

## 41. « Recommandé pour vous » ne s'affichait jamais

Le prototype filtre ses recommandations sur l'état `NOT_STARTED`, valeur que sa
propre fonction `courseStatus()` ne renvoie **jamais** — elle rend
`NOT_ENROLLED`. La section est donc toujours vide, donc toujours masquée.

Transposer le bug aurait produit du code mort, ce que STRICT_AVOID proscrit.
L'intention est limpide : proposer ce qui n'a pas été commencé. Implémentée
ainsi, avec les mieux notés d'abord.

## 42. Le contenu de l'Académie est EXTRAIT, pas retapé

Quatre cours, 29 leçons, 7 quiz, 28 questions, 2 exercices : extraits par script
de `vf-store.js` vers `database/seeders/data/lms-courses.json`, que le seeder
lit. Recopier 800 lignes de contenu pédagogique à la main, c'est garantir une
coquille quelque part — et une coquille dans une **bonne réponse de quiz**,
personne ne la voit avant qu'un apprenant soit noté faux à tort.

Réserve à connaître : le prototype affiche le **même texte d'illustration** sur
toutes ses leçons. Ce n'est pas un choix éditorial, c'est une limite du
prototype. Le corps réel s'écrira dans le studio mentor ; le reproduire tel quel
plutôt qu'inventer quatre-vingts pages de cours est la seule option honnête.

## 43. Deux jugements sur le forum, jamais mélangés

Le forum porte l'AVIS de la communauté — un sondage — et le VERDICT de
Vérif'All — une vérification. Deux enums distincts (`AvisCommunaute`,
`VerdictFactCheck`), deux colonnes, deux emplacements à l'écran.

Ce n'est pas de la coquetterie de modélisation. Une majorité qui se trompe reste
une majorité qui se trompe : c'est même le mécanisme central de la
désinformation. Une plateforme de fact-checking qui laisserait le sondage passer
pour un verdict se contredirait dans son écran le plus fréquenté. Un test
verrouille le cas : la communauté croit l'information vraie à 100 %, la
plateforme établit qu'elle est fausse, et les deux s'affichent sans s'écraser.

Trois autres décisions du forum :

- La **transmission au labo** est gratuite. C'est la plateforme qui décide de
  vérifier, pas le citoyen qui commande : lui facturer son signalement serait
  absurde. Elle n'a lieu qu'une fois — deux analyses de la même rumeur
  coûteraient double et pourraient rendre deux verdicts sur le même fil.
- Le lien signalé sort en `rel="nofollow noopener ugc"`. Une plateforme qui
  combat la désinformation ne transmet pas son autorité de référencement à
  l'arnaque qu'on lui signale.
- **Un seul niveau d'imbrication** dans les réponses, comme le prototype :
  au-delà, un fil devient illisible sur téléphone. Répondre à une réponse
  imbriquée rattache au même parent.

## 44. Le Hub relaie moins qu'il ne qualifie

Chaque brève porte le verdict rendu par l'équipe. C'est ce qui sépare le Hub
d'un agrégateur : on n'y relaie pas une information, on dit ce qu'elle vaut.

Le verdict réutilise `VerdictFactCheck` — pas de second vocabulaire en base. Le
Hub AFFICHE « DÉMENTI » là où le rapport dit « FAUX », mais c'est un registre,
pas une divergence : on dément une rumeur, on qualifie une affirmation. La
traduction vit dans une méthode du modèle, une seule fois.

Une brève sans verdict affiche « EN COURS » et non un bandeau vide : un bandeau
vide passerait pour un défaut d'affichage.

`is_official` sur les portails est un drapeau EXPLICITE, jamais déduit du nom de
domaine. Le déduire en code reviendrait à commettre l'erreur que la plateforme
enseigne à éviter : « gouv-benin.info » n'est pas « gouv.bj ».

Deux réserves d'extraction, relevées à l'écran :

- Trois brèves du prototype n'ont **pas de chapô visible** — leur texte ne vit
  que dans l'attribut `data-search`, en minuscules. Il a été récupéré là plutôt
  qu'inventé, puis recapitalisé après chaque point. C'est de la récupération,
  pas de la rédaction.
- Le prototype colle source, date et durée de lecture sur une seule ligne
  (« ARCEP Bénin · 13 août 2026 · Lecture 4 min »). La première extraction avait
  tout mis dans `source_name`, et la page affichait deux dates. Seule la source
  appartient à ce champ.

Enfin, le compteur de lectures est incrémenté au MONTAGE et non dans `render()`.
Dans un composant Livewire, `render()` rejoue à chaque interaction : un clic sur
un filtre aurait suffi à gonfler les statistiques, sans que personne s'en
aperçoive.

## 45. Le résultat invité s'affiche à la suite du formulaire

Le formulaire de vérification sans compte RESTE à l'écran, et le résultat
s'ajoute en dessous. Le remplacer — ce que faisait la première version —
obligeait à chercher un bouton « recommencer » pour vérifier une seconde chose,
et faisait disparaître ce qu'on venait de coller : on ne savait plus ce qui
avait été analysé. Le rappel de l'affirmation est donc repris dans le résultat.

La limite aux formats **texte et lien** est écrite à l'écran plutôt que laissée
à deviner devant un bouton d'envoi absent. Image, vidéo et audio mobilisent des
moteurs lourds facturés à l'appel : ce n'est pas ce qu'on offre à un visiteur
anonyme. Trois tests la verrouillent — l'ordre des blocs dans le document,
l'absence de `type="file"`, et le fait qu'un onglet forgé retombe sur celui qui
était actif.

## 46. Gamification — on ne compte que ce qu'on observe

Le prototype affiche « Invitations : 12 » sur la page de parrainage. Un partage
WhatsApp ne se constate pas depuis un serveur : compter les ENVOIS serait
inventer un chiffre. L'indicateur compte donc les **ouvertures du lien**, seule
mesure honnête, et le libellé le dit — « Invitations ouvertes ». Un indicateur
inventé est pire qu'un indicateur absent : il donne confiance dans un chiffre
faux, et personne ne va vérifier d'où il sort.

Pour la même raison, il n'existe pas d'état « invitation envoyée » : une ligne
de parrainage naît à l'INSCRIPTION du filleul. Avant, il n'y a personne à
nommer, et fabriquer des lignes remplirait la page de fantômes.

La récompense arrive à la **première vérification** du filleul, pas à son
inscription : payer l'inscription seule, c'est payer les faux comptes.

Les conditions de badge sont RECALCULÉES depuis les faits — vérifications,
leçons, quiz, certificats — jamais tenues par un compteur incrémenté. Un
compteur dérive au premier événement rejoué, et un badge qui apparaît sans
raison décrédibilise tous les autres.

## 47. Un événement plutôt qu'une dépendance

`VerificationAchevee` est le premier événement du projet, et il est là pour une
raison précise : la gamification doit réagir à une analyse terminée, mais le
fact-checking n'a aucune raison de connaître les badges. Sans événement,
l'orchestrateur — le cœur du produit — dépendrait des récompenses, et chaque
nouveauté marketing viendrait le modifier.

Le listener journalise ses échecs sans jamais les propager : un badge qui ne
s'accorde pas est ennuyeux ; une analyse qui échoue parce qu'un badge n'a pas pu
s'accorder est inacceptable.

## 48. Un parrainage survit à l'effacement du parrain

La première version de la table cascadait : effacer un compte effaçait ses
parrainages, et le FILLEUL perdait au passage une récompense en attente qui lui
était due — alors qu'il n'y est pour rien. Corrigé par une nouvelle migration :
`referrer_id` devient `nullOnDelete`.

Le service prévoyait déjà ce cas ; la cascade rendait cette branche
inatteignable, donc morte. Schéma et code se rejoignent — c'est le test qui a
révélé le désaccord.

## 49. Front-end public : l'éditorial se transpose, les verdicts se lisent

Les pages transposées telles quelles sont celles dont le contenu est la parole
du produit : accueil, pourquoi, fonctionnalités, comment ça marche, équipe,
contact, FAQ, médias, mentions légales, confidentialité, cookies. Onze pages,
extraites du prototype par script — seuls les liens et les chemins d'images sont
réécrits, ce sont les seules choses qu'une application doit calculer plutôt que
figer.

En revanche, **actualités, blog et tutoriels lisent la base**. Le prototype y
montre des fact-checks écrits en dur ; les figer reviendrait à publier, sur un
site de fact-checking, des verdicts qui n'ont jamais été rendus. Tant que la
rédaction n'a rien publié, ces pages le disent et proposent de faire vérifier
une information.

« Tutoriels » présente les COURS PUBLIÉS de l'Académie plutôt qu'une seconde
bibliothèque pédagogique. En maintenir deux garantit qu'un jour elles divergent,
et c'est la page publique qui mentirait — celle que voient les gens pas encore
inscrits.

Deux défauts trouvés à l'écran, invisibles au contrôle HTTP :

- Les vues transposées étaient servies **nues**, sans layout : ni `<head>`, ni
  balise viewport, ni CSS, ni en-tête. Chaque page répondait 200. C'est l'audit
  responsive qui les a démasquées, en mesurant un viewport de 980 px là où le
  mobile en fait 390.
- 220 images du prototype n'avaient jamais été copiées : seul le logo l'était.

Réserve à lever par le porteur : le prototype référence **sept images qu'il ne
livre pas** — `partners/fondation-evz.png`, `partners/ofaj.png`,
`landing/team/rene-fadonougbo.jpeg` et quatre logos `client/*-white.svg`. Elles
manquent dans le prototype lui-même. Leurs `alt` étant corrects, elles dégradent
en texte lisible ; aucune n'a été inventée.

## 50. Le blog du prototype est vide, et il le dit lui-même

Les neuf cartes d'articles de `blog.html` portent l'attribut
`title="Exemple de carte — contenu à venir"`. Ce sont des placeholders que son
auteur signale comme tels : les semer produirait un blog rempli d'articles qui
n'existent pas, sur un site dont tout l'objet est de distinguer ce qui existe de
ce qui n'existe pas.

Deux contenus, en revanche, sont de vraies nouvelles — le Prix WSIS 2026 et le
2ᵉ prix du concours d'Aix-Marseille Université, présentés comme réels dans le
prototype et sur la page d'accueil. Ils sont semés, avec les cinq rubriques
(Analyses, Guides, Formation, Partenariats, Outils).

## 51. Les offres d'emploi du Hub ne s'inventent pas non plus

Le prototype affiche deux offres nominatives — Ministère du Numérique, INStaD.
Une annonce d'emploi fabriquée ferait candidater quelqu'un dans le vide, sur la
page même qui met en garde contre les fausses offres. La section lit donc la
base, avec deux filtres : `is_verified` d'abord — publier une offre non
contrôlée reviendrait à la cautionner — puis la date, car une offre expirée qui
appelle encore à candidater fait perdre son temps à quelqu'un qui cherche du
travail.

L'état vide le dit franchement : « Nous ne publions que des offres dont
l'employeur et le canal ont été contrôlés. Aucune ne l'est aujourd'hui. »

## 52. Signaler n'est pas voter

Cinq personnes qui signalent le même message ne le rendent pas cinq fois plus
répréhensible. Aucun masquage automatique au-delà d'un seuil : cela offrirait à
n'importe quel groupe organisé un bouton de censure. Un humain tranche,
toujours — et la fenêtre de signalement le dit avant l'envoi, parce que
promettre un retrait qui n'aura pas lieu décourage celui qui a signalé de bonne
foi.

Un signalement par personne et par contenu : empiler dix lignes du même
signaleur fausserait la file de modération. Le motif vient d'une liste fermée —
un motif normalisé se trie, se compte et se traite en lot — et un motif forgé
retombe sur « autre ».

## 53. La modération agit là où elle lit

Clore un fil, l'épingler, le transmettre au laboratoire, apposer le verdict
officiel : ces actions vivent sur la page du sujet, visibles pour qui détient
`forum.moderate`. Un modérateur qui lit le forum doit pouvoir clore un fil qui
dérape sans changer d'application.

La permission est portée par le COMPTE et vérifiée côté serveur à chaque appel :
l'absence de bouton n'est pas une protection, la méthode reste joignable depuis
la console du navigateur. Un test appelle `basculerVerrou` en tant que simple
citoyen et attend un 403.

Le bandeau rappelle au modérateur, au moment où il tranche, que le verdict
officiel engage Vérif'All et ne se déduit pas du sondage affiché juste au-dessus.

## 54. Le Hub devient un Hub d'opportunités

Décision du porteur, appliquée partout en une fois : **« Hub d'Infos Bénin » →
« Hub opportunités »**, sidebar citoyenne comme sidebar staff, fil d'Ariane,
titres de page, page publique des fonctionnalités. Un renommage partiel laisse
deux noms pour la même chose, et personne ne sait plus lequel fait foi.

Le nom change parce que la rubrique change : elle ne relaie plus seulement de
l'actualité vérifiée, elle publie surtout des opportunités contrôlées. Un
libellé qui ne dit plus ce que la page contient coûte plus cher qu'il n'y paraît
— on ne le clique pas.

**Trois rubriques** désormais : Actualités, Opportunités Bénin, Opportunités
Internationales. Séparer les deux dernières n'est pas cosmétique : quelqu'un qui
cherche un emploi à Cotonou et quelqu'un qui cherche une bourse à l'étranger ne
lisent pas la même liste, et les mélanger oblige chacun à trier ce qui ne le
concerne pas.

Les **portails officiels** ne sont plus affichés côté citoyen. Les données
restent en base et le modèle avec : c'est une mise en retrait décidée, pas un
abandon, et les effacer rendrait le retour coûteux. L'écran d'administration
reste utile.

## 55. Une opportunité se classe, et s'efface d'elle-même

**Nature** : liste fermée — CDI, CDD, stage, alternance, volontariat, bourse,
financement, conférence, appel à candidature, autre. Sur un champ libre, la même
chose finit écrite de six façons — « stage », « Stage », « internship » — et le
filtre cesse de fonctionner sans que personne s'en aperçoive : il rend
simplement moins de résultats.

Les pastilles de filtre sont lues du **contenu réel** et non codées en dur :
proposer « Alternance » quand aucune alternance n'est publiée mène à une liste
vide, et une liste vide derrière un filtre passe pour un bug.

**Délai de grâce d'une semaine.** Une offre ne disparaît pas le jour de son
échéance : les candidatures arrivées la veille se traitent encore, et une
annonce retirée trop tôt laisse croire qu'elle n'a jamais existé à qui vient de
postuler. Elle reste donc visible sept jours, en affichant franchement « date
limite dépassée » — puis `verifall:fermer-opportunites` la **dépublie**, sans
jamais la supprimer : l'équipe doit pouvoir vérifier ce qui a été diffusé, et
une offre retirée par erreur se remet en ligne d'un clic.

La requête d'affichage **double** la tâche planifiée au lieu de s'y fier : si le
planificateur n'est pas branché sur le serveur, l'écran reste juste.

L'avertissement « aucun frais de dossier » ne s'affiche que sur les
**recrutements**. L'écrire sous une conférence payante serait faux, et un
avertissement faux use la crédibilité des vrais.

Défaut trouvé par les tests en écrivant ceci : le filtre combinait `whereNull`
et `whereDate` en **ET** au lieu de **OU** — aucune opportunité n'aurait jamais
pu s'afficher.

## 56. Des agents pour la suite du chantier

Quatre agents écrits dans `.claude/agents/`, tous adossés à un préambule commun,
`docs/AGENTS_REGLES_DU_CHANTIER.md` :

- **`transposeur`** — transpose une page du prototype, ne conçoit pas de schéma.
- **`architecte-domaine`** — migrations, enums, modèles, dépôts, services ; pas
  d'interface.
- **`verificateur-ecran`** — vérifie dans un vrai navigateur, rapporte sans
  corriger.
- **`redacteur-tests`** — écrit les tests, et ne les adapte jamais pour qu'ils
  passent.

Le préambule ne répète pas `CLAUDE.md` : il consigne **ce qui a été appris en
construisant** — le CSS de page en ligne, le build Tailwind figé, les limites de
Blade, l'interdiction du lazy loading en test, les pièges du harnais CDP. Chacun
de ces pièges a déjà coûté une erreur ; un agent qui ne les connaît pas les
refera tous.

Les directives de sprint sont dans `docs/sprints/` : **Sprint 6** découpé en
sept lots par ordre de déblocage — la saisie des faits vérifiés d'abord, parce
que son absence coûte de l'argent tous les jours — et **Sprint 8**, qui commence
par écrire le contrat d'API avant d'en coder une ligne.

## 57. Un examen n'est pas un quiz sérieux

La question s'est posée avant d'écrire une ligne : `quizzes` pouvait-il porter
les examens ? Non — et pas de peu.

Un quiz se passe quand on veut, se corrige tout seul, appartient à un cours et
n'a ni date, ni place, ni correcteur. Les quatre écrans d'examen du back-office
exigent l'inverse, et chacun de ces besoins est structurel, pas cosmétique :

- `enrolement-examens.html` affiche une colonne **Session** (« 25 août ·
  09:00 »), une colonne **Convocation** (envoyée / à envoyer) et des **demandes
  de report** à arbitrer. Un quiz n'a pas d'heure : il n'a donc ni convocation,
  ni liste d'émargement, ni taux de présence.
- `correction-examens.html` est une **file de correction humaine** : une copie
  porte une référence publique (`EX-4449`), un mentor assigné, un délai
  d'attente et un seuil d'urgence à 72 h. `quiz_attempts` est noté à la
  soumission et n'a ni correcteur, ni file.
- `questions.html` décrit une **banque réutilisable** de cinq types, avec un
  barème par question et un cycle de vie propre (« 14 à réviser »). La table
  `questions` du LMS appartient à un quiz (`quiz_id` non nul, unicité sur
  `quiz_id + position`) et ne connaît ni type, ni points, ni statut.
- `examens.html` compte les **tentatives** : un rattrapage se numérote.

Faire porter tout cela à `quizzes` aurait rendu nullable la moitié de ses
colonnes et illisible le cas courant — le quiz de fin de leçon. Le domaine vit
donc dans `app/Core/Exams/`, avec ses propres tables. Ce que le LMS et les
examens partagent, c'est le vocabulaire, pas le cycle de vie.

**La banque n'est pas encore commune aux deux.** `questions.html` annonce des
questions « réutilisables dans les quiz **et** les examens » ; seuls les examens
y puisent aujourd'hui. Faire migrer les quiz vers la banque supposait de
retoucher un schéma déjà appliqué et les services LMS qui en dépendent — hors du
périmètre de ce lot, et signalé comme tel plutôt que fait à moitié.

## 58. Ce que le schéma des examens tient lui-même

Cinq règles ne vivent pas dans le code, parce qu'une règle qui n'y vit que là
finit contournée par un job, un import ou une commande de maintenance :

- **une** session d'un examen par créneau (`exam_id + starts_at`) — sinon la
  moitié des candidats est convoquée sur une ligne fantôme ;
- **une** inscription par personne et par session — sinon un double-clic sur
  « Convoquer » part deux fois et fausse les places restantes ;
- **une** copie par personne, par examen et par **numéro** de tentative — sinon
  deux soumissions concurrentes créent deux copies « n° 1 » et le rattrapage
  devient incomptable ;
- **une** question par examen (`exam_id + question_bank_item_id`) — sinon elle
  compte deux fois au barème, et le total annoncé n'est pas celui appliqué ;
- **une** réponse par position dans une copie — sinon renvoyer le formulaire
  double les points de la même question.

**Le sujet est tiré au démarrage.** Les lignes de réponse sont écrites vides dès
l'ouverture de la copie, avec l'énoncé, le type et le barème recopiés. Un mentor
qui ajoute une question pendant qu'une épreuve est en cours ne change donc pas
le sujet sous les yeux du candidat.

**Trois colonnes figent ce qui ne doit pas bouger** : `pass_score` et
`max_points` sur la copie, `prompt` / `type` / `points_max` sur chaque réponse.
Relever le seuil d'un examen de 70 à 80 % ne recale pas après coup ceux qui
avaient eu 75 %. Une note est un fait daté, pas un calcul rejouable.

**Deux faits ne sont pas des statuts** : la convocation est partie
(`convocation_sent_at`) et le candidat s'est présenté (`attended_at`). Les
dupliquer dans l'énumération aurait donné deux sources pour le même fait, et
c'est le taux de présence affiché qui aurait menti en premier.

**`null` plutôt que zéro** partout où le chiffre n'existe pas encore : taux de
présence d'une session vide, taux de réussite d'une question jamais posée, délai
moyen de correction sans copie corrigée. Un chiffre inventé est pire qu'un
chiffre absent — il inspire confiance.

**Ce que les cascades épargnent** : supprimer un cours ne détruit ni l'examen ni
les copies (`nullOnDelete`), purger une session ne détruit pas les notes qui y
ont été obtenues, et retirer une question de la banque n'efface pas les copies
en attente — leur énoncé est recopié.

## 59. Se déconnecter était un lien — donc ne déconnectait pas

Le prototype écrit la déconnexion comme `<a href="login.html">`. Transposé
littéralement, ça donnait `<a href="{{ route('logout') }}">` : une requête GET
sur une route déclarée en POST. Le bouton renvoyait 405, et quand la redirection
masquait l'erreur, la session survivait. Sur un poste partagé — cybercafé,
téléphone prêté, poste de travail commun, c'est-à-dire le cas ordinaire au Bénin
— la personne suivante reprenait la session en cours.

Trois écrans étaient concernés : la sidebar citoyenne, le menu utilisateur, et
les deux occurrences du gabarit staff.

**Décidé** : chaque déconnexion est un `<form method="POST">` avec `@csrf` et un
`<button type="submit">`. La classe visuelle du prototype est reportée sur le
bouton, à l'identique : l'écran ne change pas, seul le verbe HTTP change.

Ce n'est pas un détail de forme. Une déconnexion en GET est déclenchable par un
tiers — une balise `<img src="…/deconnexion">` dans un message suffit — et c'est
la raison pour laquelle Laravel déclare cette route en POST.

**Effet de bord traité au passage** : les routes `login`, `register` et
`password.request` n'avaient pas de middleware `guest`. Quelqu'un de déjà
connecté pouvait rouvrir le formulaire de connexion et s'y perdre.
`RedirectIfAuthenticated::redirectUsing()` renvoie désormais chacun vers son
espace selon son rôle.

**Et une trace à ne pas recopier** : le gabarit citoyen chargeait encore
`vf-store.js`, le script de simulation du prototype — 404 sur chaque page. Ce
fichier tient les verdicts en dur dans le `localStorage` ; il n'a rien à faire
dans l'application et ne doit jamais être copié.

## 60. Connexion sociale — on rattache sur une preuve, jamais sur une adresse

Le prototype propose Google et Facebook sur la connexion et l'inscription. La
faille classique de ce dispositif est silencieuse : rattacher un compte existant
parce que l'adresse renvoyée par le fournisseur correspond revient à donner ce
compte à quiconque parvient à faire écrire cette adresse par le fournisseur.

**Décidé — trois chemins, dans cet ordre :**

1. **L'identité est déjà rattachée** → on connecte. Le rapprochement se fait sur
   l'identifiant chez le fournisseur, jamais sur l'adresse : une adresse se
   change, se recycle, se réattribue ; l'identifiant désigne la même personne
   pour toujours. Un test le vérifie en changeant l'adresse entre deux
   connexions.
2. **Aucun compte n'existe** → on en crée un, actif. L'adresse ayant été
   vérifiée par le fournisseur, redemander un code par courriel ferait refaire à
   la personne ce qu'elle vient de faire. Les consentements CGU et
   confidentialité sont enregistrés comme au formulaire ; la lettre
   d'information reste **refusée** — une case cochée d'office n'est pas un
   consentement libre.
3. **Un compte existe avec cette adresse, sans rattachement** → **on refuse**,
   sauf si le fournisseur atteste explicitement avoir vérifié l'adresse. Le
   message dit quoi faire : se connecter par mot de passe, puis relier depuis
   les réglages.

**Google atteste, Facebook non.** `FournisseurSocial::attesteLAdresse()` porte
cette différence. Facebook ne publie aucune garantie équivalente à
`email_verified` ; par conséquent, aucun rattachement automatique par Facebook,
même quand la charge utile prétend le contraire.

**Ce qu'on ne stocke pas** : ni jeton d'accès, ni jeton de rafraîchissement. On
ne lit ni les contacts ni le mur de personne ; conserver un jeton créerait une
responsabilité sans usage.

**Le mot de passe n'est jamais vide** — `Str::password(40)`, jamais communiqué.
Un champ vide autoriserait une connexion sans mot de passe si une vérification
venait à être oubliée ailleurs. Le compte se rouvre par le fournisseur ou par
« mot de passe oublié ».

**Détacher** refuse quand c'est le dernier moyen d'entrer : quelqu'un inscrit
par Google n'a pas de mot de passe qu'il connaisse, et le détacher l'enfermerait
dehors.

**Les boutons ne s'affichent que si le fournisseur est configuré** — pas même le
séparateur « ou continuer avec ». Proposer une connexion qui finit sur une
erreur du fournisseur est pire que ne rien proposer. Et la mention de
consentement se lit **avant** le clic, pas après : passer par Google n'est pas
un consentement tacite.

Voir aussi l'entrée 1, tranchée par ce même besoin.

## 61. Une note de 4,8/5 sur zéro avis

Le catalogue affichait « 4,8 ★ (1 240 avis) » et « 3 456 inscrits » sur des
cours créés le jour même. Les chiffres venaient du prototype, où ils remplissent
une maquette ; en base, ils devenaient une affirmation.

Une note fabriquée n'est pas un détail cosmétique. Elle est la première chose
qu'on regarde pour décider de s'inscrire, et sur une plateforme dont le métier
est de dire ce qui est vrai, la contradiction est totale.

**Décidé** : `rating` et `students_count` sont semés à `0`. La carte affiche
« Nouveau » tant qu'aucun avis n'existe, et l'onglet « Avis » dit qu'il n'y en a
pas encore. L'étoile réapparaîtra le jour où quelqu'un aura noté.

C'est l'application directe de la règle §2 des règles du chantier : ne jamais
coder en dur un verdict, une offre, un article ou un indicateur non mesuré.

## 62. La conformité au prototype se mesure, elle ne s'estime pas

Le constat était juste : plusieurs écrans avaient été **redessinés** au lieu
d'être transposés. Le problème n'était pas d'avoir mal transposé — c'était de ne
pas savoir lesquels.

**Décidé** : un comparateur (`scratchpad/comparer.py`) réduit chaque page à trois
listes — squelette de balises avec leurs classes, repères de texte, ressources —
et confronte le prototype au rendu. Les écrans authentifiés sont produits par
`tests/Feature/Audit/CaptureDesEcransTest.php`, qui rend chaque route en tant que
citoyen, mentor ou administrateur et écrit le HTML sur le disque.

Deux scores, et le second n'est pas un doublon du premier : plusieurs pages du
prototype construisent leurs listes en JavaScript, si bien que leur HTML statique
contient un conteneur vide là où le rendu contient dix cartes. Comparer les
suites brutes pénalisait alors la page qui affiche vraiment quelque chose —
l'inverse de ce qu'on veut mesurer. Le score de MOTIFS compare donc les
signatures distinctes, indépendamment des répétitions.

Le relevé vit dans `CONFORMITE_PROTOTYPE.md` et se refait en deux commandes.
C'est ce qui permet de dire « 94 % » plutôt que « ça devrait aller ».

**Ce que la mesure a trouvé** : neuf écrans encore en gabarit vide, trois pages
sans route, et sept écrans redessinés. Aucun n'aurait été trouvé par la suite de
tests — tous rendaient un HTTP 200.

## 63. Un gabarit vide n'est pas une transposition

Neuf écrans affichaient encore « Shell transposé du prototype. Le contenu de
cette page arrive à son sprint », posé au sprint 1A quand seule la coquille avait
été montée. Parmi eux : le **tableau de bord citoyen**, c'est-à-dire la page
d'arrivée après connexion.

**Transposés** : tableau de bord, répertoire des outils, mes tutoriels,
statistiques, évaluations, exercices, Live Classes. Deux cas particuliers :

- `/mon-espace/quiz` n'a pas de contenu propre — `quiz.html` du prototype EST le
  lecteur de quiz, ouvert avec `?c=<cours>&q=<quiz>`. Son équivalent porte les
  deux identifiants dans l'URL ; la route sans paramètre ramène vers la liste
  des évaluations, qui est l'endroit où l'on choisit.
- Le **tableau de bord du back-office** reste en attente : dix panneaux, dont
  plusieurs demandent une collecte d'analytique qui n'existe pas (navigateurs,
  visites). Le fabriquer serait exactement ce que l'entrée 67 interdit.

## 64. Le contenu de démonstration est EXTRAIT du prototype

Un écran conforme mais vide ne se compare à rien. Il fallait donc des données —
sans retomber dans l'invention.

**Décidé** : tout ce qui peuple la démonstration est extrait du prototype par
script, jamais retapé ni imaginé.

- **15 outils de vérification** (`outils.json`) — quinze URL recopiées à la main,
  c'est quinze occasions d'envoyer quelqu'un ailleurs que là où on le croit.
- **7 analyses publiées** (`faits-verifies.json`) — ce sont les contenus
  éditoriaux de Vérif'All. La règle interdit d'inventer un verdict ; elle
  n'interdit pas de reprendre ceux que la rédaction a écrits.
- **6 opportunités du Hub** — quatre béninoises extraites, deux internationales
  ajoutées (programmes réels, URL officielles) pour que la rubrique
  « Opportunités Internationales » puisse se montrer. Les dates limites sont
  RELATIVES au jour du semis : une démonstration dont toutes les offres sont
  expirées ne montre plus rien.
- **5 Live Classes** — mêmes dates relatives, dont une en cours, seul moyen de
  voir l'état « EN DIRECT » sans attendre le bon moment de la journée.

**Et le jeu de démonstration appelle enfin les seeders des sprints livrés.**
`DemoSeeder` s'était arrêté au sprint 2 : l'Académie, le forum, le Hub et le blog
n'étaient jamais semés, et tous ces écrans se comparaient à vide. Un module livré
mais absent du semis donne une démonstration vide — et un écran vide se compare
mal : on croit à une page manquante alors qu'il ne manque que la donnée.

## 65. « Vérifier un certificat » garde son gabarit autonome

Le prototype donne à cette page son propre en-tête réduit — logo, un seul lien —
au lieu du menu complet du site. L'implémentation l'avait glissée dans le gabarit
public, avec les cinq entrées de navigation et le pied de page entier.

**Décidé** : gabarit autonome, comme le prototype.

La raison tient à qui s'en sert. Un recruteur arrive ici par un lien ou un QR
code imprimé sur un diplôme, avec une seule question en tête. Lui dérouler
l'Académie, le Hub et le forum, c'est lui proposer six sorties avant qu'il ait
obtenu sa réponse.

Le libellé du résultat change aussi : « Certificat introuvable », le mot du
prototype, plutôt que « Code inconnu ». Dans les deux cas on n'écrit jamais
« faux » — un code mal recopié n'est pas une fraude, et l'accusation serait
injuste comme inexacte.

## 66. La fiche publique d'un cours existe

`tutoriels-details.html` n'avait **aucune route**. Les quatre cartes de la page
Tutoriels menaient donc nulle part, et le lien qu'un mentor colle dans un message
ne pouvait pas exister.

**Décidé** : `/tutoriels/{slug}`, publique et sans compte. On ne cache pas ce
qu'on propose d'apprendre ; seule l'inscription ouvre les leçons.

La fiche reprend les onglets du prototype — aperçu, curriculum, instructeur,
avis. Deux blocs ne sont pas repris : l'onglet « Détails » et la FAQ, dont le
contenu n'existe dans aucune colonne. Les inventer aurait fait dire à la
plateforme des choses que personne n'a écrites.

**Les quatre guides pratiques** de « Mes Tutoriels » gardent leur texte mais
pointent vers la liste des tutoriels : dans le prototype, leurs quatre liens
mènent tous à la même page statique, ce sont des liens décoratifs.

## 67. Les chiffres du prototype sont comptés, ou retirés

Le prototype est une maquette : ses nombres remplissent des cases. Transposés
tels quels, ils deviennent des affirmations — sur une plateforme dont le métier
est de dire ce qui est vrai.

**Retirés faute de source** : « 3 240 vues » d'un article (rien ne les compte),
« 1 284 discussions · 8 421 membres » du forum, « ↗ +3 vs mois dernier » des
statistiques, les échéances d'exercices, les trois lignes d'historique de
parrainage, les quatre lignes d'activité récente.

**Comptés à la place** :

- forum — discussions, membres et vérifications réellement en base ;
- statistiques — l'évolution se calcule sur les 30 jours précédant les 30
  derniers, pas sur le mois calendaire : comparer un 3 août à un mois de juillet
  complet fabriquerait une chute tous les débuts de mois ;
- **record de série** — le prototype affiche « Record personnel : 12 jours ».
  Sans colonne, ce chiffre était invérifiable : la série courante retombe à 1 dès
  qu'un jour est manqué, et l'ancienne valeur est perdue. `users.streak_best` est
  ajouté, mis à jour par `SerieDeVigilance`, et ne redescend jamais ;
- temps de lecture d'un article — calculé sur le texte réel, à 200 mots par
  minute, une minute au minimum ;
- répartition des notes d'un cours — cinq barres calculées, toutes à zéro tant
  que personne n'a noté, plutôt que les 68 % / 22 % / 7 % peints dans la
  maquette.

**Et deux modales du tableau de bord ont désormais une mémoire.** Le prototype
garde leur état dans le `localStorage` ; ici il appartient au compte, sinon
changer de navigateur rouvrirait tout. « Me rappeler plus tard » est une date et
non un booléen : la personne n'a pas refusé, elle a demandé un délai.



## 68. L'API pagine par CURSEUR, jamais par décalage

Les panneaux de transposition du simulateur mobile écrivent `?page=`. Le contrat
d'API ne l'implémente pas : `GET /api/v1/...` accepte `?cursor=` et `?limit=`, et
`page` n'existe nulle part.

**Pourquoi l'écart.** Sur un fil qui bouge — le forum, les vérifications, le Hub
— le décalage ment. Trois sujets publiés entre le chargement de la page 1 et
celui de la page 2 décalent toute la suite : le client réaffiche trois éléments
déjà vus, ou en saute trois qu'il ne verra jamais. Sur une plateforme de
fact-checking, l'élément sauté peut être précisément le démenti qu'on cherchait.

Le curseur désigne une **position dans l'ordre**, pas un rang. Ce qui s'insère
au-dessus ne le déplace pas.

**La porte que ça ferme, et c'est assumé** : on ne peut plus sauter à la page 7,
et il n'y a plus de total. Aucun des trente écrans mobiles n'en a besoin — ils
défilent tous. Le web, lui, garde sa pagination par numéros de page : ses
tableaux en affichent, et le dépôt des unités porte désormais les **deux**
méthodes plutôt qu'un drapeau qui obligerait chaque appelant à deviner quel
paginateur il tient.

Le tri des listes paginées se fait sur `id` et non sur `created_at` ou
`last_activity_at` : le curseur exige une clé strictement ordonnée et **unique**.
Deux lignes de la même seconde partageraient la date, et l'une des deux
disparaîtrait au passage de page — silencieusement, et seulement en production.

## 69. L'API s'authentifie par JETON, jamais par cookie de session

**Décidé** : jetons personnels Sanctum, `Authorization: Bearer`, durée de
30 jours **glissante**. Aucun cookie, aucune session, aucune protection CSRF.

**Pourquoi.** Une application mobile n'a pas de session de navigateur. Un cookie
imposerait à un client qui n'est pas un navigateur : un domaine, une politique
`SameSite`, un jeton CSRF et un magasin de cookies persistant. Le jeton porteur
supprime les quatre, et supprime le CSRF *par construction* — sans cookie envoyé
automatiquement, il n'y a rien à falsifier.

**Les portes que ça ferme :**

- **Pas de jeton de rafraîchissement.** Un couple accès/rafraîchissement protège
  surtout les architectures où le jeton d'accès traverse des services tiers ; il
  n'y a ici qu'un serveur. Il aurait ajouté un second secret à stocker sur le
  téléphone, une route de plus et un état de plus à tester, pour un gain nul.
  L'expiration glissante remplit le même office : tant que l'application sert,
  l'échéance recule ; dès qu'elle cesse de servir, le compte à rebours court.
- **Pas de relecture du jeton.** Le serveur n'en garde que l'empreinte. Il n'y a
  aucun endpoint pour le récupérer — un jeton relisible est un jeton qu'une
  simple faille de lecture suffit à voler.
- **Pas de connexion sociale sur l'API.** Le retour OAuth suppose un navigateur
  et un état en session. Le sprint Socialite reste web.

**Conséquence côté sécurité, et elle est bonne** : la réinitialisation du mot de
passe révoque désormais *aussi* les jetons d'API, pas seulement les sessions web.
Un téléphone volé garde son jeton indéfiniment ; ne fermer que les sessions du
navigateur laissait ouverte la porte la plus difficile à refermer, celle qu'on ne
voit pas. La liste de ce qu'il faut révoquer vit à **un seul endroit**
(`ReinitialisationMotDePasse`) : le jour où un quatrième support
d'authentification apparaîtra, l'oublier dans une seconde copie serait une porte
laissée ouverte que personne ne verrait.

## 70. La logique d'authentification quitte les composants Livewire

**Constat.** La connexion, l'inscription et la réinitialisation du mot de passe
vivaient **entièrement** dans `app/Livewire/Auth/*` : verrouillage progressif,
égalisation du temps de réponse contre l'énumération, horodatage des trois
consentements, rattachement du parrainage, révocation des sessions. Dette des
sprints 1–2, la règle 1 ayant été posée après.

L'API mobile a besoin des **mêmes** règles. Les recopier dans un contrôleur
aurait produit deux chemins qui se ressemblent aujourd'hui et divergent demain —
et c'est toujours le moins testé des deux qui reste faux. Sur ce périmètre-là, le
« moins testé » aurait été celui qui décide si un compte non activé peut entrer.

**Décidé** : extraction en trois services d'`Identity`, appelés par le web **et**
par l'API.

| Service | Ce qu'il porte |
|---|---|
| `AuthentificationCitoyen` | verrouillage, hachage leurre, refus des comptes inactifs, journal de sécurité |
| `InscriptionCitoyen` | création, rôle, consentements horodatés, parrainage, émission et vérification du code |
| `ReinitialisationMotDePasse` | anti-énumération, code à usage unique, révocation de **tout** |

Ces services ne démarrent **pas** de session et n'émettent **pas** de jeton : ils
constatent une identité. Le web enchaîne sur `Auth::login()` + régénération de
session, le mobile sur `createToken()`. Les mélanger aurait obligé chaque
appelant à défaire ce que l'autre voulait.

Deux exceptions typées remplacent les branchements par message :
`CompteVerrouille` porte les **secondes** restantes — le web les formule en
minutes dans une bulle, l'API les met dans `Retry-After` — et
`IdentifiantsInvalides` est **une seule** exception pour trois causes distinctes,
délibérément : les distinguer transformerait l'écran de connexion en oracle
permettant d'énumérer les inscrits.

**Même mouvement pour les chiffres.** Les agrégats de « Statistiques »
(répartition des verdicts, évolution mensuelle, série hebdomadaire) vivaient dans
les méthodes privées du composant. Ils sont extraits dans
`Core/Application/Services/SyntheseCitoyen` — dans `Application` et non dans un
domaine, parce qu'ils croisent quatre domaines et que les ranger dans l'un d'eux
le rendrait dépendant des trois autres, ce que la règle 6 du chantier interdit.

## 71. Contester un verdict est un SIGNALEMENT, pas un changement d'état

L'écran « Rapport » du prototype propose « Demander un expert ». Deux
implémentations étaient possibles.

**Écartée** : basculer la vérification en `RevueHumaine`. Cet état existe déjà —
l'orchestrateur l'emploie quand les moteurs se contredisent. Le rouvrir au
citoyen offrirait à quiconque **un bouton pour effacer un verdict qui le
dérange** : il suffirait de contester pour que « Faux » devienne « En
arbitrage », sur son propre rapport, sans qu'aucun humain n'ait rien examiné.

**Retenue** : la contestation est enregistrée comme un `ContentReport`
polymorphe, via `SignalementDeContenu` — le service qui sert déjà au forum et au
Hub. Elle atterrit dans la file de modération que l'équipe traite, et **un humain
tranche, toujours**. Le rapport, lui, ne bouge pas.

Trois bénéfices de la réutilisation : aucune table nouvelle, aucune règle
dupliquée, et l'idempotence acquise — le service rend le signalement existant
plutôt que d'empiler dix lignes du même signaleur, ce qui rend l'appel rejouable
depuis la file hors ligne du téléphone.

Le champ `detail` est **obligatoire** ici (10 à 1000 caractères) alors qu'il est
facultatif sur le forum : « ce verdict est faux » sans un mot d'explication ne
donne à la rédaction rien sur quoi travailler.

## 72. Le téléversement se fait AVANT la soumission, en deux appels

`POST /api/v1/uploads` puis `POST /api/v1/verifications` avec un identifiant de
téléversement, plutôt qu'un seul multipart.

**Pourquoi.** Une vidéo de vingt mégaoctets sur une connexion instable échoue une
fois sur trois. Si le fichier voyageait avec la soumission, chaque reprise le
renverrait **en entier** — et une soumission rejouée après une réponse perdue le
renverrait encore. Découpé, le téléversement se reprend seul, et la soumission
qui suit ne pèse que quelques centaines d'octets, ce qui la rend rejouable sans
douleur.

Le fichier attend **deux heures** sur le disque privé, sous un nom aléatoire, et
son identifiant est **lié au compte** : un identifiant intercepté ne permet ni de
faire analyser le fichier d'un autre, ni d'en découvrir l'existence — les trois
cas (inconnu, expiré, pas à vous) se répondent pareil.

Les règles de validation sont **celles de l'écran web, à la lettre** : mêmes
types MIME, mêmes plafonds. Les assouplir aurait ouvert par le mobile ce que le
navigateur refuse, et c'est toujours la porte la moins surveillée qu'on emprunte.

## 73. Cinq appels du simulateur mobile ne sont PAS servis

Le simulateur déclare 58 appels ; l'API en sert 53. Les cinq manquants sont
nommés ici plutôt que tus, et le contrat (`docs/kb/API_SPECIFICATION.md` §11)
décrit ce qu'il faut pour lever chaque réserve.

**Les trois notifications** (`GET /me/notifications`,
`POST /me/notifications/read-all`, `POST /me/notifications/{id}/read`). Il
n'existe aucun magasin de notifications sur cette plateforme : pas de table
`notifications`, aucune classe `Notification`, rien qui en émette. La cloche du
gabarit citoyen est décorative. Un endpoint qui renverrait éternellement une
liste vide serait **un indicateur qu'on ne mesure pas**, ce que la règle 2 du
chantier interdit.

**`POST /verifications/{uuid}/feedback`** — « Ce rapport vous a-t-il été
utile ? ». Aucun stockage, et l'écran web `rapport.html` ne le transpose pas non
plus. L'implémenter exigeait de créer une table *et* un service, c'est-à-dire de
**concevoir une fonctionnalité** plutôt que d'en transposer une — ce que la fiche
de rôle interdit explicitement.

**`GET /me/certificates/{code}/pdf`** — aucune bibliothèque de génération PDF
n'est installée. En ajouter une est une décision de dépendance qui appartient au
porteur du projet, pas à l'agent qui écrit le contrat.

**Réserve partielle** : `POST /live-classes/{uuid}/remind` **enregistre**
l'intention d'assister — la table `live_class_registrations` existe depuis le
sprint LMS, avec son unicité — mais **n'envoie pas** le rappel J-1, même cause
que les notifications. L'inscription est durable : le jour où le rappel partira,
il aura sa liste, et personne n'aura à redemander aux gens de se réinscrire.

Quand ces endpoints existeront, ce seront des **ajouts** : compatibles v1, sans
rupture pour une application déjà installée.

## 74. Ce que l'API n'expose délibérément pas

Une ressource JSON qui renvoie tout le modèle finit par renvoyer une adresse
e-mail à qui n'y a pas droit. Deux ressources distinctes sont donc écrites pour
un compte, et non une seule paramétrée :

- **`MoiResource`** — adresse, téléphone, ville, solde. Employée **uniquement**
  là où le sujet de la requête est aussi son propriétaire (`GET /me`,
  `PATCH /me`).
- **`AuteurResource`** — uuid, nom d'affichage, initiales. Rien d'autre. Employée
  partout ailleurs : forum, Hub, séances.

Une ressource unique aurait fini, le jour où quelqu'un ajouterait un champ
« pour le profil », par livrer une adresse e-mail dans une liste de messages du
forum.

Ne sortent jamais non plus : les clés primaires entières, le hachage du mot de
passe et le secret 2FA, **le rôle d'un auteur** (publier « untel est modérateur »
sur chaque message dresserait la liste nominative de l'équipe de modération),
`content_fingerprint` (il permettrait de tester si une rumeur a déjà été
soumise), `guest_hash`, `file_path`, le détail des exécutions de moteurs
(publier quel moteur a répondu quoi revient à publier notre grille d'achat),
`is_correct` et l'explication d'un quiz **avant** la copie rendue, le numéro
chiffré et la référence opérateur d'un paiement, l'adresse d'un filleul, et le
lien visio d'une séance encore lointaine.

**Et une règle de forme** : ce qui n'appartient pas au demandeur répond **404**,
jamais 403. Un uuid deviné ne doit renseigner sur rien — pas même sur l'existence
de la ressource. C'est la règle déjà appliquée aux écrans citoyens ; elle vaut
mot pour mot ici.

## 75. Le compte en cours de suppression peut lire son compte et se déconnecter

Le délai de rétractation RGPD ouvre trente jours pendant lesquels le compte
survit. L'API le bloque — `403 compte_en_suppression` — avec **deux exceptions**,
et seulement deux.

`GET /me` reste ouvert : c'est ce qui permet à l'application d'afficher « votre
compte sera effacé le … » au lieu de heurter un mur d'erreur incompréhensible.
Un utilisateur qui ne comprend pas pourquoi tout est refusé ne se rétracte pas,
il désinstalle.

`POST /auth/logout` reste ouvert : refuser à quelqu'un de se déconnecter
reviendrait à lui imposer de garder un jeton vivant sur un téléphone dont il ne
veut plus.

**L'annulation, elle, ne passe pas par l'API.** Elle se fait par le lien signé
reçu par courriel — seule voie de retour quand on ne peut plus se connecter, et
seule qui alerte la vraie personne si la demande venait d'un écran resté ouvert.

**Et la suppression se fait en deux temps sur un seul point d'entrée.** Le
simulateur ne déclare que `DELETE /api/v1/me` ; ouvrir une route « demander un
code de suppression » aurait ajouté une surface que l'écran n'appelle pas. Sans
`code`, l'appel vérifie le mot exact et le mot de passe puis envoie le code
(`202`) ; avec `code`, il exécute. Mot de passe **et** code : le premier prouve
qu'on connaît le compte, le second qu'on tient la boîte. Un téléphone volé
déverrouillé passe le premier, jamais les deux.

## 76. Le prix des packs sort de la configuration

C'est la réserve n° 15 qui se referme, treize entrées plus tard.

Le prototype et `PAIEMENT_MOBILE_MONEY.md` divergeaient d'un facteur deux à deux
et demi sur la valeur vendue. Les valeurs du prototype ont été appliquées faute
de mieux, mais **le choix n'a jamais été technique** : c'est une décision
commerciale, et elle appartient au porteur du projet. Tant qu'elle vivait dans
`config/economy.php`, la trancher demandait un développeur, un commit et un
déploiement — autant dire qu'elle ne se tranchait pas.

**Décidé** : les packs vivent en base (`unit_packs`) et se modifient depuis le
back-office, écran `admin/packs-unites`. La configuration reste comme archive du
raisonnement ; elle n'est plus lue à l'exécution.

Quatre garde-fous, dont aucun n'est décoratif :

1. **Le client n'envoie qu'un code.** Le prix et la quantité sont lus côté
   serveur, au moment de la demande. Sinon on achèterait soixante unités au prix
   de dix en modifiant un formulaire.
2. **Les montants sont figés à l'ouverture du paiement.** Changer la grille
   demain ne doit pas changer le montant d'une demande déjà en cours.
3. **Un pack acheté ne se supprime pas.** Effacer sa ligne rendrait illisibles
   les paiements qui le référencent : on saurait qu'une somme a été encaissée,
   plus ce qu'elle achetait. Le retrait de la vente est réversible et n'efface
   rien ; c'est le geste normal, et la Policy refuse l'autre.
4. **Chaque écriture est journalisée**, en ne gardant que ce qui a changé. Un
   changement de prix engage de l'argent : on doit pouvoir dire qui l'a décidé,
   et quand.

**Un seul pack mis en avant à la fois** : deux « recommandés » côte à côte ne
recommandent rien.

**Écart au prototype, assumé** : les 107 pages ne comportent aucun écran pour
cette grille. `unites-ia.html` journalise les unités consommées,
`unites-premium.html` tarife les cours — ni l'un ni l'autre ne vend de pack.
L'entrée de sidebar « Packs d'unités » est donc un AJOUT, placé auprès des
autres entrées d'unités, là où on va la chercher.

## 77. Connexion Google et Facebook — en service

Le porteur du projet a tranché : les connexions sociales doivent fonctionner.

Le dispositif était prêt depuis l'entrée 60 — Socialite installé, service de
rattachement écrit et testé, boutons transposés, cookie passé en `lax` par
l'entrée 1. Il ne manquait que les identifiants de fournisseur.

**Ce qu'il reste à faire, et c'est hors du code** : créer les projets chez
Google et chez Facebook, y déclarer les URL de retour, puis poser les quatre
variables dans `.env`. `.env.example` les documente. Tant qu'un fournisseur n'a
pas ses identifiants, son bouton **ne s'affiche pas** — pas même le séparateur
« ou continuer avec ». Proposer une connexion qui finit sur une erreur du
fournisseur est pire que ne rien proposer.

La règle de rattachement, elle, ne bouge pas : on relie sur une preuve, jamais
sur une adresse. Google atteste l'adresse, Facebook non — donc aucun
rattachement automatique par Facebook, même quand la charge utile prétend le
contraire.

## 78. Le back-office était inaccessible

Le back-office exige un second facteur. Le seeder annonçait que « la première
connexion staff passe par l'écran d'enrôlement ». **Cet écran n'existait pas.**

Conséquence exacte : un compte staff neuf saisissait ses identifiants, arrivait
sur « Vérification en deux étapes », et n'avait aucun moyen d'obtenir un code —
`verifierCode` échouait toujours, faute de secret. La seule issue était d'écrire
un secret TOTP en base à la main. Aucun test ne le voyait : ils fabriquent le
secret eux-mêmes avant de se connecter.

**Décidé** : l'écran de connexion bascule en ENRÔLEMENT quand le compte n'a pas
encore de second facteur — QR rendu par le serveur, clé en clair pour ceux qui
ne peuvent pas scanner, codes de récupération montrés une seule fois, puis un
premier code à saisir.

Le secret est préparé mais **pas confirmé** tant que ce premier code n'est pas
validé. Sinon une erreur de scan enfermerait quelqu'un dehors, sur un compte
administrateur, sans autre recours que la console.

Le QR est dessiné **par le serveur** : un code rendu en JavaScript disparaît
quand le script échoue — et on ne le photographie pas avec le téléphone qu'on
est précisément en train de configurer.

## 79. Une visite du back-office renvoyait vers la connexion citoyenne

Laravel renvoie tout visiteur non connecté vers `login`. Résultat : un membre de
l'équipe qui ouvrait `/admin/securite` atterrissait sur la connexion CITOYENNE,
où ses identifiants sont refusés — le formulaire citoyen rejette les comptes
staff, et c'est voulu. Sans connaître `/admin/connexion` par cœur, il n'avait
aucun moyen d'entrer.

**Décidé** : le chemin demandé décide. Ce qui commence par `admin/` mène à la
connexion du back-office, le reste à celle des citoyens.

Trouvé en essayant d'ouvrir un écran dans un navigateur, pas par un test : les
tests d'accès vérifiaient « la porte est fermée », jamais « on est renvoyé au
bon endroit ».

## 80. La cloche ne sonne pas, et on cesse de le promettre

Il n'existe **aucun magasin de notifications** : pas de table, pas de classe
`Notification`, rien qui en émette. La cloche de la barre supérieure vient du
prototype et n'est reliée à rien.

Ce n'est pas grave en soi — une icône inerte ne trompe personne longtemps. Ce
qui l'était, c'est le TEXTE qui s'appuyait dessus : « les mentors annoncent les
prochaines sessions dans les notifications », affiché quand aucune séance n'est
programmée. La phrase envoyait quelqu'un attendre sur un canal muet.

**Décidé** : la cloche reste — elle appartient au gabarit du prototype, et la
retirer déferait la barre supérieure de vingt-cinq écrans. Mais **plus aucun
texte ne s'y réfère**. L'état vide des Live Classes dit désormais où l'on verra
réellement l'information : sur cette page.

Côté API, la même règle a produit la même décision : les trois endpoints de
notifications ne sont pas servis (entrée 73). Un endpoint qui renverrait
éternellement une liste vide serait un indicateur qu'on ne mesure pas.

Côté mobile, la documentation Flutter masque la cloche et écrit l'écran derrière
un drapeau, prêt pour le jour où le magasin existera.

**Ce qu'il faudra pour lever cette réserve** : une table, un émetteur, et une
décision sur les canaux — dans l'application seule, ou aussi par courriel. Trois
écrans de réglages promettent déjà des préférences de notification ; ils
promettent donc, eux aussi, quelque chose qui n'arrive pas encore.


## 81. Les filtres portent des noms ANGLAIS, les corps des noms français

L'API mélange deux vocabulaires, et ce n'est pas un oubli.

**Les paramètres d'URL sont en anglais** — `category`, `level`, `scope`, `type`,
`state`, `verdict`, `period`, `limit`, `cursor`, `q`. Ils ne sont pas choisis :
ce sont ceux que le simulateur mobile déclare, écran par écran. Les traduire
aurait obligé l'auteur du client Flutter à tenir une table de correspondance
entre ce que son panneau de transposition annonce et ce que le serveur accepte —
c'est-à-dire à se tromper.

**Les champs de corps sont en français** — `titre`, `corps`, `motif`, `detail`,
`avis`, `prenom`, `mot_de_passe`, `secondes`, `reponses`. C'est la langue du
domaine, celle du projet, celle des services qu'ils alimentent.

La règle est donc simple à retenir : **ce qui va dans l'URL est anglais, ce qui
va dans le corps est français.**

**Le cas qui l'a fait écrire.** `POST /forum/threads` attendait `categorie` en
français, quand les cinq listes filtrables attendent `category`. Un client qui a
appris `category` sur cinq écrans se serait trompé sur le sixième — et le champ
aurait été ignoré **en silence** : le sujet part sans rubrique, l'appel répond
`201`, et personne ne voit rien. C'est le pire des échecs, puisqu'il produit un
résultat plausible.

**Décidé** : `category` devient le nom canonique de ce champ, par exception à la
règle du corps français, parce que **un seul nom pour une seule chose** l'emporte
sur la cohérence de vocabulaire. `categorie` reste accepté le temps d'une
version — ne casser aucun client déjà écrit contre la première rédaction du
contrat coûte trois lignes — et disparaîtra en v2.

Une seule interprétation du paramètre, aussi : `Http/Api/V1/Support/Filtre`. Les
trois filtres `category` s'écrivaient chacun à leur façon — l'un ignorait la
sentinelle `toutes`, l'autre la reconnaissait, le troisième traitait la chaîne
vide autrement. Trois lectures d'un même paramètre, c'est trois occasions de
rendre une liste plausible et fausse.

## 82. Le curseur ne porte pas les filtres, et le contrat doit le hurler

`next_cursor` encode une position dans l'ordre de tri, rien d'autre. Le client
doit renvoyer **tous** les paramètres de filtre avec le curseur.

**Pourquoi c'est consigné plutôt que laissé à l'évidence.** L'appel fautif —
curseur seul, sans filtre — **ne produit aucune erreur**. Il rend la page
suivante de la liste non filtrée. L'écran reste plausible ; il est simplement
faux à partir de la deuxième page. C'est la catégorie de bogue qu'aucun test
client ne rattrape et qu'un utilisateur finit par signaler six mois plus tard.

Le corollaire est écrit au contrat (§3.4) et vaut d'être répété ici : **on ne
filtre jamais côté client une liste paginée.** Filtrer localement ne filtre que
la page déjà chargée. Les trois listes concernées sont le catalogue de cours, le
forum et les opportunités — et les douze paramètres de filtre sont tous
implémentés côté serveur, précisément pour qu'aucun client n'ait de raison de le
faire lui-même.

**Deux comportements sur valeur inconnue**, et la différence est délibérée. Une
énumération fermée (`level`, `scope`, `type`, `state`, `verdict`, `period`)
refuse par `422` : la valeur ne peut venir que d'un client modifié ou d'une faute
de frappe, et l'accepter rendrait une liste vide sans dire pourquoi. Une
catégorie ouverte rend une **liste vide** : ce sont des données éditoriales, elles
changent, et refuser une catégorie supprimée la veille casserait un écran pour
une raison qui n'est pas une erreur du client.

## 83. La barre supérieure montrait le compte d'un autre

Cinq valeurs du prototype étaient restées figées dans les deux gabarits : solde
d'unités, expérience, initiales, nom et qualité. Elles ne dépendaient de rien —
donc **chaque personne connectée voyait celles d'Adderly**, sur les vingt-cinq
écrans citoyens, et celles de René sur les cinquante-neuf écrans du back-office.

Le défaut s'est vu tout seul sur l'écran des unités : la carte annonçait 49, le
bandeau 34. Deux chiffres pour la même chose, à trente centimètres l'un de
l'autre. Aucun test ne le voyait — ils vérifient le contenu de la page, pas
celui du gabarit qui l'entoure.

**Décidé** : les deux gabarits lisent le compte connecté. Les initiales se
calculent sur le prénom et le nom réels, la qualité sur le rôle réel.

Ce n'est pas cosmétique côté back-office. Savoir sous quel compte on agit n'y
est pas un confort : c'est ce qui rend le journal d'audit lisible par celui-là
même qui l'alimente. Un administrateur qui croit être quelqu'un d'autre signe
des décisions au nom d'un tiers.

**Ce qui reste figé, et pourquoi c'est acceptable** : la cloche de
notifications, qui n'est reliée à rien (entrée 80). Elle appartient au gabarit
du prototype ; ce qui a été retiré, ce sont les textes qui la promettaient.

## 84. Il n'y a pas de deuxième facteur à la connexion — TRANCHÉ : le contrat

Le simulateur mobile enchaîne systématiquement `connexion → code → tableau de
bord`, par un paramètre `purpose: login`. Le contrat d'API ne le fait pas :
`POST /auth/login` rend un jeton directement, et **aucune route n'existe** qui
exigerait un second facteur après une connexion réussie. `otp/request` et
`otp/verify` servent à *activer* un compte neuf, ou à réactiver un compte dont le
code n'a jamais été validé.

**Décidé** : coder le parcours du contrat. Il est servi, testé, et il ne promet
rien de faux. La connexion réussie ouvre la session ; l'écran 05 ne sert plus que
`signup` et `reset`, et l'énumération `AuthOtpPurpose` n'a que **deux** valeurs.

**Pourquoi ce sens et pas l'autre.** Les trois lectures possibles étaient : le
simulateur est simplifié, la 2FA facultative de l'écran 30 devait piloter ce
second facteur, ou le contrat devra ajouter la route. La deuxième s'écroule
d'elle-même — l'interrupteur de l'écran 30 n'a **pas non plus** d'endpoint
(réserve C.8). La troisième reste ouverte et **compatible** : ajouter la route en
v1.x ne casserait aucun client. Mais écrire aujourd'hui un écran qui attend un
code que personne n'envoie produirait exactement le défaut que la règle du
chantier interdit : un écran plausible et faux.

**Ce qui a été retiré, et ce qui ne l'a pas été.** La copie `login` de l'écran 05
(« Vérification en deux étapes ») n'est plus écrite nulle part : un libellé mort
finit toujours par réapparaître. En revanche l'écran lui-même reste piloté par
son paramètre — le jour où la route existe, il suffit d'ajouter une valeur à
l'énumération, et le test paramétré sur toutes les valeurs le dira.

**Ce que cela donne au 403 `compte_inactif`.** Un compte non activé qui tente de
se connecter reçoit `401`, strictement identique à un mot de passe faux — le
client ne peut donc pas l'orienter depuis l'écran de connexion, et il n'essaie
pas. Le `403 compte_inactif` n'apparaît que sur un jeton déjà émis, c'est-à-dire
dans les quatre autres parcours. La destination est nommée une fois, dans
`core/router/routes_auth.dart` (`cheminActivation(email)`), et se prend par la
navigation — jamais par un import croisé entre deux dossiers `features/`.

## 85. Le formulaire d'inscription — TRANCHÉ : le contrat, à la place de la maquette

| | Simulateur (écran 03) | Contrat + validation servie | Retenu |
|---|---|---|---|
| Nom | un champ « Nom et prénom » | `prenom` **et** `nom`, `min:2` chacun | deux champs |
| Profession | absente | `profession` **requise** | ajoutée |
| Ville | champ pré-rempli « Cotonou » | **non acceptée** par `register` | retirée |
| Téléphone | « Téléphone (facultatif) » | `telephone` **requis**, `min:8` | étiquette corrigée |
| Mot de passe | « 8 caractères minimum » | 12 (arbitrage n° 10) | étiquette corrigée |

**Décidé** : le contrat l'emporte, et la disposition de la maquette est
préservée autant que possible — « Prénom » et « Nom » prennent la place de « Nom
et prénom », « Profession » prend celle de « Ville ». Sept champs au lieu de six.

**Pourquoi ce sens.** `ville` n'est pas refusée bruyamment : Laravel ignore un
champ absent des règles de validation. Le formulaire aurait donc répondu `201` en
**jetant la saisie**, et la personne aurait découvert des semaines plus tard que
sa ville n'a jamais été enregistrée. C'est la catégorie de défaut la plus chère :
il produit un résultat plausible. La ville se règle par `PATCH /me`, qui
l'accepte — c'est à l'écran de profil (M5) de l'offrir.

**Pourquoi l'étiquette du téléphone change aussi.** Elle annonçait « facultatif »
là où le serveur exige huit caractères. Un formulaire ne doit pas promettre moins
que ce qu'il exigera — c'est la même règle qui a fait passer le mot de passe de 8
à 12.

**Ce qui reste ouvert.** L'écran 25 (profil, M5) porte le même écart : « Nom
affiché » recouvre deux champs, et profession comme téléphone y sont modifiables
sans avoir de champ à l'écran. Il se tranche dans le même sens, mais il se
tranche là-bas — M1 ne dessine pas l'écran de M5.

## 86. Trois commandes qui ne pilotaient rien — retirées ou jamais écrites

Trois éléments du simulateur mobile n'ont **aucun fait serveur derrière eux**. Ils
sont traités ensemble parce que la raison est la même : *une commande qui ne
pilote rien est un mensonge à l'écran, et elle coûte plus cher qu'une absence.*

**1. La case « Rester connecté » (écran 02).** Cochée par défaut dans le
simulateur. Le contrat n'a aucun paramètre correspondant : le jeton dure trente
jours de façon **glissante**, prolongée par le serveur à chaque appel (§1.4).
« Rester connecté » est donc déjà le comportement, pour tout le monde, sans
option. La décocher n'aurait rien changé. **Retirée** ; la ligne d'options ne
porte plus que « Mot de passe oublié ? ».

**2. Le compte à rebours de renvoi (écrans 04 et 05).** Le point de vigilance du
simulateur dit qu'il vient du serveur. Le contrat impose l'inverse :
`otp/request`, `otp/resend` et `password/forgot` répondent **toujours pareil,
même quand le plafond est saturé** — « un 429 visible dirait *cette adresse
intéresse quelqu'un* ». Il n'y a donc aucun délai à lire, et un délai calculé sur
l'horloge du téléphone mentirait. **Jamais écrit.** Le bouton « Renvoyer » reste
disponible en permanence.

Corollaire moins évident, et appliqué : le client **égalise son propre temps de
réponse** sur l'écran 04. Une réponse en 40 ms pour une adresse inconnue et en
900 ms pour une adresse servie se lit au chronomètre, quel que soit le message.
D'où un plancher d'attente, qui court *pendant* l'appel et ne s'y ajoute pas.

**3. Le compteur de tentatives du code (écran 05).** Le simulateur annonce qu'il
décroît à l'écran. Le serveur ne le sert pas : il rend trois messages distincts —
« Code incorrect. Vérifiez votre saisie. », « Ce code a expiré. Demandez-en un
nouveau. », « Trop de tentatives. Demandez un nouveau code. » — et rien d'autre.
**L'écran affiche ces messages tels quels** et ne fabrique pas « il vous reste 3
essais » à partir d'un compteur local, qui se désynchroniserait au premier
changement d'appareil. Les trois cas restent bien distingués, ce qui était le
vrai besoin : confondre « faux » et « expiré » fait ressaisir en boucle un code
qui ne marchera jamais.

**Ce que le serveur pourrait faire, s'il le veut un jour.** Rendre le nombre de
tentatives restantes dans `meta` sur le `422` de `otp/verify` serait un ajout
compatible, et l'écran l'afficherait. Ce n'est pas au client de l'inventer en
attendant.


## 87. Le badge de rôle des auteurs du forum — RETIRÉ

Les cartes de sujet du simulateur mobile affichent, sous le nom de l'auteur,
« Mentor Vérif'All », « Contributeur actif », « Citoyenne vérifiée ».

**L'API ne sérialise jamais le rôle d'un auteur.** `AuteurResource` porte
`uuid`, `nom_affichage`, `initiales` — rien d'autre — et l'entrée 74 en donne la
raison : *« publier "untel est modérateur" sur chaque message dresserait la liste
nominative de l'équipe de modération. »*

**Tranché : le badge disparaît.** La ligne auteur du mobile se réduit à *nom +
ancienneté*. Les trois autres possibilités ont été écartées :

- **fabriquer le badge côté client** — il faudrait le déduire de quelque chose,
  et il n'y a rien à déduire. On afficherait une qualité que personne n'a
  attribuée ;
- **demander le champ à l'API** — ce serait défaire l'entrée 74, qui protège des
  personnes réelles ;
- **afficher « Membre » pour tout le monde** — une étiquette qui ne distingue
  rien n'informe pas ; elle occupe de la place et laisse croire qu'un jour elle
  dira quelque chose.

C'est un élément **visible** de la maquette qui disparaît, et c'est pour cela
qu'il est consigné ici plutôt que dans un commentaire. Réserve C.4, close.

Un test le vérifie : aucune carte du forum ne rend « Mentor », « Contributeur »
ni « Citoyenne verifiee ».

## 88. Le cœur du forum n'a pas d'endpoint — il cesse d'être un bouton

Le simulateur pose un cœur cliquable sur chaque sujet et sur la fiche : il se
remplit, il compte. **Aucun endpoint du contrat ne permet d'aimer un sujet** — la
liste du §10.5 donne `vote`, `replies`, `report`, et rien de plus.

Le cœur du simulateur est un état local de démonstration (`S.liked`), au même
titre que ses données de test.

**Tranché** : le cœur devient un **compteur en lecture seule**, affiché
uniquement si le serveur en sert un ; le bouton **Partager**, lui, reste
fonctionnel. C'est exactement le traitement de la cloche de notifications
(entrée 80) : un bouton qui n'appelle rien fait croire à un geste enregistré, et
c'est pire que son absence.

**Pour lever la réserve** : `POST /forum/threads/{slug}/like` (ou un `DELETE`
symétrique), avec l'unicité en base — ce qui le rendrait rejouable depuis la file
hors ligne, comme le vote.

## 89. Publier un sujet ou une réponse ne part JAMAIS en file — TRANCHÉ

La réserve C.6 laissait le choix entre deux options : refuser ces deux écritures
hors ligne, ou leur attribuer une `Idempotency-Key`.

**Tranché : elles sont refusées hors ligne**, et l'écran dit ce qui manque.

Trois raisons, dans l'ordre de force :

1. **Le socle a déjà tranché en creux.** `PendingOpKind` est une énumération
   **fermée et gelée** : elle porte le vote, le signalement, la leçon terminée,
   l'inscription à un cours, le rappel de séance, la copie de quiz, le profil et
   les préférences. Ni la publication d'un sujet, ni la réponse. Y ajouter une
   valeur serait modifier le socle — c'est-à-dire une demande, pas une décision
   de parcours.
2. **Le contrat ne promet la mémorisation de réponse que pour les deux actes
   qu'il nomme** (§7.3). Sur ces deux routes-ci, une clé d'idempotence serait un
   en-tête accepté mais sans garantie : on se croirait protégé du doublon sans
   l'être.
3. **Le doublon est le pire échec possible ici.** Deux sujets identiques publiés
   par une réponse perdue, sur un forum de fact-checking, ce sont deux fils qui
   divergent sur la même rumeur.

L'écran affiche donc, sur `NetworkFailure` : *« Publier un sujet demande une
connexion. Réessayez une fois en ligne. »* et, pour une réponse, *« Répondre
demande une connexion. Votre texte est conservé. »* — le texte reste dans le
champ, ce qui est la seule chose que le client puisse honnêtement garantir.

Le vote et le signalement, eux, **partent bien en file** : le contrat les déclare
tous deux protégés par une contrainte d'unicité en base, donc rejouables.

## 90. Les six motifs de signalement ont des libellés SANS SOURCE

Le contrat donne les six **valeurs techniques** — `desinformation`, `haine`,
`arnaque`, `spam`, `donnees`, `autre` — et rappelle que « les libellés
affichables voyagent avec elles comme pour toute énumération de l'API ».

**Mais une feuille de signalement est un formulaire.** Elle doit afficher ses
options *avant* le moindre appel, et aucun endpoint ne les sert. Le simulateur ne
montre pas la feuille : il se contente d'un toast *« Signalement transmis à la
modération. »*

Les six libellés n'ont donc aucune source. **Écrits côté client**, en traduction
littérale : Désinformation, Haine, Arnaque, Spam, Données personnelles, Autre.

**Ce n'est pas la même faute que fabriquer un libellé de verdict.** Là, une
valeur inconnue arriverait du serveur et le client mentirait sur ce qu'elle
signifie. Ici la liste est **fermée au contrat**, le client n'invente aucune
option — seulement les mots pour les dire. Le seul terme réellement interprété
est `donnees`, rendu par « Données personnelles ».

**À trancher** : soit l'API les sert (un `meta.motifs` sur n'importe quelle
réponse suffirait), soit le porteur du projet valide cette rédaction. En
attendant, c'est le seul texte de ce parcours qui ne vienne ni du simulateur, ni
du contrat.

Ce que l'écran dit, en revanche, ne s'invente pas : **avant l'envoi**, la feuille
annonce qu'un humain tranche et qu'il n'y a pas de retrait automatique. C'est
l'entrée 52 — *signaler n'est pas voter* — et « promettre un retrait qui n'aura
pas lieu décourage celui qui a signalé de bonne foi ».

## 91. Les deux décomptes du Hub — un retiré, un requalifié

Le simulateur affiche, au-dessus des cartes d'opportunités :
« `<n>` opportunité(s) ouverte(s) » à gauche, « `<m>` archivée(s)
automatiquement » à droite.

**Le compteur d'archivées est retiré.** Une offre archivée n'est plus servie par
l'API — « une opportunité non publiée répond `404`, même en connaissant le
slug » — donc le client ne peut pas les compter, et aucun champ ne les compte
pour lui. Le simulateur y arrive parce qu'il garde tout son jeu de données en
mémoire, offres archivées comprises. Le transposer aurait voulu dire **fabriquer
un chiffre**, ce que la règle 2 du chantier interdit.

**Le compteur d'ouvertes est requalifié.** Il compte les offres **chargées** dont
`expiree` vaut `false`, et non un total : « il n'y a pas de total et on ne peut
pas sauter à la page 7 » (§3.1). Le libellé le dit quand une page suivante
existe — « `<n>` opportunités ouvertes affichées » — et retombe sur la formule du
simulateur quand la liste est complète. C'est la même honnêteté que l'écran 12
sur « `<n>` résultat(s) » (04-RESEAU §4.4).

**Pour lever la réserve** : un `meta.total_ouvertes` et un `meta.archivees`
seraient des ajouts compatibles v1.

## 92. Ce que le parcours M4 demande au socle

Le socle est en lecture seule : « un besoin de modification du socle est une
demande, pas une modification » (§8.1, règle 2). M4 en a six, et chacune produit
un écart visible qu'il vaut mieux consigner que masquer.

| Demande | Pourquoi elle appartient au socle | Ce que M4 fait en attendant |
|---|---|---|
| **La correspondance `verdict` vers le ton d'affichage** | Le socle la confie à M2 « en un seul endroit ». Or M4 affiche un verdict sur chaque brève du Hub, et « aucun import croisé entre deux dossiers `features/` ». Deux parcours, donc le socle (règle 3) | `support/verdict_m4.dart`, bâti **sur les quatre valeurs que le contrat publie** au §3.5 (`true`, `false`, `misleading`, `unverifiable`). Rien d'inventé, mais à fusionner avec celle de M2 |
| **Le bandeau « Données du `<date>` »** | Exigé sur tous les écrans qui lisent un cache — cinq parcours (05-HORS-LIGNE §5.4 et §5.5) | `widgets/bandeau_fraicheur.dart` |
| **L'ancienneté et la date courte** | Historique, séances, forum, Hub, transactions : quatre parcours | `support/dates_m4.dart` |
| **Un hôte de tiroir** | `VfShellScaffold` ne déclare pas de `drawer`, et les entrées du menu mènent aux cinq parcours : l'assembler dans l'un d'eux dupliquerait le menu | **Le bouton du tiroir est absent** de la barre du forum. Un bouton qui n'ouvre rien serait pire — mot pour mot l'argument du socle sur le bouton de magasin du `426` |
| **Deux variantes de `VfButton`** : la hauteur `.btn-sm` (38, déjà déclarée en `VfTouch.smallButtonHeight` mais non câblée) et une couleur par instance | Les trois boutons de vote prennent « la couleur du choix » (écran 21) ; le bouton WhatsApp prend `VfBrandColors.whatsapp`, jeton que le socle déclare « uniquement sur les boutons de partage » | Deux widgets locaux, dessinés **avec les seuls jetons du design system** — aucune couleur inventée. La cible tactile reste à 44, plancher du chantier |
| **Une hauteur de couverture sur `VfCover`** | Le composant n'expose que 112 et 86 ; la carte à la une demande 120, l'en-tête d'article 130 | `VfCover` tel quel. Écart de 8 à 18 points sur deux couvertures, assumé plutôt que dupliquer le composant |

**Trois écarts de plus, du même ordre, qui ne sont pas des demandes :**

- **Les chaînes d'interface sont sans accents ni apostrophes.** C'est la
  convention du socle — « Mettez a jour l application », « Revenir a l ecran
  precedent », « 1 action en attente d envoi » — et M4 s'y aligne. Un écran
  mêlant « Réessayer » et « Cloture » serait pire que deux écrans cohérents.
  **Un passage global reste à faire avant livraison**, et il appartient au socle
  puisqu'il touche ses composants.
- **Le formulaire « Nouveau sujet » n'a que deux champs.** Le contrat en accepte
  deux de plus à la publication — `category` et `lien_signale` — mais le
  simulateur ne les montre pas, et « le simulateur l'emporte sur l'écran ». Le
  dépôt les expose ; l'écran ne les remplit pas. Les ajouter aurait été inventer
  deux champs de formulaire.
- **Le bouton WhatsApp ouvre la feuille de partage du système**, pas WhatsApp.
  Ouvrir l'application directement exigerait un schéma d'URL que ni le contrat ni
  le simulateur ne fournissent, et un bouton qui échoue sur un téléphone sans
  WhatsApp serait pire que le détour d'un choix.

**Et une réserve du contrat, volontairement non câblée** : le paramètre `q` de
`GET /forum/threads`. **L'écran 20 n'a pas de champ de recherche** — exposer un
paramètre qu'aucune interface n'alimente produirait du code que personne
n'appelle. Le jour où la maquette gagne une recherche, le dépôt gagne un
argument.

---

## 100. Le corrigé d'un quiz — TRANCHÉ : il se déroule sur l'écran de résultat

*Réserve C.1 de `docs/flutter/09-RESERVES.md`, ouverte « à trancher avant M3 ».*

Le simulateur marque juste/faux **après chaque réponse** et déroule aussitôt la
carte XAI « Pourquoi cette réponse ». Le contrat ne le permet pas :
`GET /courses/{slug}/quiz` rend les questions **sans corrigé**, et range
`is_correct` comme l'explication parmi ce qui n'est jamais exposé avant la copie
rendue — *« un quiz réussi avec un intercepteur de trafic dévalue tous les
certificats »*. Le corrigé arrive **une seule fois**, dans `data.corrige[]` de
`POST /me/quiz/{uuid}/attempt` : *« c'est le seul moment où elles franchissent le
réseau. »*

**Décidé : l'option 1 de la réserve.** Une question à la fois, **sans marque
juste/faux ni encart XAI pendant l'épreuve**, et le corrigé **déroulé sur l'écran
de résultat**, question par question, avec l'explication du serveur dans l'encart
vert du simulateur (icône `psychology`, titre « Pourquoi cette réponse »).

**Pourquoi celle-là plutôt que la seconde option** (renoncer à l'écran de
résultat compact au profit d'une correction déroulée) : elle conserve les **deux**
écrans validés — l'écran de question et l'écran de résultat — et ne déplace que
le *moment* de l'explication. La pédagogie est préservée ; c'est le plus petit
écart possible à une maquette validée.

**Ce que cela change à l'écran, précisément :**

| Simulateur | Application |
|---|---|
| Option colorée en vert/rouge après le choix | Option **sélectionnée** seulement (bordure et fond verts) |
| Encart XAI après chaque réponse | Encart XAI **par question**, sous le score |
| Bouton « Question suivante » après réponse | Bouton « Question suivante » dès l'affichage, plus « Question précédente » |

Le dernier point est un ajout, pas un écart : sans marque de correction, revenir
sur une question n'expose plus rien, et un quiz chronométré où l'on ne peut pas
se corriger serait plus dur que le web.

**Ce qui est verrouillé par un test** : `EtatDOption` n'a que `neutre` et
`choisie` pendant l'épreuve ; les valeurs `juste` et `fausse` n'apparaissent que
sur l'écran de résultat, et un test de sémantique vérifie qu'aucune option
n'annonce « bonne réponse » avant la copie rendue.

---

## 101. Ouvrir une leçon démarre le chronomètre serveur — TRANCHÉ par le contrat lui-même

*Réserve C.3, ouverte « à trancher avant M3 ».*

La réserve craignait ceci : télécharger un cours entier appelle chaque leçon
d'avance, donc **démarre tous les chronomètres serveur à la fois**. Trois heures
plus tard, hors ligne, la personne lit sa première leçon ; le serveur, lui,
compte depuis le téléchargement.

**Le contrat a déjà tranché, et personne ne l'avait relevé.**
`GET /courses/{slug}/lessons/{uuid}` répond **`403 droit_manquant`** sur une
leçon que le serveur n'a pas ouverte (`AcademieController::lecon`, garde
`leconOuverte`). Le déverrouillage étant séquentiel, **au plus une leçon non
encore lue est accessible à un instant donné** : celle qui suit la dernière
acquise.

> **Un « téléchargement du cours entier » est impossible par construction.** Le
> serveur ne sert que ce qu'il a ouvert.

**Décidé** : le téléchargement demande **uniquement**
`data.lecons[].ouverte == true`, et n'essaie jamais une leçon verrouillée —
essayer produirait un `403` par leçon, c'est-à-dire une rafale d'appels refusés
sur un forfait facturé au mégaoctet. Le téléchargement est donc **incrémental** :
il grossit à mesure qu'on avance, et l'écran le dit — « *N* leçon(s) sur *M*
peuvent partir maintenant. Les suivantes se débloquent au fur et à mesure. »

**Ce qui reste, et pourquoi ce n'est pas grave pour un client honnête** : le
chronomètre de la *seule* leçon suivante démarre au téléchargement plutôt qu'à la
lecture. Or le serveur retient `min(déclaré, mesuré)` : le client déclare son
temps de lecture **réellement actif** — compteur en pause en arrière-plan — et
c'est donc sa déclaration qui l'emporte. Ce qui s'affaiblit, c'est la protection
du serveur contre un client *modifié*, et **cela ne se répare pas côté client**.

**Demande au contrat, ajout compatible v1** : un mode de pré-chargement qui ne
pose pas l'heure de départ — un paramètre sur la lecture d'une leçon, ou une
route distincte. Tant qu'il n'existe pas, la faiblesse est côté serveur et le
client ne la crée pas gratuitement.

**Deux corollaires, dans le même mouvement :**

- **Le quiz n'est jamais pré-chargé.** `GET /courses/{slug}/quiz` démarre lui
  aussi un chronomètre et pose `meta.commence_le` ; une copie ouverte depuis un
  cache porterait une heure de départ périmée et serait rendue hors délai sans
  que personne l'ait vu venir. Le quiz exige donc le réseau, **et l'écran le
  dit**.
- **Les vidéos ne sont pas téléchargées.** Le contrat ne rend qu'une
  `video_url`, sans poids ni durée de validité : on ne peut pas en annoncer la
  taille avant de la rapatrier, et §5.11 appelle cela « une facture ». Le
  téléchargement emporte le **texte** des leçons ; l'écran l'écrit noir sur
  blanc.

---

## 102. Le verrou du quiz n'a AUCUN champ serveur — il n'est pas transposé

Le simulateur grise la ligne du quiz tant que toutes les leçons ne sont pas
faites. **Le contrat ne porte rien qui corresponde** : `QuizResource` n'a ni
`ouverte` ni condition, et `AcademieController::quiz` ne refuse l'accès à
personne — il rend le quiz du cours publié, point.

Poser ce verrou côté client reviendrait à **recalculer une règle de
déverrouillage**, exactement ce que la table d'arbitrage du contrat (§7.4)
interdit pour les leçons : *« un client qui la recalcule ouvrirait un jour une
leçon qui ne devait pas l'être. »* La règle vaut dans les deux sens — un client
qui invente un verrou ferme un jour une porte que le serveur laissait ouverte.

**Décidé : la ligne du quiz est toujours ouvrable**, et l'écart au simulateur est
consigné ici. La ligne garde son libellé complet — « *n* min · *seuil* % pour
valider · +*n* XP » —, ce qui suffit à dire qu'on entre dans une épreuve.

**Ce qui lèverait la réserve** : un champ `ouverte` sur `QuizResource`, calculé
comme celui des leçons. Ce serait un **ajout compatible v1**, et le client le
refléterait sans rien changer d'autre — la mécanique d'affichage d'une condition
existe déjà pour les leçons.

**À ne pas confondre avec le certificat** : lui est bien gardé côté serveur
(toutes les leçons faites **et** le quiz réussi), et le client ne fait que
l'afficher quand `GET /me/certificates` le rend.

---

## 103. Quatre chiffres du catalogue disparaissent — ils ne sont servis par rien

Le simulateur affiche, sur chaque carte de cours et sur la fiche : **« ★ 4,8 »**,
**« 1 245 inscrits »**, une **pastille XP** dans la carte « Votre progression »,
et une **barre de progression sur l'ensemble du catalogue**. Aucun des quatre
n'a de source.

| Chiffre | Ce que l'API sert | Décision |
|---|---|---|
| **Note** | `rating` et `reviews_count` existent en base ; `CoursResource` **ne les sérialise pas** | La pastille dit **« Nouveau »** (arbitrage n° 61). Une étoile fabriquée serait une note que personne n'a donnée |
| **Nombre d'inscrits** | `students_count` : même situation | **La ligne disparaît** (arbitrage n° 67 : les chiffres sont comptés ou retirés). La ligne devient « `<durée>` · `<n>` étapes » |
| **Total d'XP** | Aucun endpoint de l'Académie ne le rend ; il vit dans `GET /me/gamification`, écran de M5 | **La pastille est retirée.** L'appeler ici obligerait à dupliquer le modèle de M5 dans M3, et « un composant dupliqué dans deux dossiers diverge en une semaine » |
| **Barre sur tout le catalogue** | Le catalogue est paginé **par curseur** : *« on ne peut pas sauter à la page 7, et il n'y a pas de total »* (§3.1). Le dénominateur n'existe pas | La barre porte sur **les cours suivis** — un nombre compté — et le libellé le dit : « Sur *n* cours suivis » |

**Un cinquième, sur la fiche** : la **qualité du formateur** (« Formateur
principal »). `AuteurResource` porte uuid, nom d'affichage et initiales, **jamais
le rôle** — *« publier "untel est modérateur" sur chaque message dresserait la
liste nominative de l'équipe »* (§9). Même décision que l'arbitrage n° 87 pour le
forum : la ligne se réduit au nom et à la durée du cours.

Et une **précision qui va dans l'autre sens** : `cout_unites` est le coût
**actuel** (`coutActuel()`), pas le prix en colonne. Un cours offert jusqu'à une
date rend `0`, et l'écran affiche « Gratuit » — *« renvoyer le prix brut ferait
annoncer un prix là où le serveur ne débitera rien, et l'écran mentirait dans le
sens qui fâche. »*

---

## 104. Le PDF du certificat — retiré, et remplacé par ce qui marche

`GET /me/certificates/{code}/pdf` **n'a aucun endpoint** (contrat §11, arbitrage
n° 73). Le bouton primaire « Télécharger le PDF » du simulateur est donc
**retiré** : *« un bouton qui échoue systématiquement est pire que son
absence. »*

**Ce que l'écran fait à la place**, et c'est suffisant :

1. **le partage d'image**, rendu depuis un `RepaintBoundary` — c'est le geste
   réel, celui qu'on fait vers WhatsApp ;
2. **l'encart de vérifiabilité**, qui porte l'adresse publique et le code, avec
   une **action de copie**. Le code est conçu pour être recopié : alphabet sans
   O/0, I/1, S/5.

**Le renvoi vers la page publique n'est pas cliquable**, et c'est délibéré :
`AppConfig` n'expose que `apiBaseUrl`. Construire l'adresse du site public en
devinant l'hôte reviendrait à écrire une URL de production en dur — ce que la
règle §5 du chantier interdit. L'adresse est **affichée**, comme dans le
simulateur, mais aucun bouton ne prétend l'ouvrir. Voir la demande au socle,
entrée 105.

**Ce que le client ne fera jamais** : composer le PDF lui-même. *« Un certificat
composé côté client serait falsifiable par quiconque décompile l'application. »*

**Une dernière règle, verrouillée par un test** : la carte affiche `titulaire` et
`cours_titre` **du certificat**, figés à l'émission — jamais les valeurs
courantes du compte. Et quand `delivre_le` manque, l'écran écrit « Non
communiquée » plutôt que d'inventer une date.

---

## 105. Ce que le parcours M3 demande au socle

Le socle est en lecture seule (§8.1, règle 2). M3 en a quatre demandes, et deux
écarts assumés qui n'en sont pas.

| Demande | Pourquoi elle appartient au socle | Ce que M3 fait en attendant |
|---|---|---|
| **Le bandeau « Données du `<date>`, actualisation impossible »** | Il est exigé de **tous** les écrans qui lisent un cache — donc des cinq parcours (05-HORS-LIGNE §5.4 et §5.5). Sans lui, « une échéance J-2 vieille de trois jours devient un mensonge » | `features/lms/widgets/lms_banners.dart`. M4 en a écrit un aussi (entrée 92) : **deux implémentations existent déjà**, ce qui est précisément le symptôme que la règle 3 annonce |
| **Les mises en forme de date, de durée et de taille** | Date courte, date et heure, chronomètre, octets : l'Académie, l'historique, le Hub, le portefeuille en ont tous besoin | `features/lms/widgets/lms_formats.dart`, sans ajouter `intl` — le socle a figé les dépendances pour que personne ne rouvre `pubspec.yaml` |
| **L'adresse du site public dans `AppConfig`** | Elle sert au renvoi vers la vérification publique d'un certificat, et servira à tout partage de lien | L'adresse est **affichée**, jamais construite ni ouverte (entrée 104) |
| **Une hauteur de couverture sur `VfCover`** | Le composant expose 112 et 86 ; la fiche d'un cours demande 140 et le bloc vidéo d'une leçon 150 | Un `SizedBox` autour du composant, dans `CoverAvecLibelle`. Le composant du socle n'est pas dupliqué |

**Deux écarts qui ne sont pas des demandes :**

- **Les chaînes d'interface sont sans accents ni apostrophes**, comme celles du
  socle (« Revenir a l ecran precedent », « 1 action en attente d envoi »). M3
  s'y aligne : un écran mêlant « Réessayer » et « Reessayer » serait pire que
  deux écrans cohérents. Le passage global appartient au socle, puisqu'il touche
  ses composants.
- **Le cache local passe par une interface, `LmsCache`.** Ce n'est pas une
  modification du socle — le fichier `.drift` du parcours et son implémentation
  sont dans le périmètre de M3 —, mais c'est un choix qui mérite d'être écrit :
  `flutter test` tourne sur la machine hôte, « où la bibliothèque native SQLite
  n'est pas forcément installée », et **le test qui coupe le réseau est le test
  central de ce parcours**. Il ne pouvait pas dépendre de cela. C'est la même
  raison qui a fait exister `PendingOpsStore` au socle.

**Et un défaut du socle trouvé en chemin, corrigé côté M3** : les boutons du
design system prennent **toute la largeur par défaut**
(`VfGhostButton.expanded = true`). Deux d'entre eux dans une `Row` sans
contrainte de largeur produisent une mise en page infinie — plantage à
l'exécution, invisible à l'analyse. M3 les enveloppe dans des `Expanded`. Le
signaler ici évite que les quatre autres parcours le retrouvent chacun de leur
côté.

## 110. La suppression du compte sur mobile — TRANCHÉ : deux temps, pas un nom à taper

Le simulateur mobile (écran 30) demande de **taper son nom exact**, puis
supprime. Le contrat impose autre chose, sur le même `DELETE /me` :

| Temps | Corps envoyé | Réponse |
|---|---|---|
| 1 | `confirmation` = `SUPPRIMER`, `mot_de_passe` | `202`, `data.etape = "code_envoye"`. **Rien n'est supprimé** |
| 2 | `confirmation`, `mot_de_passe`, `code` | `200`, `data.etape = "suppression_programmee"` |

**Décidé** : l'application applique le contrat. Trois écarts à la maquette, tous
implémentés — le mot attendu est celui du contrat et non le nom de la personne,
le mot de passe est exigé dès le premier temps, un code par courriel vient
ensuite.

**Pourquoi le nom ne suffit pas.** Il est écrit sur l'écran d'à côté. Il prouve
qu'on sait lire, pas qu'on est le titulaire. *« Mot de passe et code : le premier
prouve qu'on connaît le compte, le second qu'on tient la boîte. Un téléphone volé
déverrouillé passe le premier, jamais les deux. »*

**Ce que l'écran dit, et que rien d'autre ne dira** : le compte est fermé
sur-le-champ, les données ne disparaissent qu'au bout de trente jours, et
**l'annulation ne passe pas par l'application** — elle se fait par le lien signé
reçu par courriel, seule voie de retour quand on ne peut plus se connecter. La
phrase est affichée **avant** le geste, pas après : quelqu'un qui hésite doit
savoir comment revenir en arrière, et l'application ne le lui permettra pas.

La page `/compte-en-suppression` prend ensuite le relais. Elle est alimentée par
`GET /me`, qui reste servi pendant le délai — c'est l'une des deux seules
exceptions du contrat, et elle existe précisément pour cet écran : *« un
utilisateur qui ne comprend pas pourquoi tout est refusé ne se rétracte pas, il
désinstalle. »*

La réserve A.12 est levée.

## 111. L'opérateur Mobile Money est déduit du numéro — la feuille à trois logos perd son rôle

La feuille de recharge du simulateur fait **choisir** entre MTN MoMo, Moov Money
et Celtiis Cash. Le contrat attend `pack` et `numero`, et précise que
« l'opérateur est déduit du numéro par le serveur ».

Le choix affiché n'a donc **aucun effet fonctionnel** : trois boutons qui mènent
au même appel, avec une valeur que le serveur ignore. Pire, il ment sur ce qui se
passe — quelqu'un qui choisit « Moov » et saisit un numéro MTN croirait avoir
décidé.

**Décidé** : la feuille demande **le numéro**, et nomme les trois opérateurs en
texte d'aide (« votre opérateur est reconnu à partir du numéro »). C'est l'option
« la moins éloignée de la maquette » de la réserve C.7, débarrassée du seul
élément qui n'avait pas de suite.

**Les couleurs de marque ne sont pas reprises.** Le design system ne donne pas
leurs valeurs, et le socle les range hors palette avec une note explicite : « les
deviner reviendrait à inventer un jeton ». Elles se demanderont avec une maquette.

**Ce que la signature du code garantit.** `RechargerDesUnites.call()` ne prend que
`codePack` et `numero` ; `CompteRemoteDataSource.recharger()` non plus. **Ce qui
n'existe pas dans le code ne peut pas partir dans la requête** — c'est plus solide
qu'un commentaire, et un test le vérifie sur le corps réellement émis.

La réserve C.7 est levée.

## 112. Trois réglages de sécurité n'ont aucun endpoint — TRANCHÉ, écran par écran

L'écran 30 proposait trois actions que l'API v1 ne sert pas. Aucune n'est laissée
sous forme de commande inerte : *« un interrupteur qui échoue à chaque pression
est pire que son absence ; et un interrupteur de sécurité qui semblerait marcher
sans rien activer serait un mensonge sur une garantie — la faute la plus grave
possible sur cet écran. »*

| Action | Décision | Pourquoi |
|---|---|---|
| **Double authentification** | **Interrupteur retiré** | Aucun endpoint, et `PATCH /me/settings` refuse en `422` toute clé inconnue |
| **Changer mon mot de passe** | **Conservé**, il ouvre le parcours de code de M1 (`/mot-de-passe-oublie`) | C'est le seul chemin que l'API serve. L'écran annonce d'avance que toutes les sessions seront fermées — le contrat le dit (§1.5), et la surprise serait désagréable |
| **Changer mon adresse e-mail** | **Champ conservé, en lecture seule**, avec la mention de l'endroit où le faire | Le bloc reste à sa place — le prototype fait foi sur la mise en page — mais il ne promet plus ce qu'il ne peut pas tenir (arbitrage n° 6) |

**Un quatrième élément est retiré, et il ne figurait pas dans la réserve** : la
ligne « **Appareils connectés · 2** ». Il n'existe aucun endpoint de session, et
le nombre comme la mention « Cet appareil · Cotonou » figurent parmi les données
de démonstration à ne jamais transposer. Afficher un compteur d'appareils
inventé serait un indicateur non mesuré sur l'écran de sécurité — exactement ce
que la règle 2 du chantier interdit. La révocation des autres sessions n'existe
d'ailleurs pas non plus : `POST /auth/logout` révoque **le seul jeton présenté**.

Un texte court, sous la section Sécurité, dit que la double authentification et
le changement d'adresse se règlent sur le site. **L'écran ne se tait pas sur ce
qu'il ne fait pas.**

La réserve C.8 est tranchée.

## 113. Les chiffres du compte que l'API ne sert pas — retirés, jamais devinés

Quatre valeurs du simulateur n'ont aucune source. Elles sont retirées, et la
place qu'elles occupaient reste occupée par ce qui est vrai.

| Élément | Ce que montrait la maquette | Ce que l'écran montre |
|---|---|---|
| Dotation mensuelle (écran 26) | « +10 unités offertes le 1ᵉʳ septembre » | Une pastille **Dotation mensuelle** et une phrase : des unités sont **ajoutées** chaque mois. Ni montant ni date : `GET /me/wallet` ne les sert pas |
| Barème de coût (écran 26) | « texte et lien 1 unité, image 2 unités, vidéo et audio 5 unités » | « Le coût dépend du type de contenu, et c'est le serveur qui l'annonce à l'envoi. » Le barème est une valeur commerciale : le figer obligerait à publier sur les magasins pour le changer |
| Prochaine étape (écran 27) | « `<n>` / 10 vérifications terminées » + barre | Le nom du prochain badge **et sa condition en clair**, telle que l'API la rend. Aucune barre : l'API ne rend aucun couple fait/objectif, et la remplir demanderait un compteur local |
| Appareils connectés (écran 30) | « 2 », « Cet appareil · Cotonou » | Rien — voir l'entrée 112 |

**La règle appliquée est celle de la dotation mensuelle** (arbitrage n° 16) : elle
crédite, elle ne remet pas à zéro. L'écran l'annonce donc comme un **ajout**,
sans chiffrer ce que le serveur ne chiffre pas.

**Deux compteurs, à l'inverse, ont été conservés en allant chercher leur source.**
L'écran 25 affiche quatre indicateurs et `GET /me` n'en sert que deux. Plutôt que
de retirer deux tuiles d'une maquette validée, l'écran lit les deux endpoints qui
portent réellement ces chiffres — `GET /me/stats` pour les vérifications,
`GET /me/gamification` pour les badges, tous deux du périmètre M5. Ces lectures
sont **au mieux** : si elles échouent, la tuile affiche un tiret, jamais un zéro.

**Et le tiret est une décision, pas un repli.** C'est celle du socle pour la
pastille de points : « mieux vaut un tiret honnête qu'un zéro qui ferait croire à
un compte vide ». M5 l'étend au solde du profil, aux compteurs d'appoint et à la
série sur l'écran des statistiques.

Le record de série (réserve D.3) suit la même logique, mais dans l'autre sens :
l'agrégat le renvoie aujourd'hui, donc **il s'affiche**. S'il cessait de le
renvoyer, la mention disparaîtrait d'elle-même — le champ est lu comme facultatif,
et rien ne le remplace.

## 114. Le formulaire de profil — TRANCHÉ pour le nom, OUVERT pour deux champs

`PATCH /me` accepte `prenom`, `nom`, `telephone`, `profession`, `ville`, `bio`.
Le simulateur affiche « Nom affiché », « Adresse e-mail », « Ville », « Bio ».

**Décidé** : « Nom affiché » devient **deux champs, « Prénom » et « Nom »**. La
seule alternative aurait été de découper une chaîne unique sur l'espace pour
deviner les deux — et c'est le nom de quelqu'un : un nom composé donnerait un
résultat faux, définitivement enregistré.

**Laissé ouvert** : « Profession » et « Téléphone » restent modifiables par le
contrat **sans avoir de champ à l'écran**. Les ajouter serait une addition
d'interface, pas une transposition, et la maquette a été validée sans eux. La
profession, elle, est bien **affichée** — c'est elle qui alimente la ligne
« `<rôle>` · `<ville>` » de l'en-tête, le rôle au sens RBAC n'étant jamais
sérialisé par l'API. La réserve A.11 reste donc ouverte sur ces deux champs.

**Deux détails qui ne se voient pas mais qui comptent** :

- le formulaire n'envoie **que ce qui a changé**. `PATCH` veut dire « ne change
  que ce que j'envoie » ; envoyer les quatre champs à chaque enregistrement
  transformerait l'opération en écrasement, et deux téléphones ouverts sur le même
  compte s'effaceraient l'un l'autre des valeurs qu'aucun des deux n'a touchées ;
- la limite de la bio est **280 caractères et non 240**. C'est la borne du
  contrat ; afficher 240 refuserait localement ce que le serveur accepte.

## 115. Le réessai automatique de Riverpod — DÉSACTIVÉ sur les écrans du compte

Riverpod 3 rejoue de lui-même la construction d'un provider en échec, avec un
recul exponentiel, **par défaut**. Ce n'est pas anodin : la politique de réessai
de l'application est déjà écrite, et elle est ailleurs.

Elle vit dans la couche réseau du socle : l'intercepteur de réessai (trois
tentatives, sur les seuls échecs de transport et les `502/503/504`, et seulement
si la requête est rejouable) et l'intercepteur de débit, qui applique la règle du
contrat — *« attendre `meta.retry_after` secondes. Un seul essai ensuite. »*

Une seconde couche de reprise, invisible depuis le code de l'écran, multiplierait
les appels et contredirait cette phrase-là.

**Décidé** : les providers de M5 passent `retry: sansReessaiAutomatique`. Un écran
en erreur **le reste** jusqu'à ce qu'on tire pour rafraîchir ou qu'on touche
« Réessayer ». C'est plus vrai encore sur `GET /me/wallet` : un écran qui repart
tout seul en boucle sur un réseau coupé consomme le forfait de quelqu'un sans
rien lui apprendre.

**Le défaut a été découvert par un test** qui n'en finissait pas : cinq appels à
`/me/wallet` là où on en attendait un. Il concerne les cinq parcours, pas
seulement M5 — c'est la première des demandes de l'entrée 116.

## 116. Ce que le parcours M5 demande au socle

Quatre demandes, aucune contournée. Le socle est en lecture seule ; ce qui suit
est signalé, pas modifié.

**1. Trancher le réessai automatique de Riverpod, pour les cinq parcours.** Voir
l'entrée 115. M5 l'a désactivé sur ses propres providers ; la décision devrait
appartenir au socle, qui possède déjà les deux intercepteurs de réessai. Une
valeur par défaut posée au `ProviderContainer` réglerait la question une fois.

**2. Une adresse de site dans la configuration.** Trois textes de M5 renvoient
vers le site — changement d'adresse e-mail, double authentification, politique de
confidentialité. Aucun n'est cliquable : `AppConfig` ne porte que l'adresse de
l'**API**, et composer une adresse web à partir d'elle reviendrait à inventer une
URL de production. La **ligne « Politique de confidentialité » a donc été retirée
du menu** plutôt que laissée inerte. Une clé `VF_WEB_BASE_URL` la rendrait au
menu, et rendrait cliquables les deux autres mentions.

**3. `Failure` est scellé.** Un parcours ne peut donc pas déclarer un échec de
domaine qui lui soit propre. M5 en avait trois — recharge, export et suppression
refusés hors ligne — et les exprime en `ImpossibleActionFailure` avec le message
qui convient. Ce n'est pas un contournement : le contrat décrit précisément ce
code comme « l'action n'a pas de sens en l'état », dont « le message porte
l'information utile ». Mais si un jour un parcours a besoin d'un échec réellement
distinct, il faudra ouvrir la hiérarchie.

**4. Deux types de Riverpod ne sont pas exportés par `flutter_riverpod`** :
`Override` et les familles (`AsyncNotifierProviderFamily`…). Ils vivent dans
`package:riverpod/misc.dart`, un paquet **transitif** que le dépôt ne déclare pas.
M5 s'en passe par inférence de type, à deux endroits documentés ; les autres
parcours ont écrit ces types explicitement et ne compilent pas. Déclarer
`riverpod` en dépendance directe, ou fournir un alias dans le socle, réglerait le
point pour les cinq.

**Une observation, sans demande.** Le `403 compte_en_suppression` est traité au
routeur pour la **navigation**, mais l'information n'entre dans l'application que
par `GET /me` — appelé par M5. Un autre parcours qui essuierait ce 403 sans être
passé par le profil verrait un échec ordinaire. Le poser dans l'intercepteur
d'authentification du socle, à côté du `401`, fermerait le cas pour tout le monde.

## 117. Le barème de coût du simulateur — RETIRÉ des tuiles et de l'encart

Le simulateur affiche une pastille rouge sur chaque tuile de type (`1 pt`,
`1 pt`, `2 pts`, `5 pts`, `5 pts`) puis annonce, avant l'envoi : *« Cette
vérification consommera 2 unités — il vous restera 32 unités. »*

Les deux nombres sont fabriqués localement, et **aucun endpoint ne publie le
barème** : il vit dans `config/factcheck.costs` côté serveur et n'est sérialisé
nulle part. Le contrat est par ailleurs catégorique — *« le coût n'est jamais
envoyé par le client : il est lu du type »*. Le second nombre s'en déduirait par
soustraction, ce que la règle du hors-ligne interdit explicitement : *« le client
ne débite ni ne crédite jamais d'unité »*.

**Décidé** : les pastilles de coût disparaissent des tuiles, et l'encart annonce
**le solde réel** plus le fait que le coût est fixé par le serveur. L'encart
reste à sa place — avant le bouton d'envoi — parce que c'est là qu'il joue son
rôle : « personne ne doit découvrir un débit après l'avoir déclenché ».

Un test le verrouille **par l'absence de constante** : il lit les sources de M2
et échoue si un barème, une soustraction de solde ou l'un des cinq endpoints non
servis y réapparaît. C'est exactement le genre de valeur qu'on recopie « juste
pour afficher quelque chose » et qui reste ensuite trois ans.

**Ce qu'il faut pour la rétablir** : que le serveur publie le tarif, par exemple
dans l'agrégat de `GET /me/dashboard`. Une plateforme qui annonce 2 unités puis
en débite 5 a menti, même sans le vouloir.

## 118. La correspondance verdict → couleur, ÉTABLIE contre le serveur réel

Le socle a laissé ce point ouvert et a dit pourquoi : *« le contrat ne publie pas
la liste complète des valeurs techniques de `verdict` ; en figer une ici
reviendrait à deviner une forme de réponse. Le parcours M2 établit la
correspondance en un seul endroit, contre le serveur réel. »*

**Établie contre `App\Enums\VerdictFactCheck`**, qui est servi : quatre valeurs,
`true` · `false` · `misleading` · `unverifiable`, et pas une de plus. Les
couleurs ne sont pas choisies non plus — elles sont reprises de
`VerdictFactCheck::couleurBarre()`, c'est-à-dire du design system :

| Valeur | Ton | Couleur |
|---|---|---|
| `true` | `VfVerdictTone.vrai` | `#16A34A` |
| `false` | `VfVerdictTone.faux` | `#DC2626` |
| `misleading` | `VfVerdictTone.trompeur` | `#D97706` |
| `unverifiable` | `VfVerdictTone.enCours` | `#2563EB` |

**Le quatrième mérite une phrase.** Le serveur donne au « non vérifiable » le
bleu `--vinfo`, la même teinte que « EN COURS ». Ce n'est pas une approximation :
« non vérifiable » n'est pas un échec, c'est un résultat honnête quand les
preuves manquent, et le bleu dit *information* là où le vert dirait *tout va
bien* et le rouge *intox*. Le socle n'ayant qu'un ton portant cette teinte, les
deux la partagent — et c'est **le libellé du serveur** qui les distingue à
l'écran, jamais le client.

Un test paramétré verrouille les quatre couples, **et la couleur de la barre de
fiabilité** (entrée 32). Une cinquième valeur ajoutée un jour côté serveur rend
le ton neutre et affiche le libellé reçu : l'écran tient.

## 119. Ce que le rapport ne peut pas afficher — trois blocs retirés

L'écran 11 du simulateur porte dix blocs. Trois n'ont **aucune donnée** dans la
réponse de `GET /verifications/{uuid}`, et ils sont retirés plutôt que remplis.

**« 🤖 Moteurs d'analyse mobilisés ».** Le contrat n'expose *jamais* le détail
des exécutions de moteurs — *« publier quel moteur a répondu quoi revient à
publier notre grille d'achat »* (entrée 74). Le champ `scores` existe bien dans
`RapportResource`, mais il ne porte pas ce que le bloc suppose : c'est une
**répartition par verdict** (`{"false": 92.0, "true": 8.0}`), dont les clés sont
des valeurs techniques **sans libellé**. Les afficher obligerait à fabriquer un
libellé français côté client, ce que le contrat interdit en toutes lettres.

**« 🔍 Sources et preuves consultées ».** `ReportEvidence` n'est pas sérialisé.
Les quatre sources du simulateur sont d'illustration, et la section B des
réserves est nette : les transposer publierait des preuves qui n'ont pas été
consultées. C'est, sur une plateforme de fact-checking, la faute la plus grave
possible.

**« Ce rapport vous a-t-il été utile ? ».** `POST /verifications/{uuid}/feedback`
n'a aucun endpoint (contrat §11, entrée 73). Le bloc est **écrit derrière un
drapeau de fonctionnalité et masqué** : pas de boutons inertes, pas de « merci »
sans destinataire — un remerciement pour un avis qui n'est stocké nulle part est
un mensonge poli, et c'est pire qu'une question non posée.

**Ce qui les remplace, et qui est servi** : `rapport.deroule` — les étapes
réellement franchies, avec leurs libellés et leurs horodatages, écrits par
l'orchestrateur au moment où il les a franchies. Un bloc « Traçabilité de
l'analyse » les affiche, un par un, sans rien en déduire.

**Pour lever la réserve** : un tableau `preuves` dans `RapportResource` (nom,
domaine, position), et `moteurs` avec des libellés — ou `scores_libelles`.

## 120. La chronologie du suivi — cinq étapes éditoriales, pilotées par l'avancement

Le simulateur déroule cinq étapes de pipeline ; l'orchestrateur réel en franchit
**six** (`App\Enums\EtapePipeline`) et n'en publie que **la courante** (`etape`,
`etape_libelle`). La liste complète n'est dans aucune réponse.

On ne peut donc ni recopier six libellés sans les deviner, ni aligner les cinq du
simulateur un à un sur les six du serveur.

**Décidé** : la chronologie affiche les **cinq étapes éditoriales** du
simulateur — que les réserves classent explicitement comme « la parole du
produit », au même titre que les textes de méthode — et leur état est une
fonction **pure de `data.avancement`**. Aucune minuterie, aucun `Timer`, aucune
interpolation : le seul mouvement possible est celui que le serveur a déclaré
(entrée 28). Un test vérifie que la fonction est pure, monotone, qu'aucune étape
n'est franchie à 0 %, et qu'une analyse arrêtée n'a **aucun point qui bat** — un
battement dirait qu'il se passe quelque chose.

L'en-tête, lui, affiche le libellé **exact** de l'étape courante, qui est servi.

**Deux nombres du simulateur ne s'affichent pas** : « étape N sur M » et
« environ S secondes restantes ». Ni le cardinal des étapes ni une estimation de
durée ne sont publiés. L'écran affiche donc le libellé de l'étape et le
pourcentage — « le temps restant vient du serveur, ou n'est pas affiché ».

**Pour lever la réserve** : publier la liste des étapes dans `meta.etapes` de
`GET /verifications/{uuid}`, ou le cardinal dans `data.etapes_total`.

## 121. « Reprise programmée » — sans rendez-vous, et sans bouton de relance

L'entrée 35 demande que l'écran affiche le statut « Reprise programmée », **le
rendez-vous annoncé**, et un bouton « Relancer maintenant » une fois le délai
écoulé.

`VerificationResource` sert bien `statut` et `statut_libelle`. Il ne sert **ni**
`retry_after` — le modèle le porte, la ressource ne l'expose pas — **ni** aucune
route de relance manuelle : le contrat n'en déclare aucune.

**Décidé** : l'écran affiche le libellé du serveur et explique que **l'analyse
reprendra d'elle-même**, en disant franchement que l'heure de la prochaine
tentative n'est pas publiée. Aucun compte à rebours inventé, aucun bouton inerte.
Un bouton « Relancer maintenant » qui n'appellerait rien serait pire que son
absence.

**Le remboursement, lui, se dit — mais seulement quand il est certain.**
`refunded_at` n'est pas sérialisé. Un seul chemin du back rembourse sans
condition : la renonciation après épuisement des tentatives (`failed`), et
`cout_unites` est servi. C'est donc le seul cas où l'écran affirme « vos N unités
vous ont été rendues ». Pour les deux autres — analyse partielle, réponse déjà
connue — **c'est le serveur qui l'écrit lui-même** dans `rapport.reserve`
(« Vos unités vous sont rendues. »), et l'écran relaie sa phrase telle quelle.
Affirmer un remboursement qu'on ne peut pas lire serait le rapport creux que la
règle §7 du chantier proscrit.

**Pour lever la réserve** : `rembourse` (booléen) et `retry_after` dans
`VerificationResource`, et une route de relance si le produit la veut.

## 122. Le tableau de bord — trois blocs que l'agrégat ne nourrit pas

`GET /me/dashboard` porte exactement : `salutation`, `compteurs`
(`verifications`, `cours_suivis`, `badges`, `unites`), `niveau`, `serie`,
`activite`, `cours_offert`. Trois blocs du simulateur demandent autre chose.

**1. La troisième tuile de KPI — « Points d'expérience ».** L'agrégat ne sert pas
l'XP absolu, seulement un niveau et un pourcentage de palier. Afficher un nombre
d'XP exigerait de l'inventer ; laisser un tiret permanent ferait une tuile morte.
**Décidé** : la tuile affiche le compteur réellement servi — `badges` — avec son
vrai libellé « Badges obtenus », en conservant l'accent violet de la position.
Ajouter `xp` à `compteurs` la rendrait à son libellé d'origine.

**2. La ligne « Un verdict vous attend » de la carte Priorités.** Elle est
alimentée, dans le simulateur, par **la notification la plus récente**. Sans
magasin de notifications, il n'existe aucun état « lu / non lu » sur un verdict.
La fabriquer depuis la dernière analyse terminée dirait « vous attend » d'un
rapport peut-être déjà lu. **Décidé** : la ligne disparaît — ce que la
documentation de l'écran autorise explicitement. La carte conserve « Leçon en
attente », alimentée par `GET /me/courses/resume`, et **disparaît entièrement**
s'il n'y a rien à reprendre.

**3. La carte « Prochaine Live Class ».** Elle affiche un titre de séance, un
intervenant et une date : trois données que l'agrégat ne porte pas. Les tirer de
`GET /live-classes` ajouterait un appel que la fiche de l'écran ne déclare pas,
pour un module qui n'est pas celui de M2, et dupliquerait un modèle de M3.
**Décidé** : la carte est retirée. Un `prochaine_seance` dans l'agrégat serait la
réponse propre — c'est un aller-retour de moins, ce qui est justement la raison
d'être de cet agrégat.

**Et une quatrième chose, plus petite** : l'histogramme n'a **qu'une série**. La
légende du simulateur en annonce deux (Vérifications, Leçons terminées) ;
`activite` ne porte que la première. La seconde entrée de légende est retirée
plutôt qu'accompagnée d'une courbe inventée.

## 123. Le partage d'un rapport — pas de lien, parce qu'il n'y en a pas

La feuille de partage du simulateur propose quatre destinations : WhatsApp,
Facebook, Copier le lien, Image 9:16.

Un rapport de vérification est **privé** : `GET /verifications/{uuid}` est borné
au propriétaire, et « ce qui n'appartient pas au demandeur répond 404 ». Il
n'existe donc **aucune adresse publique** à partager. « Copier le lien » n'aurait
rien à copier, et un partage Facebook sans URL n'ouvre rien.

**Décidé** : la feuille porte trois entrées — **WhatsApp** (le texte du verdict,
par `wa.me`, avec repli sur la feuille système si WhatsApp n'est pas installé),
**Copier le texte**, et **Image 9:16**. L'image est rendue côté client depuis un
`RepaintBoundary`, ce qui la rend partageable **hors ligne** : c'est précisément
le document qu'on rouvre pour le montrer à quelqu'un, souvent sans réseau.

Ce qui voyage, ce sont les champs servis : verdict, affirmation, indice de
fiabilité, référence, marque. C'est ce que les gens repostent.

**Pour rétablir le lien** : une page publique de vérification, ou un lien signé à
durée limitée — la même mécanique que le PDF de certificat, qui attend pour les
mêmes raisons.

## 124. Le filtre des outils — LOCAL, et l'écart de la réserve C.5 est levé

La réserve C.5 note que `GET /tools?category=` n'apparaît pas au contrat.
**Vérification faite sur l'implémentation** : le paramètre existe bien
(`SeanceEtOutilsController::outils`), il accepte la sentinelle `toutes`, et la
réponse porte `meta.categories` — les catégories lues **en base**.

Cela ne change pas la décision, mais la raison n'est plus celle de la réserve.

**Décidé** : le client appelle `GET /tools` **sans filtre**, met la réponse en
cache 24 h (durée fixée par le contrat), alimente ses pastilles depuis
`meta.categories`, et **filtre localement**. Deux raisons :

- la liste **n'est pas paginée** — l'interdiction de filtrer côté client
  (entrée 82) vise les listes à curseur, où filtrer localement ne filtre que la
  page chargée. Ici la liste arrive entière ;
- un filtre distant **ne marcherait pas hors ligne**, et l'annuaire est
  précisément ce qu'on consulte sans réseau.

Les pastilles viennent de `meta.categories` et jamais d'une liste écrite à la
main : « deux listes finissent par diverger, et c'est le filtre qui cesse de
trouver quoi que ce soit » (entrée 55).

## 125. L'historique — pas de filtre « en cours », et le décompte le dit

Deux écarts entre l'entrée 31 et ce que l'API permet.

**1. On ne peut pas filtrer sur « en cours ».** L'entrée 31 demande que le filtre
mélange verdicts et état. Or `verdict` est une **énumération fermée** côté
serveur : une valeur hors liste répond `422`. Les analyses en cours
**apparaissent bien** dans la liste non filtrée, avec leur `statut_libelle` à la
place du verdict — « un tiret laisserait croire à un échec » — et toucher une
ligne mène au suivi plutôt qu'au rapport. Mais aucune pastille ne les isole. Pour
lever : accepter une valeur `en_cours` sur le paramètre `verdict`, ou ouvrir un
paramètre `statut`.

**2. Il n'y a pas de total.** La pagination par curseur n'en donne aucun
(entrée 68). Le décompte du simulateur — « n résultat(s) » — devient donc
« n résultat(s) chargé(s) » : le libellé supporte cette honnêteté plutôt que de
promettre ce que l'API ne donne pas.

**Les quatre KPI, eux, portent bien sur tout l'historique** — ils le doivent, dit
l'entrée 31, et les calculer sur la page afficherait « 1 » à quelqu'un qui a
trois cents vérifications. Ils viennent de `GET /me/stats`, qui sert exactement
les trois nombres demandés (`verifications`, `intox_detectees`, `verdicts.true`).
Cet endpoint appartient à l'écran 27 de M5 ; **M2 le lit sans partager une ligne
de code** avec lui — c'est la dépendance par l'API que la règle de propriété
autorise, par opposition à l'import croisé qu'elle interdit.

## 126. Reverb n'est PAS branché — le sondage est le mode nominal, et il suffit

Le contrat pose la hiérarchie : *« l'avancement se suit par sondage, **ou** par
Reverb quand le canal est branché »*. Le sondage est donc le mode nominal, et le
plafond de lecture — 120 par minute — a été dimensionné exprès pour une lecture
toutes les trois secondes.

**Décidé : le canal `private-verification.{uuid}` n'est pas branché en v1**, pour
deux raisons qui ne sont ni l'une ni l'autre un manque de temps :

- c'est un canal **privé**. Il exige un point d'authentification de diffusion
  (`/broadcasting/auth`) que le contrat ne publie pas. En composer l'adresse
  serait inventer un endpoint ;
- **la forme des événements n'est documentée nulle part** (réserve F.4). La
  deviner serait deviner une forme de réponse.

L'écran sonde donc toutes les trois secondes, **lit `X-RateLimit-Remaining` et
s'espace de lui-même sous 10 %** — c'est précisément là que l'auto-régulation du
socle sert. Au-delà de cinq minutes il cesse de sonder **et le dit**, en proposant
de revenir plus tard (entrée 28) : un téléphone qui sonde une heure en
arrière-plan vide une batterie et un forfait sans rien apprendre.

**Pour lever** : publier l'adresse d'authentification de diffusion et la forme des
événements. Le client n'aurait alors même pas besoin de lire la charge utile — un
événement quelconque suffirait à déclencher une relecture, ce qui est de toute
façon le rattrapage exigé après une coupure de socket.

## 127. Ce que le parcours M2 demande au socle

Cinq demandes, aucune contournée. Le socle est en lecture seule ; ce qui suit est
signalé, pas modifié.

**1. `VerificationTile` appartient au socle.** Elle est employée par l'accueil
**et** par « Mes vérifications », c'est-à-dire par deux dossiers `features/`.
M2 possède les deux, mais la règle de propriété est claire : un composant partagé
appartient au socle. Elle vit pour l'instant dans `features/verify/widgets/` et
l'accueil l'importe — la seule dépendance entre les deux dossiers de ce parcours,
et elle est écrite ici pour être vue.

**2. Le solde d'unités appartient au socle.** `VfPointsPill` y est déjà ; la
source de sa valeur, non. M2 tient un `soldeUnitesProvider` — alimenté par
`compteurs.unites` et par `meta.solde`, jamais par le cache, et sans aucune
méthode de débit — et M5 tiendra le sien, alimenté par `GET /me/wallet`. C'est
exactement la duplication que la règle 3 veut éviter, et les deux finiront par
diverger.

**3. Le sélecteur de fichiers manque pour l'audio.** `image_picker` sait ouvrir
une photo et une vidéo ; il ne sait **ni ouvrir un fichier audio, ni enregistrer
une note vocale**. Le type `audio` est pourtant servi par l'API, avec son plafond
de 25 Mo. L'écran le **dit** plutôt que d'ouvrir un sélecteur qui n'existe pas —
un bouton qui n'ouvre rien est pire qu'une absence — mais c'est une capacité du
produit qui manque. Il faut `file_picker` (et de quoi enregistrer) au
`pubspec.yaml`, qui appartient au socle.

**4. Le réessai automatique de Riverpod, pour les cinq parcours.** M5 l'a signalé
et désactivé chez lui (entrée 115) ; M2 fait de même, avec sa propre fonction — la
constante de M5 vit dans son dossier, et l'importer serait un import croisé. Deux
parcours qui écrivent la même ligne pour la même raison, c'est un réglage qui
manque au socle.

**5. Les drapeaux de fonctionnalité devraient vivre dans `AppConfig`.** M2 en a
deux — les notifications et le retour d'utilité — et les lit par
`bool.fromEnvironment`, qui est bien de la configuration mais pas celle du socle.
Le jour où ces endpoints existeront, la bonne façon de lever le drapeau est de le
remonter à côté de `VF_DEV_SESSION`.

**Une observation, sans demande.** Deux fichiers de M2 n'entrent pas dans le
nommage annoncé par la section 8.3 : `domain/entities/home_tableau_de_bord.dart`
et `domain/repositories/dashboard_repository.dart`. La liste ne prévoyait de nom
ni pour l'agrégat d'accueil ni pour son dépôt ; les deux appartiennent sans
ambiguïté à M2, et le préfixe `home_` suit le découpage des dossiers.


## 128. Les accents rendus aux libellés — et les trois pièges de la substitution

Le socle a écrit ses chaînes sans accents — « Verifier », « Deconnexion »,
« Recompenses » — et les cinq parcours s'y sont alignés, ce qui était le bon
réflexe : mélanger deux orthographes à l'écran aurait été pire. Mais le
simulateur, lui, les porte toutes : treize « Vérifier », dix « Communauté »,
sept « Réglages ». C'était donc un **écart de transposition**, pas une
convention — et sur un produit béninois francophone dont le métier est la
rigueur, « Recompenses » se lit comme un bug.

**545 chaînes dans 123 fichiers** ont été corrigées, `lib/` et `test/` ensemble.

### Ce qui n'a PAS été corrigé, et pourquoi

**Les mots ambigus.** J'ai d'abord voulu dériver les corrections des maquettes
en rapprochant chaque mot de sa forme accentuée. La méthode trouve les bons
mots — et vingt pièges : « affiche » deviendrait « affiché », « copie »
deviendrait « copié », « base » deviendrait « basé ». Ces mots existent dans les
deux formes et **seule la phrase tranche**. Ils sont donc comptés, signalés, et
laissés en l'état. Une machine ne peut pas les traiter ; l'honnêteté est de le
dire plutôt que de deviner.

**Les jetons.** Un libellé montré à quelqu'un contient un espace ou commence par
une majuscule. Tout le reste — un seul mot, tout en minuscules — est un jeton :
nom de route, segment d'URL, valeur d'énumération envoyée au serveur, clé de
cache. Aucun n'a bougé.

### Les trois pièges, tous rencontrés pour de vrai

Ils méritent d'être écrits, parce qu'ils se reproduiront à la prochaine passe
mécanique sur des chaînes Dart :

1. **Les interpolations portent du code.** `'${texte.detail}'` contient un
   identifiant, pas un mot. Première tentative : `texte.détail` — **vingt-et-un
   fichiers ont cessé de compiler**. Celui-là, au moins, l'analyseur le voit.

2. **Une chaîne mal découpée cache une interpolation ouverte.**
   `'forum:threads:${categorie ?? ''}'` se lit naïvement comme la chaîne
   `forum:threads:${categorie ?? ` : l'accolade n'est jamais fermée, le masque
   ne s'applique pas, et l'identifiant se fait accentuer.

3. **Le plus grave, et le seul invisible.** L'ordre des opérations compte :
   juger *avant* de masquer met la règle en défaut, parce que les majuscules
   logées dans l'interpolation font passer un chemin pour un libellé.
   `path: '${VfRoutes.hub}/opportunites/:slug'` est devenu `'opportunités'` —
   **la navigation cassait, `flutter analyze` ne disait rien**, et seul un test
   de lien profond l'a rattrapé.

Le troisième est la vraie leçon de cette passe : sur des chaînes, le compilateur
ne protège de rien. Ce qui a protégé, ce sont les 411 tests — et le fait d'avoir
copié `lib/` et `test/` avant d'écrire, ce qui a permis de repartir du propre
trois fois plutôt que de rafistoler.

### Trois asymétries corrigées à la main

Une correction ne vaut que si elle atteint les deux côtés. Trois cas ne le
pouvaient pas :

- `'Verif All Mobile · ${version.value ?? '...'}'` — piège n°2 : la chaîne était
  écartée côté `lib/`, corrigée côté test. La marque prend son accent.
- `find.text('CATEGORIE INEDITE')` — l'entrée du test était accentuée, mais la
  forme tout en capitales n'était pas dans la table. L'attente suit.
- `find.text('ecran unites')` — un décor de test rendu par `Text('ecran $nom')`
  où `nom` est un **nom de route**, correctement laissé nu. L'attente revient à
  la forme sans accent : ici, c'est la forme juste.

Le script est conservé sous `tools/conformite/accents-flutter.py`, avec les
trois pièges commentés à l'endroit exact où ils frappent.

---

## 129. Le bandeau « Données du `<date>` » — trois versions, une seule au socle

Trois parcours avaient écrit le même bandeau d'horodatage : `BandeauFraicheur`
(M4), `BandeauDonneesDatees` (M3), et `AgeDuCache.bandeau` — une phrase, pas un
widget — chez M2. Le socle le doit à **cinq** écrans (05-HORS-LIGNE §5.4), donc
il lui appartient : `VfStaleDataBanner`.

**Ce que la fusion garde des deux, au lieu de choisir la plus complète :**

| Divergence | M3 | M4 | Ce qui est retenu |
|---|---|---|---|
| La reprise | absente — ses écrans se rafraîchissent en tirant vers le bas | « un bandeau qui constate sans offrir de sortie est une impasse » | `onRetry` **facultatif**. Deux boutons « Réessayer » pour le même geste inviteraient à se demander lequel marche |
| Le ton | discret : filet gris, nuage barré | appuyé : fond ambré, icône d'historique | **Les deux**, sous `VfStaleTone`. Le ton suit ce que la donnée périmée risque de faire croire, pas une préférence d'écran |

Le ton appuyé n'est pas décoratif, et M4 avait écrit pourquoi : « sur le Hub, le
cache vit quinze minutes parce que le contenu porte une **échéance**, et le
client n'a pas le droit de la recalculer. La date d'extraction est la seule chose
qui rende le compte à rebours affiché honnête. » Un bandeau discret y serait
insuffisant ; un bandeau ambré sur un catalogue de cours alarmerait pour rien.
Chaque parcours garde donc le ton qu'il avait : aucun écran ne change d'aspect.

**Deux mots ont changé, et c'est une correction.** Le libellé est désormais
« **Données** du 20/08/2026 **à** 09:30, actualisation impossible. » Les trois
versions écrivaient « Donnees » : la passe d'accents (entrée 128) ne portait pas
ce mot dans sa table, et « à » avait été laissé au titre des mots ambigus. Sur un
bandeau qui existe pour dire la vérité d'une date, l'orthographe n'est pas un
détail.

**Et l'année est désormais toujours là.** M2 écrivait `20/08 à 09:30`, sans elle :
un cache de six mois se serait lu comme un cache du mois dernier.

---

## 130. Dates, durées et tailles — fusionnées, et les DEUX anciennetés conservées

`LmsFormats` (M3), `DatesM4` (M4) et `AgeDuCache` (M2) faisaient le même travail.
Elles vivent dans `core/format/vf_formats.dart`, sans ajouter `intl` : le produit
n'a qu'une langue, et six fonctions couvrent le besoin des cinq parcours.

**Ce qui aurait été perdu en gardant « la plus complète ».** M4 et M2 avaient
chacun une ancienneté, et elles diffèrent sur un point : au-delà d'une semaine,
celle de M4 bascule sur une date, celle de M2 continue de compter les jours. Ce
ne sont pas deux versions de la même fonction, ce sont deux questions :

- « quand ce message a-t-il été écrit ? » — « il y a 43 jours » n'aide personne
  à situer un message ; une date, si. C'est `anciennete()` ;
- « à quand remonte ma dernière synchronisation ? » — c'est la **durée** qui dit
  s'il faut se méfier de ce qu'on lit, et une date ne le dit pas. C'est `age()`.

Les deux sont au socle, avec la raison de chacune. Un test les compare sur la
même date et vérifie qu'elles ne rendent pas la même chose.

---

## 131. La correspondance verdict → ton — une seule, et c'est le serveur qui tranche

M2 (`TonDuVerdict`, entrée 118) et M4 (`VerdictM4`, entrée 92) l'avaient écrite
chacun de son côté, et le socle les y invitait — il confiait la correspondance à
M2 « en un seul endroit », sans prévoir que M4 afficherait lui aussi des verdicts,
sur chaque brève du Hub. Elle est désormais `VfVerdictTones.pour`, à côté de
`VfVerdictTone`.

**Elles divergeaient sur `unverifiable`, et il fallait regarder pourquoi.**

M4 lui donnait le gris neutre, avec un argument juste : « *invérifiable* est un
verdict rendu, pas une analyse en attente ; les confondre laisserait croire qu'un
résultat va encore arriver ».

C'est pourtant le **bleu** qui est retenu, parce que le serveur le publie :
`VerdictFactCheck::couleurBarre()` rend `#2563EB` pour `NonVerifiable`, et
`badge()` rend `vbadge-progress`. Ce n'est pas une approximation de sa part — « non
vérifiable n'est pas un échec, c'est un résultat honnête quand les preuves
manquent », et le bleu dit *information* là où le vert dirait *tout va bien* et le
rouge *intox*.

Ce qui sépare les deux cas à l'écran, c'est le **libellé** — « Non vérifiable »
contre « En cours » — et il vient du serveur (`verdict_libelle`) : le client ne le
fabrique jamais. La crainte de M4 est levée par le contrat lui-même. M2 garde ce
qui lui est propre : le couple `ValeurLibelle`, le ton d'un **statut** d'analyse,
et la couleur de la barre de fiabilité.

**Un écran change d'aspect** : une brève du Hub dont le verdict est
`unverifiable` passe du gris au bleu. C'est la correction, pas un effet de bord.

---

## 132. Le réessai automatique de Riverpod — tranché au conteneur

Riverpod 3 rejoue de lui-même un provider en échec, avec un recul exponentiel.
M5 l'avait désactivé chez lui (entrée 115), M2 aussi, avec sa propre fonction
(entrée 127.4) — deux parcours écrivant la même ligne pour la même raison.
**M1, M3 et M4 ne l'avaient pas désactivé** : trois parcours rejouaient donc en
silence pendant que deux s'en gardaient. C'était l'incohérence, pas la ligne, qui
posait problème.

**Décidé** : `sansReessaiAutomatique` vit au socle
(`core/state/riverpod_retry.dart`) et il est posé **une fois**, sur le
`ProviderContainer` de la racine de composition. Les providers de M2 et M5 le
citent encore là où le comportement compte, mais ils ne le réécrivent plus.

**La doc du socle disait « une seule tentative », et le code disait autre chose.**
La phrase est vraie de la couche réseau — l'intercepteur de réessai (trois
tentatives, sur les seuls échecs de transport et les `502/503/504`) et
l'intercepteur de débit (« attendre `meta.retry_after`, un seul essai ensuite »).
Elle ne l'était pas des providers. Elle l'est maintenant partout, et un test
verrouille la politique.

*Note de type* : `Retry` n'est exporté ni par `flutter_riverpod`, ni par
`package:riverpod/misc.dart`. La signature est écrite en clair ; elle lui est
structurellement identique.

---

## 133. `VfButton.expanded` ne peut plus forcer une largeur infinie

**Le défaut.** `expanded` valait `true` par défaut — le bon défaut — mais il
posait `width: double.infinity` sans regarder les contraintes reçues. Deux de ces
boutons dans une `Row` produisaient une mise en page **infinie** : plantage à
l'exécution, **invisible à `flutter analyze`**. M3 l'a rencontré et l'a contourné
par des `Expanded` (entrée 105).

**La cause est corrigée, pas les symptômes.** `expanded` signifie désormais
« prends toute la largeur **disponible** » : là où il n'y en a pas de bornée — une
`Row`, une `Wrap`, un `ListView` horizontal — le bouton prend sa largeur
naturelle. Les `Expanded` de M3 continuent de fonctionner à l'identique.

**La première correction était mauvaise, et les tests l'ont dit.** Elle employait
un `LayoutBuilder` : il donne la bonne réponse, mais **il interdit les dimensions
intrinsèques** à tout ce qui l'entoure. L'écran de connexion de M1 pose un
`IntrinsicHeight` autour de ses boutons, et **quatorze tests sont tombés d'un
coup**. La correction retenue est un `RenderProxyBox` : il délègue ses
intrinsèques à son enfant et ne décide que d'une chose — resserrer la largeur
quand elle est bornée.

Un test met deux boutons pleine largeur dans une `Row` **sans `Expanded`** et
vérifie qu'aucune exception n'est levée ; un second remonte l'`IntrinsicHeight`
qui avait piégé la première tentative.

**Recensement du piège latent** : aucun autre parcours n'avait posé deux boutons
pleine largeur dans une `Row` sans `Expanded` — le défaut n'était donc pas visible
ailleurs. Il l'aurait été à la première mise en page à deux boutons.

---

## 134. `VfCover` accepte une hauteur de maquette

Le composant n'exposait que 112 et 86. M3 demandait 140 (fiche de cours) et 150
(bloc vidéo d'une leçon), M4 120 (carte à la une) et 130 (en-tête d'article).
M3 avait enveloppé le composant dans un `SizedBox`, M4 avait **assumé un écart de
8 à 18 points**. Deux parcours, donc le socle : `VfCover.height`.

`height` ne remplace pas `brief` : la variante brève est un **état nommé** du
design system, la hauteur libre est une **mesure de maquette**. Les mélanger
aurait fait disparaître la première.

Conséquence : les deux couvertures de M4 prennent enfin la hauteur de leur
maquette. L'écart assumé est levé, il n'est plus assumé.

---

## 135. Le solde d'unités — une seule source pour une seule pastille

`VfPointsPill` était au socle ; **la source de sa valeur, non**. Quatre écrans
l'alimentaient de quatre façons, et la même pastille affichait donc quatre choses
selon l'onglet :

| Écran | Ce qu'il donnait | Ce que ça valait |
|---|---|---|
| Tableau de bord, Vérifier, Mes vérifications (M2) | `soldeUnitesProvider` | juste |
| Catalogue (M3) | `session.utilisateur.unites` | le solde **à l'émission du jeton** — périmé dès la seconde suivante |
| Hub (M4) | `null`, en dur | un tiret, toujours |
| Mon profil (M5) | l'état du profil | juste, mais séparé |

C'est le troisième signe qu'un composant appartient au socle : **s'il divergeait
entre deux parcours, l'utilisateur le verrait**. Il le voyait.

Le provider de M2 — le mieux raisonné : jamais de cache, aucune méthode de débit —
est monté au socle (`core/session/solde_unites.dart`). Il est alimenté par les
seules lectures réseau : `compteurs.unites` de l'agrégat d'accueil, `meta.solde`
après une soumission, `GET /me`, `GET /me/wallet`, et le résumé rendu **avec le
jeton** — qui est lui aussi une lecture serveur, à l'instant de l'émission.
`AuthController` l'**oublie** à la déconnexion : un solde qui survivrait à la
purge s'afficherait au compte suivant sur un téléphone partagé.

---

## 136. Les deux `403` de COMPTE se traitent au routeur, plus écran par écran

Le contrat distingue trois `403` par leur code métier, et deux décrivent l'état du
**compte**, pas celui de la requête :

- `compte_en_suppression` — « **toute** route répond ainsi, sauf `GET /me` et
  `POST /auth/logout` » (§1.7). Ce n'est pas une erreur d'écran, c'est une
  destination, et elle vaut pour les trente écrans à la fois ;
- `compte_inactif` — le jeton est valable, l'adresse n'a jamais été prouvée. La
  réponse est partout la même : reprendre la saisie du code.

Tant qu'ils n'étaient pas dans l'intercepteur, l'information n'entrait dans
l'application que par `GET /me`, appelé par le seul parcours du compte : un écran
de l'Académie ou du forum qui essuyait ce `403` affichait un **échec ordinaire**.
M1 et M5 l'ont tous deux signalé (entrées 116 et suivantes).

**Ce qui a été refusé au passage** : fabriquer un `DateTime.now()` pour remplir
`suppressionDemandeeLe`. Le `403` dit *que* le compte est en suppression, pas
*depuis quand* — et un délai de rétractation faux serait pire que pas de délai.
La session porte donc un booléen distinct, et c'est `GET /me` — qui reste servi —
qui apporte la date.

**Et une impasse évitée** : sans adresse connue — une session restaurée du
stockage sécurisé ne porte pas de profil — la garde ne redirige vers **aucun**
écran de code. Envoyer quelqu'un saisir un code sans savoir où il a été envoyé
n'est pas une aide.

Le **troisième** `403`, `droit_manquant`, reste ce qu'il est : un refus qui
appartient à l'écran, parce que lui seul sait dire ce que la personne peut faire à
la place. Un test le vérifie, et un autre vérifie qu'un `403` **sans** code métier
— celui d'un mandataire d'infrastructure — ne ferme rien du tout.

---

## 137. Le tiroir a enfin un hôte, et la déconnexion un seul point d'entrée

`VfDrawer` — la coque — était au socle depuis le début, mais sans hôte : rien ne
l'ouvrait. Trois écrans posent un bouton de tiroir dans le simulateur — tableau de
bord (07), forum (20), Mon profil (25) — et aucun des trois ne pouvait s'en
servir. M4 avait retiré le bouton plutôt que d'en poser un qui n'ouvre rien
(entrée 92) ; c'était le bon réflexe, et c'est exactement ce que cette entrée
lève.

**Pourquoi le socle assemble le menu, alors que `VfDrawer` disait le contraire.**
Sa réserve — « les câbler ici forcerait le socle à connaître les cinq parcours » —
ne tenait plus : `VfShellScaffold` les connaît **déjà**, il déduit l'onglet
courant du chemin et nomme huit routes des cinq. Et assembler le menu dans l'un
des trois écrans l'aurait dupliqué dans les deux autres.

Les entrées ne sont pas inventées : ce sont **les quatre groupes du §2.5**, mot
pour mot. « Certificats & badges » mène à l'écran 27, qui porte les deux — la
grille de badges **et** la liste « Mes certificats ». Ni rôle ni XP dans l'en-tête
du compte : l'API n'expose le rôle de personne dans ce résumé, et le XP n'est
servi par aucun agrégat que le socle lise (entrées 87 et 122).

**Le bouton du tiroir ne s'ouvre pas avec `Scaffold.of`.** Plusieurs écrans posent
**leur propre** `Scaffold` sous la coque ; `Scaffold.of` depuis leur barre
supérieure aurait trouvé celui-là, qui n'a pas de tiroir — et le bouton n'aurait
rien ouvert, en silence. `VfShellScope` désigne le seul qui en porte un, et
`isInsideShell` permet aux écrans hors coque — rapport, leçon, certificat — de ne
pas poser le bouton du tout.

**Conséquence sur la déconnexion.** Le bouton rouge du tiroir doit fermer la
session, et un composant du socle ne peut pas importer un provider de parcours.
`deconnexionProvider` — « la seule dépendance de M5 vers M1 » — est donc monté au
socle. Son défaut **ferme et purge localement** ; la racine de composition le
surcharge avec `FermerSession`, qui révoque en plus le jeton côté serveur.
Contrairement à `localDataPurgerProvider`, il n'a pas de défaut qui lève : un
défaut qui échoue laisserait quelqu'un enfermé dans une session qu'il veut
quitter. Effet secondaire heureux : M5 n'a plus **aucune** dépendance vers M1.

---

## 138. L'adresse du site public — et les chemins qu'il sert vraiment

M3 affichait l'adresse de vérification publique d'un certificat **sans pouvoir
l'ouvrir** (entrée 104), M5 avait **retiré du menu** la ligne « Politique de
confidentialité » plutôt que de la laisser inerte (entrée 116). Les deux pour la
même raison : `AppConfig` ne portait que l'adresse de l'API, et « composer une
adresse web à partir d'elle reviendrait à inventer une URL de production ».

`VF_WEB_BASE_URL` existe. **Vide par défaut, et rien n'est écrit en dur** : elle
arrive par `--dart-define-from-file`, comme celle de l'API. Vide, tout se comporte
comme avant — l'adresse s'affiche sans être cliquable, la ligne reste absente.
Renseignée, les deux deviennent des liens, en **onglet système**.

**Les chemins ne viennent pas de la maquette.** Le simulateur écrit
`verifall.com/verify-certificate` ; le site sert `/verifier-un-certificat` et
`/confidentialite` (`routes/web.php`, routes nommées `public.verify-certificate`
et `public.privacy`), et la page de vérification accepte `?code=`. Recopier la
maquette aurait produit un lien vers un 404 — sur un certificat, c'est-à-dire à
l'endroit exact où quelqu'un vérifie qu'on ne lui ment pas.

---

## 139. Le bouton WhatsApp — une variante nommée, pas une couleur libre

M4 demandait « une couleur par instance » sur `VfButton` (entrée 92), M5 avait
dessiné son propre bouton vert (entrée 116) : **deux parcours avaient redessiné le
même bouton**, avec le même jeton `VfBrandColors.whatsapp`. La demande était donc
légitime — mais pas sous la forme demandée.

Une couleur libre par instance autoriserait n'importe quel bouton à sortir de la
palette. `VfButtonVariant.whatsapp` et `VfWhatsAppButton` n'autorisent que
celui-ci, et le socle range d'ailleurs cette teinte dans `VfBrandColors` et non
dans la palette : « ce sont des logos, pas des jetons ».

Chaque parcours garde ce qui lui est propre : le libellé (« Partager sur
WhatsApp » contre « Inviter sur WhatsApp »), l'annonce au lecteur d'écran, et
l'état « Preparation... » du parrainage. Et la cible reste **la feuille de partage
du système** : ouvrir l'application exigerait un schéma d'URL que ni le contrat ni
le simulateur ne fournissent.

**Ce qui est refusé** dans la même demande : la hauteur `.btn-sm` (38) et la
couleur du bouton de vote. Un seul parcours en a besoin, M4 a lui-même gardé la
cible tactile à 44 — « le plancher du chantier, sans exception » —, et son bouton
de vote porte un état `selected` que `VfButton` ne modélise pas. Il reste local.

---

## 140. La purge se disait totale — elle oubliait les médias copiés

`AuthController.signOut` annonce une purge « totale et non sélective », parce
qu'« un tri *ce qui est sensible / ce qui ne l'est pas* se trompe toujours au
moins une fois ». Elle vidait la base — cache de lecture et file d'écriture — et
s'arrêtait là.

Or une soumission mise en file **copie le fichier** dans le stockage privé de
l'application (`<support>/medias/`), et c'est nécessaire : « l'utilisateur peut
supprimer la photo de sa galerie entre-temps », et « le téléversement serveur
expire au bout de deux heures ». Ces copies survivaient à la déconnexion : une
photo, une capture d'écran, un enregistrement d'un compte restait lisible par le
suivant — sur le téléphone partagé que le socle cite lui-même comme cas ordinaire.

M1 l'avait signalé, en notant que cela dépendait des conventions de M2. C'est
précisément pour cela que le **nom du dossier** est désormais déclaré au socle,
à côté du contrat de purge : M2 écrit les copies, le socle les efface, et deux
constantes écrites séparément auraient fini par diverger. La moitié oubliée d'une
purge est une fuite.

*Ce qui n'est pas couvert, et qui est dit* : les **brouillons en mémoire**. Ils ne
sont écrits nulle part — ils vivent dans des providers qui survivent à la
déconnexion. Voir l'entrée 143.

---

## 141. `device_info_plus` — accepté, parce que le point d'injection l'attendait

La demande vient d'un seul parcours, ce qui la rendrait suspecte. Elle est
retenue pour trois raisons :

1. **`pubspec.yaml` appartient au socle** — c'est la définition même d'une demande
   qu'un parcours ne peut pas honorer seul ;
2. elle **ferme un point de la définition de fini de M1** : « `appareil` est
   envoyé avec le **modèle réel** du téléphone ». `dart:io` ne connaît que le
   système et sa version ;
3. **le coût est nul** : M1 avait laissé le point d'injection en place et écrit
   ce qu'il attendait — « c'est le point d'injection qui accueillera
   `device_info_plus` le jour où le socle le déclarera ». Aucune ligne de M1 ne
   change.

La lecture est **asynchrone** : elle se fait au démarrage, dans la racine de
composition, exactement comme la version du client. Rendre `NomAppareil.valeur()`
asynchrone aurait contaminé la source de données puis le dépôt.

`NomAppareilSysteme` reste le repli quand la plateforme ne dit rien : moins
précis, jamais faux. Et **aucun nom de modèle n'est fabriqué** — un « Samsung
Galaxy » par défaut ferait révoquer le mauvais appareil.

---

## 142. `riverpod` était une dépendance transitive — il est déclaré

`Override` et les types de famille (`AsyncNotifierProviderFamily`…) ne sont
exportés ni par `flutter_riverpod`, ni par `riverpod.dart` : ils vivent dans
`package:riverpod/misc.dart`, un paquet que le dépôt utilisait **sans le
déclarer**. M5 s'en passait par inférence de type à deux endroits, et la racine de
composition laissait sa liste d'`overrides` non typée avec un commentaire
d'excuse.

`riverpod: ^3.4.2` — la version que `flutter_riverpod` résout déjà — est déclaré.
Dépendre d'un paquet transitif est ce que `depend_on_referenced_packages` reproche
à juste titre : le jour où `flutter_riverpod` change de dépendance, le code casse
sans qu'aucun fichier ait bougé.

*Une limite qui reste* : le type `Retry` n'est exporté par **aucun** des deux. La
politique de réessai du socle (entrée 132) l'écrit donc en clair.

---

## 143. Ce que la passe d'intégration REFUSE, et ce qu'elle REPORTE

Une file d'attente ne se vide pas en disant oui à tout. Ce qui suit a été examiné
et **écarté** — avec sa raison, pour qu'on ne le redemande pas sans la lire.

### Refusé — le parcours peut s'adapter, ou l'a déjà fait

**`VerificationTile` au socle (M2).** Elle est employée par `home/` et `verify/`,
et c'est le seul import croisé du dépôt — M2 l'a écrit pour qu'on le voie, ce qui
était juste. Mais **les deux dossiers appartiennent à M2** (§8.3 les liste
ensemble dans son périmètre d'écriture). La règle 4 interdit les imports entre
deux *parcours*, pas entre deux dossiers d'un même parcours. Et la monter au socle
aurait un coût réel : elle rend une `Verification`, entité de domaine de M2 — le
socle se mettrait à dépendre d'un parcours, ce qui inverse la règle de dépendance
que tout le découpage protège (« ils dépendent tous de l'enveloppe du socle,
jamais l'inverse »). Elle reste où elle est.

**Les drapeaux de fonctionnalité dans `AppConfig` (M2).** `DrapeauxM2` les lit par
`bool.fromEnvironment`, qui **est** la configuration — la même source que
`config/dev.json`. Les monter dans `AppConfig` transformerait deux constantes de
compilation en lectures d'exécution dans les écrans de M2 : c'est réécrire un
parcours pour un gain d'uniformité, sans gain fonctionnel. Les deux clés sont en
revanche **documentées** dans `config/README.md` et `config/exemple.json`, ce qui
répond au vrai risque — qu'on ne sache plus qu'elles existent. À revoir le jour où
les endpoints existent : ce jour-là, la bascule devient une décision de
déploiement, et sa place sera dans `AppConfig`.

**Ouvrir la hiérarchie `Failure` (M5).** Elle est scellée, et c'est structurel :
elle **est** la liste fermée des treize codes d'erreur du contrat, et un `switch`
exhaustif garantit que chaque échec en porte un. L'ouvrir laisserait un parcours
déclarer un échec que l'API ne peut pas produire. M5 le dit lui-même : ses trois
cas — recharge, export et suppression refusés hors ligne — ne sont pas un
contournement, le contrat décrit `action_impossible` comme « l'action n'a pas de
sens en l'état », dont « le message porte l'information utile ». Et
`LocalStorageFailure` montre qu'un échec qui ne traverse rien a déjà sa place.
À rouvrir seulement si un besoin réellement distinct se présente.

**La hauteur et la couleur par instance sur `VfButton` (M4).** Voir l'entrée 139 :
le bouton WhatsApp est monté au socle parce que **deux** parcours l'avaient écrit ;
le bouton de vote reste local parce qu'un seul en a besoin, qu'il porte un état
`selected` que `VfButton` ne modélise pas, et que M4 a lui-même gardé la cible
tactile à 44 plutôt que les 38 du simulateur.

### Reporté — la demande est fondée, la décision n'appartient pas à cette passe

**`file_picker` et l'enregistrement audio (M2).** Le besoin est réel : le contrat
sert le type `audio` avec son plafond de 25 Mo, `image_picker` ne sait ni ouvrir
un fichier audio ni enregistrer une note vocale, et l'écran le **dit** faute de
mieux — ce qui est la bonne dégradation. Mais ce n'est pas une demande au socle
comparable aux autres : ajouter un enregistreur, c'est une **permission
microphone**, une mention RGPD sur ce qu'elle sert, deux déclarations de
plateforme (Android et iOS), et un écran à écrire. Déclarer les paquets sans
écrire l'écran laisserait deux dépendances mortes ; écrire l'écran serait ajouter
une capacité produit au milieu d'une passe d'intégration. **C'est une décision du
porteur**, et elle se prend avec le texte de la demande de permission.

**Les captures de référence (06-TESTS §6.5).** Voir ci-dessous, elles ont leur
propre relevé.

**`integration_test/`.** Idem.

### Le recensement de l'`autoDispose` — ce que la doc fausse a laissé derrière

La §1.3 disait « un `autoDispose` par défaut, une exception motivée ». C'est une
**règle du chantier** ; elle a été lue comme une **description de Riverpod**, et
M3 a rencontré un vrai `UnmountedRefException` en croyant que ses providers se
libéraient seuls. La documentation est corrigée : avec l'API classique de
`flutter_riverpod` 3, un provider n'est **pas** auto-disposé.

Le relevé fait à l'intégration compte **vingt-sept providers d'écran déclarés sans
`.autoDispose`**, répartis sur les cinq parcours : cinq chez M1 (contrôleurs
d'authentification), huit chez M2, quatre chez M3, trois chez M4, six chez M5, et
un déclaré dans une page.

**Ils ne sont pas corrigés ici, et c'est délibéré.** Certains sont voulus et
motivés — le brouillon d'une vérification « doit survivre à une mise en
arrière-plan », le solde vit dans la barre supérieure de six écrans. Les autres
gardent en mémoire l'état d'un écran quitté : une liste du forum, un fil, une
grille de badges. Décider écran par écran lesquels doivent se libérer est un
arbitrage de parcours, pas une correction transverse — et le faire à l'aveugle
changerait le comportement de cinq parcours en une passe.

**Ce qu'il faut savoir en attendant** : la purge de la déconnexion efface le
stockage sécurisé, la base et les médias copiés (entrée 140), mais **pas l'état en
mémoire**. Sur un téléphone partagé, un compte fermé puis un autre ouvert dans la
**même exécution** peut voir, brièvement, une liste chargée par le précédent. Le
cas est étroit — l'application est le plus souvent relancée — mais il est réel, et
il est écrit ici plutôt que découvert.

---

## 144. Captures de référence et tests d'intégration — ce qui manque, exactement

Les cinq parcours ont sauté les deux, tous pour la même raison. Elle est vérifiée,
et elle n'est qu'à moitié vraie.

### Les captures de référence : le blocage n'est pas la licence, c'est le format

`assets/fonts/` ne contient toujours **que son README** : les huit fichiers de
police sont absents, et « un test de capture qui charge une police depuis le
réseau rend des rectangles » (06-TESTS §6.5). La réserve tient.

Mais la raison consignée — « licences à déposer par le porteur » — n'est pas la
bonne. Inter, Instrument Sans et IBM Plex Mono sont sous **SIL OFL 1.1** :
l'embarquement et la redistribution sont expressément permis. Ce qui bloque
réellement est ailleurs, et il y a trois choses :

1. **Le format.** Le dépôt web héberge déjà Inter 400/500/600/700 et Instrument
   Sans 400/500/600/700 — dans `public/assets/css/fonts/files/`, en **`.woff2`**.
   Flutter ne charge ni woff ni woff2 : il lui faut du `.ttf` ou du `.otf`. Les
   fichiers présents ne sont donc **pas** réutilisables tels quels.
2. **IBM Plex Mono n'existe nulle part dans le projet.** Le web ne la déclare pas
   du tout ; le design system mobile l'emploie sur les trois éléments qu'un humain
   doit recopier sans se tromper — référence de vérification, identifiant de
   certificat, code de parrainage.
3. **Instrument Sans ExtraBold (800) n'existe pas non plus.** Le CSS du web fait
   pointer la graisse 800 sur le fichier 700 ; `pubspec.yaml` attend, lui, un
   `InstrumentSans-ExtraBold.ttf` distinct. L'un des deux a tort, et c'est à
   trancher avant de capturer quoi que ce soit.

**Ce qu'il faut, exactement, pour débloquer** : déposer huit `.ttf` dans
`verifall_app_flutter/assets/fonts/` — Inter 400/500/600/700, Instrument Sans 700
et 800, IBM Plex Mono 400 et 600 — puis décommenter le bloc `fonts:` de
`pubspec.yaml`, qui est **déjà écrit** avec les bons chemins et les bonnes
graisses. Ensuite seulement : un `flutter_test_config.dart` qui charge les polices
(`FontLoader`) avant la suite, et une plateforme de référence **unique** pour
produire les fichiers témoins — un rendu de texte diffère entre Windows, macOS et
Linux, et des témoins produits sur un poste échouent sur un autre.

Tant que ces trois points ne sont pas réglés, **toute mesure de fidélité serait
fausse**, et une capture validée à tort est pire que pas de capture.

### `integration_test/` : l'absence n'est pas tenable, et le dossier ne se crée pas à vide

Le dossier n'existe dans aucun parcours, et `integration_test` n'est même pas
déclaré en dépendance de développement.

**Ce qui est déjà couvert, et qu'il ne faut pas recompter.** Les cinq parcours ont
écrit des tests de parcours au niveau widget — `parcours_m1_test.dart`,
`parcours_m2_test.dart`, `parcours_m4_test.dart` — qui montent l'application avec
des doublures de réseau et suivent le chemin de bout en bout. La logique de
parcours est donc éprouvée, y compris hors ligne, y compris l'idempotence.

**Ce que seul un appareil peut prouver**, et qui reste donc non vérifié :

- le **mode avion** réel, avec la bascule de connectivité du système ;
- l'**application tuée puis relancée** pendant une analyse en cours ;
- le **lien profond application fermée**, qui passe par le système d'exploitation ;
- la **feuille de partage** et l'ouverture en onglet système ;
- le **retour d'application** après un paiement Mobile Money
  (`AppLifecycleState.resumed`), qui est le mécanisme même de la relecture du
  solde ;
- le **stockage sécurisé** réel, et la purge des médias sur un vrai système de
  fichiers.

**Ce qu'il faudrait pour le créer utilement** : `integration_test` en
`dev_dependencies`, un émulateur ou un appareil, **un serveur de recette joignable
avec des comptes semés**, et une décision sur la façon dont les tests obtiennent
une session — la surcharge de développement du socle existe pour cela.

Aucun de ces quatre n'est disponible ici. Créer le dossier maintenant produirait
des tests qui ne peuvent pas s'exécuter, c'est-à-dire l'équivalent du bouton qui
n'ouvre rien — et une case cochée qui ne prouve rien est exactement ce que la
onzième règle du chantier reproche à une suite verte.

**Conclusion** : l'absence n'est **pas** tenable pour une livraison, mais elle
l'est pour cette passe. Elle est inscrite ici comme un manque nommé, pas comme un
oubli.

---

## 145. Qui porte le verdict corrigé — les DEUX, donc une table polymorphe

La consigne demandait de vérifier « lequel porte réellement le verdict publié ».
La réponse n'est pas celle qu'on attend, et elle change la forme de la table.

**`HubArticle` porte le verdict PUBLIÉ.** C'est lui que sert
`/actualites/{slug}`, lui que `Referencement::pourFaitVerifie()` transforme en
`ClaimReview`, donc lui que liront les panneaux de fact-checking de Google, Meta
et TikTok. C'est le verdict que le public voit.

**`VerifiedFact` porte le verdict SERVI.** Quand l'équipe a tranché, aucun
moteur n'est consulté : le rapport rendu au citoyen reprend ce verdict-là, à
100 % de confiance. Il n'a pas d'URL publique, mais il sort dans un rapport —
et un rapport se partage.

Corriger l'un en taisant l'autre laisserait la moitié de l'histoire dans
l'ombre. D'où `verdict_revisions` polymorphe (`revisable_type` /
`revisable_id`), et non deux colonnes de clé étrangère.

**La consigne indiquait `app/Livewire/Staff/FaitsVerifies.php` comme l'endroit
où `HubArticle` est modifié aujourd'hui. C'est inexact, et l'écart mérite
d'être écrit** : cet écran gère `VerifiedFact`. Un relevé du dépôt le confirme —
`HubArticle` n'apparaît dans aucun composant du back-office
(`Seo`, `HubController`, `ArticleHubResource`, `DepotPlanDuSiteEloquent`,
`Citizen\ArticleHub`, `Citizen\Forum`, `Citizen\HubInfos`,
`Public\ActualitesPubliques`, `Public\ArticlePublic`, et le modèle). Les brèves
du Hub sont **semées**, jamais éditées par un écran.

Conséquence pratique : le seul chemin de code où un verdict change réellement
aujourd'hui est `GestionDesFaitsVerifies::mettreAJour()`, et c'est là que le
journal est branché. La table accepte déjà `HubArticle` ; le jour où un écran
d'édition du Hub existera, il n'aura qu'à appeler
`JournalDesCorrections::consignerSiLeVerdictAChange()`. Rien d'autre à écrire.

**Le câblage, sans toucher aux fournisseurs.** `app/Providers/*.php` est
verrouillé pendant le chantier parallèle, et sans binding le contrat
`DepotCorrections` n'est pas résoluble — toutes les pages publiques de
vérification tomberaient, la garantie de transparence faisant tomber le site
qu'elle sert. L'implémentation par défaut est donc déclarée sur l'interface
elle-même, par `#[Bind(DepotCorrectionsEloquent::class)]`, le mécanisme prévu
par le conteneur pour cela. N'importe quel `bind()` explicite l'écrase :
l'intégration peut ajouter la ligne habituelle dans `AppServiceProvider` pour
l'uniformité des six autres dépôts, elle prendra le pas sans rien casser.

---

## 146. La raison d'une correction est refusée vide — et le verdict avec

Une raison optionnelle est le piège que le code de conduite de l'IFCN vise
précisément. Une correction sans motif dit qu'on a changé d'avis ; elle ne dit
pas ce qu'on a appris, et le lecteur qui avait partagé l'ancien verdict n'a
toujours aucun moyen de savoir s'il a eu tort de le croire.

L'obligation est posée **quatre fois**, et chaque niveau couvre ce que le
précédent laisse passer :

1. **L'écran** (`Livewire\Staff\FaitsVerifies`) : `required|min:20` dès que le
   verdict sélectionné diffère de celui qui était en base. Vingt caractères,
   parce qu'en dessous on n'écrit pas ce qu'on a appris, on écrit « erreur ».
   Un message sous le champ vaut toujours mieux qu'une exception.
2. **Le service** (`JournalDesCorrections::consigner()`) lève
   `RaisonDeCorrectionManquante` sur une raison vide **ou faite d'espaces**.
3. **La transaction** : l'écriture du verdict et celle de la correction sont
   dans le même `DB::transaction()`. L'exception annule les deux — on n'obtient
   jamais un verdict corrigé sans son entrée au journal. C'est le point
   important : sans cela, le refus serait un message, pas une garantie.
4. **La base** : `raison` est `NOT NULL`, plus une contrainte
   `CHECK (TRIM(raison) <> '')`.

**Ce que le quatrième niveau ne couvre pas, et pourquoi.** La contrainte `CHECK`
n'est posée que sur MySQL/MariaDB et PostgreSQL : SQLite ne connaît pas
`ALTER TABLE … ADD CONSTRAINT`, et l'y obtenir imposerait de réécrire la
création complète de la table en SQL brut — on perdrait les conventions de
nommage de Laravel pour couvrir un moteur qui ne sert qu'aux tests. Le modèle
`VerdictRevision` porte donc le même invariant dans un `saving`, qui lui vaut
sur tous les pilotes. C'est ce garde-fou que le test « refuse une raison vide
jusque dans le modèle » exerce.

**Ce qui n'est PAS journalisé, délibérément** : le premier verdict rendu sur une
affirmation. Publier n'est pas corriger. Consigner les publications ferait de
`/corrections` un journal des parutions, que personne ne lirait — et une page
que personne ne lit ne prouve rien.

---

## 147. Trois pages qui ne sont pas au prototype — comment elles sont écrites

`/corrections`, `/methodologie` et `/financement` n'existent dans aucune des 106
pages de `web/`. La règle « référence visuelle absolue » ne dit rien de ce
qu'elle ne contient pas ; il fallait trancher comment les habiller.

**Décision : leur structure est copiée sur `facts.html` et
`mentions-legales.html`** — héros gris `bg-[#f8f9fa] pt-36 pb-20`, colonne
`max-w-3xl`, cartes `bg-white rounded-xl shadow-card border border-line p-6`.
Elles appartiennent au site plutôt que d'y être posées.

**Chaque classe employée a été vérifiée présente** dans le build figé
(`tailwind.css` + `verifall.css` + `verifall-app.css`), une par une. Trois
manquaient et **elles seules** vivent dans `public/assets/css/pages/transparence.css` :
`line-through` (la pastille de l'ancien verdict, barrée), les puces `list-disc`
et la numérotation des étapes du pipeline, faite au compteur CSS pour qu'une
étape ajoutée à `EtapePipeline` renumérote la page toute seule.

**Racine `<div>` et non `<main>`.** Le gabarit public ouvre déjà
`<main id="contenu">` autour du contenu ; les pages existantes en ouvrent un
second à l'intérieur, ce qui duplique l'identifiant. On ne reproduit pas le
défaut sur des pages neuves — l'effet visuel est nul, la validité y gagne.

**Aucun chiffre n'est écrit en dur sur `/methodologie`.** Les six étapes
viennent de `EtapePipeline`, les douze poids de `config/factcheck.weights` via
`MoteurAnalyse::poids()`, les seuils de `config/factcheck.decision`. Les tests
le prouvent en modifiant la configuration et en vérifiant que la page suit. Une
page de méthodologie rédigée à la main devient fausse au premier changement de
pondération, et rien ne casse pour le signaler.

**La page annonce que les moteurs sont simulés** tant que
`factcheck.fake_adapters` est vrai. Le taire sur la page qui promet la méthode
serait le seul mensonge vraiment grave possible ici.

**Aucune des trois ne revendique d'accréditation.** Elles écrivent que Vérif'All
n'est pas signataire du code de conduite de l'IFCN à ce jour. Ces pages
**préparent** une candidature ; les présenter comme un label serait une fausse
déclaration — et la première qu'un vérificateur irait vérifier.

---

## 148. `/financement` — un seul montant est publiable, et c'est 800 €

La consigne nomme dix soutiens. Le dépôt n'en chiffre **qu'un** :

> `database/seeders/ActualitesVerifallSeeder.php` — « Rang — 2ᵉ prix, doté de
> 800 € », « Financement du prix — Fonds de dotation Demain ».

Tout le reste est établi sans montant : la Fondation de France
(soutien au projet et à son module pédagogique), le Prix WSIS 2026 de l'UIT
(distinction, aucune dotation dans nos sources), et les soutiens du lancement —
AIESEC, U-Report Porto-Novo, CEMAAC Bénin, Association Mairie des Jeunes du
Bénin, JESPD — plus les partenaires affichés au carrousel de l'accueil : OFAJ,
RYCO, fondation EVZ, Aix-Marseille Université.

**Ils sont donc nommés sans montant, et la page dit qu'elle le fait
exprès.** Un budget plausible aurait été plus impressionnant et faux ; sur une
plateforme de fact-checking, c'est la faute qui coûte le plus cher.

Le test garde la règle : il compte les occurrences du signe `€` dans la page
rendue et exige qu'il n'y en ait **qu'une**. Toute somme ajoutée sans source
fait tomber la suite.

Deux mentions méritaient d'être justifiées plutôt que tues :

- **La vente de packs d'unités** est le modèle économique du produit. La page
  l'annonce, et précise qu'aucun encaissement n'est en service —
  `config('economy.momo.driver')` vaut `fake` tant qu'aucun contrat opérateur
  n'est signé. Taire une source de revenus prévue serait exactement le reproche
  qu'une page de financement existe pour éviter.
- **L'absence de régie publicitaire** est vérifiable dans le code :
  `MarketingCampaign` n'a ni annonceur ni facturation, ce sont des annonces de
  la plateforme elle-même.

Ce que la page reconnaît ne pas dire : il n'y a **pas de budget annuel ni de
répartition par origine**. Ce n'est pas caché, ce n'est pas établi — et elle
l'écrit.

---

## 149. L'auteur d'une correction est conservé, jamais affiché

`verdict_revisions.auteur_id` référence l'utilisateur qui a corrigé. Il n'est
**pas** rendu sur `/corrections`.

Deux raisons, qui vont dans le même sens. La première tient au RGPD : nommer
une personne sur une page publique et indexable expose une donnée personnelle
sans nécessité — la correction engage la rédaction, pas un individu. La seconde
est éditoriale : `AuditLogService` conserve déjà l'imputabilité interne, et
c'est là qu'elle doit vivre.

La colonne est en `nullOnDelete` : l'effacement d'un compte, au titre du droit
à l'oubli, ne doit jamais faire disparaître une correction publiée. La ligne
survit sans son auteur, ce qui est le bon compromis — l'inverse
(une correction supprimée avec son auteur) trouerait le journal.

**Même logique pour l'affirmation.** Elle est recopiée dans la table au moment
de la correction plutôt que lue par la relation. Un contenu supprimé ou réécrit
plus tard laisserait sinon une ligne de journal qui renvoie à rien — pas une
trace, un trou.

---

## 150. Le format de la carte de démenti — 1200 × 630, et non 1080 × 1080

Les deux formats se défendent, et ils ne servent pas le même moment.

Le **carré 1080 × 1080** est meilleur DANS le fil WhatsApp : il occupe plus de
hauteur dans la bulle, donc il se lit de plus loin et il résiste mieux au
défilement. C'est le format des cartes de fact-checking qu'on voit circuler.

Le **1200 × 630** est celui de l'`og:image`, et c'est là que le carré perd :
tout ce qui consomme une `og:image` RECADRE. Twitter/X en 2:1, les vignettes de
prévisualisation en 1.91:1, plusieurs messageries en bandeau. Un carré recadré
au centre perd ses bandes haute et basse — c'est-à-dire, sur cette carte-ci, le
**verdict** et la **date**. Exactement les deux choses qui la justifient.

**Tranché : 1200 × 630, une seule image pour les deux usages.**

Trois raisons, dans l'ordre :

1. Une carte qui perd son verdict au recadrage est pire qu'une carte un peu
   moins haute dans le fil. Le pire cas du carré est catastrophique ; le pire
   cas du 1200 × 630 est « un peu petit ».
2. Deux formats voudraient dire deux rendus, deux fichiers, deux colonnes et
   deux chemins d'invalidation — pour un gain qui ne se voit que dans un seul
   des deux canaux.
3. Le dessin compense la perte de hauteur là où elle se paie : le verdict est
   écrit en 104 points sur une bande pleine de 200 pixels. À la largeur d'une
   bulle de conversation, ce mot reste lisible.

**Réserve honnête** : si la mesure d'usage montrait un jour que la carte est
massivement renvoyée en pièce jointe plutôt que collée en lien, le carré
redeviendrait le bon choix. Le format est une constante de classe, pas une
valeur dispersée : le changement serait local.

---

## 151. Le mot du verdict sur la carte — l'enum, jamais « EN COURS »

Deux vocabulaires disent le verdict dans le produit, et l'arbitrage 44 les
assume tous les deux : `VerdictFactCheck::libelle()` rend *Vrai / Faux /
Trompeur / Non vérifiable*, tandis que `HubArticle::libelleDuBandeau()` rend le
registre du Hub — *VÉRIFIÉ / DÉMENTI / TROMPEUR / EN COURS*. La page publique
d'un fait vérifié affiche le second (« VERDICT : DÉMENTI »).

La carte affiche **le premier**, en capitales.

La raison n'est pas esthétique, elle est factuelle : `libelleDuBandeau()` fait
tomber `NonVerifiable` sur **« EN COURS »**. Sur une page, c'est acceptable —
le lecteur a le contexte autour, et une brève sans verdict est bien une brève
en cours de traitement. Sur une **image détachée de tout**, qui circulera seule
pendant des mois, « EN COURS » affirmerait qu'un travail est en train de se
faire, alors que « non vérifiable » dit l'inverse : le travail A ÉTÉ fait, et
les éléments ne permettent pas de trancher. C'est une différence de sens, pas
de ton, et une carte n'a personne pour la rattraper.

S'ajoute une raison plus simple : « FAUX » est le mot que la personne qui reçoit
la rumeur doit lire en un quart de seconde. « DÉMENTI » demande de savoir qui
dément quoi.

Le mot « VERDICT », lui, est repris tel quel de la page — la carte et la page se
répondent. **Aucun texte de verdict n'est écrit dans ce lot** : tout sort de
l'enum, y compris la phrase de raison de repli (`explication()`) quand une brève
n'a pas de chapô.

---

## 152. Trois `.ttf` entrent dans le dépôt — parce que GD ne lit pas le `.woff2`

Le site charge ses polices depuis `public/assets/css/fonts/files/` : neuf
fichiers, tous en `.woff2`. FreeType, que GD emploie pour `imagettftext()`,
n'ouvre que du `.ttf` ou de l'`.otf`. Recherche faite sur l'intégralité du
dépôt, `vendor/` compris : **aucun `.ttf`**.

Les trois issues possibles :

| Issue | Ce qu'elle produit |
|---|---|
| La fonte bitmap intégrée de GD | Cinq pixels de haut, sans accents. Sur une image de 1200 pixels, illisible — et « Vérifié » y perdrait son accent |
| Une police du système | Marche sur ce poste (`C:\Windows\Fonts`), pas sur l'hébergement. Une carte dont la police dépend du serveur est une carte qui change de dessin au déploiement |
| Convertir les `.woff2` du dépôt | Trois fichiers de 67 Ko, versionnés, identiques partout |

**Tranché : la conversion.** Un `.woff2` n'est qu'un `.ttf` compressé ; la
conversion le décompresse sans toucher un seul contour. Les trois graisses
employées (400, 600, 700) vivent dans `resources/fonts/` — et non dans
`public/`, puisqu'aucun navigateur ne les demande jamais.

Inter est sous **SIL Open Font License 1.1**, qui autorise expressément la
redistribution ; le nom de la fonte est inchangé, comme la licence l'exige. La
provenance, la couverture de caractères vérifiée (`É È À Ç Œ « » ’ — …`) et la
commande de reconversion sont consignées dans `resources/fonts/LISEZ-MOI.md`.

**Aucune dépendance Composer ajoutée.** La conversion est un outillage de poste
joué une fois (`fontTools`), pas une brique du produit.

---

## 153. La carte est servie par une ROUTE, jamais par `public/storage`

Le réflexe est d'écrire la carte sur le disque `public` et de pointer
l'`og:image` sur `storage/…`. Il suppose le lien symbolique de
`php artisan storage:link` — qui **n'existe pas ici** (`public/storage` est
absent, vérifié) et qui, sur beaucoup d'hébergements mutualisés, ne peut pas
exister.

Le mode de panne mérite d'être nommé, parce qu'il est silencieux : une
`og:image` en 404 ne casse aucune page. Elle fait simplement disparaître
l'aperçu. Personne ne le voit en recette ; on le découvre en collant un lien
dans une conversation, c'est-à-dire au moment où la carte devait servir.

**Tranché** : `App\Http\Controllers\Public\CarteDeDementiController` lit le
fichier et pose trois en-têtes. Il ne dessine pas — la fabrique dessine, dans la
file. Il ne cherche pas l'article — la liaison de route le fait, par `slug`.

Deux conséquences assumées :

- **404 plutôt qu'une image de remplacement** quand la carte n'existe pas
  (brouillon, verdict absent, file jamais passée). Une carte de démenti
  générique ne dément rien, et prêterait notre marque à un verdict que nous
  n'avons pas rendu.
- **`Cache-Control: public, max-age=86400`, pas un an.** L'URL de la carte est
  stable ; un cache perpétuel figerait un verdict corrigé chez tous les
  intermédiaires, sans moyen de purge. L'`ETag`, lui, porte l'empreinte : un
  navigateur qui revient repart avec un 304.

---

## 154. L'empreinte dans le nom du fichier — contre le cache d'`og:image`

WhatsApp et Facebook gardent une `og:image` en cache **plusieurs jours, par
URL**. C'est le détail qui décide de toute la mécanique de nommage.

Une carte écrite sous un nom fixe (`{uuid}.png`) survivrait à sa correction : le
verdict changerait en base, la page dirait VRAI, et les aperçus continueraient
d'afficher FAUX pendant une semaine — sur le canal où la carte a le plus de
portée. **Une carte périmée est pire que pas de carte** : elle porte notre
marque au service d'une affirmation que nous avons nous-mêmes retirée.

**Tranché** — le nom porte une empreinte de ce que la carte MONTRE :

```
cartes-de-dementi/{uuid}-{12 caractères de sha256}.png
```

L'empreinte couvre le gabarit, le verdict, le titre, le chapô, la date de
publication et le `slug` — rien d'autre. Changer la catégorie d'une brève ne
refabrique pas son image.

Trois effets s'enchaînent, et c'est pour ces trois-là que l'empreinte existe :

1. `estAJour()` compare le chemin attendu au chemin enregistré : mettre en file
   à chaque enregistrement ne coûte rien tant que rien n'a changé ;
2. l'ancienne carte est **effacée** au moment où la nouvelle est écrite ;
3. l'`og:image` est `…/carte.png?v={empreinte}` — la route reste stable pour les
   liens déjà partagés, mais l'URL annoncée change, ce qui force les
   plateformes à relire l'image.

La version du gabarit entre dans l'empreinte pour une raison de maintenance :
sans elle, un changement de dessin ne regénérerait **aucune** carte existante,
et le parc resterait figé sur l'ancien modèle sans que personne s'en aperçoive.

**Poids mesuré** : 27 à 30 Ko par carte, 1200 × 630, ~90 ms de rendu. La
contrainte du lot était « quelques dizaines de kilooctets, pour une 3G » ; le
test la garde à moins de 100 Ko et échouera le jour où une évolution du dessin
la ferait franchir.

---

## 155. Ouvertures et clics de l'infolettre — RETIRÉS, pas remis à zéro

`compte-admin/newsletters.html` affiche quatre indicateurs et six colonnes,
dont **« Taux d'ouverture : 42 % »**, **« Ouvertures »** avec sa barre de
progression, et **« Clics »**.

Aucun de ces trois chiffres n'existe. Les mesurer demande un **pixel de suivi**
— une image d'un pixel chargée depuis nos serveurs à l'ouverture — et une
**réécriture de chaque lien** pour les faire passer par un redirecteur qui
compte. Ni l'un ni l'autre n'est présent, et ni l'un ni l'autre n'est anodin :
tous deux traitent une donnée de comportement, donc relèvent du consentement,
donc de la politique de confidentialité, qui n'en parle pas.

**Décision** : les trois indicateurs sont **remplacés**, pas mis à zéro. Un
« 0 % » se lit « personne n'a ouvert », ce qui est faux ; l'absence se lit
« pas mesuré », ce qui est vrai. À leur place, l'écran montre ce que la base
sait réellement :

| Prototype | Écran livré | Source du chiffre |
|---|---|---|
| Abonnés `12 208` | Abonnés confirmés | `COUNT` des lignes confirmées et non désabonnées |
| Taux d'ouverture `42 %` | En attente de confirmation | `COUNT` des lignes sans `confirmed_at` |
| Désabonnements `0,4 %` | Désabonnés | `COUNT` des lignes avec `unsubscribed_at` |
| Campagnes envoyées `6` | Dernier envoi + son âge | `sent_at` de la dernière édition partie |

Le troisième remplacement est le plus utile des quatre, et il n'était pas au
prototype : **l'âge du dernier envoi**. Un rendez-vous hebdomadaire meurt de
deux lundis manqués, et la panne d'un cron ne fait aucun bruit — c'est le seul
indicateur qui rende cette panne visible.

Les colonnes « Ouvertures » et « Clics » de la table cèdent la place aux trois
compteurs déjà présents en base : **destinataires, envoyés, échoués**. Les trois
ensemble, toujours : une campagne qui n'afficherait que ses succès laisserait
croire qu'elle a touché tout le monde.

**Ce qu'il faudrait pour les rétablir** : un domaine de suivi, une case de
consentement au moment de l'abonnement, un paragraphe dans la politique de
confidentialité, et une table d'événements. C'est un lot, pas une colonne.

---

## 156. Pas de bouton « envoyer maintenant » — l'édition part par le planificateur

> **RENVERSÉ le 2 septembre 2026, à la demande du produit.** La diffusion
> manuelle existe désormais (écran Infolettre, onglet « Diffuser »).
>
> L'argument ci-dessous n'était pas faux, et il n'a pas été jeté : il condamnait
> le **simple clic**, pas la fonctionnalité. Quatre gardes répondent chacune à
> un morceau de l'objection — une confirmation qui ANNONCE LE NOMBRE de
> destinataires, la permission `newsletter.send` distincte de `newsletter.view`,
> la mise en file plutôt qu'un envoi dans la requête, et une trace d'audit
> nominative. L'idempotence était déjà résolue par `reserverEnvoi()`, qui insère
> la ligne de livraison AVANT l'envoi.
>
> Ce que les gardes ne corrigent pas : l'irréversibilité. Elle ne se corrige
> pas — elle s'annonce, et c'est le rôle du texte de la modale.
>
> Le texte d'origine est conservé tel quel ci-dessous.


Le prototype porte « **+ Nouvelle campagne** » et une barre d'actions groupées
(publier, archiver, dupliquer, supprimer). Aucun de ces gestes n'a été porté.

**La raison n'est pas le temps, c'est l'irréversibilité.** Un clic qui expédie
plusieurs milliers de courriels n'a pas d'annulation : une fois dans les boîtes,
c'est fini. Le geste demanderait au minimum un aperçu, une confirmation
nominative, un envoi de test à soi-même, et un verrou contre le double clic —
soit un parcours entier, pas un bouton.

**Décision** : l'édition hebdomadaire part par `verifall:infolettre-hebdomadaire`,
planifiée le lundi. La commande est **idempotente** : la relancer à la main après
un incident est sans danger, ce qui couvre le vrai besoin — « le cron n'a pas
tourné, il faut envoyer ». L'écran d'administration **montre**, il n'expédie pas.

Le seul geste d'écriture qu'il porte est **désabonner une adresse**, journalisé.
Il répond à un cas réel et fréquent : quelqu'un écrit au support plutôt que de
cliquer, et il faut pouvoir le retirer tout de suite.

`newsletter.send` reste déclaré à la matrice : la Policy s'en sert déjà pour ce
retrait, et l'écran d'envoi, le jour où il existera, n'aura pas à inventer un
droit.

---

## 157. Le récapitulatif n'est PAS mis en file — sinon les trois compteurs mentent

`OtpCodeMail` et `ExportPretMail` implémentent `ShouldQueue`. Le récapitulatif
hebdomadaire, non. L'incohérence est délibérée.

Un Mailable mis en file rend la main immédiatement : la commande saurait alors
que le courriel est **parti en file**, jamais s'il est **arrivé au relais**. Les
trois compteurs de la campagne deviendraient deux compteurs et un vœu —
`failed_count` resterait à zéro quoi qu'il arrive, et un envoi à moitié raté se
lirait comme un envoi réussi. C'est exactement l'illusion que la colonne
« échoués » existe pour dissiper.

**Décision** : le récapitulatif est envoyé **depuis la commande**, destinataire
par destinataire, chacun dans un `try`, avec une trace écrite en base pour
chacun. Un échec sur une adresse n'interrompt pas l'édition — une boîte pleine
ne doit pas priver les trois mille suivantes.

**Le prix, et il faut le connaître** : la commande dure. Sur trois mille
adresses et un relais mutualisé, cela se compte en minutes. Elle est donc
planifiée `runInBackground()`, pour ne pas retenir le planificateur pendant ce
temps.

**Quand ce choix devra être revu** : au-delà de quelques milliers d'abonnés, ou
le jour où le relais impose une limite de débit. Le remplacement est alors un
Job par tranche qui écrit les mêmes traces — la table `newsletter_deliveries`
est déjà taillée pour, puisqu'elle réserve avant d'envoyer.

Le courriel de **confirmation**, lui, reste en file : il part d'une requête web,
et faire attendre un formulaire public le temps d'un aller-retour SMTP est une
mauvaise idée pour tout le monde.

---

## 158. Un dépôt par CAS D'USAGE, pas par table

`DepotAbonnes` sert les abonnés, les traces d'envoi **et** les fact-checks
publiés de la semaine. Trois responsabilités dans une interface : la remarque
est légitime, la décision est assumée.

Trois interfaces auraient demandé **trois liaisons dans
`app/Providers/AppServiceProvider.php`** — fichier de couture, verrouillé
pendant que quatre agents écrivent en parallèle, et dont chaque ligne ajoutée
est un conflit potentiel au moment de l'intégration. Un port par **cas
d'usage** — ce que l'infolettre a besoin de lire et d'écrire — plutôt qu'un port
par table, ne coûte qu'une ligne.

Ce qui reste hors du dépôt est ce qui compte : **l'envoi**. Le dépôt ne connaît
ni Mailable ni transport ; il sait *à qui* écrire, pas *comment*. C'est ce qui
permet d'éprouver l'idempotence sans serveur SMTP.

**Un point de vigilance nommé** : `factChecksPublies()` interroge `HubArticle`,
et **jamais** `VerifiedFact`. Les deux se ressemblent et ne sont pas
interchangeables — `VerifiedFact` est l'enregistrement interne de l'équipe, sans
page publique. La confusion a déjà produit un plan du site plein de 404 ; dans
une infolettre, elle produirait des liens morts envoyés à toute la liste d'un
coup.

---

## 159. `edition_key` hors du `$fillable` de `Campaign` — `forceFill` assumé

La clé d'édition (`hebdo-2026-W35`) est ce qui empêche deux infolettres pour la
même semaine. Elle vit dans une nouvelle colonne de `campaigns`, table
existante.

`app/Models/Campaign.php` **n'appartient pas à ce lot**. Y ajouter une ligne de
`$fillable` pendant que trois autres agents écrivent, c'est prendre le risque
qu'un `git merge` la perde en silence — et une colonne qu'aucun code ne peut
écrire est pire qu'une colonne absente : la migration passe, l'unicité existe,
et rien ne s'en sert.

**Décision** : la campagne d'édition est créée par `forceFill()`, ce que le dépôt
documente à l'endroit exact où il le fait. Le patron existe déjà dans le dépôt —
`PacksUnites` écrit ainsi ses bascules d'état.

**Ce que l'intégration peut faire ensuite, et qui serait mieux** : ajouter
`'edition_key'` au `$fillable` de `Campaign`, puis remplacer le `forceFill` par
un `create`. C'est une amélioration de confort, pas une correction : le
comportement est identique, et les tests le couvrent dans les deux cas.

---

## 160. Les notes de communauté ne se publient PAS à la majorité

**Le problème.** Un forum citoyen béninois discute de santé publique et de
politique. Sur ces sujets, un vote majoritaire ne mesure pas la justesse d'une
information : il mesure la taille du camp le plus mobilisé. Publier « la note
la plus approuvée » revient donc à donner le micro au groupe le plus nombreux —
c'est-à-dire à fabriquer la chambre d'écho qu'on prétend combattre.

**La décision.** Une note n'est publiée que si elle est jugée utile par des gens
**qui votent habituellement en désaccord entre eux**. Le score se calcule en
trois temps, dans `app/Core/Community/Services/ScoreDePontage.php` :

1. **accord entre deux votants** = (votes identiques − votes opposés) ÷ votes
   communs, dans [−1, 1] ;
2. **divergence des approbateurs** d'une note = moyenne des (1 − accord) ÷ 2 sur
   les paires d'approbateurs dont l'accord est connu ;
3. **pontage** = (votes utiles ÷ votes totaux) × divergence.

**Les deux seuils, et leur raisonnement.**

| Constante | Valeur | Pourquoi cette valeur |
|---|---|---|
| `VOTES_MINIMUM` | 5 | En dessous, il n'existe presque pas de paires d'approbateurs et une seule paire déciderait de la publication : trois amis coordonnés publieraient ce qu'ils veulent. À cinq votes, on dispose d'au plus dix paires. **Si on l'augmente** (20), presque aucune note ne franchirait le seuil sur un forum de quelques centaines de membres actifs — et le contexte utile sur une rumeur WhatsApp vaut le premier jour, pas la semaine suivante. **Si on le diminue**, le démarrage à froid revient. |
| `NOTES_COMMUNES_MINIMUM` | 2 | Sur une seule note commune, l'accord vaut mécaniquement +1 ou −1 : ce n'est pas une tendance, c'est une coïncidence. Deux notes autorisent une valeur intermédiaire, donc une nuance. **Si on l'augmente**, les accords deviennent plus fiables mais un forum jeune n'a plus aucune paire qualifiée — donc plus aucune note publiable. |
| `SEUIL_DE_PUBLICATION` | 0,25 | Deux chemins y mènent, tous deux exigeants. À l'unanimité (utilité = 1), il faut encore une divergence ≥ 0,25, soit des approbateurs qui se contredisent ailleurs au moins une fois sur quatre — un bloc homogène plafonne près de zéro et ne passe **jamais**, quel que soit son nombre. À camps parfaitement opposés (divergence = 1), il faut encore un quart de votes favorables. **Si on l'augmente** (0,5), il faudrait à la fois l'opposition parfaite et la majorité franche : presque rien ne se publierait et le mécanisme deviendrait décoratif. **Si on le diminue** (0,1), une divergence de 0,1 se produit entre gens du même bord qui divergent une fois sur vingt : on retomberait sur un vote majoritaire déguisé — avec l'apparence de rigueur en plus, ce qui est pire que le vote majoritaire assumé. |

**Le démarrage à froid, traité en deux garde-fous.** Le plancher de votes
ci-dessus ; et un score **nul** — pas zéro — quand aucune paire d'approbateurs
n'a d'historique commun. Dans les deux cas la note reste `EnAttente`, jamais
`NonRetenue` : `bridging_score` reste `NULL` en base, et l'écran dit « en
attente d'évaluation ». Écrire 0 effacerait la différence entre une note qui
attend et une note qui a échoué.

**Le coût, traité par la planification.** Comparer chaque paire de votants est
quadratique — O(V² × N). Le calcul ne vit donc pas dans une requête web : il
tourne dans `verifall:pontage` et le résultat est **stocké** sur la note.
Conséquence assumée : une note franchit le pont au prochain passage de la
commande, pas au moment du vote qui la fait basculer. Ce délai est même sain —
il empêche de tester des combinaisons de votes en direct pour trouver celle qui
publie.

---

## 161. Accord par paires, et non factorisation matricielle

**Ce que fait X.** L'algorithme publié de Community Notes apprend, par
factorisation matricielle, une position sur un axe latent pour chaque votant et
chaque note, puis publie les notes dont l'« utilité intrinsèque » survit à cette
projection.

**Ce qui a été retenu ici.** La mesure d'accord **par paires** décrite à
l'entrée 160. Même intention — faire remonter ce qui rassemble des camps
opposés — mais un calcul directement lisible, sans hyperparamètre à régler à
l'aveugle et sans phase d'apprentissage à surveiller.

**Ce que cela coûte.** La complexité est quadratique en nombre de votants, là où
la factorisation est linéaire par itération. C'est acceptable à l'échelle d'un
forum national, et c'est **borné par la planification** : la commande tourne à
heure fixe, jamais sur le chemin d'une requête.

**Ce qu'il faudra pour changer d'avis.** Si le nombre de votants distincts
dépasse quelques milliers, la boucle de paires devra céder la place à une
factorisation. Le point de bascule est identifiable sans instrumentation : c'est
le moment où `verifall:pontage` cesse de tenir dans sa fenêtre de planification.
Le contrat `DepotNotes::matriceDesVotes()` a été dessiné pour cela — il rend une
matrice, pas des objets, et une autre implémentation de `ScoreDePontage::scores()`
la consomme sans que rien d'autre bouge.

---

## 162. Une note en attente n'est pas invisible de TOUS — sinon aucune ne serait publiée

**La consigne du lot disait** : une note qui n'a pas franchi le pont « reste en
attente, visible seulement de son auteur ».

**Le problème, pris au pied de la lettre.** Une note que personne ne peut voir
ne reçoit aucun vote. Sans vote, elle ne franchit jamais le pont. Sans
franchissement, elle n'est jamais publiée. Le mécanisme entier serait du code
mort dès le premier jour — et le seuil de cinq votes n'aurait aucun moyen d'être
atteint, jamais.

**La décision.** Une note en attente n'est pas publiée **comme contexte**, et
c'est cela que la consigne protège. Elle est en revanche proposée à
l'**évaluation** dans une file distincte :

- les notes **publiées** s'affichent en tête, comme contexte, pour tout le
  monde, y compris les visiteurs non connectés ;
- les notes **en attente de l'auteur lui-même** apparaissent dans un bloc à
  part, avec l'explication de leur état ;
- les notes **en attente des autres** vivent dans une file **repliée par
  défaut**, ouverte aux seules personnes connectées, sous un intitulé qui dit ce
  qu'elle est : « n notes à évaluer — elles ne sont pas publiées ».

Un visiteur non connecté ne voit **que** les notes publiées : lui montrer la
file reviendrait à publier des notes qui n'ont rien franchi. C'est exactement le
compromis de X, qui distingue le lecteur du *rater*.

**Ce que le test vérifie** : un lecteur tiers ne voit pas le texte d'une note en
attente au chargement de la page — seulement le décompte de la file.

---

## 163. Rien de ce qui invite à jouer le système n'est affiché

**Le score de pontage n'apparaît nulle part côté citoyen.** Hors contexte, `0,4267`
ne veut rien dire ; et un nombre affiché est un nombre qu'on cherche à faire
monter. Il sert à trier les notes publiées et à les comparer au seuil, un point
c'est tout. Un test l'assure explicitement.

**La réputation de l'auteur d'une note n'est pas affichée non plus.** Deux
raisons, et la seconde est la vraie :

1. elle coûterait une poignée de requêtes par note affichée ;
2. surtout, elle pousserait à juger **l'auteur** plutôt que **la note** — c'est
   précisément ce que le pontage est conçu pour éviter. Une note lue avec le
   badge de son auteur redevient un vote de confiance, donc un vote de camp.

**Ce qui est affiché** : sa propre réputation, à côté du formulaire de
proposition, et **jamais le palier seul** — les faits qui le produisent sont
imprimés à côté (n démentis, n signalements retenus, n notes publiées). Un
libellé sans ses faits serait un chiffre inventé au sens de la règle §5 du
chantier.

---

## 164. La réputation se calcule, elle ne se saisit pas

**Aucune colonne `reputation` n'existe**, et c'est délibéré : une colonne se met
à jour à la main, dérive, et finit par affirmer quelque chose qu'aucun fait ne
soutient.

**Ce qui ne compte pas** : le nombre de messages, les « j'aime », l'ancienneté du
compte. Compter les messages récompense celui qui parle beaucoup — sur une
plateforme de fact-checking, c'est aussi le profil du militant et du spammeur.

**Le barème**, lu à chaque affichage depuis `content_reports`, `threads`,
`verifications`, `analysis_reports`, `hub_articles` et `community_notes` :

| Fait | Points | Raison |
|---|---|---|
| Sujet escaladé → verdict **Faux** ou **Trompeur** | +5 | La personne a repéré une désinformation avant la plateforme. C'est le service que le forum est censé rendre. |
| Sujet escaladé → verdict **Vrai** ou **Non vérifiable** | +2 | Lever un doute est un résultat. Mettre zéro pousserait à n'escalader que ses convictions, jamais ses doutes. |
| Signalement de contenu **retenu** | +3 | Une décision humaine a confirmé le signalement. |
| Signalement **rejeté** | −2 | Sans coût, signaler ce qui déplaît devient gratuit et la file de modération se remplit de règlements de comptes. Le malus reste **inférieur** au gain : quelqu'un qui se trompe une fois sur deux reste bénéficiaire, parce qu'on veut qu'il continue de signaler. |
| Note de communauté **publiée** | +2 | Elle a franchi le pont — donc convaincu des camps opposés. |
| Démenti issu de son signalement **repris en brève du Hub** | +5 | Le signalement n'a pas seulement été vérifié, il a été **diffusé**. Au Bénin, une rumeur voyage en image sur WhatsApp : la reprise éditoriale est le moment où le démenti se met à circuler à parité. |

**Le total ne descend jamais sous zéro** : un score négatif serait une sanction
publique que personne n'a prononcée. Le malus freine la montée, il ne salit pas.

**Les paliers** — « Aucune vérification confirmée » (0), « Contributeur » (1),
« Contributeur confirmé » (10), « Vigie » (30) — sont **nos** bornes, pas celles
d'une spécification. Elles sont volontairement basses : un palier qu'on
n'atteint jamais ne récompense rien. Elles se déplacent dans
`ReputationCitoyenne::palier()`, à un seul endroit.

**Ce que le verdict officiel n'a pas.** Les points sont lus sur le **rapport
d'analyse**, jamais sur le verdict officiel apposé par un modérateur : ce
dernier est un acte éditorial, et l'y adosser ferait dépendre la réputation d'un
citoyen de l'assiduité d'un modérateur.

---

## 165. « Escalader en fact-check » quitte le bandeau de modération

**Le constat.** `TransmissionAuLabo::transmettre()` existait, testé, fonctionnel
— et son unique déclencheur était un bouton du bandeau de modération, donc
invisible pour la quasi-totalité des visiteurs. Le pont entre le forum et le
laboratoire, qui est le mécanisme central du produit, était en pratique
inatteignable.

**La décision.** Le bouton a son propre bloc sur la page d'un sujet, et il est
visible pour **l'auteur du sujet** autant que pour les modérateurs. Le
raisonnement a deux branches :

- l'ouvrir à **tout le monde** ferait de chaque fil une file d'analyses. Le
  laboratoire est gratuit pour le demandeur, il ne l'est pas pour la plateforme,
  et une rumeur populaire serait escaladée cinquante fois avant que la première
  analyse ne rende son verdict ;
- le réserver aux **seuls modérateurs** l'enterre, comme constaté. Celui qui a
  signalé la rumeur est précisément celui qui veut savoir.

Un sujet déjà transmis ne se ré-escalade pas : deux analyses de la même rumeur
pourraient rendre deux verdicts sur le même fil. La règle vit dans
`TransmissionAuLabo::peutEscalader()`, à un seul endroit ; le composant Livewire
l'interroge, il ne la réécrit pas.

**La traçabilité, qui manquait.** `threads.verification_id` disait *que* le sujet
était parti au laboratoire, jamais *par qui* ni *quand*. Deux colonnes ajoutées
— `escalated_by`, `escalated_at` — et le bloc les affiche avec l'état de
l'analyse. Le journal d'audit portait déjà l'information, mais c'est une preuve,
pas une source de données d'affichage : il n'est ni lisible par un citoyen ni
interrogeable par sujet. Sans ces colonnes, on ne peut répondre ni à « depuis
combien de temps attend-on ce verdict ? » ni à « ses escalades
aboutissent-elles ? » — qui est la matière première de l'entrée 164.

**Ce qui n'est pas lié.** La référence de la vérification est affichée, pas
rendue cliquable : `citizen.report` est réservée à qui a **commandé** l'analyse,
et une analyse née du forum n'appartient à personne. Un lien y mènerait à un
404 — c'est-à-dire au bouton qui n'ouvre rien.

---

## 166. Le bloc d'infolettre : sept pages éditoriales, pas le gabarit

**Décision prise à l'intégration, revue après coup.** Elle mérite d'être lue en
entier, parce que la première version était mauvaise et que la raison de son
échec est instructive.

### Ce qui avait été fait, et pourquoi c'était trop

`<x-infolettre-abonnement />` avait d'abord été inséré dans
`components/layouts/public.blade.php` — donc sur les vingt-deux pages publiques,
mentions légales et politique de cookies comprises. Deux arguments le
justifiaient en apparence :

1. la campagne `newsletter-boom` pointait vers `#newsletter-section`, une ancre
   qui n'existait dans aucune page ; les campagnes s'affichant par un composeur
   attaché au gabarit, l'ancre devait donc exister partout ;
2. le bloc est le seul endroit qui affiche le retour des trois parcours
   d'abonnement.

**Les deux tombent à la vérification.**

Le second d'abord : `AbonnementController` redirige les trois parcours —
souscription, confirmation, désabonnement — vers `public.home`, et rien d'autre.
Le retour n'a jamais eu besoin d'une autre page que l'accueil.

Le premier ensuite, et c'est le plus instructif : `#newsletter-section` est une
ancre **relative**, qui ne mène quelque part que si la page courante porte déjà
le bloc. En `/#newsletter-section`, le navigateur va à l'accueil puis défile —
depuis n'importe où. C'est une valeur en base de données, pas du code.

Autrement dit : **un problème de données avait été résolu par une modification de
gabarit touchant vingt et une pages qui n'en avaient pas besoin.** C'est le genre
d'écart qui passe, parce qu'il fonctionne — et qui coûte, parce qu'il déplace la
référence visuelle sur tout le site pour un besoin qui tenait dans un caractère.

### Ce que la vérification du rendu a révélé — et qui change la réponse

Le rendu réel de l'accueil montrait `id="nl-email"` **deux fois**. Raison : le
prototype porte depuis toujours son propre formulaire d'abonnement sur
l'accueil — « Rejoignez la communauté anti-fake news ». Le lot C avait conclu
qu'« aucun formulaire d'abonnement n'existe nulle part » ; c'était faux, et
poser le composant sur l'accueil y ajoutait un second formulaire plus un
identifiant DOM dupliqué, qui casse l'association du `<label for>`.

Ce formulaire-là était **factice** : `data-vf-form`, dont le gestionnaire de
`verifall.js` appelle `ev.preventDefault()`, attend 900 ms et affiche
« Bienvenue dans la communauté anti-fake news ! Vérifiez votre boîte mail. »
Aucune `action`, aucun `name` sur le champ, aucun jeton. **Quelqu'un
s'inscrivait, était remercié, et n'existait nulle part.** C'est pire que
l'absence de formulaire, parce que rien ne signale la panne — ni à la personne,
ni à l'équipe.

La règle « on transpose, on n'invente pas » donne ici la réponse exacte : il
fallait faire fonctionner CE formulaire, pas en poser un second à côté. Les deux
attributs `data-vf-form*` sont retirés — les laisser ferait ravaler la
soumission par le script — et le formulaire poste vers
`public.newsletter.subscribe` avec `@csrf` et `name="email"`. **Le balisage, les
classes et le libellé du bouton sont inchangés.**

### Ce qui est fait

L'accueil emploie **son propre formulaire**, désormais réel, et porte l'ancre
`#newsletter-section` sur la section que le prototype consacre déjà au sujet.
Les **six autres pages éditoriales** reçoivent le composant :

| Avec un formulaire | Sans — prototype intact |
|---|---|
| `/` *(celui du prototype, rendu réel)* | `/contact`, `/faq`, `/pourquoi-verifall` |
| `/actualites`, `/actualites/{slug}` | `/fonctionnalites`, `/comment-ca-marche` |
| `/blog`, `/blog/{slug}` | `/notre-equipe`, `/medias`, `/tutoriels`… |
| `/corrections`, `/methodologie` | `/mentions-legales`, `/confidentialite` |
| | `/cookies`, `/financement` |
| | `/verifier-une-information`, `/verifier-un-certificat` |

Le critère est le moment, pas la page : quelqu'un qui vient de lire un démenti
est la personne la plus susceptible d'en vouloir la suite chaque lundi. Sur une
mention légale, la même invitation est du bruit.

`/financement` en est écartée à dessein, bien qu'elle soit éditoriale : une page
qui explique d'où vient l'argent perd de sa force si elle se termine par une
sollicitation.

### Ce qui empêche la rechute

`tests/Feature/IntegrationDesLotsTest.php` garde cinq faits ensemble : le
formulaire de l'accueil poste vraiment et n'a plus `data-vf-form` ; aucune page
ne porte **deux** formulaires ; l'ancre est présente sur les pages éditoriales,
**absente** des pages légales et utilitaires ; et l'ancre semée est bien
`/#newsletter-section`.

Ce dernier test est le moins évident et le plus utile : sans lui, quelqu'un
rétablirait l'ancre relative, constaterait que la campagne ne mène plus nulle
part depuis les autres pages, et remettrait le composant dans le gabarit. Le
raccourci se reprendrait tout seul.

### Vérifié au passage

`/hors-ligne` emploie `errors.layout`, jamais le gabarit public : aucun
formulaire n'est donc proposé à quelqu'un qui n'a pas de réseau. C'était le seul
défaut réellement grave que la pose globale aurait pu produire.

---

## 167. La carte de démenti est câblée par un OBSERVATEUR, pas par l'écran

**Décision prise à l'intégration.** Le lot qui a produit la carte ne pouvait pas
la brancher : l'observateur, la route et la balise `og:image` vivent dans des
fichiers qui lui étaient interdits pendant le chantier parallèle.

**Pourquoi un observateur et non un appel dans l'écran de publication.** Un
fact-check change de verdict par plusieurs chemins — l'écran d'administration,
un seeder, une commande de rattrapage à venir. Un appel posé dans un seul de ces
chemins produit exactement le défaut qu'on veut éviter : une carte qui annonce
FAUX sur un verdict devenu VRAI, déjà partagée telle quelle sur WhatsApp.

**Ce que l'observateur ne fait pas.** Il ne fabrique rien. Il appelle
`planifierSiNecessaire()`, qui met en file et sort — sans effet quand la carte
est déjà à jour. La distinction compte : en service, la file est une table, donc
une police de caractères manquante fait échouer le TRAVAIL, pas la requête de
celui qui publie. Le contraire ferait tomber la publication d'un article sur une
panne de rendu d'image.

**Le trou connu.** Aucune commande de rattrapage n'existe pour les fact-checks
publiés AVANT la mise en place de l'observateur. Sur une installation neuve la
question ne se pose pas — le seeder passe par le même `saved`. Sur une base déjà
en service, ces articles resteront sans carte jusqu'à leur prochain
enregistrement. Une commande `verifall:engendrer-cartes-manquantes` reste à
écrire, et le point est nommé ici plutôt que découvert plus tard.

---

## 168. Le formulaire de contact, et les deux écrans qui manquaient derrière

**Le formulaire était factice**, exactement comme celui de l'infolettre :
`data-vf-form`, `preventDefault()`, 900 ms d'attente, puis « Message envoyé !
Notre équipe vous répondra très vite. » Aucune `action`, aucun `name`, aucune
route POST. Quelqu'un écrivait, était remercié, et son message n'arrivait nulle
part — sur un site où l'on signale des intox.

**Trois décisions méritent d'être relues.**

*L'ordre des opérations.* On ÉCRIT en base d'abord, on prévient par courriel
ensuite. L'ordre inverse — n'envoyer qu'un courriel — perd le message dès que le
serveur SMTP refuse, et personne ne l'apprend. Le test correspondant fait
échouer l'envoi et vérifie que le message est quand même là.

*La réponse est la même dans les trois cas.* Message accepté, piège déclenché,
rafale détectée : la personne voit toujours la même confirmation. Un robot à qui
l'on répond « rejeté » apprend quoi changer ; un robot remercié n'apprend rien.
Les cas écartés sont journalisés côté serveur, là où ils servent.

*Le piège s'appelle `website`.* Pas `honeypot` — un robot qui reconnaît le
second l'évite. Il est masqué par le STYLE et non par `type="hidden"` : les
robots ignorent les champs cachés, mais remplissent volontiers ce qui ressemble
à un champ ordinaire. Second signal : le délai. Moins de trois secondes entre
l'affichage et l'envoi, c'est une machine. **Horodatage absent, on laisse
passer** — un navigateur ancien ou un onglet dormant suffisent à le perdre, et
refuser là écarterait des humains.

**Deux écrans ajoutés au prototype**, consignés comme tels :

*« Contenus signalés »* (COMMUNAUTÉ). La table `content_reports` se remplissait
depuis des mois et **aucun écran ne permettait de la lire, pour aucun type de
contenu**. Une seule file pour les sujets, les réponses et les notes de
communauté : trois écrans séparés auraient produit trois habitudes, et un type
qu'on finit par ne plus relever. Les plus ANCIENS en haut, contrairement à tous
les autres écrans — une file de modération se vide par le bas.

*« Messages reçus »* (RESSOURCES & COM). Ouvert à l'administration seule : le
périmètre du modérateur est le forum, et cette boîte contient des adresses et
des messages libres qui peuvent nommer des personnes.

**Aucun des deux n'offre de suppression.** Un signalement se tranche — retenu ou
rejeté — et la trace demeure : c'est elle qui permet de répondre, plus tard, à
« pourquoi ce contenu est-il resté en ligne ? » Un message se marque
« indésirable », ce qui le sort de la file sans le détruire : un faux positif se
rattrape, une suppression non.

---

## 169. Les cinq classes Tailwind « manquantes » ne seront PAS déclarées

Un relevé de conformité signalait cinq classes employées dans les vues et
absentes du build figé : `py-2.5`, `px-3.5`, `disabled:opacity-40`,
`!text-[16px]`, `hover:text-vfalse`. Écrites dans le balisage, elles ne
produisent aucun effet et aucune erreur. La conclusion naturelle — « il faut les
déclarer » — est **fausse**, et il a fallu comparer les deux dépôts pour
l'établir.

**Ce que la vérification donne :**

- le CSS du prototype et celui du projet sont **identiques au bit près** —
  même empreinte MD5, `4faab83a…` ;
- le prototype emploie lui-même `py-2.5` dans **95** de ses pages et `px-3.5`
  dans **44** ;
- son propre CSS ne les définit pas davantage.

**Le prototype s'affiche donc déjà sans ces classes.** La transposition les
reproduit à l'identique, ce qui est exactement ce qu'on lui demande. Les
déclarer changerait le rendu de dizaines d'écrans EXISTANTS : ce serait une
refonte, et « no redesign » l'interdit.

**Le risque réel est à l'inverse de celui qu'on croyait.** Le jour où quelqu'un
ranime la chaîne Vite et regénère ce fichier depuis la configuration du
prototype, ces classes apparaîtront — puisqu'elles sont bien dans le balisage —
et l'espacement de tout le site se déplacera d'un coup, sans qu'aucun test
fonctionnel ne bronche. `tests/Feature/BuildTailwindFigeTest.php` épingle donc
l'empreinte du build ET l'absence de ces déclarations dans toutes les feuilles
annexes.

---

## 170. Un secret de double authentification illisible n'est PAS un facteur

**Le défaut.** Après une rotation d'`APP_KEY`, les secrets TOTP en base ne se
déchiffrent plus : `DecryptException — The MAC is invalid`. Aucun garde nulle
part ; l'exception remontait jusqu'à l'écran de connexion du back-office.
**Tout le personnel enfermé dehors sur une erreur 500**, et aucun moyen d'en
sortir par l'interface, puisque l'écran qui permettrait de se réenrôler est
précisément celui qui plante.

Trouvé sur la base de développement, où l'administrateur de démonstration était
dans cet état exact.

**La décision.** `estActive()` ne se contente plus de regarder
`two_factor_confirmed_at` : elle exige aussi que le secret soit **lisible**. Un
secret que l'application ne sait plus déchiffrer ne protège plus personne — il
enferme seulement son propriétaire dehors. En répondant faux, on renvoie vers
l'ENRÔLEMENT, seul chemin qui remette le compte en état. Le mot de passe a déjà
été vérifié à ce stade : reconstituer un facteur qui n'en était plus un ne coûte
aucune sécurité.

**Le détail qui a coûté le plus de temps.** Purger les colonnes cassées ne peut
PAS passer par le modèle : `save()` compare l'état courant à l'état d'origine
pour savoir ce qui a changé, et cette comparaison déchiffre justement la valeur
qu'on veut remplacer. `reinitialiser()` écrit donc par le constructeur de
requêtes.

**La commande de développement.** `verifall:code-2fa {email}` affiche le code
courant d'un compte du personnel. Elle refuse de s'exécuter hors environnement
local — le contrôle est le tout premier geste, avant même de lire la base — elle
n'est jamais planifiée, et elle ne change rien à la vérification. La réponse
habituelle « désactive la 2FA pour ce compte » ne marchait pas : l'écran bascule
alors sur l'enrôlement, qui redemande un code.

## 171. La fenêtre de campagne passe à deux colonnes, et cesse de recadrer

**L'écart.** Le prototype pose l'image de campagne en bandeau horizontal
au-dessus du texte. La fenêtre est désormais une grille à deux colonnes
au-delà de 640 px — image à gauche, titre, texte et boutons à droite — et
revient à l'empilement en dessous.

**Pourquoi.** La feuille imposait `height: 180px; object-fit: cover`. Le visuel
en place mesure **816 × 816, parfaitement carré** : la règle en découpait donc
une bande horizontale au milieu, soit **environ 22 % de sa hauteur**. Sur une
affiche — le format que ces visuels prennent presque toujours — le titre passe
au-dessus du cadre et l'appel à l'action en dessous ; il ne reste que le fond.

Le défaut était discret parce que l'image se chargeait correctement. Rien ne
signalait qu'on n'en voyait qu'un tiers.

**Ce qui a été retiré, et pourquoi c'est le vrai sujet.** L'`<img>` déclarait
`width="440" height="180"`. Ces attributs servent au navigateur à réserver la
place avant chargement — utile — mais ils AFFIRMENT un rapport de forme. Or ces
visuels sont téléversés depuis l'administration, dans les proportions que leur
auteur a choisies. Annoncer un rapport faux pour éviter un décalage de mise en
page revenait à déformer chaque image pour protéger une modale déjà bornée en
hauteur par sa feuille de style.

**Ce qui n'a pas changé.** Le seuil de 640 px n'est pas plus bas : en dessous,
deux colonnes donneraient une image en timbre-poste à côté d'un texte à trois
mots par ligne. La classe `--avec-media` est posée par le gabarit plutôt que
déduite par `:has()` — faire dépendre la mise en page d'un sélecteur récent
alors que le gabarit connaît déjà la réponse serait un pari sans contrepartie.

Demandé explicitement. Quatre tests couvrent l'écart, dont un qui lit la feuille
et refuse le retour de `object-fit: cover`.

## 172. Le mot qui efface un compte s'écrit dans les deux langues

**Le défaut.** L'écran de suppression demande de taper un mot, et
`SuppressionCompteService` **compare ce mot côté serveur**. Traduire l'écran en
« Type DELETE » aurait refusé tout anglophone tapant exactement ce qu'on lui
demandait — avec un message d'erreur lui disant de taper ce qu'il venait de
taper. Sur le seul écran où un refus incompréhensible ne se rattrape pas.

**La décision.** `MOTS_DE_CONFIRMATION` accepte SUPPRIMER **et** DELETE,
partout, quelle que soit la langue courante : quelqu'un qui bascule entre
l'affichage et la validation ne doit pas être bloqué non plus. La valeur de
référence reste `SUPPRIMER`, que documente le contrat d'API et qu'envoie
l'application Flutter — la renommer aurait cassé les deux.

**Ce qui a été refusé.** Une première version normalisait la casse, ce qui
acceptait « supprimer » en minuscules. **On ajoute une langue, pas une
tolérance.** Taper des capitales demande un geste de plus, et c'est précisément
ce geste que le prototype réclame avant d'effacer un compte. Normaliser aurait
allégé le seul écran où l'on souhaite que l'utilisateur ralentisse.

Élargir ce qu'on accepte n'affaiblit rien ici : le mot ne prouve que
l'intention. L'identité reste prouvée par le mot de passe, puis par le code
envoyé par courriel.

## 173. Ce qui est écrit en base ne se traduit pas ; ce qui est affiché, si

**Le défaut.** Trois endroits confondaient le mot affiché et la valeur
technique. Chacun cassait quelque chose sans lever la moindre erreur.

`unit_logs.label` est une colonne **persistée**, cherchée en `LIKE` et exportée
en CSV. En y écrivant le libellé traduit, chaque écriture prenait la langue de
l'écran du moment : les mêmes opérations libellées en deux langues, et la
recherche « Recharge » ne trouvant plus les lignes écrites en anglais. Un relevé
qui se contredit d'une ligne à l'autre ne se défend pas devant quelqu'un qui
conteste son solde — ce à quoi il sert précisément.

L'import de tableur comparait la colonne verdict à `libelle()`, qui suit la
langue de l'interface. Il suffisait qu'un membre du personnel ait consulté le
site public en anglais pour que « trompeur » cesse d'être reconnu : **un fichier
accepté la veille, refusé le lendemain**, ses lignes ignorées sans explication.

Les pastilles du répertoire OSINT portaient une seule chaîne pour deux rôles,
mot affiché et valeur cherchée. Traduire l'affichage aurait traduit le filtre —
et `category = 'Search'` dans une colonne remplie de « Recherche » ne renvoie
rien. Pas d'erreur : une liste vide, qu'on prendrait pour une absence d'outils.

**La règle retenue, une fois pour toutes.** On écrit dans une langue fixe, on
traduit à l'affichage, et ce qui doit reconnaître un mot venu d'ailleurs accepte
toutes les langues. La recherche traduit le terme à rebours pour retrouver ce
qu'elle vient de montrer.

Un libellé **saisi par un humain** — le motif d'un ajustement administrateur —
n'est jamais traduit : c'est une donnée, pas un gabarit. Le traduire reviendrait
à réécrire ce que quelqu'un a écrit, et ce texte-là est justement celui qu'on
relira pour justifier l'opération.

## 174. On testait le mécanisme de langue, jamais ce qu'il atteint

**Le constat.** `MultilingueTest` prouvait que la bascule fonctionne :
l'attribut `lang` change, la session retient le choix. Tout cela était vrai, et
tout cela restait vrai pendant que **1 112 chaînes** s'affichaient en français
sur l'interface anglaise. Le trou n'était pas dans le mécanisme, il était dans
ce que le mécanisme atteint. Aucun test ne regardait le contenu.

**Ce qui rend ce trou particulier.** Une clé de traduction absente ne lève
**aucune exception** : elle s'affiche telle quelle à l'écran. C'est arrivé
pendant ce chantier — un lot interrompu à mi-course a laissé la barre latérale
citoyenne afficher `cit_citizen.mes_evaluations` en toutes lettres, pendant que
la compilation passait et que les tests restaient verts.

Un contrôle de parité FR/EN ne l'aurait pas vu non plus : les deux fichiers
avaient exactement les mêmes clés manquantes.

**La décision.** Trois contrôles permanents dans `CouvertureTraductionTest` :
les clés appelées par une vue existent, les deux langues déclarent les mêmes
clés, et une valeur anglaise identique au français est signalée sauf exception
**nommée**. Les exceptions sont listées une par une plutôt que déduites par une
règle tolérante — une règle tolérante finit par absorber de vrais oublis en
silence.

**La limite, assumée.** Une clé calculée ne se vérifie pas statiquement. Là où
l'indirection est utile — la séparation libellé/filtre du répertoire OSINT — le
fichier de langue est couvert autrement : `OutilsTest` parcourt les catégories
et exige un libellé pour chacune, dans les deux langues.

À noter enfin : `fallback_locale` valant `fr`, une clé anglaise oubliée retombe
**silencieusement** sur le français à l'exécution. La promesse « une clé
manquante se voit » n'est donc vraie qu'en français, et c'est pourquoi ces
contrôles lisent les fichiers plutôt que le rendu.

## 175. L'`alt` d'une image dans un lien EST le nom du lien

**Le défaut.** Cinq images portaient leur nom de fichier en guise de texte de
remplacement : `alt="google-store"`, `alt="app-store"`, `alt="logo-icon"`. Ces
images étant placées dans des liens, un lecteur d'écran annonçait « lien,
google-store ».

**La décision.** Noms accessibles décrivant ce que l'image montre — « Google
Play », « App Store », « Vérif'All » — et traduits comme le reste.

**Ce qui a été délibérément laissé.** Les deux badges de magasin pointent vers
`/verifier-une-information` et non vers un magasin d'applications. Le nom
accessible retenu décrit donc le BADGE, pas une promesse de téléchargement : un
utilisateur voyant a exactement la même surprise qu'un utilisateur aveugle, et
écrire « Télécharger sur Google Play » aurait creusé l'écart au lieu de le
combler. Changer la destination est une décision produit, signalée et non prise.

**Ce qui n'était pas un défaut.** Les autres `alt=""` du dépôt sont corrects :
images décoratives portant `aria-hidden="true"`, à côté d'un lien qui porte déjà
le titre. Un `alt` vide y est le bon motif.

## 176. La console admin et mentor change de référence visuelle

**La décision.** Le 18 octobre 2026, le porteur du projet a demandé de passer
du prototype premium (`resources/prototype-premium/`) à l'implémentation de
l'administration et de l'espace mentor. Pour ces deux espaces, la « référence
visuelle absolue » de `CLAUDE.md` est donc ce prototype, et non plus
`resources/prototype/`. L'espace citoyen et les pages publiques ne changent pas.

**Ce que cela change.** Le design system staff (`adminbrand`, Inter, Material
Symbols) cède la place à celui du prototype premium : colonne vert forêt
`#0E2A1E`, Bricolage Grotesque et Public Sans, icônes Lucide, mode sombre, thème
clair par défaut. Le build Tailwind citoyen reste figé ; le staff reçoit sa propre
feuille, construite et contrôlée de la même façon.

**Ce qui ne change pas.** Les services, dépôts, Policies, permissions et le
contrat DataTables. On réhabille ; on ne réécrit pas la logique.

**Ce qui reste à trancher.** Les décisions D5 à D7 de
`docs/IMPLEMENTATION_PLAN.md` : permissions du mentor sur le Hub et la
Communauté, corps éditable des modèles de courriels, et données RGPD des fiches.
