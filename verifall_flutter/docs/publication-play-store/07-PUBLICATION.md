# Checklist de soumission — dans l'ordre

Chaque étape renvoie au document qui la détaille. Cocher dans l'ordre : les
étapes de construction dépendent de celles de signature, qui doivent être
faites une seule fois et pour toujours.

## 1. Une seule fois, pour toute la vie de l'application

- [ ] Générer le magasin de clés (`01-SIGNATURE-ET-KEYSTORE.md`, étape 1).
- [ ] Créer `android/key.properties` à partir de `key.properties.exemple`
      (étape 2) — **ne jamais le committer**.
- [ ] Première soumission seulement : activer la **signature d'application
      Play** dans la Console (étape 3). Sans elle, le fichier `.jks` généré à
      l'étape précédente devient lui-même la clé de distribution, et sa perte
      devient définitive.
- [ ] Déclarer `https://verifall.com/confidentialite` comme politique de
      confidentialité dans la Console Play
      (`05-POLITIQUE-DE-CONFIDENTIALITE.md`).
- [ ] Faire relire cette politique par quelqu'un d'habilité à engager
      l'organisation, en particulier sur les trois points propres au mobile
      listés dans ce même document.

## 2. À chaque soumission

- [ ] Faire avancer `version:` dans `pubspec.yaml`
      (`02-VERSION-ET-BUILD-AAB.md`) — le numéro après `+` ne doit jamais
      avoir déjà été soumis, pas même en interne.
- [ ] `flutter analyze` → 0 issue.
- [ ] `flutter test` → tout passe.
- [ ] `dart format . --set-exit-if-changed` → aucun fichier reformaté.
- [ ] Construire : `flutter build appbundle --dart-define-from-file=config/prod.json`.
- [ ] Revérifier `03-PERMISSIONS.md` : une permission a-t-elle été ajoutée
      depuis la dernière soumission ? Si oui, son usage doit être visible et
      justifiable dans la fiche du magasin.
- [ ] Revérifier `04-DATA-SAFETY.md` : une nouvelle donnée personnelle
      est-elle collectée depuis la dernière soumission (audio, paiement,
      notifications) ? Si oui, mettre à jour le formulaire **avant** de
      soumettre — le corriger après coup, une fois le rejet reçu, coûte des
      jours.
- [ ] Captures d'écran à jour si l'interface a changé de façon visible depuis
      les 6 visuels livrés le 9 octobre 2026 dans `visuels/`
      (`06-CAPTURES-ECRAN.md`).
- [ ] Note de version **en français, qui dit ce qui change pour la personne**,
      pas ce qui change dans le code (§7.3 du document de livraison).

## 3. Diffusion — trois canaux, dans cet ordre (§7.4)

1. **Interne** — l'équipe, à chaque compilation de la branche principale.
2. **Recette / test fermé** — groupe restreint, les cinq parcours du produit
   validés avant d'aller plus loin.
3. **Production, déploiement progressif** — commencer à une petite fraction
   des utilisateurs, suivre le taux de plantage, élargir ensuite. Une fois à
   100 %, un correctif de régression attend la prochaine revue — c'est le
   coût d'avoir sauté l'étape de progressivité, pas une option de confort.

## 4. Après publication

- [ ] Consigner ce qui est livré, ce qui ne l'est pas, et les écarts au
      simulateur dans `docs/ARBITRAGES.md` du dépôt — « dire ce qui n'est pas
      fait, un rapport qui tait une réserve est pire qu'un retard annoncé »
      (§7.7).
- [ ] Suivre le taux de plantage réel reste impossible tant qu'aucun fournisseur
      de télémétrie n'est branché (voir `04-DATA-SAFETY.md`, §7.6 du document
      de livraison) — **ce n'est pas un oubli de cette checklist, c'est une
      décision produit non tranchée** qui mérite d'être reprise avant une
      diffusion à grande échelle : sans elle, un plantage massif en production
      ne se voit que par les avis une étoile.
