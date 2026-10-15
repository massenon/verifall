import 'package:flutter/foundation.dart';

/// Un pack d'unités, **tel que le serveur le rend**.
///
/// POURQUOI aucune valeur n'est écrite côté client : « un barème en dur dans
/// l'application obligerait à publier une mise à jour sur les magasins pour
/// changer un prix » (03-ECRANS-06 §3.26). Le prix et la quantité sont lus en
/// base au moment de la recharge, et ce que l'écran affiche n'est qu'un reflet.
@immutable
class PackUnites {
  const PackUnites({
    required this.code,
    required this.libelle,
    required this.unites,
    required this.prixFcfa,
    required this.misEnAvant,
  });

  /// **Le seul champ que le client renvoie** au moment d'acheter. Ni le prix, ni
  /// la quantité : « un client modifié qui enverrait 99 999 unités n'obtient
  /// rien de plus ».
  final String code;

  final String libelle;
  final int unites;
  final int prixFcfa;

  /// Alimente la pastille d'accroche à droite de la ligne (« Populaire »,
  /// « Meilleur prix » dans le simulateur). Le serveur ne rend qu'un booléen :
  /// le texte de l'accroche n'est pas servi, et on ne l'invente pas.
  final bool misEnAvant;

  /// Le prix unitaire, arrondi — **une division, pas une donnée**.
  ///
  /// Il ne s'agit pas de recalculer ce que le serveur calcule : le serveur ne
  /// rend aucun prix unitaire, et celui-ci est une lecture de deux chiffres
  /// servis, présentée pour comparer les packs entre eux.
  int get prixParUnite => unites == 0 ? 0 : (prixFcfa / unites).round();
}

/// Le portefeuille : un solde **frais**, et la grille tarifaire.
///
/// Les deux voyagent ensemble parce que l'écran les affiche ensemble, et que
/// « deux appels pour un écran qu'on ouvre en attendant un taxi, c'est une
/// seconde de trop ».
@immutable
class Portefeuille {
  const Portefeuille({
    required this.solde,
    required this.packs,
    required this.luLe,
  });

  /// **Jamais servi depuis le cache** (contrat §7.1). Cette entité n'est donc
  /// jamais persistée en base locale, et [solde] n'est pas nullable : un
  /// portefeuille qui existe est un portefeuille lu du réseau.
  final int solde;

  final List<PackUnites> packs;

  /// L'instant de la lecture. Sert à dater l'affichage, jamais à autoriser une
  /// relecture depuis le cache.
  final DateTime luLe;
}

/// Une ligne du relevé.
///
/// `soldeApres` est **stocké côté serveur**, pas recomposé : « un relevé se lit
/// comme il a été écrit ». Recomposer les soldes à l'affichage donnerait, au
/// premier écart de comptabilité, une histoire cohérente et fausse plutôt
/// qu'une incohérence visible.
@immutable
class MouvementUnite {
  const MouvementUnite({
    required this.uuid,
    required this.delta,
    required this.libelle,
    this.soldeApres,
    this.motif,
    this.motifLibelle,
    this.date,
  });

  final String uuid;
  final int delta;

  /// Le libellé rédigé par le serveur, affiché tel quel.
  final String libelle;

  final int? soldeApres;
  final String? motif;

  /// Le libellé du motif **vient du serveur** : le couple valeur technique /
  /// libellé interdit de fabriquer un libellé côté client.
  final String? motifLibelle;

  final DateTime? date;

  bool get estUnCredit => delta > 0;

  /// Une ligne à zéro n'est pas une anomalie : c'est une réponse déjà connue,
  /// rendue et **non facturée** (arbitrage n° 25). Sans cette mention, une
  /// gratuité passerait pour un bogue.
  bool get estNonFacturee => delta == 0;
}

/// Ce que rend `POST /me/wallet/topup` : une demande **ouverte**, pas un crédit.
///
/// > « La réponse est `201` et le solde ne bouge pas : il ne bougera qu'au
/// > rappel signé de l'opérateur. »
@immutable
class DemandeRecharge {
  const DemandeRecharge({
    required this.uuid,
    required this.statut,
    required this.statutLibelle,
    this.operateur,
    this.montantFcfa,
    this.unites,
  });

  final String uuid;
  final String statut;

  /// Rendu par le serveur. Le client n'a aucune table de correspondance des
  /// statuts de paiement, et c'est délibéré.
  final String statutLibelle;

  /// **Déduit du numéro par le serveur** (réserve C.7). Le client ne le choisit
  /// pas et ne l'envoie pas : « un client qui choisirait son opérateur pourrait
  /// viser celui dont la vérification de signature est la plus permissive. »
  final String? operateur;

  final int? montantFcfa;
  final int? unites;
}

/// Une page du relevé, **par curseur**.
///
/// POURQUOI le domaine porte son propre type de page plutôt que le `CursorPage`
/// du socle : « `domain/` ne dépend de rien » (01-FONDATIONS §1.2). Le
/// `CursorPage` vit dans `data/models/`, et l'importer ici renverserait les deux
/// règles de dépendance non négociables. La conversion se fait dans
/// l'implémentation du dépôt, qui est en `data/` et a le droit de connaître les
/// deux.
///
/// POURQUOI il n'y a ni numéro de page ni total : « sur un fil qui bouge, le
/// décalage ment ». Le curseur est **opaque** — renvoyé tel quel, jamais lu,
/// jamais fabriqué.
@immutable
class PageDeMouvements {
  const PageDeMouvements({
    required this.mouvements,
    required this.curseurSuivant,
    required this.encoreDesPages,
  });

  static const PageDeMouvements vide = PageDeMouvements(
    mouvements: <MouvementUnite>[],
    curseurSuivant: null,
    encoreDesPages: false,
  );

  final List<MouvementUnite> mouvements;
  final String? curseurSuivant;
  final bool encoreDesPages;

  bool get estDerniere => !encoreDesPages || curseurSuivant == null;
}
