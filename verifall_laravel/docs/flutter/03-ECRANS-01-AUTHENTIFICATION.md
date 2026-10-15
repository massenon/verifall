# 3. Les 30 écrans — chapitre 1 : Authentification

Six écrans, module `auth`. Le parcours est celui du README du simulateur :

```
splash → connexion | inscription → code de vérification → tableau de bord
         mot de passe oublié → code de vérification → nouveau mot de passe
```

Un seul écran de code sert les trois usages, par un paramètre `purpose`. C'est
un choix du simulateur, et il est bon : trois écrans identiques auraient dérivé.

---

## 3.1 — Écran 01 · Écran d'accueil (splash)

**Ancre** : `prototype_html/mobile/index.html#/splash`
**Route** : `/` — **Page** : `SplashCarouselPage` — **État** :
`onboardingProvider (seen: bool)`
**Origine** : `web/connexion.html`, carrousel d'authentification

### Ce qui est à l'écran

Plein écran, fond forêt, barre d'état en blanc. Quatre diapositives se
succèdent en fondu, chacune portant en arrière-plan un visuel du dossier
`web/images/slider/`. Ces visuels sont des affiches très typographiques : le
simulateur les traite **en texture** — flou de 3 px, saturation 80 %, luminosité
92 %, échelle 1,12 — sous un voile dégradé sombre en haut et en bas, plus ouvert
au centre. C'est une décision assumée, écrite en commentaire dans le simulateur :
en portrait, sous une copie superposée, une affiche lisible devient un fond
illisible.

De haut en bas :

1. le numéro de l'étape en très grand (44, blanc à 24 %), le mot-clé en vert
   primaire (12, interlettrage `.22em`), puis l'accroche en dessous ;
2. à droite du bloc précédent, un bouton texte **Passer** ;
3. en bas : la citation en 21/1.35 encadrée de guillemets français, la signature
   en Instrument Sans 16, le sous-titre en blanc à 65 % ;
4. la **trace de vérification** : quatre points reliés par un trait, chacun
   surmonté d'un mot en capitales et sous-titré d'un verbe —
   `CLAIM/Identifier · SOURCE/Questionner · EVIDENCE/Vérifier · VERDICT/Comprendre`.
   Le point actif est vert avec un halo ;
5. deux boutons empilés : **Se connecter** (primaire plein ; libellé
   **Commencer** sur la dernière diapositive) et **Créer un compte citoyen**
   (contour blanc à 30 %).

Les quatre contenus (numéro, mot-clé, accroche, citation, signature, sous-titre,
mots de la trace) sont dans le simulateur et se reprennent tels quels : c'est du
contenu éditorial, la parole du produit.

### Arbre de widgets

```
Scaffold
 └ Stack
    ├ PageView (4 diapositives, avance automatique 4,2 s)
    │   └ VfSlide (image + dégradé)
    └ SafeArea
       └ Column
          ├ VfSlideCaption
          ├ VfVerificationTrace (CLAIM → VERDICT)
          └ VfPrimaryButton ×2
```

### État

`onboardingProvider` ne porte qu'un booléen : l'onboarding a-t-il été vu. Il est
lu **au démarrage**, avant le premier rendu, depuis le stockage sécurisé.
L'index de diapositive est un état local de la page, pas un provider.

### Appels réseau

**Aucun.** Le simulateur l'écrit noir sur blanc : les quatre visuels sont des
ressources locales, *« jamais un chargement réseau au premier lancement »*.
C'est le premier écran que voit quelqu'un qui vient d'installer l'application,
souvent sur une connexion médiocre.

### Chargement, vide, erreur

Sans réseau ni donnée, il n'y a pas d'état de chargement. Le seul risque est un
visuel manquant dans le paquet : le repli est le fond forêt uni, jamais un carré
gris.

### Hors ligne

Entièrement fonctionnel. Les deux boutons mènent à des écrans qui, eux,
demanderont le réseau — c'est là que le message d'absence de connexion apparaît,
pas ici.

### Accessibilité

- Chaque point de la trace est un bouton avec pour libellé `« CLAIM : Identifier »`
  et l'index annoncé.
- L'avance automatique s'arrête dès que le focus entre dans la page et sous
  `disableAnimations` — un carrousel qui défile pendant qu'un lecteur d'écran lit
  la diapositive précédente rend le contenu inatteignable.
