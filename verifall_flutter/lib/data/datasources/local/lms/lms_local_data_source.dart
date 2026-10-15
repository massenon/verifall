import 'dart:convert';

import 'package:drift/drift.dart';

import '../database.dart';

/// Une entrée de cache : sa charge utile JSON et la date de sa capture.
class EntreeDeCache {
  const EntreeDeCache({
    required this.data,
    required this.meta,
    required this.lueLe,
    this.octets = 0,
  });

  /// La charge utile **telle que le serveur l'a envoyée**.
  final Object? data;
  final Map<String, Object?> meta;

  /// `fetched_at`. « Ce n'est pas décoratif : c'est lui qui alimente le bandeau
  /// "Données du `<date>`, actualisation impossible". »
  final DateTime lueLe;

  final int octets;
}

/// Le contrat du cache local de l'Académie.
///
/// POURQUOI une interface devant Drift : c'est la même raison que pour
/// `PendingOpsStore` au socle — « `flutter test` tourne sur la machine hôte, où
/// la bibliothèque native SQLite n'est pas forcément installée ». Or **le test
/// qui coupe le réseau et vérifie qu'un cours reste consultable est LE test de
/// ce parcours** : il ne peut pas dépendre d'une bibliothèque native.
///
/// L'interface décrit ce que le cache sait faire ; l'implémentation Drift la
/// remplit ; une doublure en mémoire la remplit aussi, et le hors ligne se teste
/// pour de vrai.
abstract interface class LmsCache {
  Future<EntreeDeCache?> lireCatalogue(String cle);

  Future<void> ecrireCatalogue(
    String cle,
    Object? data,
    Map<String, Object?> meta,
    DateTime lueLe,
  );

  Future<EntreeDeCache?> lireCours(String slug);

  Future<DateTime?> dateDeTelechargement(String slug);

  Future<void> ecrireCours(
    String slug,
    Object? data,
    Map<String, Object?> meta,
    DateTime lueLe,
  );

  Future<void> marquerTelecharge(String slug, DateTime quand);

  Future<void> supprimerTelechargement(String slug);

  Future<EntreeDeCache?> lireLecon(String uuid);

  Future<Set<String>> leconsEnCache(String coursSlug);

  Future<int> ecrireLecon({
    required String uuid,
    required String coursSlug,
    required Object? data,
    required Map<String, Object?> meta,
    required int secondesRequises,
    required DateTime lueLe,
  });

  Future<int> octetsDuCours(String slug);

  Future<int> lireSecondesActives(String leconUuid);

  Future<void> ecrireSecondesActives(String leconUuid, int secondes);

  Future<EntreeDeCache?> lireCertificat(String code);

  Future<void> ecrireCertificat(
    String code,
    Object? data,
    Map<String, Object?> meta,
    DateTime lueLe,
  );

  Future<EntreeDeCache?> lireSeances();

  Future<void> ecrireSeances(
    Object? data,
    Map<String, Object?> meta,
    DateTime lueLe,
  );
}

/// Le cache local de l'Académie.
///
/// # La règle qui gouverne ce fichier
///
/// **Trois données ne s'y trouvent jamais** (contrat §7.1) : le solde d'unités,
/// l'état d'une analyse en cours, et **le résultat d'un quiz**. « On relit cet
/// écran précisément parce qu'on attend un changement. » Une note affichée
/// depuis un cache serait une note fausse. Il n'y a donc, ici, ni méthode de
/// lecture ni méthode d'écriture pour un résultat de quiz — et pas davantage
/// pour ses questions, dont la simple demande démarre le chronomètre serveur.
///
/// # Pourquoi le JSON brut plutôt que des colonnes
///
/// Le contrat autorise les ajouts de champ **sans préavis** (§6.2). Stocker
/// l'entité recomposée perdrait, au premier passage par le cache, tout ce que
/// cette version du client ne sait pas encore lire.
class LmsLocalDataSource implements LmsCache {
  const LmsLocalDataSource(this._db);

  final AppDatabase _db;

  // --- Catalogue ------------------------------------------------------------

  @override
  Future<EntreeDeCache?> lireCatalogue(String cle) async {
    final LmsCatalogueEntree? ligne = await (_db.select(
      _db.lmsCatalogue,
    )..where((LmsCatalogue t) => t.cle.equals(cle))).getSingleOrNull();
    if (ligne == null) {
      return null;
    }
    return _decoder(ligne.chargeUtile, ligne.recupereLe);
  }

  @override
  Future<void> ecrireCatalogue(
    String cle,
    Object? data,
    Map<String, Object?> meta,
    DateTime lueLe,
  ) async {
    await _db
        .into(_db.lmsCatalogue)
        .insertOnConflictUpdate(
          LmsCatalogueCompanion.insert(
            cle: cle,
            chargeUtile: _encoder(data, meta),
            recupereLe: lueLe.millisecondsSinceEpoch,
          ),
        );
  }

