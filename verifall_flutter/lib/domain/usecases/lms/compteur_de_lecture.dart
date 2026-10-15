import 'dart:async';

import '../../repositories/course_repository.dart';

/// Le compteur de **lecture active** d'une leçon.
///
/// # Ce qu'il est, et ce qu'il n'est pas
///
/// Ce n'est pas la vérité : c'est une **déclaration**. Le serveur note l'heure
/// d'ouverture quand la leçon est demandée, et retient le **minimum** entre ce
/// que le client annonce et ce qu'il a lui-même mesuré (§7.4) :
///
/// > Le client peut annoncer *moins* — l'application met son compteur en pause
/// > quand elle passe en arrière-plan, et c'est légitime. Il ne peut **jamais
/// > annoncer plus** : sinon il suffirait d'envoyer « j'ai lu pendant deux
/// > heures ».
///
/// # Pourquoi il ne compte que « au premier plan »
///
/// Parce que c'est exactement ce que le contrat appelle légitime, et parce que
/// l'inverse — compter en arrière-plan — reviendrait à déclarer plus que le
/// temps réellement lu. C'est un test de **cycle de vie**, pas un test
/// d'horloge (06-TESTS §6.3).
///
/// # Pourquoi le compteur est persisté
///
/// Le serveur ne remet pas son propre chronomètre à zéro quand on rouvre une
/// leçon : « quitter et rouvrir suffirait à effacer le temps déjà passé — et
/// punirait exactement les gens qui lisent en plusieurs fois, dans le
/// transport, ce qui est le cas d'usage principal d'une application mobile au
/// Bénin ». Le compteur local suit la même règle.
class CompteurDeLecture {
  CompteurDeLecture({
    required this.depot,
    required this.leconUuid,
    required this.secondesRequises,
  });

  final CourseRepository depot;
  final String leconUuid;

  /// `meta.secondes_requises` — **du serveur**, jamais une constante.
  final int secondesRequises;

  int _secondes = 0;
  bool _enPause = true;
  Timer? _timer;

  final StreamController<int> _flux = StreamController<int>.broadcast();

  /// Le nombre de secondes lues, à chaque battement.
  Stream<int> get flux => _flux.stream;

  int get secondes => _secondes;

  /// Il reste combien de secondes avant que la validation soit possible ?
  ///
  /// C'est ce nombre que le bouton affiche : « Encore 20 s de lecture », jamais
  /// un bouton grisé muet.
  int get secondesManquantes {
    final int reste = secondesRequises - _secondes;
    return reste > 0 ? reste : 0;
  }

  bool get peutValider => secondesManquantes == 0;

  /// Reprend le compte là où il s'était arrêté, y compris entre deux sessions.
  Future<void> demarrer() async {
    _secondes = await depot.secondesLues(leconUuid);
    _flux.add(_secondes);
    reprendre();
  }

  /// Appelé sur `AppLifecycleState.resumed`.
  void reprendre() {
    if (!_enPause) {
      return;
    }
    _enPause = false;
    _timer = Timer.periodic(const Duration(seconds: 1), (_) => _battement());
  }

  /// Appelé sur tout état qui n'est pas `resumed`.
  ///
  /// **Le compteur ne tourne pas en arrière-plan**, et c'est ce qui rend la
  /// déclaration honnête.
  Future<void> mettreEnPause() async {
    if (_enPause) {
      return;
    }
    _enPause = true;
    _timer?.cancel();
    _timer = null;
    await depot.enregistrerSecondesLues(leconUuid, _secondes);
  }

  /// Un battement, exposé pour que le test de cycle de vie n'ait pas à attendre
  /// une seconde réelle par seconde comptée.
  void battementDeTest() => _battement();

  void _battement() {
    if (_enPause) {
      return;
    }
    _secondes++;
    if (!_flux.isClosed) {
      _flux.add(_secondes);
    }
  }

  Future<void> liberer() async {
    await mettreEnPause();
    await _flux.close();
  }
}
