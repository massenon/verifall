import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/network/auth_controller.dart';
import '../../../../domain/entities/gamification_recompenses.dart';
import '../../../../domain/entities/profile_moi.dart';
import '../../../../domain/entities/stats_statistiques.dart';
import '../../../../domain/repositories/gamification_repository.dart';
import '../../../../domain/repositories/profile_repository.dart';
import '../../../../domain/usecases/profile/enregistrer_le_profil.dart';
import 'profile_dependencies.dart';
import '../../../../core/state/riverpod_retry.dart';
import '../../../../core/session/solde_unites.dart';

/// L'état de l'écran 25.
///
/// # Pourquoi quatre compteurs ne viennent pas du même appel
///
/// La maquette affiche quatre KPI : Vérifications · Badges · Jours de série ·
/// Unités. `GET /me` en sert **deux** — les unités et la série — et ignore les
/// deux autres. Les vérifications sont comptées par `GET /me/stats`, les badges
/// par `GET /me/gamification` : deux endpoints du périmètre M5.
///
/// La règle §2 du chantier interdit d'afficher un chiffre non mesuré. Deux
/// options existaient : retirer deux tuiles de la maquette, ou lire les deux
/// endpoints qui portent réellement ces chiffres. La seconde préserve l'écran
/// **et** l'honnêteté ; c'est celle-ci. Les deux lectures d'appoint sont **au
/// mieux** : si elles échouent, la tuile affiche un tiret plutôt que zéro — un
/// zéro ferait croire à un compte sans activité.
@immutable
class EtatProfil {
  const EtatProfil({
    required this.moi,
    this.niveau,
    this.verifications,
    this.badges,
  });

  final Moi moi;

  /// Le palier, rendu par `GET /me/gamification`. Le client ne le calcule pas :
  /// « 500 XP par niveau » est une règle métier serveur.
  final NiveauCitoyen? niveau;

  final int? verifications;
  final int? badges;

  /// Le solde d'unités, ou `null` quand le profil vient du cache.
  ///
  /// POURQUOI l'absence du solde suffit à reconnaître un profil de cache : il
  /// n'est jamais écrit en base locale (contrat §7.1), et il est toujours rendu
  /// par le serveur. `unites == null` **est** la marque du hors ligne, et elle
  /// est mécanique plutôt que déclarative.
  int? get solde => moi.unites;

  bool get vientDuCache => moi.unites == null;

  EtatProfil avecProfil(Moi profil) => EtatProfil(
    moi: profil,
    niveau: niveau,
    verifications: verifications,
    badges: badges,
  );
}

/// `profileProvider` — le nom vient du simulateur, et c'est un engagement.
///
/// `AsyncNotifier` et non `FutureProvider` : c'est l'un des trois écrans où l'on
/// **lit et écrit** (01-FONDATIONS §1.3).
///
/// `autoDispose` : « un provider qui survit à son écran garde en mémoire des
/// données de compte ; sur un téléphone partagé, c'est une fuite ».
/// Le réessai automatique de Riverpod est désactivé : voir
/// [sansReessaiAutomatique].
final AsyncNotifierProvider<ProfileNotifier, EtatProfil> profileProvider =
    AsyncNotifierProvider<ProfileNotifier, EtatProfil>(
      ProfileNotifier.new,
      isAutoDispose: true,
      retry: sansReessaiAutomatique,
    );

class ProfileNotifier extends AsyncNotifier<EtatProfil> {
  ProfileRepository get _profils => ref.read(profileRepositoryProvider);

  GamificationRepository get _recompenses =>
      ref.read(gamificationRepositoryProvider);

  @override
  Future<EtatProfil> build() => _charger();

  /// Relit tout. Employé par le tirer-pour-rafraîchir et au retour d'un écran
  /// qui a pu faire bouger le solde.
  Future<void> rafraichir() async {
    state = await AsyncValue.guard(_charger);
  }

  /// Enregistre le profil.
  ///
  /// Rend le résultat plutôt que d'afficher quoi que ce soit : c'est l'écran qui
  /// choisit le toast, parce que c'est lui qui a le contexte de rendu.
  Future<ResultatEnregistrement> enregistrer(
    ModificationProfil modification,
  ) async {
    final ({ResultatEnregistrement resultat, Moi? profil}) issue = await ref
        .read(enregistrerLeProfilProvider)(modification);

    final EtatProfil? courant = state.value;
    if (issue.profil != null && courant != null) {
      state = AsyncValue<EtatProfil>.data(courant.avecProfil(issue.profil!));
    }
    return issue.resultat;
  }

  Future<EtatProfil> _charger() async {
    final Moi moi = await _profils.moi();

    // Le compte en cours de suppression se traite AU NIVEAU DU ROUTEUR, pas
    // ecran par ecran : « toute route repond 403 compte_en_suppression, sauf
    // GET /me et POST /auth/logout » (contrat 1.7). `GET /me` etant l'un des
    // deux survivants, c'est ici — et seulement ici — que l'information entre
    // dans l'application.
    ref
        .read(authControllerProvider.notifier)
        .setDeletionRequestedAt(moi.suppressionDemandeeLe);

    // `GET /me` est une lecture RESEAU : elle alimente la source unique du solde
    // (arbitrage n° 135). Un profil servi depuis le cache porte `unites` a
    // `null`, et n'ecrase donc rien.
    ref.read(soldeUnitesProvider.notifier).relu(moi.unites);

    final _Appoints appoints = await _compteursDAppoint();

    return EtatProfil(
      moi: moi,
      niveau: appoints.niveau,
      verifications: appoints.verifications,
      badges: appoints.badges,
    );
  }

  /// Les compteurs que `GET /me` ne sert pas, **au mieux**.
  ///
  /// Un échec ici ne fait pas échouer l'écran : le profil est déjà lu, et une
  /// tuile à tiret vaut mieux qu'une page d'erreur.
  Future<_Appoints> _compteursDAppoint() async {
    int? verifications;
    int? badges;
    NiveauCitoyen? niveau;

    try {
      final Statistiques stats = await _profils.statistiques();
      verifications = stats.verifications;
    } on Failure {
      verifications = null;
    }

    try {
      final Recompenses recompenses = await _recompenses.recompenses();
      badges = recompenses.badgesObtenus;
      niveau = recompenses.niveau;
    } on Failure {
      badges = null;
    }

    return _Appoints(
      verifications: verifications,
      badges: badges,
      niveau: niveau,
    );
  }
}

/// Ce que les deux lectures d'appoint ont pu rendre.
@immutable
class _Appoints {
  const _Appoints({this.verifications, this.badges, this.niveau});

  final int? verifications;
  final int? badges;
  final NiveauCitoyen? niveau;
}
