# Politique de confidentialité

## Elle existe déjà — ne pas en écrire une seconde

Vérifié le 9 octobre 2026 : `verifall_laravel/resources/views/public/confidentialite.blade.php`
publie une politique de confidentialité complète et structurée (responsable du
traitement, bases légales, données collectées, destinataires et
sous-traitants, durées de conservation, droits RGPD, mineurs), servie en
production à **`https://verifall.com/confidentialite`** — confirmé joignable
(HTTP 200) à cette même date.

**Ne pas rédiger un second texte pour l'application mobile.** Une politique de
confidentialité dupliquée est un risque, pas une précaution : les deux
versions divergent tôt ou tard, et le jour où elles le font, aucune des deux
n'est plus fiable. L'application a déjà le mécanisme pour pointer vers
l'unique version qui fait foi — voir ci-dessous.

## Comment l'application mobile s'y relie déjà

`lib/core/config/app_config.dart` porte `webBaseUrl`
(`VF_WEB_BASE_URL` dans `config/*.json`, déjà renseignée à
`https://verifall.com` dans `config/prod.json`). Le commentaire du champ dans
le code le dit explicitement : *« M5 a retiré du menu la ligne "Politique de
confidentialité" plutôt que de la laisser inerte... Renseignée, les deux
deviennent des liens. »* (arbitrage n° 138 cité dans le fichier).

**Conséquence pratique : une fois le bundle construit avec `config/prod.json`
(voir `02-VERSION-ET-BUILD-AAB.md`), l'écran Réglages affiche déjà un lien
cliquable vers `https://verifall.com/confidentialite`.** Il n'y a rien à
coder pour ce document précis — seulement à vérifier que le lien part bien
vers la bonne page avant la première soumission.

## Ce qu'il reste à faire, et qui n'est pas du code

1. **Renseigner le champ « URL de la politique de confidentialité » dans la
   Google Play Console** (Contenu de l'application → Politique de
   confidentialité) avec `https://verifall.com/confidentialite`.
2. **Faire relire le texte publié** par quelqu'un d'habilité à engager
   l'organisation, en vérifiant qu'il couvre bien ce que l'**application
   mobile** fait spécifiquement et que la page web ne mentionne peut-être pas
   explicitement :
   - le modèle de l'appareil envoyé au serveur à l'émission d'un jeton
     (`lib/data/datasources/remote/auth/nom_appareil.dart`) ;
   - le stockage local chiffré du jeton d'authentification
     (`flutter_secure_storage`) ;
   - l'identifiant d'installation anonyme généré pour la télémétrie de
     plantage (`lib/core/telemetry/identifiant_installation.dart`) —
     actuellement généré mais **non transmis** (voir `04-DATA-SAFETY.md`),
     donc rien à déclarer tant qu'aucun fournisseur n'est branché, mais à
     ajouter au texte le jour où `main.dart` en configure un.

   La page actuelle couvre déjà, en des termes génériques, les « données
   techniques » et les « contenus que vous soumettez » — à confirmer que
   cette formulation suffit pour les trois points mobiles ci-dessus, ou si une
   section dédiée à l'application devient nécessaire.
3. Vérifier que les réglages dynamiques dont dépend la page
   (`legal_entity`, `legal_address`, `legal_host_name`, `legal_contact_donnees`
   — lus via `$legal->entite()`, etc. dans le gabarit Blade) sont **remplis**
   en production. Un responsable du traitement non renseigné affiche un champ
   vide ou un repli, ce qui est un motif de rejet aussi sûr qu'une politique
   absente.

## Ce que ce document ne fait pas

Il ne reproduit pas le texte de la politique — elle vit à un seul endroit,
`confidentialite.blade.php`, et toute modification se fait là, jamais ici.
