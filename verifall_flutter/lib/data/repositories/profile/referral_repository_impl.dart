import '../../../core/errors/failure.dart';
import '../../../domain/entities/referral_parrainage.dart';
import '../../../domain/repositories/referral_repository.dart';
import '../../datasources/local/profile/compte_local_datasource.dart';
import '../../datasources/remote/profile/compte_remote_datasource.dart';
import '../../models/profile/parrainage_model.dart';

/// Le parrainage.
///
/// # Pourquoi ce cache-là est le plus important du parcours
///
/// Le QR est généré **nativement**, à partir du lien rendu par l'API : « il doit
/// marcher hors ligne, en présentiel ». Montrer un code à quelqu'un dans une
/// cour d'école ne doit pas dépendre du réseau. Sans ce cache, l'écran le plus
/// souvent ouvert sans connexion serait précisément celui qui n'aurait rien à
/// montrer.
class ReferralRepositoryImpl implements ReferralRepository {
  const ReferralRepositoryImpl({required this.distant, required this.local});

  // Les dependances sont injectees et PUBLIQUES, comme la file d'ecriture du
  // socle (`PendingOpsQueue`) : un test doit pouvoir relire ce qu'on lui a
  // donne, et un champ prive nomme differemment de son parametre nomme n'est
  // pas exprimable en Dart sans dupliquer l'ecriture.
  final CompteRemoteDataSource distant;
  final CompteLocalDataSource local;

  @override
  Future<Parrainage> parrainage({bool forcerLeReseau = false}) async {
    try {
      final Parrainage frais = await distant.parrainage();
      await local.ecrire(
        RessourceDeCompte.parrainage,
        ParrainageModel.versJson(frais),
      );
      return frais;
    } on Failure {
      if (forcerLeReseau) {
        rethrow;
      }
      final EntreeDeCache? entree = await local.lire(
        RessourceDeCompte.parrainage,
      );
      if (entree == null) {
        rethrow;
      }
      // La date de lecture est celle du cache : les INDICATEURS sont donc
      // dates, et l'ecran le dit. Le code et le lien, eux, ne perimant pas,
      // c'est ce qui rend le QR utilisable hors ligne sans mentir.
      return ParrainageModel.depuisJson(
        entree.contenu,
        luLe: entree.recupereLe,
      );
    }
  }

  @override
  Future<Invitation> inviter() => distant.inviter();
}
