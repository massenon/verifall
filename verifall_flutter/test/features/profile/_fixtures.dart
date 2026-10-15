import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:verifall_app/core/config/config_providers.dart';
import 'package:verifall_app/core/logging/app_logger.dart';
import 'package:verifall_app/core/network/api_client.dart';
import 'package:verifall_app/core/network/connectivity_providers.dart';
import 'package:verifall_app/core/network/network_providers.dart';
import 'package:verifall_app/core/network/token_store.dart';
import 'package:verifall_app/data/datasources/local/pending_ops.dart';
import 'package:verifall_app/data/datasources/local/profile/compte_local_datasource.dart';
import 'package:verifall_app/presentation/features/profile/providers/profile_dependencies.dart';
import 'package:verifall_app/presentation/features/profile/providers/settings_provider.dart';

import '../../support/fakes.dart';
import 'package:verifall_app/core/session/deconnexion.dart';

/// Un serveur scripté.
///
/// POURQUOI un adaptateur Dio plutôt qu'un simulacre de dépôt : le parcours M5
/// tient dans ce qu'il **envoie** — `pack` et `numéro` et rien d'autre, une
/// valeur voulue et jamais une bascule, deux temps sur `DELETE /me`. Un
/// simulacre de dépôt vérifierait qu'on a appelé une méthode ; l'adaptateur
/// vérifie ce qui part réellement sur le réseau, ce qui est la seule chose que
/// le serveur verra.
class ServeurScripte implements HttpClientAdapter {
  ServeurScripte(this.reponses);

  /// `METHODE chemin` → corps de réponse.
  final Map<String, Object? Function(RequestOptions options)> reponses;

  final List<RequestOptions> recues = <RequestOptions>[];

  /// Le statut à rendre, par clé, quand ce n'est pas `200`.
  final Map<String, int> statuts = <String, int>{};

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    recues.add(options);
    final String cle = '${options.method} ${options.path}';
    final Object? Function(RequestOptions)? fabrique = reponses[cle];
    if (fabrique == null) {
      return ResponseBody.fromString(
        jsonEncode(<String, Object?>{
          'error': <String, Object?>{
            'code': 'introuvable',
            'message': 'Aucune réponse scriptee pour $cle.',
          },
        }),
        404,
        headers: _entetesJson,
      );
    }
    return ResponseBody.fromString(
      jsonEncode(fabrique(options)),
      statuts[cle] ?? 200,
      headers: _entetesJson,
    );
  }

  /// La dernière requête reçue pour une clé donnée.
  RequestOptions requetePour(String cle) {
    return recues.lastWhere(
      (RequestOptions o) => '${o.method} ${o.path}' == cle,
    );
  }

  /// Le corps envoyé, décodé.
  Map<String, Object?> corpsDe(String cle) {
    final Object? donnees = requetePour(cle).data;
    if (donnees is Map) {
      return donnees.map(
        (Object? k, Object? v) => MapEntry<String, Object?>('$k', v),
      );
    }
    return const <String, Object?>{};
  }

  int comptePour(String cle) =>
      recues.where((RequestOptions o) => '${o.method} ${o.path}' == cle).length;

  @override
  void close({bool force = false}) {}

  static const Map<String, List<String>> _entetesJson = <String, List<String>>{
    Headers.contentTypeHeader: <String>[Headers.jsonContentType],
  };
}

/// Un cache en mémoire.
///
/// POURQUOI il existe alors que Drift a un mode « en mémoire » : `flutter test`
/// tourne sur la machine hôte, où la bibliothèque native SQLite n'est pas
/// forcément installée. La même raison que pour le magasin de file du socle.
class CacheEnMemoire implements CompteLocalDataSource {
  final Map<RessourceDeCompte, Object> contenus = <RessourceDeCompte, Object>{};
  final Map<RessourceDeCompte, DateTime> dates =
      <RessourceDeCompte, DateTime>{};

  @override
  Future<EntreeDeCache?> lire(RessourceDeCompte ressource) async {
    final Object? valeur = contenus[ressource];
    if (valeur is! Map<String, Object?>) {
      return null;
    }
    return EntreeDeCache(
      contenu: valeur,
      recupereLe: dates[ressource] ?? DateTime(2026),
    );
  }

  @override
  Future<List<Map<String, Object?>>?> lireListe(
    RessourceDeCompte ressource,
  ) async {
    final Object? valeur = contenus[ressource];
    return valeur is List<Map<String, Object?>> ? valeur : null;
  }

  @override
  Future<void> ecrire(
    RessourceDeCompte ressource,
    Map<String, Object?> contenu,
  ) async {
    contenus[ressource] = contenu;
    dates[ressource] = DateTime(2026, 8, 25);
  }

  @override
  Future<void> ecrireListe(
    RessourceDeCompte ressource,
    List<Map<String, Object?>> contenu,
  ) async {
    contenus[ressource] = contenu;
    dates[ressource] = DateTime(2026, 8, 25);
  }
}