- « Passer » est atteignable au premier `Tab` : c'est la sortie.

### Point de vigilance (repris du simulateur)

Affiché **une seule fois**, drapeau `onboarding_seen` en stockage sécurisé.
Ensuite l'application ouvre directement sur la connexion ou le tableau de bord.

---

## 3.2 — Écran 02 · Connexion

**Ancre** : `#/login` — **Route** : `/connexion` — **Page** : `LoginPage`
**État** : `authControllerProvider` — **Origine** : `web/connexion.html`

### Ce qui est à l'écran

Fond forêt sur toute la hauteur, marges latérales de 22, barre d'état claire, ni
barre supérieure ni barre basse. En haut, poussé au centre vertical : un carré
de 56 à rayon 18, vert primaire, portant un **V** blanc en Instrument Sans 25 ;
puis le titre **« Content de vous revoir. »** en 26/1.15 blanc, et une phrase en
13 à 70 % de blanc : *« Connectez-vous pour vérifier, apprendre et alerter votre
communauté. »*

En dessous, une carte blanche à 18 de marge :

- champ **Adresse e-mail** (clavier e-mail, autocomplétion `username`) ;
- champ **Mot de passe** (masqué, autocomplétion `current-password`) ;
- une ligne à deux éléments : case **Rester connecté** cochée par défaut, et un
  lien texte primaire **Mot de passe oublié ?** ;
- bouton primaire pleine largeur **Se connecter** ;
- une ligne centrée : *« Pas encore de compte ? »* + lien **Créer un compte
  citoyen**.

Sous la carte, en blanc à 50 %, centré : *« En continuant, vous acceptez la
politique de confidentialité. Consentements RGPD recueillis à la création du
compte. »*

### Arbre de widgets

```
Scaffold
 └ SafeArea
    └ Column
       ├ VfBrandHeader
       └ VfAuthCard
          ├ VfTextField ×2
          ├ VfCheckbox + VfLinkButton
          └ VfPrimaryButton
```

### État

`authControllerProvider` : inconnu → en cours → connecté | échec. La saisie
elle-même est locale à la page ; seule la soumission passe par le contrôleur.

### Appels réseau

**`POST /api/v1/auth/login`** — corps : `email`, `mot_de_passe`, `appareil?`.
Réponse : la forme commune d'émission de jeton —

```json
{ "data": { "token": "…", "type": "Bearer", "expire_le": "…",
            "utilisateur": { "uuid", "prenom", "email", "unites" } } }
```

Envoyer `appareil` avec le modèle réel du téléphone : c'est ce qui rend la liste
des sessions lisible et révocable en connaissance de cause.

**`POST /api/v1/auth/otp/request`** — corps : `email`. Le contrat le rattache à
cet écran et à l'inscription : il sert à **redemander un code d'activation**
quand un compte n'a jamais été activé.

> **Divergence à connaître.** Le simulateur enchaîne systématiquement sur
> l'écran de code avec `purpose = login`. **Le contrat ne le fait pas** :
> `POST /auth/login` rend un jeton directement, et il n'existe aucune
> vérification en deux étapes obligatoire à la connexion. Réserve A.10 de la
> section 9 — à trancher avant d'écrire M1.

### Les échecs, et leur code exact

| Situation | Réponse |
|---|---|
| Mot de passe faux | `401 non_authentifie`, message « Identifiants invalides. » |
| **Compte jamais activé** | `401`, **strictement identique** — distinguer les deux transformerait l'écran de connexion en annuaire des inscrits |
| Trop de tentatives | Verrouillage progressif par compte et par IP ; **le message du serveur contient déjà le délai**, l'afficher tel quel |
| Champs manquants | `422 validation`, `details` par champ |

### Chargement, vide, erreur

- **Chargement** : le bouton passe en indicateur, les champs se verrouillent.
  Pas de voile plein écran — l'écran doit rester lisible.
- **Erreur de saisie** : le simulateur valide l'adresse côté client par un motif
  minimal et affiche un toast rouge *« Entrez une adresse e-mail valide. »*. Le
  mot de passe n'est **pas** validé côté client : le contrat fixe la politique à
  **12 caractères minimum, casse mixte, chiffre, symbole et absence des fuites
  connues**, et précise que *« le client se limite à vérifier la longueur avant
  l'envoi — le reste est serveur »*.