  // --- Fiche d'un cours -----------------------------------------------------

  @override
  Future<EntreeDeCache?> lireCours(String slug) async {
    final LmsCoursEntree? ligne = await _ligneCours(slug);
    if (ligne == null) {
      return null;
    }
    return _decoder(ligne.chargeUtile, ligne.recupereLe, octets: ligne.octets);
  }

  @override
  Future<DateTime?> dateDeTelechargement(String slug) async {
    final LmsCoursEntree? ligne = await _ligneCours(slug);
    final int? quand = ligne?.telechargeLe;
    return quand == null ? null : DateTime.fromMillisecondsSinceEpoch(quand);
  }

  @override
  Future<void> ecrireCours(
    String slug,
    Object? data,
    Map<String, Object?> meta,
    DateTime lueLe,
  ) async {
    final String charge = _encoder(data, meta);
    await _db
        .into(_db.lmsCours)
        .insertOnConflictUpdate(
          LmsCoursCompanion.insert(
            slug: slug,
            chargeUtile: charge,
            // `meta` est deja dans la charge utile ; la colonne le garde a part
            // pour rester lisible depuis un outil de base de donnees.
            meta: jsonEncode(meta),
            recupereLe: lueLe.millisecondsSinceEpoch,
            octets: Value<int>(utf8.encode(charge).length),
          ),
        );
  }

  /// Marque un cours comme **explicitement téléchargé**.
  ///
  /// POURQUOI la distinction avec un cours simplement consulté : « c'est le
  /// seul cache explicitement demandé par l'utilisateur » (§5.11). Il survit à
  /// l'heure de durée de vie du catalogue, et il ne disparaît que sur demande.
  @override
  Future<void> marquerTelecharge(String slug, DateTime quand) async {
    await (_db.update(
      _db.lmsCours,
    )..where((LmsCours t) => t.slug.equals(slug))).write(
      LmsCoursCompanion(telechargeLe: Value<int>(quand.millisecondsSinceEpoch)),
    );
  }

  /// Supprime un cours téléchargé : sa fiche, ses leçons, ses compteurs.
  ///
  /// « Un cours téléchargé doit pouvoir se supprimer » (§5.11).
  @override
  Future<void> supprimerTelechargement(String slug) async {
    await _db.transaction(() async {
      final List<LmsLeconEntree> lecons = await (_db.select(
        _db.lmsLecon,
      )..where((LmsLecon t) => t.coursSlug.equals(slug))).get();
      for (final LmsLeconEntree lecon in lecons) {
        await (_db.delete(
          _db.lmsLectureActive,
        )..where((LmsLectureActive t) => t.leconUuid.equals(lecon.uuid))).go();
      }
      await (_db.delete(
        _db.lmsLecon,
      )..where((LmsLecon t) => t.coursSlug.equals(slug))).go();
      await (_db.delete(
        _db.lmsCours,
      )..where((LmsCours t) => t.slug.equals(slug))).go();
    });
  }

  // --- Leçons ---------------------------------------------------------------

  @override
  Future<EntreeDeCache?> lireLecon(String uuid) async {
    final LmsLeconEntree? ligne = await (_db.select(
      _db.lmsLecon,
    )..where((LmsLecon t) => t.uuid.equals(uuid))).getSingleOrNull();
    if (ligne == null) {
      return null;
    }
    final EntreeDeCache entree = _decoder(
      ligne.chargeUtile,
      ligne.recupereLe,
      octets: ligne.octets,
    );
    // `secondes_requises` est remis dans le `meta` restitue : c'est le serveur
    // qui l'a dit, et le relire du cache evite de coder 45 quelque part.
    return EntreeDeCache(
      data: entree.data,
      meta: <String, Object?>{
        ...entree.meta,
        'secondes_requises': ligne.secondesRequises,
      },
      lueLe: entree.lueLe,
      octets: entree.octets,
    );
  }

  @override
  Future<Set<String>> leconsEnCache(String coursSlug) async {
    final List<LmsLeconEntree> lignes = await (_db.select(
      _db.lmsLecon,
    )..where((LmsLecon t) => t.coursSlug.equals(coursSlug))).get();
    return lignes.map((LmsLeconEntree l) => l.uuid).toSet();
  }

