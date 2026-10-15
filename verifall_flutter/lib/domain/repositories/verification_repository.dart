import 'package:flutter/foundation.dart';

import '../../data/datasources/remote/verify/verification_remote_source.dart';
import '../../data/models/api_envelope.dart';
import '../entities/verification_entite.dart';
import '../entities/verification_type_contenu.dart';

/// Le résultat d'une soumission.
///
/// Il porte **trois** informations, et chacune sert à un endroit précis :
/// la vérification créée, le solde recalculé, et le fait que la demande soit
/// partie ou soit restée en file.
@immutable
class SoumissionAboutie {
  const SoumissionAboutie({
    required this.verification,
    required this.solde,
    required this.acteUuid,
  });

  final Verification verification;

  /// `meta.solde`, **recalculé par le serveur**. Jamais une soustraction locale
  /// (§5.9 hors ligne : « le client ne débite ni ne crédite jamais d'unité »).
  final int? solde;

  /// L'identifiant de l'acte — celui qui a servi d'`Idempotency-Key`.
  final String acteUuid;
}

/// La demande n'a pas pu partir : elle est en file.
///
/// POURQUOI c'est un **résultat** et pas une erreur : l'écran doit dire « votre
/// demande partira dès le retour du réseau » et surtout **ne pas simuler une
/// attente** — pas de chronologie d'analyse tant que rien n'est parti (§7.5).
@immutable
class SoumissionMiseEnFile {
  const SoumissionMiseEnFile({required this.acteUuid});

  /// L'identifiant client, généré **par acte et non par tentative**, réutilisé à
  /// l'identique à chaque rejeu. C'est ce qui empêche d'être débité deux fois
  /// pour une seule question (§7.3).
  final String acteUuid;
}

/// Le résultat d'une soumission : partie, ou mise en file.
sealed class ResultatSoumission {
  const ResultatSoumission();
}

final class SoumissionEnvoyee extends ResultatSoumission {
  const SoumissionEnvoyee(this.aboutie);

  final SoumissionAboutie aboutie;
}

final class SoumissionEnAttente extends ResultatSoumission {
  const SoumissionEnAttente(this.enFile);

  final SoumissionMiseEnFile enFile;
}

/// Le dépôt des vérifications.
///
/// POURQUOI une interface : elle permet de tester la soumission — l'acte le plus
/// coûteux de l'application, celui où une réponse perdue débite deux fois —
/// sans monter de serveur ni de base native.
abstract interface class VerificationRepository {
  /// `POST /uploads` puis `POST /verifications`. **Deux appels, jamais un
  /// multipart unique** (arbitrage n° 72).
  ///
  /// [acteUuid] est généré **à l'acte** par l'appelant et part en
  /// `Idempotency-Key`. Toutes les reprises du même acte portent la même clé.
  Future<ResultatSoumission> soumettre({
    required String acteUuid,
    required TypeContenu type,
    required String affirmation,
    String? contexte,
    String? cheminFichierLocal,
    String? nomFichier,
  });

  /// `GET /verifications/{uuid}` — **le même endpoint pour le suivi et pour le
  /// rapport**.
  ///
  /// [depuisLeCache] n'est autorisé que pour un rapport déjà rendu : « l'état
  /// d'une analyse en cours ne doit JAMAIS venir du cache » (§7.1). C'est le
  /// dépôt qui fait respecter la règle, pas l'écran.
  Future<Verification> lire(String uuid, {bool autoriserLeCache = false});

  /// `GET /me/verifications` — pagination **par curseur**, filtres passés au
  /// serveur.
  ///
  /// POURQUOI aucun filtrage local : « le curseur ne porte pas les filtres »
  /// (§3.4). Filtrer localement ne filtrerait que la page chargée, et la
  /// deuxième page démentirait la première (arbitrage n° 82).
  Future<CursorPage<Verification>> lister({
    String? cursor,
    String? verdict,
    String? recherche,
    int limit,
  });

  /// La page servie **depuis Drift**, pour la lecture hors ligne.
  Future<List<Verification>> listerDepuisLeCache();

  /// L'instant du dernier rafraîchissement de la liste, pour le bandeau
  /// « Données du `<date>`, actualisation impossible ».
  Future<DateTime?> dateDuCacheDeLaListe();

  /// `GET /me/stats` — les quatre indicateurs de l'écran 12.
  ///
  /// POURQUOI ils ne se calculent pas sur la liste : « les indicateurs portent
  /// sur **tout l'historique**, jamais sur la page filtrée. Ce sont des repères
  /// sur son activité ; les faire suivre les filtres leur ferait changer de
  /// sens » (arbitrage n° 31). Et la pagination par curseur ne donne aucun
  /// total : il n'y a donc rien à compter côté client.
  Future<IndicateursHistorique> indicateurs();

  /// `POST /verifications/{uuid}/contest` — une **demande de contre-expertise**.
  ///
  /// Elle ne change pas l'état du rapport : « laisser un citoyen basculer son
  /// propre rapport en arbitrage offrirait à quiconque un bouton pour effacer un
  /// verdict qui le dérange » (arbitrage n° 71). Idempotent côté serveur.
  Future<void> contester(String uuid, {required String detail, String? motif});
}
