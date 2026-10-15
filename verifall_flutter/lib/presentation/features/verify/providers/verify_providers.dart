import 'dart:async';
import 'dart:io';

import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:infinite_scroll_pagination/infinite_scroll_pagination.dart';
import 'package:path/path.dart' as p;
import 'package:path_provider/path_provider.dart';
import 'package:uuid/uuid.dart';

import '../../../../data/datasources/remote/verify/verification_remote_source.dart';
import '../../../../data/models/api_envelope.dart';
import '../../../../data/repositories/verify/verification_providers.dart';
import '../../../../domain/entities/tool_outil.dart';
import '../../../../domain/entities/verification_brouillon.dart';
import '../../../../domain/entities/verification_entite.dart';
import '../../../../domain/entities/verification_type_contenu.dart';
import '../../../../domain/repositories/tool_repository.dart';
import '../../../../domain/repositories/verification_repository.dart';
import '../../../../domain/usecases/verify/rythme_du_sondage.dart';
import '../../../../core/state/riverpod_retry.dart';
import '../../../../core/session/solde_unites.dart';
import '../../../../core/network/local_data_purger.dart';

// ═════════════════════════════════════════════════════════════════════════════
// Écran 09 — le brouillon de saisie
// ═════════════════════════════════════════════════════════════════════════════

/// Le brouillon de l'écran « Vérifier ».
///
/// POURQUOI il n'est **pas** `autoDispose` : « le brouillon doit survivre à une
/// mise en arrière-plan. Quelqu'un qui bascule vers WhatsApp pour recopier un
/// message ne doit pas revenir sur un champ vide. » Un `autoDispose` le
/// jetterait au premier changement d'onglet.
final NotifierProvider<BrouillonDeVerification, VerificationBrouillon>
verifyFormProvider =
    NotifierProvider<BrouillonDeVerification, VerificationBrouillon>(
      BrouillonDeVerification.new,
    );

class BrouillonDeVerification extends Notifier<VerificationBrouillon> {
  @override
  VerificationBrouillon build() => const VerificationBrouillon();

  /// **Changer de type efface le fichier.** Un fichier audio n'a rien à faire
  /// dans une analyse d'image, et le laisser en place ferait partir le mauvais
  /// téléversement.
  void choisirLeType(TypeContenu type) {
    if (type == state.type) {
      return;
    }
    state = state.changerDeType(type);
  }

  void saisir(String valeur) => state = state.copyWith(saisie: valeur);

  void contexte(String valeur) => state = state.copyWith(contexte: valeur);

  void fichier(FichierChoisi? choisi) => state = choisi == null
      ? state.copyWith(effacerFichier: true)
      : state.copyWith(fichier: choisi);

  void vider() => state = const VerificationBrouillon();
}

/// Copie un média dans le stockage **privé** de l'application.
///
/// POURQUOI la copie et pas la référence : deux raisons, et les deux sont des
/// cas réels.
///
/// 1. « L'utilisateur peut supprimer la photo de sa galerie entre-temps » — une
///    soumission mise en file pointerait alors sur un fichier disparu ;
/// 2. le téléversement serveur **expire au bout de deux heures** : un rejeu
///    au-delà doit re-téléverser, donc la file doit garder le fichier.
///
/// Le nom est **aléatoire** : reprendre le nom d'origine ferait fuiter, dans
/// l'espace de l'application, ce que la personne a nommé chez elle.
Future<FichierChoisi> copierDansLeStockagePrive({
  required String cheminSource,
  required String nomAffiche,
}) async {
  final Directory dossier = await getApplicationSupportDirectory();
  // Le NOM du dossier vient du socle : c'est lui qui l'efface a la deconnexion,
  // et deux constantes ecrites separement auraient fini par diverger
  // (arbitrage n° 140).
  final Directory medias = Directory(
    p.join(dossier.path, dossierDesMediasEnFile),
  );
  if (!medias.existsSync()) {
    await medias.create(recursive: true);
  }
  final String extension = p.extension(nomAffiche);
  final File copie = await File(
    cheminSource,
  ).copy(p.join(medias.path, '${const Uuid().v4()}$extension'));
  return FichierChoisi(
    chemin: copie.path,
    nom: nomAffiche,
    octets: await copie.length(),
  );
}

