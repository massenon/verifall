import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/domain/entities/course_enrollment.dart';
import 'package:verifall_app/domain/repositories/course_repository.dart';
import 'package:verifall_app/domain/usecases/lms/compteur_de_lecture.dart';

/// Le compteur de durée active.
///
/// « Le compteur de durée active de la leçon doit se figer en arrière-plan.
/// **C'est un test de cycle de vie, pas un test d'horloge.** » (06-TESTS §6.3)
///
/// Les battements sont provoqués à la main : un test qui attendrait une seconde
/// réelle par seconde comptée mettrait quarante-cinq secondes à vérifier une
/// règle qui tient en trois lignes.
void main() {
  late _DepotMinimal depot;

  setUp(() => depot = _DepotMinimal());

  test('il ne compte QUE au premier plan', () async {
    final CompteurDeLecture compteur = CompteurDeLecture(
      depot: depot,
      leconUuid: 'lecon-1',
      secondesRequises: 5,
    );
    await compteur.demarrer();

    compteur.battementDeTest();
    compteur.battementDeTest();
    expect(compteur.secondes, 2);

    // L'application passe en arriere-plan.
    await compteur.mettreEnPause();
    compteur.battementDeTest();
    compteur.battementDeTest();
    compteur.battementDeTest();

    // Rien n'a bouge : « le client peut annoncer moins — l'application met son
    // compteur en pause quand elle passe en arriere-plan, et c'est legitime.
    // Il ne peut JAMAIS annoncer plus. »
    expect(compteur.secondes, 2);

    compteur.reprendre();
    compteur.battementDeTest();
    expect(compteur.secondes, 3);

    await compteur.liberer();
  });

  test('le bouton dit ce qui manque, jamais un grise muet', () async {
    final CompteurDeLecture compteur = CompteurDeLecture(
      depot: depot,
      leconUuid: 'lecon-1',
      // `meta.secondes_requises` vient du SERVEUR. Le test passe une valeur qui
      // n'est PAS 45 : coder 45 quelque part ferait echouer ce test.
      secondesRequises: 3,
    );
    await compteur.demarrer();

    expect(compteur.secondesManquantes, 3);
    expect(compteur.peutValider, isFalse);

    compteur.battementDeTest();
    expect(compteur.secondesManquantes, 2);

    compteur
      ..battementDeTest()
      ..battementDeTest();
    expect(compteur.secondesManquantes, 0);
    expect(compteur.peutValider, isTrue);

    await compteur.liberer();
  });

  test('il reprend ou il en etait, y compris entre deux sessions', () async {
    depot.secondesEnregistrees['lecon-1'] = 30;

    final CompteurDeLecture compteur = CompteurDeLecture(
      depot: depot,
      leconUuid: 'lecon-1',
      secondesRequises: 45,
    );
    await compteur.demarrer();

    // Le serveur non plus ne remet pas son chronometre a zero : « quitter et
    // rouvrir suffirait a effacer le temps deja passe — et punirait exactement
    // les gens qui lisent en plusieurs fois, dans le transport ».
    expect(compteur.secondes, 30);
    expect(compteur.secondesManquantes, 15);

    await compteur.liberer();
  });

  test('la mise en pause enregistre ce qui a ete lu', () async {
    final CompteurDeLecture compteur = CompteurDeLecture(
      depot: depot,
      leconUuid: 'lecon-1',
      secondesRequises: 45,
    );
    await compteur.demarrer();
    compteur
      ..battementDeTest()
      ..battementDeTest();
    await compteur.mettreEnPause();

    expect(depot.secondesEnregistrees['lecon-1'], 2);

    await compteur.liberer();
  });
}

/// Un dépôt réduit à ce dont le compteur a besoin.
///
/// POURQUOI il n'implémente que deux méthodes utilement : le compteur vit dans
/// `domain/`, et « un cas d'usage doit être testable sans démarrer un widget ».
class _DepotMinimal implements CourseRepository {
  final Map<String, int> secondesEnregistrees = <String, int>{};

  @override
  Future<int> secondesLues(String leconUuid) async =>
      secondesEnregistrees[leconUuid] ?? 0;

  @override
  Future<void> enregistrerSecondesLues(String leconUuid, int secondes) async {
    secondesEnregistrees[leconUuid] = secondes;
  }

  @override
  Future<CatalogueLu> catalogue(FiltreCatalogue filtre) =>
      throw UnimplementedError();

  @override
  Future<PageDeCatalogue> pageSuivante(
    FiltreCatalogue filtre,
    String curseur,
  ) => throw UnimplementedError();

  @override
  Future<FicheLue> fiche(String slug) => throw UnimplementedError();

  @override
  Future<List<CourseEnrollment>> mesInscriptions() =>
      throw UnimplementedError();

  @override
  Future<CourseResume?> reprendre() => throw UnimplementedError();

  @override
  Future<CourseProgress> avancement(String slug) => throw UnimplementedError();

  @override
  Future<CourseEnrollment?> sInscrire(String slug) =>
      throw UnimplementedError();

  @override
  Future<LeconLue> lecon(String coursSlug, String leconUuid) =>
      throw UnimplementedError();

  @override
  Future<bool> terminerLecon(
    String coursSlug,
    String leconUuid,
    int secondes,
  ) => throw UnimplementedError();

  @override
  Future<void> marquerTelecharge(String slug) => throw UnimplementedError();

  @override
  Future<void> supprimerTelechargement(String slug) =>
      throw UnimplementedError();

  @override
  Future<int> octetsStockes(String slug) => throw UnimplementedError();
}