- **Erreur d'authentification** : `error.message` est déjà rédigé en français.
  **L'afficher tel quel**, sans le reformuler.
- **Erreur réseau** : message d'indisponibilité + bouton « Réessayer ». Ne
  jamais afficher un code HTTP brut.

### Hors ligne

L'écran s'affiche, la soumission est **refusée immédiatement** avec un message
explicite. Une connexion ne se met pas en file : rejouer une authentification
plus tard n'a aucun sens, et la personne resterait devant un écran figé.

### Accessibilité

Les deux champs sont reliés à leur étiquette flottante ; « Mot de passe oublié ? »
et « Créer un compte citoyen » sont des boutons, pas du texte cliquable. La
hauteur minimale de 32 du lien de la ligne d'options est **insuffisante** au
regard de la règle des 44 — voir les réserves, section 9.

### Point de vigilance

Le jeton Sanctum va dans **`flutter_secure_storage`**, jamais dans les
préférences partagées. Le fond forêt impose une barre d'état claire
(`SystemUiOverlayStyle.light`).

> **Le point de vigilance du simulateur est périmé sur un point.** Il annonce un
> « refresh silencieux sur 401 » ; le contrat écarte délibérément le jeton de
> rafraîchissement (arbitrage n° 69). **Sur 401 : purger et revenir ici. Ne pas
> boucler sur un renouvellement — il n'y a rien à renouveler.** La durée est de
> 30 jours **glissante**, prolongée par le serveur à chaque appel : une
> application dont on se sert ne redemande jamais le mot de passe.

---

## 3.3 — Écran 03 · Créer un compte

**Ancre** : `#/signup` — **Route** : `/inscription` — **Page** : `SignupPage`
**État** : `signupFormProvider` (StateNotifier)
**Origine** : `web/inscription.html` + `SECURITE_RGPD.md`

### Ce qui est à l'écran

Barre supérieure avec retour, titre **Créer un compte**. Pas de barre basse.

1. Titre **« Rejoignez la boussole »** et sous-titre : *« Un compte citoyen
   gratuit, 10 unités de vérification offertes à l'inscription. »*
2. Une carte avec six champs, dans cet ordre : **Nom et prénom**, **Adresse
   e-mail**, **Téléphone (facultatif)** pré-rempli de l'indicatif du Bénin,
   **Ville** pré-remplie à Cotonou, **Mot de passe**, **Code de parrainage
   (facultatif)**.
3. Une carte **Vos consentements** (sur-titre vert) portant trois cases
   **jamais pré-cochées** :
   - *« J'accepte les conditions d'utilisation et la politique de
     confidentialité. »* — obligatoire, astérisque rouge ;
   - *« J'autorise le traitement de mes contenus soumis aux seules fins de
     vérification. »* — obligatoire ;
   - *« Je souhaite recevoir le résumé hebdomadaire des vérifications par
     e-mail. »* — facultatif, mention `(facultatif)` en gris.
   Puis une phrase : *« Chaque consentement est enregistré séparément, avec sa
   date. Vous pouvez les retirer à tout moment depuis Réglages. »*
4. Bouton primaire **Créer mon compte**, puis une ligne *« Déjà inscrit ? »* +
   lien **Se connecter**.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar (retour)
 └ ListView
    ├ VfAuthHeader
    ├ VfTextField ×6
    ├ VfConsentGroup
    │   └ VfConsentTile (obligatoire | facultatif)
    └ VfPrimaryButton
