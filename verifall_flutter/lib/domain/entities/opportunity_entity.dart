import 'package:flutter/foundation.dart';

/// Les deux rubriques d'opportunités, et **les valeurs réelles du paramètre**.
///
/// # Le piège que ce type existe pour fermer
///
/// Le panneau du simulateur écrit `?scope=bj|intl`. Le contrat prévient :
///
/// > « C'est une notation abrégée signifiant "l'un ou l'autre" ; les valeurs
/// > réelles sont **`benin`** et **`international`**. Envoyer `bj` produit un
/// > `422` — pas un silence, mais autant le savoir avant d'écrire le
/// > client. » (§3.5)
///
/// `scope` est une **énumération fermée** : une valeur hors liste répond `422`,
/// pas une liste vide (§3.7). Recopier `bj` aurait donc cassé la rubrique
/// entière, et seulement à l'exécution.
enum PorteeOpportunite {
  benin('benin', 'Opportunités Bénin', 'Opportunité Bénin'),
  international(
    'international',
    'Opportunités Internationales',
    'Opportunité internationale',
  );

  const PorteeOpportunite(
    this.wireValue,
    this.libellePastille,
    this.libelleEcran,
  );

  /// Ce qui part dans `?scope=`.
  final String wireValue;

  /// Le libellé de la pastille de rubrique, tel que le simulateur l'écrit.
  final String libellePastille;

  /// Le titre de la barre supérieure du détail d'une offre.
  final String libelleEcran;

  static PorteeOpportunite? parse(String? raw) {
    for (final PorteeOpportunite portee in PorteeOpportunite.values) {
      if (portee.wireValue == raw) {
        return portee;
      }
    }
    return null;
  }
}

/// La famille visuelle d'un type d'opportunité.
///
/// POURQUOI une famille et pas une couleur par type : ce sont les quatre classes
/// du simulateur (`.opp-type`, `.contract`, `.study`, `.event`). Dix couleurs
/// pour dix types rendraient la liste illisible ; quatre familles se
/// reconnaissent d'un coup d'œil.
enum FamilleOpportunite {
  /// `.opp-type.contract` — bleu.
  contrat,

  /// `.opp-type.study` — violet.
  etude,

  /// `.opp-type.event` — orange.
  evenement,

  /// `.opp-type` sans modificateur — vert clair. C'est le style de base du
  /// simulateur, pas une couleur inventée pour les types qu'il ne montre pas.
  autre,
}

/// La taxonomie **fermée** des opportunités (§3.5 du contrat).
///
/// POURQUOI elle est fermée : « sur un champ libre, la même chose finit écrite
/// de six façons et le filtre cesse de fonctionner sans que personne s'en
/// aperçoive — il rend simplement moins de résultats. »
///
/// Le simulateur en montre huit ; le contrat en publie **dix** — il ajoute
/// `appel` (appel à candidature) et `autre`. Ces deux-là prennent la famille de
/// base, faute d'une classe visuelle dans le simulateur.
///
/// **Aucun libellé n'est écrit ici.** Le libellé affiché vient toujours de
/// `type_libelle` : « ajouter un cas côté serveur casserait tous les `switch` du
/// client » (§8).
enum TypeOpportunite {
  cdi('cdi', FamilleOpportunite.contrat),
  cdd('cdd', FamilleOpportunite.contrat),
  stage('stage', FamilleOpportunite.contrat),
  alternance('alternance', FamilleOpportunite.contrat),
  volontariat('volontariat', FamilleOpportunite.autre),
  bourse('bourse', FamilleOpportunite.etude),
  financement('financement', FamilleOpportunite.etude),
  conference('conference', FamilleOpportunite.evenement),
  appel('appel', FamilleOpportunite.autre),
  autre('autre', FamilleOpportunite.autre);

  const TypeOpportunite(this.wireValue, this.famille);

  /// Ce qui part dans `?type=`.
  final String wireValue;

  final FamilleOpportunite famille;

  static TypeOpportunite? parse(String? raw) {
    for (final TypeOpportunite type in TypeOpportunite.values) {
      if (type.wireValue == raw) {
        return type;
      }
    }
    return null;
  }
}

/// Un type **tel qu'il apparaît réellement dans les offres chargées**.
///
/// POURQUOI les pastilles de filtre se construisent là-dessus et non sur
/// [TypeOpportunite.values] : l'arbitrage n° 55 en fait une règle — « proposer
/// un type sans offre mène à une liste vide, et une liste vide derrière un
/// filtre passe pour un bug ».
///
/// Le libellé vient du serveur, la valeur sert au filtre.
@immutable
class TypeVu {
  const TypeVu({required this.type, required this.libelle});

  final TypeOpportunite type;
  final String libelle;

  @override
  bool operator ==(Object other) =>
      other is TypeVu && other.type == type && other.libelle == libelle;

  @override
  int get hashCode => Object.hash(type, libelle);
}

/// L'état d'échéance affiché, **déduit de ce que le serveur envoie**.
///
/// # La nuance qui compte
///
/// Ce n'est pas le client qui décide qu'une offre est fermée : c'est
/// `data.expiree`. Ce n'est pas lui qui compte les jours : c'est
/// `data.jours_restants`. Cette énumération ne fait que **choisir la formule et
/// la couleur** à partir de ces deux valeurs, ce qui est le travail d'un écran.
///
/// > « L'archivage est une commande planifiée ; le client ne l'applique
/// > **jamais** lui-même — une horloge de téléphone mal réglée ferait
/// > disparaître une offre encore ouverte, ou pire, présenterait comme
/// > candidatable une offre fermée. » (§10.5 du contrat)
///
/// Le simulateur, lui, compare des dates : il tourne sans serveur. Le
/// transposer serait la faute nommée en réserve E de la section 9.
enum EtatEcheance {
  /// Plus de 7 jours — ardoise.
  lointaine,

