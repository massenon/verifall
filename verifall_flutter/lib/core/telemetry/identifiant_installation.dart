import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:uuid/uuid.dart';

import '../network/local_data_purger.dart';

/// L'identifiant **anonyme** d'installation.
///
/// # Ce qu'il est, et surtout ce qu'il n'est pas
///
/// Il répond à « est-ce le même appareil ? », jamais à « qui est-ce ? ». C'est
/// ce que le §7.6 autorise, et dans ces termes : « un identifiant d'installation
/// anonyme suffit à regrouper les incidents d'un même appareil. Il se régénère à
/// la désinstallation, et **il se purge à la déconnexion** — comme le reste. »
///
/// Il n'est donc jamais l'identifiant du compte, jamais dérivé de l'adresse,
/// jamais dérivé d'un identifiant matériel. Un UUID v4, tiré au hasard.
///
/// # Pourquoi la purge à la déconnexion compte vraiment ici
///
/// « Sur un téléphone partagé — le cas ordinaire au Bénin (arbitrage n° 59) —
/// un cache de lecture oublié est une fuite de données. » Le raisonnement vaut
/// pour cet identifiant : conservé après la déconnexion, il rattacherait les
/// incidents de la personne suivante à la même trace que la précédente. Deux
/// utilisateurs successifs deviendraient un seul, dans un journal que ni l'un ni
/// l'autre ne peut consulter.
///
/// # Pourquoi le stockage sécurisé
///
/// Ce n'est pas un secret, et il n'a pas besoin de chiffrement. On y va parce
/// que c'est le seul stockage clé-valeur déjà présent — `SecureTokenStore` s'en
/// sert — et qu'ajouter une seconde mécanique de persistance pour une chaîne de
/// 36 caractères coûterait plus qu'elle ne rapporte.
class IdentifiantDInstallation {
  IdentifiantDInstallation({FlutterSecureStorage? stockage, Uuid? tirage})
    : _stockage = stockage ?? const FlutterSecureStorage(),
      _tirage = tirage ?? const Uuid();

  static const String _cle = 'vf_installation_id';

  final FlutterSecureStorage _stockage;
  final Uuid _tirage;

  /// Lit l'identifiant, ou en tire un nouveau et le conserve.
  ///
  /// En cas d'échec du stockage — plateforme sans coffre, refus de l'appareil —
  /// on rend un identifiant **éphémère** plutôt que de lever : une télémétrie
  /// indisponible ne doit pas empêcher l'application de démarrer. Les incidents
  /// de cette session ne se regrouperont pas avec les précédents, ce qui est un
  /// moindre mal.
  Future<String> lireOuCreer() async {
    try {
      final String? existant = await _stockage.read(key: _cle);

      if (existant != null && existant.isNotEmpty) {
        return existant;
      }

      final String neuf = _tirage.v4();
      await _stockage.write(key: _cle, value: neuf);

      return neuf;
    } on Object catch (_) {
      return _tirage.v4();
    }
  }

  /// Efface l'identifiant. À appeler à la déconnexion, avec le reste.
  Future<void> oublier() async {
    try {
      await _stockage.delete(key: _cle);
    } on Object catch (_) {
      // Un effacement impossible ne doit pas faire échouer une déconnexion :
      // la personne serait retenue sur un appareil qu'elle veut quitter.
    }
  }
}

/// L'identifiant d'installation, vu comme une donnée locale à purger.
///
/// Il en EST une : le §7.6 dit qu'« il se purge à la déconnexion, comme le
/// reste ». Passer par `LocalDataPurger` plutôt que d'ajouter un appel dans
/// `signOut` a deux effets, et les deux comptent :
///
///   · la déconnexion garde **un seul** point d'effacement, celui que le
///     contrat décrit — un second appel ailleurs serait la moitié qu'on oublie
///     le jour où l'on ajoute un troisième chemin de déconnexion ;
///   · les tests substituent déjà ce joint. Sans lui, une déconnexion de test
///     appelait le vrai coffre de la plateforme, qui ne répond pas hors
///     appareil — et le parcours M1 s'arrêtait sur un délai de dix minutes.
class PurgeDeLIdentifiant implements LocalDataPurger {
  const PurgeDeLIdentifiant(this._identifiant);

  final IdentifiantDInstallation _identifiant;

  @override
  Future<void> purgeAll() => _identifiant.oublier();
}