```

### État

`signupFormProvider` porte les six champs et la carte des consentements. Le
consentement à la lettre d'information est celui qui, dans le simulateur, alimente
la préférence `notifEmail` après création — voir la réserve correspondante,
section 9 : côté back, l'arbitrage n° 11 en fait un **consentement tracé**, pas
une préférence.

### Appels réseau

**`POST /api/v1/auth/register`** — corps attendu par le contrat :
`prenom`, `nom`, `telephone`, `profession`, `email`, `mot_de_passe`, `cgu`,
`confidentialite`, `newsletter?`, `code_parrainage?`.

Réponse : `201 { "data": { "uuid", "email", "etape": "code_envoye" } }`.
**Aucun jeton n'est rendu** : le compte existe mais n'est pas activé. C'est
`POST /auth/otp/verify` qui l'ouvre, une fois la maîtrise de la boîte prouvée.

**`POST /api/v1/auth/otp/request`** — corps : `email`.

> **Le formulaire du simulateur et le corps attendu ne coïncident pas.** Le
> simulateur a un champ unique « Nom et prénom » et un champ « Ville », et n'a
> pas de « Profession » ; le contrat attend `prenom` et `nom` séparés, exige
> `profession`, et **n'accepte pas `ville`** (elle se règle ensuite par
> `PATCH /me`). Les deux consentements obligatoires correspondent à `cgu` et
> `confidentialite`, le facultatif à `newsletter`. Réserve A.11 de la section 9 —
> **c'est un changement de maquette, il se décide, il ne se fait pas en passant.**

Les 10 unités de bienvenue sont créditées **par le serveur** à la validation du
code ; l'écran suivant s'en fait l'écho par un toast et des confettis.

### Chargement, vide, erreur

- Validations que le simulateur fait côté client, et seulement celles-là : nom
  d'au moins 3 caractères, forme d'adresse e-mail, mot de passe d'au moins
  8 caractères, et **les deux consentements obligatoires cochés**. Chaque échec
  produit un toast rouge nommant précisément ce qui manque.
- La règle réelle vit côté serveur — **12 caractères minimum**, casse mixte,
  chiffre, symbole, absence des fuites connues. Le contrat cadre exactement ce
  que fait le client : *« se limiter à vérifier la longueur avant l'envoi »*.
  L'étiquette du champ doit donc annoncer **12**, pas 8 : un formulaire ne doit
  pas promettre moins que ce qu'il exigera (réserve A.3, désormais tranchée).
- Erreurs serveur : `422 validation` porte `details`, **clé = champ envoyé**.
  Elles se rendent sous le champ concerné, pas dans un toast — une adresse déjà
  prise se dit sur le champ de l'adresse.

### Hors ligne

Refus immédiat et explicite. Une inscription ne se met pas en file : elle crée
une identité, et une identité créée hors ligne ne peut pas recevoir son code.

### Accessibilité

Chaque consentement est un `checkbox` avec son texte complet en libellé — pas
« case 1 ». L'astérisque rouge est doublé du mot « obligatoire » dans le libellé
d'accessibilité, la couleur seule ne portant rien.

### Point de vigilance (repris du simulateur)

Consentements **granulaires, jamais pré-cochés**, horodatés séparément (RGPD
art. 7). C'est la **seule addition structurelle** faite au prototype web, et
l'arbitrage n° 3 dit pourquoi : la conformité l'emporte sur la fidélité visuelle,
sur ce point et seulement celui-là.

---

## 3.4 — Écran 04 · Mot de passe oublié

**Ancre** : `#/forgot` — **Route** : `/mot-de-passe-oublie`
**Page** : `ForgotPasswordPage` — **État** : `passwordResetProvider`
**Origine** : `web/mot-de-passe-oublie.html`

### Ce qui est à l'écran

Barre supérieure avec retour. Un carré de 52 à rayon 16, fond vert clair, icône
`lock_reset` en vert foncé. Titre **« Réinitialiser mon mot de passe »**, puis :
*« Indiquez l'adresse e-mail de votre compte. Nous vous envoyons un code à
6 chiffres pour prouver que c'est bien vous. »*

Un champ **Adresse e-mail**, un bouton primaire **Envoyer le code**, puis un
encart d'information (icône bleue) : *« Par sécurité, la réponse est toujours la
même, que l'adresse existe ou non : cela évite de révéler qui possède un
compte. »* Enfin un bouton fantôme **Retour à la connexion**.

### Arbre de widgets

```
Scaffold
 ├ VfAppBar (retour)
 └ Column
    ├ VfIconBadge
    ├ VfTextField (e-mail)
    ├ VfPrimaryButton
    └ VfInfoCard
```

### Appels réseau

**`POST /api/v1/auth/password/forgot`** — corps : `email`.

### Chargement, vide, erreur

