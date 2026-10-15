import 'dart:io' show FileSystemException;

import 'package:dio/dio.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/errors/failure_mapper.dart';
import '../../../../core/network/api_client.dart';
import '../../../../core/network/api_endpoints.dart';
import '../../../../domain/entities/tool_outil.dart';
import '../../../../domain/entities/verification_entite.dart';
import '../../../../domain/entities/verification_type_contenu.dart';
import '../../../models/api_envelope.dart';
import '../../../models/json_reader.dart';
import '../../../models/televersement.dart';

/// Les indicateurs de l'historique — `GET /me/stats`.
///
/// POURQUOI M2 lit cet endpoint alors que l'écran des statistiques appartient à
/// M5 : les quatre KPI de « Mes vérifications » portent sur **tout
/// l'historique**, jamais sur la page filtrée (arbitrage n° 31). Or la
/// pagination par curseur ne donne **aucun total** (§3.1) — les calculer sur la
/// page chargée afficherait « 20 vérifications » à quelqu'un qui en a trois
/// cents. Cet endpoint sert exactement les trois nombres demandés.
///
/// Aucun code n'est partagé avec M5 : « les dépendances fonctionnelles se
/// résolvent par la navigation et par l'API, jamais par un import croisé ».
class IndicateursHistorique {
  const IndicateursHistorique({
    required this.total,
    required this.intoxDemasquees,
    required this.confirmeesFiables,
  });

  factory IndicateursHistorique.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    final JsonReader verdicts = JsonReader(
      reader.objectOrNull('verdicts') ?? const <String, Object?>{},
    );
    return IndicateursHistorique(
      total: reader.intValue('verifications'),
      // Le serveur additionne deja `false` et `misleading` : « du point de vue
      // de quelqu'un qui a failli partager, les deux sont des pieges »
      // (arbitrage n. 31). Le recalculer ici donnerait une seconde formule,
      // et deux formules donnent deux nombres.
      intoxDemasquees: reader.intValue('intox_detectees'),
      confirmeesFiables: verdicts.intValue('true'),
    );
  }

  final int total;
  final int intoxDemasquees;
  final int confirmeesFiables;
}

/// Ce qu'une soumission rend : la vérification et le solde recalculé.
class ReponseSoumission {
  const ReponseSoumission({required this.verification, this.solde});

  final Verification verification;

  /// `meta.solde`. **Jamais une soustraction locale.**
  final int? solde;
}

/// La source réseau du parcours « Vérifier ».
///
/// Elle ne contient **aucune** règle d'affichage et **aucun** barème : elle
/// traduit des appels du contrat en objets du domaine, et c'est tout.
class VerificationRemoteSource {
  const VerificationRemoteSource(this._client);

  final ApiClient _client;

  /// `POST /uploads` — multipart, **premier des deux appels**.
  ///
  /// POURQUOI il passe par `client.raw` : le socle expose un envoi JSON, pas un
  /// multipart avec progression et annulation. « Accès brut, pour les cas que le
  /// socle ne couvre pas — l'envoi de fichier en multipart avec progression,
  /// par exemple » (commentaire d'`ApiClient.raw`).
  ///
  /// POURQUOI l'appel n'est pas réessayé automatiquement : l'intercepteur de
  /// réessai ne couvre que les `GET`. Une vidéo de 100 Mo renvoyée en boucle sur
  /// un réseau instable coûte le forfait de quelqu'un.
  Future<Televersement> televerser({
    required TypeTeleversement type,
    required String cheminLocal,
    required String nomFichier,
    void Function(int envoyes, int total)? progression,
    CancelToken? annulation,
  }) async {
    final FormData corps;
    try {
      corps = FormData.fromMap(<String, Object?>{
        'type': type.wireValue,
        'fichier': await MultipartFile.fromFile(
          cheminLocal,
          filename: nomFichier,
        ),
      });
    } on FileSystemException catch (erreur) {
      // La copie locale a disparu : acte mis en file il y a longtemps, stockage
      // nettoye par le systeme, fichier supprime. Ce n'est PAS une panne
      // reseau — l'ecran doit redemander le fichier, pas proposer d'attendre le
      // retour du reseau. Le distinguer evite un message qui envoie chercher un
      // probleme la ou il n'est pas.
      throw NotFoundFailure(
        serverMessage:
            'Le fichier choisi n est plus disponible sur cet appareil. '
            'Selectionnez-le a nouveau.',
        debug: '${erreur.osError}',
      );
    }

    try {
      final Response<Object?> reponse = await _client.raw.post<Object?>(
        ApiEndpoints.uploads,
        data: corps,
        cancelToken: annulation,
        onSendProgress: progression,
      );
      final ApiEnvelope enveloppe = ApiEnvelope.fromJson(
        asJsonObject(reponse.data),
      );
      if (enveloppe.hasError) {
        throw FailureMapper.fromResponse(reponse);
      }
      return Televersement.fromJson(enveloppe.dataObject);
    } on DioException catch (erreur) {
      // Aucune DioException ne franchit la frontiere de `data/` : un ecran qui
      // l'attraperait serait a reecrire le jour ou l'on change de bibliotheque.
      throw FailureMapper.fromDioException(erreur);
    }
  }

