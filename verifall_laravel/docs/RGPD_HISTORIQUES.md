# RGPD — historique des connexions et lectures du Hub

> Décision D7 du 18 octobre 2026, arbitrage n° 178. Cadre : RGPD, et loi
> béninoise n° 2017-20 portant Code du numérique (livre V, protection des
> données à caractère personnel ; autorité de contrôle : APDP).

## Fiches du registre des traitements

| | Historique des connexions | Historique des lectures du Hub |
|---|---|---|
| **Finalité** | Sécurité des comptes : détecter une prise de contrôle, répondre au support | Permettre à la personne de retrouver ce qu'elle a lu ; aider le support à la demande |
| **Base légale** | Intérêt légitime (art. 6.1.f), obligation de sécurité (art. 32) | Consentement (art. 6.1.a), séparé, non pré-coché, retirable |
| **Personnes** | Tout compte (citoyen, mentor, personnel) | Comptes citoyens ayant consenti |
| **Données** | Date, résultat, espace, méthode, famille de navigateur et de système, catégorie d'appareil, IP tronquée, empreinte HMAC de l'IP, pays (si fourni par le mandataire) | Date, rubrique, identifiant et titre du contenu, action |
| **Données exclues** | IP entière, User-Agent complet, géolocalisation fine | Tout contenu lu hors Hub ; toute lecture sans consentement |
| **Destinataires** | Administrateurs (permission `user.view`), la personne (export) | Idem ; jamais les mentors |
| **Conservation** | 12 mois | 90 jours, effacement immédiat au retrait du consentement |
| **Effacement** | Purge planifiée ; anonymisation du compte | Idem |
| **Droits** | Accès et portabilité (export) ; opposition : non, motif légitime impérieux de sécurité ; effacement avec le compte | Accès, portabilité, retrait du consentement, effacement |
| **Code** | `JournalDesConnexions`, `JournaliserLaConnexion`, table `historique_connexions` | `HistoriqueDuHub`, table `consultations_hub` |

## Analyse de proportionnalité (intérêt légitime)

1. **Intérêt** : protéger des comptes qui portent des unités payées, une
   identité vérifiée et, pour le personnel, des droits d'administration.
2. **Nécessité** : on ne peut pas reconnaître une connexion inhabituelle sans
   connaître les précédentes. Le minimum utile est retenu : la famille de
   navigateur et non sa chaîne complète, le réseau et non l'adresse.
3. **Équilibre** : la personne s'attend à ce qu'un service protège son compte ;
   la durée est bornée ; l'information figure dans la politique de
   confidentialité ; aucune décision automatisée n'est prise sur ces données.

## Pourquoi pas l'intérêt légitime pour le Hub

Une liste de lectures révèle des centres d'intérêt, parfois des données
sensibles (santé, opinions). Le profilage qui en résulte n'est pas attendu de
la personne et n'est pas nécessaire au service : seul le consentement convient.

## À faire hors du code

- Ajouter ces deux traitements à la politique de confidentialité.
- Les inscrire au registre tenu par le responsable de traitement, et le
  déclarer à l'APDP si la formalité s'applique.
