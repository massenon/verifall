import '../../../core/errors/failure.dart';
import '../../entities/profile_moi.dart';
import '../../repositories/profile_repository.dart';

/// Ce qu'un enregistrement de profil a produit.
enum ResultatEnregistrement {
  /// Le serveur a accepté, le profil rendu fait foi.
  enregistre,

  /// Le réseau manquait : l'acte est en file et **l'écran doit le dire**.
  misEnFile,
}

/// Enregistrer mon profil.
///
/// # Ce que ce cas d'usage refuse, et pourquoi
///
/// - **Un nom vide.** Le simulateur le refuse déjà, et c'est la seule validation
///   qui a du sens côté client : elle évite un aller-retour pour une faute
///   évidente. Toutes les autres arrivent en `422 validation`, avec `details`
///   indexé par champ, et se rendent sous le champ concerné.
/// - **L'adresse e-mail.** Elle n'est pas dans [ModificationProfil], donc elle
///   ne peut pas partir. `PATCH /me` ne l'accepte pas : elle sert d'identifiant
///   de connexion *et* de canal de récupération (arbitrage n° 6).
///
/// # Ce qu'il fait quand le réseau manque
///
/// Il met la modification en file plutôt que d'échouer : `PATCH /me` figure dans
/// la liste fermée des actes rejouables (§7.2), et la dernière écriture gagne
/// côté serveur. Mais il **rend l'information** à l'écran, qui doit annoncer que
/// l'envoi partira plus tard — « ne jamais faire croire qu'une écriture a
/// abouti ».
class EnregistrerLeProfil {
  const EnregistrerLeProfil(this._repository);

  final ProfileRepository _repository;

  Future<({ResultatEnregistrement resultat, Moi? profil})> call(
    ModificationProfil modification,
  ) async {
    _verifierLesNoms(modification);

    try {
      final Moi profil = await _repository.mettreAJour(modification);
      return (resultat: ResultatEnregistrement.enregistre, profil: profil);
    } on NetworkFailure {
      // SEUL l'echec de transport part en file. Un 422 est un refus motive : le
      // rejouer a l'identique produirait le meme refus, et l'ecran doit montrer
      // les messages du serveur sous les champs concernes.
      await _repository.mettreAJourPlusTard(modification);
      return (resultat: ResultatEnregistrement.misEnFile, profil: null);
    }
  }

  void _verifierLesNoms(ModificationProfil modification) {
    final Map<String, List<String>> erreurs = <String, List<String>>{};
    if (modification.prenom != null && modification.prenom!.trim().isEmpty) {
      erreurs['prenom'] = <String>['Le prenom ne peut pas être vide.'];
    }
    if (modification.nom != null && modification.nom!.trim().isEmpty) {
      erreurs['nom'] = <String>['Le nom ne peut pas être vide.'];
    }
    if (erreurs.isEmpty) {
      return;
    }
    // On leve le MEME type d'échec que le serveur : l'ecran n'a ainsi qu'un
    // seul chemin d'affichage d'erreur de champ, qu'elle vienne d'ici ou de la.
    throw ValidationFailure(
      fieldErrors: erreurs,
      serverMessage: 'Verifiez les champs indiques.',
    );
  }
}