  /// Rend le nombre d'octets réellement écrits.
  ///
  /// POURQUOI on mesure au lieu d'estimer : le téléchargement doit être
  /// « chiffré en taille » (§5.11), et « sur un forfait facturé au mégaoctet,
  /// un téléchargement silencieux est une facture ». Une taille mesurée est un
  /// fait ; une taille estimée serait un chiffre inventé.
  @override
  Future<int> ecrireLecon({
    required String uuid,
    required String coursSlug,
    required Object? data,
    required Map<String, Object?> meta,
    required int secondesRequises,
    required DateTime lueLe,
  }) async {
    final String charge = _encoder(data, meta);
    final int octets = utf8.encode(charge).length;
    await _db
        .into(_db.lmsLecon)
        .insertOnConflictUpdate(
          LmsLeconCompanion.insert(
            uuid: uuid,
            coursSlug: coursSlug,
            chargeUtile: charge,
            secondesRequises: secondesRequises,
            recupereLe: lueLe.millisecondsSinceEpoch,
            octets: Value<int>(octets),
          ),
        );
    return octets;
  }

  /// Le poids total de ce qui est stocké pour un cours.
  @override
  Future<int> octetsDuCours(String slug) async {
    final LmsCoursEntree? cours = await _ligneCours(slug);
    final List<LmsLeconEntree> lecons = await (_db.select(
      _db.lmsLecon,
    )..where((LmsLecon t) => t.coursSlug.equals(slug))).get();
    return (cours?.octets ?? 0) +
        lecons.fold<int>(0, (int total, LmsLeconEntree l) => total + l.octets);
  }

  // --- Compteur de lecture active -------------------------------------------

  @override
  Future<int> lireSecondesActives(String leconUuid) async {
    final LmsLectureActiveEntree? ligne =
        await (_db.select(_db.lmsLectureActive)
              ..where((LmsLectureActive t) => t.leconUuid.equals(leconUuid)))
            .getSingleOrNull();
    return ligne?.secondes ?? 0;
  }

  @override
  Future<void> ecrireSecondesActives(String leconUuid, int secondes) async {
    await _db
        .into(_db.lmsLectureActive)
        .insertOnConflictUpdate(
          LmsLectureActiveCompanion.insert(
            leconUuid: leconUuid,
            secondes: Value<int>(secondes),
            majLe: DateTime.now().millisecondsSinceEpoch,
          ),
        );
  }

  // --- Certificats ----------------------------------------------------------

  @override
  Future<EntreeDeCache?> lireCertificat(String code) async {
    final LmsCertificatEntree? ligne = await (_db.select(
      _db.lmsCertificat,
    )..where((LmsCertificat t) => t.code.equals(code))).getSingleOrNull();
    if (ligne == null) {
      return null;
    }
    return _decoder(ligne.chargeUtile, ligne.recupereLe);
  }

  @override
  Future<void> ecrireCertificat(
    String code,
    Object? data,
    Map<String, Object?> meta,
    DateTime lueLe,
  ) async {
    await _db
        .into(_db.lmsCertificat)
        .insertOnConflictUpdate(
          LmsCertificatCompanion.insert(
            code: code,
            chargeUtile: _encoder(data, meta),
            recupereLe: lueLe.millisecondsSinceEpoch,
          ),
        );
  }

  /// La liste entière, sous la clé réservée [cleListe].
  static const String cleListe = '__liste__';

  // --- Séances --------------------------------------------------------------

  @override
  Future<EntreeDeCache?> lireSeances() async {
    final LmsSeancesEntree? ligne = await (_db.select(
      _db.lmsSeances,
    )..where((LmsSeances t) => t.cle.equals(cleListe))).getSingleOrNull();
    if (ligne == null) {
      return null;
    }
    return _decoder(ligne.chargeUtile, ligne.recupereLe);
  }

  @override
  Future<void> ecrireSeances(
    Object? data,
    Map<String, Object?> meta,
    DateTime lueLe,
  ) async {
    await _db
        .into(_db.lmsSeances)
        .insertOnConflictUpdate(
          LmsSeancesCompanion.insert(
            cle: cleListe,
            chargeUtile: _encoder(data, meta),
            recupereLe: lueLe.millisecondsSinceEpoch,
          ),
        );
  }

  // --- Interne --------------------------------------------------------------

  Future<LmsCoursEntree?> _ligneCours(String slug) => (_db.select(
    _db.lmsCours,
  )..where((LmsCours t) => t.slug.equals(slug))).getSingleOrNull();

  static String _encoder(Object? data, Map<String, Object?> meta) =>
      jsonEncode(<String, Object?>{'data': data, 'meta': meta});

  static EntreeDeCache _decoder(String brut, int recupereLe, {int octets = 0}) {
    final Object? decode = jsonDecode(brut);
    final Map<String, Object?> enveloppe = decode is Map
        ? decode.map(
            (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
          )
        : const <String, Object?>{};
    final Object? meta = enveloppe['meta'];
    return EntreeDeCache(
      data: enveloppe['data'],
      meta: meta is Map
          ? meta.map(
              (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
            )
          : const <String, Object?>{},
      lueLe: DateTime.fromMillisecondsSinceEpoch(recupereLe),
      octets: octets,
    );
  }
}