/// L'état de la soumission en cours.
sealed class EtatDeSoumission {
  const EtatDeSoumission();
}

final class SoumissionAuRepos extends EtatDeSoumission {
  const SoumissionAuRepos();
}

final class SoumissionEnCours extends EtatDeSoumission {
  const SoumissionEnCours();
}

final class SoumissionPartie extends EtatDeSoumission {
  const SoumissionPartie(this.resultat);

  final ResultatSoumission resultat;
}

final class SoumissionEnEchec extends EtatDeSoumission {
  const SoumissionEnEchec(this.echec);

  final Object echec;
}

/// Le lanceur d'une vérification.
///
/// # La clé d'idempotence est tirée ICI
///
/// Elle est générée **au moment où la personne appuie sur le bouton**, avant
/// toute tentative réseau, et réutilisée telle quelle par le rejeu de la file.
/// « Une clé regénérée à l'envoi ne protège de rien » : c'est le scénario qui
/// débite deux fois pour une seule question.
final NotifierProvider<LanceurDeVerification, EtatDeSoumission>
soumissionProvider = NotifierProvider<LanceurDeVerification, EtatDeSoumission>(
  LanceurDeVerification.new,
);

class LanceurDeVerification extends Notifier<EtatDeSoumission> {
  @override
  EtatDeSoumission build() => const SoumissionAuRepos();

  Future<void> lancer(VerificationBrouillon brouillon) async {
    if (state is SoumissionEnCours) {
      return;
    }
    state = const SoumissionEnCours();

    // UN uuid, UN acte. Il sert d'`Idempotency-Key` en ligne et de clé de file
    // hors ligne — c'est le meme identifiant, et c'est tout l'interet.
    final String acteUuid = const Uuid().v4();
    final VerificationRepository depot = ref.read(
      verificationRepositoryProvider,
    );

    try {
      final ResultatSoumission resultat = await depot.soumettre(
        acteUuid: acteUuid,
        type: brouillon.type,
        affirmation: brouillon.type.exigeUnFichier
            ? (brouillon.fichier?.nom ?? '')
            : brouillon.saisie.trim(),
        contexte: brouillon.contexte,
        cheminFichierLocal: brouillon.fichier?.chemin,
        nomFichier: brouillon.fichier?.nom,
      );
      if (resultat case SoumissionEnvoyee(:final SoumissionAboutie aboutie)) {
        // Le solde affiche vient de `meta.solde`, RECALCULE par le serveur.
        // Jamais d'une soustraction locale (contrat 5.9).
        ref.read(soldeUnitesProvider.notifier).relu(aboutie.solde);
      }
      state = SoumissionPartie(resultat);
    } on Object catch (echec) {
      state = SoumissionEnEchec(echec);
    }
  }

  void reprendre() => state = const SoumissionAuRepos();
}

// ═════════════════════════════════════════════════════════════════════════════
// Écran 10 — le suivi d'une analyse
// ═════════════════════════════════════════════════════════════════════════════

/// L'état de l'écran de suivi.
class EtatDuSuivi {
  const EtatDuSuivi({
    this.verification,
    this.echec,
    this.tropLong = false,
    this.enChargement = true,
  });

  final Verification? verification;
  final Object? echec;

  /// Vrai quand l'analyse traîne au-delà du délai raisonnable. L'arbitrage
  /// n° 28 demande que l'écran **le dise** et propose de revenir plus tard.
  final bool tropLong;

  final bool enChargement;
}