  /// `POST /verifications` — **second des deux appels**.
  ///
  /// [acteUuid] part en `Idempotency-Key` : une clé **par acte**, pas par
  /// tentative. « Une reprise avec la même clé renvoie la réponse mémorisée à
  /// l'identique, et rien n'est réexécuté » (§7.3).
  ///
  /// Le corps ne porte **aucun coût** : « il est lu du type » (§10.3).
  Future<ReponseSoumission> soumettre({
    required String acteUuid,
    required TypeContenu type,
    required String affirmation,
    String? contexte,
    String? televersement,
  }) async {
    final ApiEnvelope enveloppe = await _client.post(
      ApiEndpoints.verifications,
      idempotencyKey: acteUuid,
      body: <String, Object?>{
        'type': type.wireValue,
        'affirmation': affirmation,
        if (contexte != null && contexte.trim().isNotEmpty)
          'contexte': contexte.trim(),
        'televersement': ?televersement,
      },
    );
    return ReponseSoumission(
      verification: Verification.fromJson(enveloppe.dataObject),
      solde: enveloppe.solde,
    );
  }

  /// `GET /verifications/{uuid}` — suivi **et** rapport.
  Future<Verification> lire(String uuid, {CancelToken? annulation}) async {
    final ApiEnvelope enveloppe = await _client.get(
      ApiEndpoints.verification(uuid),
      cancelToken: annulation,
    );
    return Verification.fromJson(enveloppe.dataObject);
  }

  /// `GET /me/verifications` — pagination par curseur.
  ///
  /// **Les filtres repartent avec le curseur, à l'identique.** Le curseur
  /// n'encode qu'une position dans l'ordre de tri : l'oublier ne produit pas
  /// d'erreur, il rend la page suivante de la liste **non filtrée** — « la liste
  /// reste plausible, elle est simplement fausse à partir de la deuxième page »
  /// (§3.4).
  Future<CursorPage<Verification>> lister({
    String? cursor,
    String? verdict,
    String? recherche,
    int limit = PageMeta.defaultLimit,
  }) async {
    final ApiEnvelope enveloppe = await _client.getPage(
      ApiEndpoints.myVerifications,
      cursor: cursor,
      limit: limit,
      filters: <String, Object?>{
        // Omettre le parametre signifie << aucun filtre >> (contrat 3.6).
        if (verdict != null && verdict.isNotEmpty) 'verdict': verdict,
        if (recherche != null && recherche.trim().isNotEmpty)
          'q': recherche.trim(),
      },
    );
    return enveloppe.toPage(Verification.fromJson);
  }

  /// `POST /verifications/{uuid}/contest`.
  ///
  /// `detail` est **obligatoire**, 10 à 1000 caractères : « ce verdict est faux
  /// sans un mot d'explication ne donne à la rédaction rien sur quoi
  /// travailler ».
  Future<void> contester(
    String uuid, {
    required String detail,
    String? motif,
  }) async {
    await _client.post(
      ApiEndpoints.verificationContest(uuid),
      body: <String, Object?>{
        'detail': detail.trim(),
        if (motif != null && motif.isNotEmpty) 'motif': motif,
      },
    );
  }

  /// `GET /tools` — **ni paginé, ni filtré côté client de curseur**.
  ///
  /// Les catégories viennent de `meta.categories` : « proposer une catégorie
  /// vide mène à une liste vide, et une liste vide derrière un filtre passe pour
  /// un bug » (arbitrage n° 55).
  Future<AnnuaireOutils> outils() async {
    final ApiEnvelope enveloppe = await _client.get(ApiEndpoints.tools);
    return AnnuaireOutils(
      outils: <Outil>[
        for (final Map<String, Object?> outil in enveloppe.dataList)
          Outil.fromJson(outil),
      ],
      categories: <String>[
        for (final Object? c in JsonReader(
          enveloppe.meta,
        ).listOrEmpty('categories'))
          if (c != null) '$c',
      ],
    );
  }

  /// `GET /me/stats` — les quatre indicateurs de l'écran 12.
  ///
  /// La période reste `30d`, celle des titres de l'écran : **le simulateur
  /// n'expose aucun sélecteur de période**, et en ajouter un serait inventer un
  /// élément d'interface (réserve D.4).
  Future<IndicateursHistorique> indicateurs() async {
    final ApiEnvelope enveloppe = await _client.get(
      ApiEndpoints.stats,
      query: <String, Object?>{'period': '30d'},
    );
    return IndicateursHistorique.fromJson(enveloppe.dataObject);
  }

  /// Le quota de lecture restant, pour que le sondage s'espace **avant** de se
  /// faire éconduire. « Un plafond sans en-têtes est un mur invisible » (§5.3).
  bool get doitSEspacer => _client.rateLimit.shouldThrottle;
}

/// Le message par défaut d'un échec sans message serveur.
///
/// POURQUOI il vit ici et pas dans le socle : le socle ne choisit pas les mots
/// d'un écran. Quand le serveur en fournit un, c'est le sien qui s'affiche —
/// « déjà rédigé en français, affichable tel quel » (§2.1).
String messageAffichable(Failure echec, String repli) =>
    echec.hasServerMessage ? echec.serverMessage! : repli;