/// Un conteneur de providers câblé sur le serveur scripté.
///
/// La base Drift et la file d'écriture ne sont pas montées : elles ne
/// s'exécutent pas sur la machine hôte. Les tests qui portent sur la file
/// passent par le magasin en mémoire du socle.
ProviderContainer conteneurDeTest({
  required ServeurScripte serveur,
  required CacheEnMemoire cache,
  bool horsLigne = false,
  Future<void> Function()? deconnexion,
}) {
  final ApiClient client = ApiClient.create(
    baseUrl: 'https://exemple.invalid',
    tokenStore: InMemoryTokenStore(),
    logger: AppLogger(config: testConfig(), sink: RecordingLogSink()),
    clientVersion: '1.0.0',
    onUnauthenticated: () async {},
    onClientObsolete: () {},
    onAccountInactive: () {},
    onAccountBeingDeleted: () {},
    adapter: serveur,
  );

  return ProviderContainer(
    // Le type de la liste n'est pas ecrit : `Override` n'est pas exporte par
    // `flutter_riverpod`, et l'importer depuis `package:riverpod` reviendrait a
    // dependre d'un paquet transitif. L'inference donne le bon type.
    overrides: [
      // La configuration et la version du client sont surchargees au demarrage
      // par `main.dart` ; en test, c'est ici. Sans elles, le logger — dont la
      // file d'ecriture depend — refuse de se construire.
      appConfigProvider.overrideWithValue(testConfig()),
      clientVersionProvider.overrideWithValue('1.0.0'),
      apiClientProvider.overrideWithValue(client),
      compteLocalDataSourceProvider.overrideWithValue(cache),
      // La file d'ecriture passe par le magasin EN MEMOIRE du socle : sans
      // cette surcharge, `pendingOpsQueueProvider` ouvrirait la vraie base
      // Drift, et `flutter test` tourne sur une machine hote ou la
      // bibliotheque native SQLite n'est pas forcement installee.
      pendingOpsStoreProvider.overrideWithValue(InMemoryPendingOpsStore()),
      horsLigneProvider.overrideWithValue(() async => horsLigne),
      networkStatusProvider.overrideWith(
        (Ref ref) => Stream<NetworkStatus>.value(
          horsLigne ? NetworkStatus.horsLigne : NetworkStatus.joignable,
        ),
      ),
      deconnexionProvider.overrideWithValue(deconnexion ?? () async {}),
      versionInstalleeProvider.overrideWith(
        (Ref ref) async => 'version 1.0.0 (build 1)',
      ),
    ],
  );
}

// --- Reponses, calquees sur les ressources reelles du back -----------------
//
// POURQUOI elles sont ecrites a la main plutot que copiees du simulateur : « ne
// jamais mettre les donnees du simulateur en dur dans un test » (06-TESTS 6.8).
// Les CLES, elles, viennent des ressources Laravel : `MoiResource`,
// `MouvementUniteResource`, `BadgeResource`, `CertificatResource`.

Map<String, Object?> reponseMoi({
  int? unites = 34,
  String? suppressionDemandeeLe,
}) => <String, Object?>{
  'data': <String, Object?>{
    'uuid': 'uuid-du-compte',
    'prenom': 'Rita',
    'nom': 'Ahouandjinou',
    'nom_affichage': 'Rita Ahouandjinou',
    'initiales': 'RA',
    'email': 'rita@exemple.invalid',
    'telephone': '97000000',
    'profession': 'Journaliste',
    'ville': 'Cotonou',
    'bio': 'Verifie avant de partager.',
    'unites': unites,
    'xp': 1280,
    'serie_jours': 7,
    'inscrit_le': '2026-01-05T08:00:00+00:00',
    'suppression_demandee_le': suppressionDemandeeLe,
  },
};

Map<String, Object?> reponsePortefeuille({int solde = 34}) => <String, Object?>{
  'data': <String, Object?>{
    'solde': solde,
    'packs': <Map<String, Object?>>[
      <String, Object?>{
        'code': 'pack-10',
        'libelle': 'Depannage',
        'unites': 10,
        'prix_fcfa': 1000,
        'mis_en_avant': false,
      },
      <String, Object?>{
        'code': 'pack-50',
        'libelle': 'Populaire',
        'unites': 50,
        'prix_fcfa': 4000,
        'mis_en_avant': true,
      },
    ],
  },
};

Map<String, Object?> reponseMouvements() => <String, Object?>{
  'data': <Map<String, Object?>>[
    <String, Object?>{
      'uuid': 'mvt-1',
      'delta': 50,
      'solde_apres': 84,
      'motif': 'recharge',
      'motif_libelle': 'Recharge Mobile Money',
      'libelle': 'Recharge 50 unités',
      'date': '2026-08-20T10:00:00+00:00',
    },
    <String, Object?>{
      'uuid': 'mvt-2',
      'delta': 0,
      'solde_apres': 84,
      'motif': 'verification',
      'motif_libelle': 'Vérification',
      'libelle': 'Rumeur déjà vérifiée',
      'date': '2026-08-21T10:00:00+00:00',
    },
  ],
  'meta': <String, Object?>{
    'page': <String, Object?>{
      'per_page': 20,
      'has_more': false,
      'next_cursor': null,
      'prev_cursor': null,
    },
  },
};

