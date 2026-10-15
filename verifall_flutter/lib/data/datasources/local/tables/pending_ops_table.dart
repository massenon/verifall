import 'package:drift/drift.dart';

/// L'état d'une opération dans la file d'écriture.
///
/// POURQUOI `enPanne` existe et n'est pas un simple retrait : « une file qui
/// abandonne en silence fait disparaître le travail de quelqu'un »
/// (05-HORS-LIGNE §5.7). La personne doit pouvoir réessayer ou abandonner
/// explicitement.
enum PendingOpStatus {
  /// En attente d'un créneau de rejeu.
  enAttente,

  /// Envoi en cours. Une seule opération à la fois.
  enCours,

  /// Tentatives épuisées, ou refus définitif du serveur. **Visible**, jamais
  /// silencieuse.
  enPanne,
}

/// La file d'écriture hors ligne.
///
/// Structure imposée par la note d'architecture — `uuid client`, `op`,
/// `payload`, `created_at` — plus ce qu'il faut pour qu'elle soit exploitable :
/// `attempts`, `last_error`, `status` (05-HORS-LIGNE §5.7).
@DataClassName('PendingOpRow')
class PendingOps extends Table {
  /// L'identifiant client, généré **au moment où l'utilisateur agit** et non au
  /// moment de l'envoi.
  ///
  /// C'est lui qui part en `Idempotency-Key` sur les deux actes qui coûtent
  /// (`POST /verifications`, `POST /me/quiz/{uuid}/attempt`). « Une clé
  /// regénérée à l'envoi ne protège de rien » — c'est le scénario qui débite
  /// quelqu'un deux fois pour une seule question.
  TextColumn get clientUuid => text().withLength(min: 1, max: 128)();

  /// Le nom logique de l'acte (voir `PendingOpKind`).
  TextColumn get op => text()();

  /// Le chemin d'API visé, gardé tel quel : il porte le slug ou l'uuid de la
  /// ressource et n'a pas à être reconstruit au rejeu.
  TextColumn get endpoint => text()();

  TextColumn get method => text().withDefault(const Constant('POST'))();

  /// La charge utile, sérialisée en JSON.
  TextColumn get payload => text()();

  /// La copie locale du fichier, pour une soumission de vérification.
  ///
  /// POURQUOI la file garde le fichier et pas seulement son identifiant : le
  /// téléversement **expire au bout de deux heures** côté serveur, et un rejeu
  /// au-delà doit re-téléverser (04-RESEAU §4.10).
  TextColumn get localFilePath => text().nullable()();

  DateTimeColumn get createdAt => dateTime()();

  IntColumn get attempts => integer().withDefault(const Constant(0))();

  /// Le prochain créneau de rejeu — c'est ici que vit le recul exponentiel.
  DateTimeColumn get nextAttemptAt => dateTime().nullable()();

  /// Le dernier motif d'échec, **déjà expurgé**, pour l'afficher dans le détail
  /// du bandeau.
  TextColumn get lastError => text().nullable()();

  TextColumn get status => textEnum<PendingOpStatus>().withDefault(
    Constant(PendingOpStatus.enAttente.name),
  )();

  @override
  Set<Column<Object>> get primaryKey => <Column<Object>>{clientUuid};
}
