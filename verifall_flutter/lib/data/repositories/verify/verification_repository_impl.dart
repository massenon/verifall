import '../../../core/errors/failure.dart';
import '../../../core/logging/app_logger.dart';
import '../../../domain/entities/verification_entite.dart';
import '../../../domain/entities/verification_type_contenu.dart';
import '../../../domain/repositories/verification_repository.dart';
import '../../datasources/local/pending_ops.dart';
import '../../datasources/local/verify/verification_local_source.dart';
import '../../datasources/remote/verify/verification_remote_source.dart';
import '../../models/api_envelope.dart';
import '../../models/televersement.dart';

/// Le dépôt des vérifications.
///
/// C'est ici que vivent les deux mécaniques les plus délicates du parcours :
/// **la séquence de téléversement en deux temps** et **la mise en file d'un acte
/// qui coûte**.
class VerificationRepositoryImpl implements VerificationRepository {
  const VerificationRepositoryImpl({
    required this.distant,
    required this.local,
    required this.file,
    required this.journal,
  });

  /// Les quatre dependances sont injectees et **publiques**, comme celles de la
  /// file d'ecriture du socle : « la file est le composant le plus difficile a
  /// reproduire a la main, et ses tests doivent pouvoir relire ce qu'on lui a
  /// donne ». La soumission d'une verification est exactement du meme ordre.
  final VerificationRemoteSource distant;
  final VerificationLocalSource local;
  final PendingOpsStore file;
  final AppLogger journal;

  @override
  Future<ResultatSoumission> soumettre({
    required String acteUuid,
    required TypeContenu type,
    required String affirmation,
    String? contexte,
    String? cheminFichierLocal,
    String? nomFichier,
  }) async {
    String? televersement;

    // ── Temps 1 : le fichier, s'il y en a un ────────────────────────────────
    //
    // « Une video de vingt megaoctets sur une connexion instable echoue une
    // fois sur trois. Si le fichier voyageait avec la soumission, chaque
    // reprise le renverrait en entier. » (arbitrage n. 72)
    final TypeTeleversement? typeFichier = type.televersement;
    if (typeFichier != null && cheminFichierLocal != null) {
      try {
        final Televersement depose = await distant.televerser(
          type: typeFichier,
          cheminLocal: cheminFichierLocal,
          nomFichier: nomFichier ?? 'fichier',
        );
        televersement = depose.uuid;
      } on NetworkFailure {
        // Hors ligne AVANT meme d'avoir depose le fichier : on met l'acte en
        // file AVEC la copie locale. Le rejeu re-televersera, parce que le
        // televersement serveur expire au bout de deux heures.
        return _mettreEnFile(
          acteUuid: acteUuid,
          type: type,
          affirmation: affirmation,
          contexte: contexte,
          cheminFichierLocal: cheminFichierLocal,
        );
      }
    }

    // ── Temps 2 : la soumission, quelques centaines d'octets ────────────────
    try {
      final ReponseSoumission reponse = await distant.soumettre(
        acteUuid: acteUuid,
        type: type,
        affirmation: affirmation,
        contexte: contexte,
        televersement: televersement,
      );
      if (reponse.verification.terminee) {
        // Une reponse deja connue revient instantanement : elle est mise au
        // cache tout de suite, comme n'importe quel rapport rendu.
        await local.ecrireUne(reponse.verification);
      }
      return SoumissionEnvoyee(
        SoumissionAboutie(
          verification: reponse.verification,
          solde: reponse.solde,
          acteUuid: acteUuid,
        ),
      );
    } on NetworkFailure {
      return _mettreEnFile(
        acteUuid: acteUuid,
        type: type,
        affirmation: affirmation,
        contexte: contexte,
        cheminFichierLocal: cheminFichierLocal,
        televersement: televersement,
      );
    }
  }