La réponse est **volontairement indifférenciée**, et le contrat va plus loin que
le simulateur : `otp/request` et `password/forgot` *« répondent toujours pareil,
que le compte existe ou non, **et même quand le plafond est saturé** »* — un 429
visible dirait « cette adresse intéresse quelqu'un ».

Deux conséquences pour l'écran :

1. **Ne jamais brancher l'affichage sur l'existence du compte**, ni sur le temps
   de réponse : une différence de latence énumère les comptes aussi sûrement
   qu'un message.
2. **Ne pas afficher de compte à rebours de renvoi ici.** Le plafond réel est de
   **3 demandes par 10 minutes et par adresse**, mais il est invisible au client
   par construction. Un compte à rebours fabriqué localement mentirait.

Le simulateur affiche toujours le même toast — *« Si un compte existe, un code
vient d'être envoyé. »* — puis navigue vers l'écran de code avec
`purpose = reset`. Le seul échec affiché est celui de la **forme** de l'adresse,
et l'échec réseau.

### Hors ligne

Refus immédiat, message explicite.

### Point de vigilance (repris du simulateur)

Réponse indifférenciée, limitation de débit **côté serveur**. Le code expire en
10 minutes et n'est utilisable qu'une fois — contrat fixé par l'arbitrage n° 4 :
six chiffres, dix minutes, usage unique, cinq tentatives.

---

## 3.5 — Écran 05 · Code de vérification

**Ancre** : `#/otp` — **Route** : `/otp?purpose=` — **Page** : `OtpPage`
**État** : `otpControllerProvider(purpose)`
**Origine** : `SECURITE_RGPD.md`, double authentification citoyenne

### Ce qui est à l'écran

Un seul écran, **trois copies** selon `purpose` :

| `purpose` | Titre | Phrase |
|---|---|---|
| `login` | Vérification en deux étapes | *Saisissez le code envoyé à votre adresse e-mail pour terminer la connexion.* |
| `signup` | Confirmez votre adresse | *Nous avons envoyé un code à 6 chiffres pour valider votre adresse e-mail.* |
| `reset` | Vérifions que c'est bien vous | *Saisissez le code reçu par e-mail pour choisir un nouveau mot de passe.* |

Barre supérieure avec retour, titre **Vérification**. Carré vert clair de 52,
icône `mark_email_read`. Titre, phrase, puis une ligne rappelant l'adresse
destinataire précédée d'une icône `alternate_email`. Six cases de 46 × 56,
espacées, chiffres en 22/700. Bouton primaire **Confirmer**. Une ligne : *« Pas
reçu ? Vérifiez vos indésirables · »* + lien **Renvoyer**. Enfin un encart vert
clair, icône bouclier : *« Vérif'All ne vous demandera jamais ce code par
message, par téléphone ou sur le forum. »*

### Arbre de widgets

```
Scaffold
 ├ VfAppBar (retour)
 └ Column
    ├ VfIconBadge (mail)
    ├ Text.headline + adresse
    ├ VfOtpField (length: 6)
    │   └ Row (6 × VfOtpBox)
    └ VfPrimaryButton + VfResendLink
```

### État

`otpControllerProvider(purpose)` : les six chiffres et le nombre de tentatives.

**Le compte à rebours de renvoi n'est pas affichable.** Le point de vigilance du
simulateur dit qu'il vient du serveur — mais le contrat impose l'inverse : les
routes de code répondent **toujours pareil, même plafond saturé**, précisément
pour ne rien révéler. Il n'y a donc aucun délai à lire, et un délai calculé
localement mentirait. Le bouton « Renvoyer » reste disponible ; le plafond réel
est de 3 demandes par 10 minutes et par adresse.

### Appels réseau

**`POST /api/v1/auth/otp/verify`** — corps : `email`, `code`, `appareil?`.
C'est l'appel qui **active un compte neuf** et rend un jeton, dans la forme
commune (`token`, `type`, `expire_le`, `utilisateur`).

**`POST /api/v1/auth/otp/resend`** — corps : `email`. Le contrat le dit
franchement : `otp/request` et `otp/resend` sont **le même geste**, deux URI
parce que l'écran a un bouton « renvoyer ».

