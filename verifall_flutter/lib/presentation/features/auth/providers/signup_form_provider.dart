import 'dart:async' show unawaited;

import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/telemetry/consentement_telemetrie_store.dart';
import '../../../../domain/entities/auth_consentements.dart';
import '../../../../domain/entities/auth_inscription.dart';
import '../../../../domain/repositories/auth_repository.dart';
import '../../../../domain/usecases/auth/regles_de_saisie.dart';
import 'auth_providers.dart';
import 'etat_auth.dart';

/// L'état de l'écran 03 — les sept champs et les trois consentements.
///
/// # Pourquoi les consentements vivent dans l'état et pas dans les widgets
///
/// Une case à cocher qui garde son état dans le widget se remet à `false` au
/// premier rebuild — ce qui, ici, effacerait un consentement sans que personne
/// ne le voie. Le RGPD demande de pouvoir prouver le consentement ; un état de
/// widget ne se prouve pas.
@immutable
class EtatInscription {
  const EtatInscription({
    this.prenom = '',
    this.nom = '',
    this.email = '',
    // Le simulateur pre-remplit l'indicatif du Benin. On le garde : c'est un
    // service rendu, pas une donnee inventee — la personne peut l'effacer.
    this.telephone = indicatifBenin,
    this.profession = '',
    this.motDePasse = '',
    this.codeParrainage = '',
    this.consentements = const AuthConsentements(),
    this.consentementTelemetrie = false,
    this.formulaire = const EtatAuth(),
  });

  /// L'indicatif pré-rempli, repris tel quel du simulateur.
  static const String indicatifBenin = '+229 ';

  final String prenom;
  final String nom;
  final String email;
  final String telephone;
  final String profession;
  final String motDePasse;
  final String codeParrainage;
  final AuthConsentements consentements;

  /// Consentement local de télémétrie — **ne part pas vers le serveur**.
  ///
  /// Il est séparé de [AuthConsentements] parce qu'il pilote si un incident
  /// quitte l'appareil, pas si un traitement est autorisé côté API. Le
  /// mélanger dans le même objet ferait voyager une décision purement locale.
  final bool consentementTelemetrie;

  final EtatAuth formulaire;

  EtatInscription copyWith({
    String? prenom,
    String? nom,
    String? email,
    String? telephone,
    String? profession,
    String? motDePasse,
    String? codeParrainage,
    AuthConsentements? consentements,
    bool? consentementTelemetrie,
    EtatAuth? formulaire,
  }) => EtatInscription(
    prenom: prenom ?? this.prenom,
    nom: nom ?? this.nom,
    email: email ?? this.email,
    telephone: telephone ?? this.telephone,
    profession: profession ?? this.profession,
    motDePasse: motDePasse ?? this.motDePasse,
    codeParrainage: codeParrainage ?? this.codeParrainage,
    consentements: consentements ?? this.consentements,
    consentementTelemetrie:
        consentementTelemetrie ?? this.consentementTelemetrie,
    formulaire: formulaire ?? this.formulaire,
  );

  /// POURQUOI aucun champ n'apparaît : cette classe porte un mot de passe.
  @override
  String toString() => 'EtatInscription(<masquée>)';
}

/// Le résultat d'une tentative d'inscription, du point de vue de l'écran.
sealed class ResultatInscription {
  const ResultatInscription();
}

/// Le compte est créé, **sans jeton** : le parcours enchaîne obligatoirement sur
/// la saisie du code (§10.1 du contrat).
class InscriptionAConfirmer extends ResultatInscription {
  const InscriptionAConfirmer(this.inscription);

  final AuthInscription inscription;
}

/// L'envoi n'a pas eu lieu, ou il a échoué. Le détail est dans l'état.
class InscriptionRefusee extends ResultatInscription {
  const InscriptionRefusee();
}

/// Le formulaire d'inscription.
///
/// # Les validations faites ici, et **seulement** celles-là
///
/// Le simulateur en fait quatre ; on en fait autant, adaptées aux champs du
/// contrat : nom et prénom d'au moins deux caractères, forme de l'adresse,
/// téléphone d'au moins huit caractères, profession non vide, **longueur** du
/// mot de passe, et **les deux consentements obligatoires cochés**.
///
/// Le mot de passe n'est vérifié **que sur sa longueur** : la politique réelle
/// (casse mixte, chiffre, symbole, absence des fuites connues) appartient au
/// serveur, et une règle du client qui n'est pas exactement celle du serveur
/// refuserait des mots de passe valides.
class SignupFormController extends Notifier<EtatInscription> {
  @override
  EtatInscription build() => const EtatInscription();