  /// Met l'acte en file **avec sa clé d'idempotence déjà décidée**.
  ///
  /// POURQUOI on construit le [PendingOp] à la main au lieu d'appeler
  /// `PendingOpsQueue.enqueue` : cette dernière génère son propre identifiant.
  /// Or la clé de cet acte a été tirée **au moment où la personne a appuyé sur
  /// le bouton**, avant même la première tentative réseau. « Une clé regénérée à
  /// l'envoi ne protège de rien » — c'est le scénario qui débite deux fois pour
  /// une seule question (§5.6).
  ///
  /// Et **aucune unité n'est débitée localement** : le solde est un fait
  /// serveur (§5.9).
  Future<ResultatSoumission> _mettreEnFile({
    required String acteUuid,
    required TypeContenu type,
    required String affirmation,
    String? contexte,
    String? cheminFichierLocal,
    String? televersement,
  }) async {
    await file.enqueue(
      PendingOp(
        clientUuid: acteUuid,
        kind: PendingOpKind.soumettreVerification,
        endpoint: '/verifications',
        payload: <String, Object?>{
          'type': type.wireValue,
          'affirmation': affirmation,
          if (contexte != null && contexte.trim().isNotEmpty)
            'contexte': contexte.trim(),
          // On ne rejoue le televersement que s'il a REUSSI. Sinon la charge
          // part sans lui et le serveur repondra 422 : le rejeu doit passer par
          // un nouveau depot, d'ou la copie locale conservee ci-dessous.
          'televersement': ?televersement,
        },
        // Le media est COPIE, pas seulement reference : la personne peut
        // supprimer la photo de sa galerie entre-temps.
        localFilePath: cheminFichierLocal,
        createdAt: DateTime.now().toUtc(),
      ),
    );
    journal.info(
      LogChannel.pendingOps,
      'Soumission mise en file : elle partira au retour du réseau',
    );
    return SoumissionEnAttente(SoumissionMiseEnFile(acteUuid: acteUuid));
  }

  @override
  Future<Verification> lire(
    String uuid, {
    bool autoriserLeCache = false,
  }) async {
    try {
      final Verification distante = await distant.lire(uuid);
      await local.ecrireUne(distante);
      return distante;
    } on Failure {
      // Le cache n'est consulte QUE pour un rapport déjà rendu. « L'etat d'une
      // analyse en cours ne doit jamais venir du cache » : le lire ferait
      // croire a une analyse bloquee (contrat 7.1). C'est l'appelant qui sait
      // s'il consulte un rapport ou un suivi, d'ou le drapeau.
      if (!autoriserLeCache) {
        rethrow;
      }
      final Verification? enCache = await local.lireUne(uuid);
      if (enCache == null) {
        rethrow;
      }
      return enCache;
    }
  }

  @override
  Future<CursorPage<Verification>> lister({
    String? cursor,
    String? verdict,
    String? recherche,
    int limit = PageMeta.defaultLimit,
  }) async {
    final CursorPage<Verification> page = await distant.lister(
      cursor: cursor,
      verdict: verdict,
      recherche: recherche,
      limit: limit,
    );
    // Seule la PREMIERE page non filtree alimente le cache : c'est celle qu'on
    // relira hors ligne. Ecrire une page filtree ferait passer un filtre pour
    // l'historique entier.
    final bool premierePageSansFiltre =
        cursor == null &&
        (verdict == null || verdict.isEmpty) &&
        (recherche == null || recherche.trim().isEmpty);
    if (premierePageSansFiltre) {
      await local.ecrireLaListe(page.items);
    }
    return page;
  }

  @override
  Future<List<Verification>> listerDepuisLeCache() => local.lireLaListe();

  @override
  Future<DateTime?> dateDuCacheDeLaListe() => local.dateDeLaListe();

  @override
  Future<IndicateursHistorique> indicateurs() => distant.indicateurs();

  @override
  Future<void> contester(
    String uuid, {
    required String detail,
    String? motif,
  }) async {
    try {
      await distant.contester(uuid, detail: detail, motif: motif);
    } on NetworkFailure {
      // La contestation est idempotente cote serveur — « dix appels ne creent
      // qu'une demande » (arbitrage n. 71) — donc rejouable sans risque de
      // doublon. Elle n'exige pas de cle d'idempotence pour autant.
      await file.enqueue(
        PendingOp(
          clientUuid: 'contest-$uuid',
          kind: PendingOpKind.signaler,
          endpoint: '/verifications/$uuid/contest',
          payload: <String, Object?>{
            'detail': detail.trim(),
            if (motif != null && motif.isNotEmpty) 'motif': motif,
          },
          createdAt: DateTime.now().toUtc(),
        ),
      );
    }
  }
}
