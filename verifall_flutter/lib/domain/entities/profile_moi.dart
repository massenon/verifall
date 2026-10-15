import 'package:flutter/foundation.dart';

/// Mon compte, et jamais celui de quelqu'un d'autre.
///
/// POURQUOI une entité distincte de l'auteur du socle (`Auteur`) : le contrat
/// sépare les deux exprès (§9, arbitrage n° 74). `MoiResource` porte adresse,
/// téléphone et ville ; elle n'est employée que là où le sujet de la requête est
/// aussi son propriétaire. Partout ailleurs, un compte se réduit à uuid, nom
/// d'affichage et initiales. Confondre les deux entités côté client, c'est
/// s'exposer à afficher un jour l'adresse de quelqu'un sur une fiche publique.
@immutable
class Moi {
  const Moi({
    required this.uuid,
    required this.prenom,
    required this.nom,
    required this.nomAffichage,
    required this.initiales,
    required this.email,
    this.telephone,
    this.profession,
    this.ville,
    this.bio,
    this.unites,
    this.xp,
    this.serieJours,
    this.inscritLe,
    this.suppressionDemandeeLe,
  });

  final String uuid;
  final String prenom;
  final String nom;

  /// Rendu **par le serveur**. Le recomposer localement à partir de [prenom] et
  /// [nom] donnerait un second résultat sur un nom composé, et deux écrans
  /// afficheraient deux noms pour la même personne.
  final String nomAffichage;

  /// Rendues par le serveur, pour la même raison que [nomAffichage].
  final String initiales;

  /// **Non modifiable.** Elle sert d'identifiant de connexion *et* de canal de
  /// récupération ; la changer sans vérifier la nouvelle boîte offrirait une
  /// prise de compte à qui emprunte un téléphone déverrouillé (arbitrage n° 6).
  /// Aucun endpoint de changement n'existe en v1 — l'opération se fait sur le
  /// site web.
  final String email;

  final String? telephone;

  /// C'est ce champ qui alimente la ligne « `<rôle>` · `<ville>` » de l'en-tête
  /// du profil. Le **rôle** au sens RBAC n'est jamais sérialisé par l'API (§9) ;
  /// ce que le simulateur appelle « rôle » est la profession déclarée.
  final String? profession;

  final String? ville;
  final String? bio;

  /// Le solde d'unités — **`null` quand il n'a pas été lu du réseau**.
  ///
  /// POURQUOI il est nullable alors que le serveur rend toujours un entier :
  /// c'est le seul moyen de tenir la règle du contrat (§7.1). Le solde « ne se
  /// lit jamais depuis le cache » ; un profil relu hors ligne le porte donc à
  /// `null`, et l'écran affiche un tiret. Un zéro ferait croire à un compte
  /// vide, et un chiffre d'il y a cinq minutes ferait croire à un paiement
  /// perdu.
  final int? unites;

  final int? xp;
  final int? serieJours;
  final DateTime? inscritLe;

  /// Renseigné pendant les trente jours de rétractation RGPD (§1.7 du contrat).
  ///
  /// `GET /me` reste servi pendant ce délai — c'est l'une des deux seules
  /// exceptions — précisément pour que l'application dise « votre compte sera
  /// effacé le … » au lieu de heurter un mur de 403.
  final DateTime? suppressionDemandeeLe;

  bool get estEnSuppression => suppressionDemandeeLe != null;

  /// La date d'effacement effectif, trente jours après la demande.
  ///
  /// POURQUOI elle se calcule ici alors qu'on ne calcule jamais ce que le
  /// serveur calcule : le serveur ne rend **pas** cette date, il rend la date de
  /// la demande. Le délai de trente jours, lui, est une règle écrite au contrat
  /// et non une donnée de compte — la déduire n'invente aucun fait.
  DateTime? get effacementPrevuLe =>
      suppressionDemandeeLe?.add(const Duration(days: 30));

  /// Le même profil, privé de son solde.
  ///
  /// Employé **avant écriture en cache** : c'est ce qui garantit qu'aucun solde
  /// périmé ne peut ressortir de la base locale.
  Moi sansSolde() => copyWith(effacerUnites: true);

  Moi copyWith({
    String? prenom,
    String? nom,
    String? telephone,
    String? profession,
    String? ville,
    String? bio,
    int? unites,
    bool effacerUnites = false,
  }) {
    return Moi(
      uuid: uuid,
      prenom: prenom ?? this.prenom,
      nom: nom ?? this.nom,
      nomAffichage: nomAffichage,
      initiales: initiales,
      email: email,
      telephone: telephone ?? this.telephone,
      profession: profession ?? this.profession,
      ville: ville ?? this.ville,
      bio: bio ?? this.bio,
      unites: effacerUnites ? null : (unites ?? this.unites),
      xp: xp,
      serieJours: serieJours,
      inscritLe: inscritLe,
      suppressionDemandeeLe: suppressionDemandeeLe,
    );
  }

  @override
  String toString() => 'Moi($uuid)';
}

/// Ce qu'un enregistrement de profil envoie — **et rien d'autre**.
///
/// # Pourquoi les champs sont nullables et pourquoi c'est le cœur du sujet
///
/// `PATCH` veut dire « ne change QUE ce que j'envoie » : les champs absents
/// gardent leur valeur côté serveur. Envoyer systématiquement les six champs
/// transformerait chaque enregistrement en écrasement complet — et effacerait la
/// profession de quelqu'un qui n'a modifié que sa ville.
///
/// # Pourquoi l'adresse e-mail n'y figure pas
///
/// `PATCH /me` ne l'accepte pas. Un champ envoyé qui n'existe pas au contrat est
/// un champ qui reviendra en `422`.
@immutable
class ModificationProfil {
  const ModificationProfil({
    this.prenom,
    this.nom,
    this.telephone,
    this.profession,
    this.ville,
    this.bio,
  });

  final String? prenom;
  final String? nom;
  final String? telephone;
  final String? profession;
  final String? ville;
  final String? bio;

  bool get estVide =>
      prenom == null &&
      nom == null &&
      telephone == null &&
      profession == null &&
      ville == null &&
      bio == null;

  /// Le corps de la requête, **sans les clés absentes**.
  Map<String, Object?> versCorps() => <String, Object?>{
    if (prenom != null) 'prenom': prenom,
    if (nom != null) 'nom': nom,
    if (telephone != null) 'telephone': telephone,
    if (profession != null) 'profession': profession,
    if (ville != null) 'ville': ville,
    if (bio != null) 'bio': bio,
  };
}