/// Le suivi d'une analyse, **par sondage**.
///
/// # Pourquoi le sondage et pas Reverb
///
/// Le contrat pose la hiérarchie : *« l'avancement se suit par sondage, **ou**
/// par Reverb quand le canal est branché »*. Le sondage est donc le mode
/// **nominal**, pas le repli, et le plafond de lecture a été dimensionné pour
/// lui.
///
/// Reverb **n'est pas branché** en v1, et pour une raison précise : le canal
/// `private-verification.{uuid}` est privé, donc il exige un point
/// d'authentification de diffusion que le contrat ne publie pas ; et la forme
/// des événements n'est documentée nulle part (réserve F.4). Deviner l'un ou
/// l'autre serait inventer une forme de réponse. Voir `docs/ARBITRAGES.md`.
///
/// # Ce que ce notifier ne fait jamais
///
/// Il ne lit **pas** le cache : « l'état d'une analyse en cours ne doit jamais
/// venir du cache » (§7.1). Et il n'anime rien : la barre affiche
/// `data.avancement`, tel quel.
// Le type de famille n'est pas exporte par `riverpod` : on laisse l'inference
// faire, plutot que d'importer un chemin `src/` interne — un import prive est
// une dependance qui casse a la prochaine version mineure.
final suiviProvider = NotifierProvider.autoDispose
    .family<SuiviDAnalyse, EtatDuSuivi, String>(SuiviDAnalyse.new);

class SuiviDAnalyse extends Notifier<EtatDuSuivi> {
  SuiviDAnalyse(this.uuid);

  final String uuid;

  Timer? _minuterie;
  DateTime? _debut;

  @override
  EtatDuSuivi build() {
    _debut = DateTime.now();
    ref.onDispose(() => _minuterie?.cancel());
    // La premiere lecture part tout de suite : un ecran qui attend trois
    // secondes avant son premier appel a l'air casse.
    unawaited(relire());
    return const EtatDuSuivi();
  }

  Future<void> relire() async {
    try {
      final Verification v = await ref
          .read(verificationRepositoryProvider)
          // `autoriserLeCache: false` : c'est ICI que la regle du contrat se
          // fait respecter, pas dans le widget.
          .lire(uuid);
      state = EtatDuSuivi(verification: v, enChargement: false);
      if (v.aUnRapport) {
        // Le rapport existe : plus rien a sonder. La bascule vers l'ecran 11
        // se fait sur la NULLITE de `rapport`, jamais sur sa vacuite.
        _minuterie?.cancel();
        return;
      }
      _reprogrammer();
    } on Object catch (echec) {
      state = EtatDuSuivi(
        verification: state.verification,
        echec: echec,
        enChargement: false,
      );
      _reprogrammer();
    }
  }

  void _reprogrammer() {
    _minuterie?.cancel();

    final DateTime? debut = _debut;
    if (debut != null &&
        DateTime.now().difference(debut) >
            RythmeDuSondage.avantDeProposerDeRevenir) {
      // On cesse de sonder et on le DIT : « l'ecran doit le dire et proposer de
      // revenir plus tard » (arbitrage n. 28). Continuer une heure viderait une
      // batterie et un forfait sans rien apprendre de plus.
      state = EtatDuSuivi(
        verification: state.verification,
        echec: state.echec,
        tropLong: true,
        enChargement: false,
      );
      return;
    }

    // Le client lit `X-RateLimit-Remaining` et s'espace SOUS 10 % : c'est
    // precisement ici que l'auto-regulation sert (contrat 5.4).
    final bool quotaFaible = ref
        .read(verificationRemoteSourceProvider)
        .doitSEspacer;
    _minuterie = Timer(
      RythmeDuSondage.prochain(quotaFaible: quotaFaible),
      relire,
    );
  }
}

// ═════════════════════════════════════════════════════════════════════════════
// Écran 11 — le rapport
// ═════════════════════════════════════════════════════════════════════════════

