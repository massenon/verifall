import 'package:flutter_riverpod/flutter_riverpod.dart';

/// L'effacement des données locales.
///
/// POURQUOI c'est une interface déclarée dans `core/` et implémentée dans
/// `data/` : `core/` n'a pas à connaître Drift, et la purge doit pouvoir être
/// vérifiée en test sans démarrer une base. C'est `main.dart` — la racine de
/// composition — qui relie les deux.
///
/// POURQUOI il n'existe **pas** d'implémentation neutre par défaut : une purge
/// qui ne purge rien, en silence, est une fuite de données sur un téléphone
/// partagé. Mieux vaut un démarrage qui échoue bruyamment qu'un cache oublié.
/// Le dossier des **copies locales de médias**, sous le stockage de support de
/// l'application.
///
/// # Pourquoi ce nom est déclaré au socle et pas dans le parcours qui l'écrit
///
/// Une soumission de vérification mise en file garde **une copie du fichier** :
/// « l'utilisateur peut supprimer la photo de sa galerie entre-temps », et « le
/// téléversement serveur expire au bout de deux heures ». M2 écrit ces copies ;
/// la **purge**, elle, appartient à M1 et au socle. Deux constantes écrites
/// séparément auraient fini par diverger — et la moitié oubliée d'une purge est
/// une fuite de données sur un téléphone partagé. Arbitrage n° 140.
const String dossierDesMediasEnFile = 'medias';

abstract interface class LocalDataPurger {
  /// Efface **tout ce qui est écrit sur l'appareil** : la base locale — cache de
  /// lecture et file d'écriture — **et les copies locales de médias**.
  ///
  /// Appelée à la déconnexion, sur `401 non_authentifie`, et à la suppression du
  /// compte. Le contrat le rappelle dans son aide-mémoire : « purger
  /// `flutter_secure_storage` **et** la base locale à la déconnexion. Sur un
  /// téléphone partagé, un cache de lecture oublié est une fuite de données. »
  Future<void> purgeAll();
}

final Provider<LocalDataPurger> localDataPurgerProvider =
    Provider<LocalDataPurger>(
      (Ref ref) => throw StateError(
        'localDataPurgerProvider doit être surcharge au demarrage. '
        'Une purge par defaut qui ne purge rien serait une fuite.',
      ),
    );

/// Enchaîne plusieurs purgeurs, et les exécute TOUS.
///
/// # Pourquoi la composition plutôt qu'un purgeur qui sait tout faire
///
/// Ce qu'il faut effacer à la déconnexion vient de couches qui ne se connaissent
/// pas : la base locale appartient à `data/`, l'identifiant anonyme de
/// télémétrie à `core/telemetry/`. Faire connaître l'un à l'autre romprait les
/// deux règles de dépendance du §1.2. La racine de composition, elle, a le droit
/// de connaître les deux — c'est sa seule raison d'être.
///
/// # Pourquoi chaque échec est ATTRAPÉ
///
/// « La moitié oubliée d'une purge est une fuite de données sur un téléphone
/// partagé. » Si le premier purgeur lève, laisser l'exception remonter
/// empêcherait les suivants de tourner : un échec sur la base locale
/// laisserait l'identifiant de télémétrie en place, et la personne suivante
/// hériterait de la trace de la précédente.
///
/// On les lance donc tous, et l'on relance à la FIN le premier échec rencontré —
/// pour que l'appelant le voie, comme le fait déjà `signOut`.
class PurgeurCompose implements LocalDataPurger {
  const PurgeurCompose(this._purgeurs);

  final List<LocalDataPurger> _purgeurs;

  @override
  Future<void> purgeAll() async {
    Object? premierEchec;
    StackTrace? premierePile;

    for (final LocalDataPurger purgeur in _purgeurs) {
      try {
        await purgeur.purgeAll();
      } on Object catch (echec, pile) {
        premierEchec ??= echec;
        premierePile ??= pile;
      }
    }

    if (premierEchec != null) {
      Error.throwWithStackTrace(premierEchec, premierePile!);
    }
  }
}
