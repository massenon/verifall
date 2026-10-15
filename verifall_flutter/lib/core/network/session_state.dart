import 'package:flutter/foundation.dart';

import '../../data/models/auth_token.dart';

/// Les **trois** états de session, et pas davantage (§1.3).
///
/// POURQUOI l'état « inconnu » existe : au démarrage, le temps de lire le
/// stockage sécurisé, on ne sait pas encore. Sans ce troisième état, l'écran de
/// connexion clignote une fraction de seconde au lancement de chaque session
/// déjà ouverte.
enum SessionStatus { inconnu, connecte, deconnecte }

/// L'état d'authentification, global et unique.
///
/// POURQUOI il est global alors que « un `autoDispose` par défaut » est la règle
/// (§1.3) : c'est l'exception motivée. Une session qui se rejette en changeant
/// d'écran déconnecterait l'utilisateur à chaque navigation.
@immutable
class SessionState {
  const SessionState._({
    required this.status,
    this.utilisateur,
    this.expireLe,
    this.suppressionDemandeeLe,
    this.suppressionSignaleeParLeServeur = false,
    this.compteInactif = false,
    this.viaDeveloperOverride = false,
  });

  const SessionState.inconnu() : this._(status: SessionStatus.inconnu);

  const SessionState.deconnecte() : this._(status: SessionStatus.deconnecte);

  /// Session rouverte depuis le stockage sécurisé : le jeton est là, le profil
  /// non.
  ///
  /// POURQUOI on ne fabrique pas un profil vide qui ressemblerait à un vrai :
  /// c'est `GET /me` qui rend le profil, et un `UtilisateurResume` aux champs
  /// vides finirait par s'afficher quelque part. [utilisateur] reste `null`, et
  /// c'est un fait, pas un oubli.
  const SessionState.restauree() : this._(status: SessionStatus.connecte);

  const SessionState.connecte({
    required UtilisateurResume utilisateur,
    DateTime? expireLe,
    DateTime? suppressionDemandeeLe,
    bool viaDeveloperOverride = false,
  }) : this._(
         status: SessionStatus.connecte,
         utilisateur: utilisateur,
         expireLe: expireLe,
         suppressionDemandeeLe: suppressionDemandeeLe,
         viaDeveloperOverride: viaDeveloperOverride,
       );

  final SessionStatus status;

  /// Le résumé de compte reçu avec le jeton (§1.2 du contrat).
  ///
  /// Il vaut `null` quand la session a été restaurée depuis le stockage
  /// sécurisé : le jeton s'y trouve, le profil non. **Ne pas le fabriquer** —
  /// c'est `GET /me` qui le rend, et c'est le travail de M1/M5.
  final UtilisateurResume? utilisateur;

  final DateTime? expireLe;

  /// Renseigné quand `GET /me` porte `data.suppression_demandee_le` (§1.7).
  ///
  /// POURQUOI il vit dans l'état de session et pas dans un écran : **toute**
  /// route répond alors `403 compte_en_suppression`, sauf `GET /me` et
  /// `POST /auth/logout`. C'est donc au routeur de trancher, une fois, et pas à
  /// trente écrans d'écrire trente messages.
  final DateTime? suppressionDemandeeLe;

  /// Vrai quand **une requête quelconque** a essuyé `403 compte_en_suppression`.
  ///
  /// POURQUOI ce booléen existe à côté de [suppressionDemandeeLe] : le 403 dit
  /// *que* le compte est en suppression, il ne dit pas **depuis quand**. Seul
  /// `GET /me` porte la date. Fabriquer un `DateTime.now()` pour remplir le
  /// champ ferait afficher un délai de rétractation faux — « trente jours à
  /// partir d'aujourd'hui » alors qu'il en reste peut-être trois.
  ///
  /// L'état est donc su sans la date, et l'écran d'état lit `GET /me` — qui
  /// reste servi (§1.7) — pour l'obtenir.
  final bool suppressionSignaleeParLeServeur;

  /// Vrai quand une requête a essuyé `403 compte_inactif`.
  ///
  /// Le compte existe et le jeton est valable, mais l'adresse n'a jamais été
  /// prouvée. Le contrat le traite **globalement** : c'est l'intercepteur
  /// d'authentification qui l'apprend, pas trente écrans (arbitrage n° 136).
  final bool compteInactif;

  /// Vrai quand la session vient de la surcharge de développement (§8.0).
  ///
  /// Sert à afficher un repère visible pendant le développement : une session de
  /// recette prise pour une vraie fait perdre une demi-journée.
  final bool viaDeveloperOverride;

  bool get isUnknown => status == SessionStatus.inconnu;

  bool get isSignedIn => status == SessionStatus.connecte;

  bool get isSignedOut => status == SessionStatus.deconnecte;

  bool get isBeingDeleted =>
      suppressionDemandeeLe != null || suppressionSignaleeParLeServeur;

  SessionState withDeletionRequestedAt(DateTime? at) => SessionState._(
    status: status,
    utilisateur: utilisateur,
    expireLe: expireLe,
    suppressionDemandeeLe: at,
    suppressionSignaleeParLeServeur: suppressionSignaleeParLeServeur,
    compteInactif: compteInactif,
    viaDeveloperOverride: viaDeveloperOverride,
  );

  /// Le `403 compte_en_suppression` essuyé par n'importe quelle requête.
  SessionState withDeletionReportedByServer() => SessionState._(
    status: status,
    utilisateur: utilisateur,
    expireLe: expireLe,
    suppressionDemandeeLe: suppressionDemandeeLe,
    suppressionSignaleeParLeServeur: true,
    compteInactif: compteInactif,
    viaDeveloperOverride: viaDeveloperOverride,
  );

  /// Le `403 compte_inactif` essuyé par n'importe quelle requête.
  SessionState withInactiveAccount() => SessionState._(
    status: status,
    utilisateur: utilisateur,
    expireLe: expireLe,
    suppressionDemandeeLe: suppressionDemandeeLe,
    suppressionSignaleeParLeServeur: suppressionSignaleeParLeServeur,
    compteInactif: true,
    viaDeveloperOverride: viaDeveloperOverride,
  );

  SessionState withUtilisateur(UtilisateurResume utilisateur) => SessionState._(
    status: status,
    utilisateur: utilisateur,
    expireLe: expireLe,
    suppressionDemandeeLe: suppressionDemandeeLe,
    suppressionSignaleeParLeServeur: suppressionSignaleeParLeServeur,
    compteInactif: compteInactif,
    viaDeveloperOverride: viaDeveloperOverride,
  );

  @override
  String toString() => 'SessionState(${status.name})';
}