/// Le rapport d'une vérification.
///
/// **Le cache est autorisé ici**, et seulement ici : « le rapport est mis en
/// cache : c'est le document que l'on rouvre pour le montrer à quelqu'un,
/// souvent sans réseau ».
final rapportProvider = FutureProvider.autoDispose.family<Verification, String>(
  (Ref ref, String uuid) => ref
      .read(verificationRepositoryProvider)
      .lire(uuid, autoriserLeCache: true),
  retry: sansReessaiAutomatique,
);

// `_sansReessai` vivait ici. Le reglage vit desormais au socle
// (`core/state/riverpod_retry.dart`), pose une fois sur le conteneur :
// M5 ecrivait la meme ligne, et trois parcours ne l'ecrivaient pas du tout.
// Voir l'arbitrage n° 132.

// ═════════════════════════════════════════════════════════════════════════════
// Écran 12 — l'historique
// ═════════════════════════════════════════════════════════════════════════════

/// Les filtres de l'historique, tels qu'ils partent **au serveur**.
class FiltresHistorique {
  const FiltresHistorique({this.verdict, this.recherche = ''});

  /// Une des quatre valeurs servies, ou `null` pour « Tous ».
  ///
  /// POURQUOI il n'y a pas de filtre « en cours » : `verdict` est une
  /// **énumération fermée** côté serveur, et une valeur hors liste répond `422`
  /// (§3.7). Les analyses en cours apparaissent bien dans la liste non filtrée,
  /// avec leur état — mais on ne peut pas filtrer dessus. Réserve consignée.
  final String? verdict;

  final String recherche;

  FiltresHistorique copyWith({
    String? verdict,
    bool effacerVerdict = false,
    String? recherche,
  }) => FiltresHistorique(
    verdict: effacerVerdict ? null : (verdict ?? this.verdict),
    recherche: recherche ?? this.recherche,
  );
}

final NotifierProvider<FiltresDeLHistorique, FiltresHistorique>
filtresHistoriqueProvider =
    NotifierProvider<FiltresDeLHistorique, FiltresHistorique>(
      FiltresDeLHistorique.new,
    );

class FiltresDeLHistorique extends Notifier<FiltresHistorique> {
  @override
  FiltresHistorique build() => const FiltresHistorique();

  void verdict(String? valeur) => state = valeur == null
      ? state.copyWith(effacerVerdict: true)
      : state.copyWith(verdict: valeur);

  void rechercher(String texte) => state = state.copyWith(recherche: texte);

  void reinitialiser() => state = const FiltresHistorique();
}

/// La liste paginée **par curseur**.
///
/// # Deux règles qui tiennent dans ce notifier
///
/// 1. **On ne filtre jamais côté client.** « Filtrer localement ne filtre que la
///    page déjà chargée ; à la deuxième page, l'utilisateur voit apparaître des
///    éléments qui ne correspondent pas à sa sélection » (§3.4, arbitrage
///    n° 82). Les filtres partent au serveur, et **repartent avec le curseur**.
/// 2. **Le curseur est opaque.** On renvoie `next_cursor` tel quel, on ne le lit
///    jamais. Un curseur périmé répond `422` : la bonne réponse est alors
///    d'**oublier le curseur et de recharger depuis le début**, jamais de
///    réessayer la même requête.
final NotifierProvider<
  HistoriqueDesVerifications,
  PagingState<String, Verification>
>
historiqueProvider =
    NotifierProvider<
      HistoriqueDesVerifications,
      PagingState<String, Verification>
    >(HistoriqueDesVerifications.new);