Après succès : `signup` ouvre la session et pose le tableau de bord **en
racine** — l'utilisateur ne doit pas pouvoir revenir en arrière dans le parcours
d'authentification ; `reset` remplace l'écran courant par « Nouveau mot de
passe ». Le cas `login` est suspendu à la réserve A.10 : le contrat n'impose
aucune vérification en deux étapes à la connexion.

`signup` produit en plus, dans le simulateur : +10 unités, une ligne de
transaction « Bonus de bienvenue », un toast et des confettis. **Le crédit est
fait par le serveur** ; le client lit `data.utilisateur.unites` de la réponse,
il ne l'additionne pas.

### Chargement, vide, erreur

- Code incomplet : bouton inactif.
- Code faux : les cases passent en erreur, le compteur de tentatives décroît et
  l'écran l'annonce. Après cinq tentatives, le code est mort — le dire, et
  proposer d'en redemander un.
- Code expiré : message distinct de « code faux ». Confondre les deux fait
  ressaisir en boucle un code qui ne marchera jamais.

### Hors ligne

Refus immédiat.

### Accessibilité

`autofillHints: oneTimeCode` reste utile sur iOS. Chaque case porte son libellé
« Chiffre n sur 6 ». Le collage d'un code complet doit remplir les six cases —
c'est le geste réel de quelqu'un qui bascule vers sa boîte mail.

### Point de vigilance (repris du simulateur)

Canal **e-mail, pas SMS** : couverture universelle, coût nul, aucune dépendance
opérateur. Le contrat le confirme — toutes les routes de code prennent un `email`
et rien d'autre. Le libellé de l'écran Réglages (30) reste à corriger : voir les
réserves, section 9.

---

## 3.6 — Écran 06 · Nouveau mot de passe

**Ancre** : `#/reset` — **Route** : `/mot-de-passe/reinitialiser`
**Page** : `ResetPasswordPage` — **État** : `passwordResetProvider`

### Ce qui est à l'écran

Barre supérieure avec retour, titre **Nouveau mot de passe**. Titre
**« Choisissez un nouveau mot de passe »**, phrase : *« 8 caractères minimum.
Évitez un mot de passe déjà utilisé ailleurs. »* Deux champs masqués — **Nouveau
mot de passe**, **Confirmer le mot de passe** — puis un bouton primaire
**Enregistrer et se connecter**. Enfin un encart, icône orange `devices` : *« Par
sécurité, toutes vos autres sessions seront déconnectées. »*

### Arbre de widgets

```
Scaffold
 ├ VfAppBar (retour)
 └ Column
    ├ VfTextField ×2 (masqués)
    ├ VfPrimaryButton
    └ VfInfoCard (sessions)
```

### Appels réseau

**`POST /api/v1/auth/password/reset`** — corps : `email`, `code`,
`mot_de_passe`, `appareil?`.

C'est le troisième point d'entrée qui **rend un jeton**, dans la même forme que
les deux autres. Le client n'a donc pas besoin de repasser par la connexion : le
bouton « Enregistrer et se connecter » dit exactement ce qui se passe.

### Chargement, vide, erreur

Le simulateur vérifie deux choses côté client : longueur minimale et égalité des
deux champs, chacune avec son toast rouge. La politique réelle appartient au
serveur, qui rendra un `422 validation` avec `details`. Comme sur l'inscription,
**l'étiquette doit annoncer 12 caractères**, pas 8.

Un code faux ou expiré revient en `422 validation` sur le champ `code` : les
distinguer par le message du serveur, et ne pas fabriquer le sien.

### Après succès

Session ouverte, tableau de bord posé en racine, toast *« Mot de passe modifié —
autres sessions déconnectées. »*

### Hors ligne

Refus immédiat.

### Point de vigilance (confirmé par le contrat)

`POST /auth/password/reset` révoque **tous** les jetons d'API, **toutes** les
sessions web et **tous** les cookies « se souvenir de moi » — l'arbitrage n° 69
précise que l'inclusion des jetons d'API est récente et volontaire : *« un
téléphone volé garde son jeton indéfiniment ; ne fermer que les sessions du
navigateur laissait ouverte la porte la plus difficile à refermer. »*

Le client doit donc **purger son stockage sécurisé et son cache local** au
passage, exactement comme à la déconnexion, avant d'installer le jeton neuf que
la réponse lui rend.