  AuthRepository get _repository => ref.read(authRepositoryProvider);

  void majPrenom(String v) => state = state.copyWith(prenom: v);

  void majNom(String v) => state = state.copyWith(nom: v);

  void majEmail(String v) => state = state.copyWith(email: v);

  void majTelephone(String v) => state = state.copyWith(telephone: v);

  void majProfession(String v) => state = state.copyWith(profession: v);

  void majMotDePasse(String v) => state = state.copyWith(motDePasse: v);

  void majCodeParrainage(String v) => state = state.copyWith(codeParrainage: v);

  /// POURQUOI la valeur voulue et non une bascule : une bascule perd sa cible
  /// quand l'état est reconstruit entre le geste et l'écriture, et l'on
  /// enregistrerait l'inverse du consentement donné.
  void majConsentement({
    bool? cgu,
    bool? confidentialite,
    bool? newsletter,
    bool? telemetrie,
  }) {
    state = state.copyWith(
      consentements: state.consentements.copyWith(
        cgu: cgu,
        confidentialite: confidentialite,
        newsletter: newsletter,
      ),
      consentementTelemetrie: telemetrie,
    );
  }

  /// Le premier défaut de saisie, ou `null`. Il devient un toast rouge qui
  /// **nomme précisément ce qui manque** — jamais « formulaire invalide ».
  String? premierDefaut() {
    if (!ReglesDeSaisie.nomValide(state.prenom) ||
        !ReglesDeSaisie.nomValide(state.nom)) {
      return 'Indiquez votre prénom et votre nom.';
    }
    if (!ReglesDeSaisie.emailValide(state.email)) {
      return 'Entrez une adresse e-mail valide.';
    }
    if (!ReglesDeSaisie.telephoneValide(state.telephone)) {
      return 'Indiquez un numéro de téléphone.';
    }
    if (!ReglesDeSaisie.professionValide(state.profession)) {
      return 'Indiquez votre profession.';
    }
    if (!ReglesDeSaisie.motDePasseAssezLong(state.motDePasse)) {
      return '${ReglesDeSaisie.longueurMotDePasse} caractères minimum '
          'pour le mot de passe.';
    }
    if (!state.consentements.obligatoiresAcceptes) {
      return 'Les deux consentements obligatoires sont requis.';
    }
    return null;
  }

  /// Envoie `POST /auth/register`.
  ///
  /// **Aucun jeton n'est installé ici** : le compte existe mais n'est pas
  /// activé. Émettre un jeton à l'inscription aurait donné un accès complet à
  /// quelqu'un qui n'a jamais prouvé tenir l'adresse déclarée.
  Future<ResultatInscription> soumettre() async {
    if (state.formulaire.envoiEnCours) {
      return const InscriptionRefusee();
    }
    final String? defaut = premierDefaut();
    if (defaut != null) {
      state = state.copyWith(formulaire: EtatAuth(messageGlobal: defaut));
      return const InscriptionRefusee();
    }

    state = state.copyWith(formulaire: state.formulaire.enCours());

    try {
      final AuthInscription inscription = await _repository.inscription(
        DemandeInscription(
          prenom: state.prenom,
          nom: state.nom,
          telephone: state.telephone,
          profession: state.profession,
          email: state.email,
          motDePasse: state.motDePasse,
          consentements: state.consentements,
          codeParrainage: state.codeParrainage,
        ),
      );
      // Le mot de passe est efface de l'état des que l'envoi a reussi : il n'a
      // plus aucune raison de rester en memoire, et l'ecran suivant est celui
      // du code.
      // Persistance locale du consentement de télémétrie. Elle n'attend pas
      // de réseau et ne doit pas bloquer la suite du parcours : `unawaited`
      // est délibéré — le coffre sécurisé peut prendre quelques dizaines de ms.
      if (state.consentementTelemetrie) {
        unawaited(ConsentementTelemetrieStore().ecrire(true));
      }
      state = state.copyWith(motDePasse: '', formulaire: const EtatAuth());
      return InscriptionAConfirmer(inscription);
    } on Failure catch (echec) {
      state = state.copyWith(
        formulaire: EtatAuth.depuisEchec(
          echec,
          phraseDeRepli: echec is NetworkFailure
              ? EtatAuth.phraseHorsLigne
              : EtatAuth.phraseIndisponible,
        ),
      );
      return const InscriptionRefusee();
    }
  }
}

final NotifierProvider<SignupFormController, EtatInscription>
signupFormProvider = NotifierProvider<SignupFormController, EtatInscription>(
  SignupFormController.new,
);