Map<String, Object?> reponseGamification({int? record}) => <String, Object?>{
  'data': <String, Object?>{
    'niveau': <String, Object?>{
      'rang': 3,
      'nom': 'Vigilant',
      'xp': 1280,
      'xp_dans_le_palier': 280,
      'xp_restant': 220,
      'pourcentage': 56,
    },
    'serie': <String, Object?>{
      'jours': 7,
      // La cle est ABSENTE quand le record n'est pas rendu : c'est le cas que
      // la reserve D.3 decrit, et l'ecran ne doit alors rien afficher.
      'record': ?record,
      'vivante': true,
      'jours_avant_la_prime': 3,
    },
    'badges': <Map<String, Object?>>[
      <String, Object?>{
        'slug': 'premier-pas',
        'nom': 'Premier pas',
        'description': 'Premiere vérification',
        'icone': 'flag',
        'condition': 'Realiser une premiere vérification',
        'xp': 20,
        'unites': 0,
        'debloque': true,
        'debloque_le': '2026-02-01T09:00:00+00:00',
      },
      <String, Object?>{
        'slug': 'eclaireur',
        'nom': 'Eclaireur',
        'description': 'Série de 7 jours',
        'icone': 'local_fire_department',
        'condition': 'Série de 7 jours',
        'xp': 60,
        'unites': 5,
        'debloque': false,
      },
    ],
  },
};

Map<String, Object?> reponseCertificats() => <String, Object?>{
  'data': <Map<String, Object?>>[
    <String, Object?>{
      'code': 'VA-2026-ABCD',
      'titulaire': 'Rita Ahouandjinou',
      'cours_titre': 'Reconnaitre une intox',
      'delivre_le': '2026-06-10T12:00:00+00:00',
      'revoque': false,
    },
  ],
};

Map<String, Object?> reponseStats({int verifications = 12}) =>
    <String, Object?>{
      'data': <String, Object?>{
        'periode_jours': 30,
        'verifications': verifications,
        'intox_detectees': 5,
        'evolution_mensuelle': 2,
        'lecons_terminees': 8,
        'lecons_cette_semaine': 2,
        'verdicts': <String, Object?>{'true': 4, 'misleading': 3, 'false': 2},
        'types': <String, Object?>{
          'text': 6,
          'link': 3,
          'image': 3,
          'video': 0,
          'audio': 0,
        },
        'activite': <String, Object?>{
          'jours': <String>['1/8', '2/8', '3/8'],
          'valeurs': <int>[2, 0, 1],
        },
        'semaine': <Map<String, Object?>>[
          <String, Object?>{
            'lettre': 'L',
            'actif': true,
            'aujourdhui': false,
            'futur': false,
          },
          <String, Object?>{
            'lettre': 'M',
            'actif': false,
            'aujourdhui': true,
            'futur': false,
          },
        ],
      },
    };

Map<String, Object?> reponseParrainage({int visites = 8, int points = 0}) =>
    <String, Object?>{
      'data': <String, Object?>{
        'code': 'RITA7X9',
        'lien': 'https://exemple.invalid/r/RITA7X9',
        'indicateurs': <String, Object?>{
          'visites': visites,
          'inscrits': 2,
          'actifs': 1,
          'points': points,
        },
        'filleuls': <Map<String, Object?>>[
          <String, Object?>{
            'uuid': 'parrainage-1',
            'prenom': 'Koffi',
            'statut': 'registered',
            'statut_libelle': 'En attente',
            'inscrit_le': '2026-08-01T09:00:00+00:00',
            'recompense_le': null,
          },
        ],
      },
    };

Map<String, Object?> reponseReglages({bool forumActif = true}) =>
    <String, Object?>{
      'data': <String, Object?>{
        'notifications': <String, Object?>{
          'verification_results': <String, Object?>{
            'libelle': 'Résultats de vérification',
            'active': true,
          },
          'forum_activity': <String, Object?>{
            'libelle': 'Activité du forum',
            'active': forumActif,
          },
        },
        'newsletter': false,
      },
    };

// Pourquoi les tests **abonnent** les providers qu'ils lisent.
//
// Tous les providers de M5 sont `autoDispose` — « un provider qui survit à son
// écran garde en mémoire des données de compte ; sur un téléphone partagé,
// c'est une fuite ». Sans abonné, Riverpod les rejette dès la fin du `read`, et
// la construction asynchrone en cours se retrouve à utiliser un `Ref` déjà
// disposé. En test, l'abonnement `conteneur.listen(...)` tient le rôle de
// l'écran.
//
// Le type de l'abonnement n'est pas nommé dans les tests : `ProviderListenable`
// n'est pas exporté par `flutter_riverpod`, et l'importer depuis
// `package:riverpod` reviendrait à dépendre d'un paquet transitif.