class HistoriqueDesVerifications
    extends Notifier<PagingState<String, Verification>> {
  bool _enCours = false;

  @override
  PagingState<String, Verification> build() {
    // Un changement de filtre repart de zero : garder les pages precedentes
    // melangerait deux selections dans une meme liste.
    ref.listen<FiltresHistorique>(filtresHistoriqueProvider, (
      FiltresHistorique? avant,
      FiltresHistorique apres,
    ) {
      state = PagingState<String, Verification>();
      unawaited(pageSuivante());
    });
    // La premiere page part APRES la construction : lire `state` pendant
    // `build()` reviendrait a lire un provider qui n'existe pas encore.
    unawaited(Future<void>.microtask(pageSuivante));
    return PagingState<String, Verification>();
  }

  Future<void> pageSuivante() async {
    if (_enCours || !state.hasNextPage) {
      return;
    }
    _enCours = true;
    state = state.copyWith(isLoading: true, error: null);

    final FiltresHistorique filtres = ref.read(filtresHistoriqueProvider);
    final String? curseur = state.keys?.lastOrNull;

    try {
      final CursorPage<Verification> page = await ref
          .read(verificationRepositoryProvider)
          .lister(
            cursor: curseur,
            // Les filtres REPARTENT a l'identique avec le curseur : sans eux,
            // la page suivante serait celle de la liste NON filtree, et la
            // liste resterait plausible tout en etant fausse.
            verdict: filtres.verdict,
            recherche: filtres.recherche,
          );

      state = state.copyWith(
        pages: <List<Verification>>[...?state.pages, page.items],
        keys: <String>[...?state.keys, page.page.nextCursor ?? ''],
        hasNextPage: !page.page.isLastPage,
        isLoading: false,
      );
    } on Object catch (echec) {
      state = state.copyWith(error: echec, isLoading: false);
    } finally {
      _enCours = false;
    }
  }

  void recharger() {
    state = PagingState<String, Verification>();
    unawaited(pageSuivante());
  }
}

/// Les quatre indicateurs de l'écran 12, **sur tout l'historique**.
final FutureProvider<IndicateursHistorique> indicateursProvider =
    FutureProvider<IndicateursHistorique>(
      (Ref ref) => ref.read(verificationRepositoryProvider).indicateurs(),
      retry: sansReessaiAutomatique,
    );

/// La liste servie depuis Drift, pour la lecture hors ligne, avec sa date.
final FutureProvider<({List<Verification> liste, DateTime? luLe})>
historiqueEnCacheProvider =
    FutureProvider<({List<Verification> liste, DateTime? luLe})>((
      Ref ref,
    ) async {
      final VerificationRepository depot = ref.read(
        verificationRepositoryProvider,
      );
      return (
        liste: await depot.listerDepuisLeCache(),
        luLe: await depot.dateDuCacheDeLaListe(),
      );
    }, retry: sansReessaiAutomatique);

// ═════════════════════════════════════════════════════════════════════════════
// Écran 13 — l'annuaire d'outils
// ═════════════════════════════════════════════════════════════════════════════

/// L'annuaire, cache 24 h.
final FutureProvider<AnnuaireOutils> toolsProvider =
    FutureProvider<AnnuaireOutils>(
      (Ref ref) => ref.read(toolRepositoryProvider).annuaire(),
      retry: sansReessaiAutomatique,
    );

/// La catégorie sélectionnée. **Filtre local**, sur la liste entière.
///
/// C'est légitime **parce que la liste n'est pas paginée** : l'interdiction de
/// filtrer côté client vise les listes à curseur, où filtrer localement ne
/// filtre que la page chargée. Ici la liste arrive entière, et le filtre marche
/// donc aussi hors ligne — ce qui est exactement ce qu'on veut d'un répertoire
/// d'outils consultable sans réseau.
final NotifierProvider<CategorieDOutils, String?> categorieOutilsProvider =
    NotifierProvider<CategorieDOutils, String?>(CategorieDOutils.new);

class CategorieDOutils extends Notifier<String?> {
  @override
  String? build() => null;

  void choisir(String? categorie) => state = categorie;
}

/// Le dépôt d'outils, exposé pour la lecture hors ligne de l'écran.
final Provider<ToolRepository> annuaireDepotProvider = Provider<ToolRepository>(
  (Ref ref) => ref.watch(toolRepositoryProvider),
);