  /// 2 à 7 jours — orange.
  proche,

  /// `jours_restants == 1` — rouge.
  demain,

  /// `jours_restants == 0` — rouge.
  aujourdHui,

  /// `expiree == true` — rouge. L'offre reste consultable pendant sa période de
  /// grâce, puis disparaît.
  depassee,

  /// Le serveur n'a pas envoyé de compte à rebours. L'écran affiche la date
  /// seule, sans J-n : **inventer un décompte serait pire que ne rien dire**.
  inconnue,
}

/// Une opportunité du Hub.
@immutable
class Opportunite {
  const Opportunite({
    required this.slug,
    required this.titre,
    required this.expiree,
    required this.verifiee,
    this.chapo,
    this.corps,
    this.organisation,
    this.lieu,
    this.portee,
    this.type,
    this.typeLibelle,
    this.joursRestants,
    this.dateLimite,
    this.lienCandidature,
  });

  /// **Un slug**, jamais un entier (§4).
  final String slug;

  final String titre;

  /// **Vient du serveur, jamais d'une comparaison de dates locale.**
  final bool expiree;

  /// « Dit qu'**un humain de l'équipe a contrôlé l'annonce**. Ça ne se déduit
  /// d'aucun autre champ » (§10.5). C'est ce seul champ qui pilote la pastille
  /// « EMPLOYEUR VÉRIFIÉ ».
  final bool verifiee;

  final String? chapo;

  /// Le corps de l'offre, en prose.
  final String? corps;

  final String? organisation;
  final String? lieu;

  final PorteeOpportunite? portee;

  /// `null` quand le serveur envoie une valeur que cette version du client ne
  /// connaît pas. Ajouter une valeur est une évolution compatible (§6.2) :
  /// l'écran ne doit pas tomber, il montre l'offre sans pastille de type.
  final TypeOpportunite? type;

  /// Le libellé affichable, **rendu par le serveur**.
  final String? typeLibelle;

  /// **Vient du serveur.** Jamais calculé (§10.5).
  final int? joursRestants;

  final DateTime? dateLimite;

  /// L'adresse de candidature. Ouverte en **onglet système**, jamais en WebView
  /// maison. Absente = pas de bouton, plutôt qu'un bouton qui ne mène nulle
  /// part.
  final String? lienCandidature;

  /// L'état affiché de l'échéance.
  ///
  /// POURQUOI [expiree] l'emporte sur tout : c'est le champ que le serveur
  /// bascule, et lui seul. Un `jours_restants` positif sur une offre expirée
  /// serait une incohérence de données, pas une raison de rouvrir la
  /// candidature.
  EtatEcheance get etatEcheance {
    if (expiree) {
      return EtatEcheance.depassee;
    }
    final int? jours = joursRestants;
    if (jours == null) {
      return EtatEcheance.inconnue;
    }
    if (jours <= 0) {
      return EtatEcheance.aujourdHui;
    }
    if (jours == 1) {
      return EtatEcheance.demain;
    }
    return jours <= 7 ? EtatEcheance.proche : EtatEcheance.lointaine;
  }

  /// Une offre clôturée n'affiche **aucun bouton de candidature**.
  bool get candidatureOuverte => !expiree && lienCandidature != null;

  @override
  String toString() => 'Opportunite($slug)';
}

/// La fiche d'une offre : l'offre, **et l'avertissement qui l'accompagne
/// toujours**.
///
/// # Pourquoi l'avertissement est dans ce type et pas dans le widget
///
/// Le contrat tranche la réserve A.6 :
///
/// > « La fiche d'une opportunité porte **toujours** `meta.avertissement`.
/// > Systématique, pas conditionnel : **une offre frauduleuse ne se signale pas
/// > d'elle-même, et c'est la plus dangereuse.** »
///
/// Le client ne décide donc pas de le montrer ; il montre ce qu'il reçoit. Le
/// mettre dans le type plutôt que dans une condition d'affichage supprime
/// l'endroit où quelqu'un pourrait un jour écrire « sauf si conférence ».
@immutable
class FicheOpportunite {
  const FicheOpportunite({required this.opportunite, this.avertissement});

  final Opportunite opportunite;

  /// `meta.avertissement`, affiché tel quel. `null` uniquement si le serveur
  /// n'en envoie pas — auquel cas l'écran n'invente pas de texte à sa place.
  final String? avertissement;
}

/// Une page d'opportunités.
@immutable
class PageOpportunites {
  const PageOpportunites({
    required this.opportunites,
    this.curseurSuivant,
    this.recupereLe,
  });

  /// **L'ordre vient du serveur** : ouvertes d'abord, échéance la plus proche en
  /// tête, clôturées ensuite. Le retrier localement à partir des dates ferait
  /// diverger l'affichage et le curseur (03-ECRANS-05 §3.22).
  final List<Opportunite> opportunites;

  final String? curseurSuivant;

  final DateTime? recupereLe;

  bool get vientDuCache => recupereLe != null;
}
