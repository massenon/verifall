import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../data/datasources/remote/community/ouvreur_de_lien_systeme.dart';
import '../../../../domain/entities/article_entity.dart';
import '../../../shared/shared.dart';
import '../providers/hub_providers.dart';
import '../support/liens_m4.dart';
import '../widgets/etats_m4.dart';
import '../widgets/hub_widgets.dart';
import '../../../../core/format/vf_formats.dart';

/// Écran 24 — **Article vérifié**.
///
/// # Le verdict vient de l'équipe, pas d'un agrégateur
///
/// C'est l'arbitrage n° 44 : « on n'y relaie pas une information, on dit ce
/// qu'elle vaut ». Une brève sans verdict affiche **« EN COURS »** et non un
/// bandeau vide — « un bandeau vide passerait pour un défaut d'affichage ».
///
/// Le Hub affiche « DEMENTI » là où un rapport dit « FAUX » : c'est un registre,
/// pas une divergence. Le contrat rend cela mécanique — valeur technique et
/// libellé voyagent ensemble, et **le client affiche le libellé reçu**.
///
/// # Le lien profond doit résoudre application fermée
///
/// `verifall://hub/{slug}` (§1.4). Sur `404`, le repli **n'affirme rien** : « le
/// 404 du contrat couvre aussi bien "dépublié" que "jamais existé", et un slug
/// deviné ne doit renseigner sur rien ».
class ArticlePage extends ConsumerWidget {
  const ArticlePage({required this.slug, super.key});

  final String slug;

  /// Le paragraphe **fixe** qui explique la méthode.
  ///
  /// POURQUOI il est écrit en dur et ce n'est pas une donnée inventée : « c'est
  /// du contenu éditorial — la parole du produit — et il se transpose tel quel »
  /// (03-ECRANS-05 §3.24). Il ne décrit aucun fait propre à l'article.
  static const String methode =
      'La redaction Vérif All applique la meme methode a chaque cas : '
      'identification de l affirmation, recherche de la source primaire, '
      'vérification de la date, puis recoupement par au moins deux sources '
      'independantes.';

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<EtatArticle> etat = ref.watch(articleProvider(slug));

    return Scaffold(
      backgroundColor: VfColors.canvas,
      body: Column(
        children: <Widget>[
          VfAppBar(
            // « Titre = la categorie. » Sans categorie servie, le nom de
            // l'ecran plutôt qu'une rubrique fabriquee.
            title: etat.value?.article.categorie ?? 'Article verifie',
            actions: <Widget>[
              VfIconButton(
                icon: Icons.ios_share_rounded,
                tooltip: 'Partager cet article',
                onPressed: etat.hasValue
                    ? () => _partager(ref, etat.value!.article)
                    : null,
              ),
            ],
          ),
          Expanded(
            child: etat.when(
              loading: () => const _Squelette(),
              error: (Object erreur, StackTrace _) =>
                  _erreur(context, ref, erreur),
              data: (EtatArticle valeur) => _contenu(context, ref, valeur),
            ),
          ),
        ],
      ),
    );
  }

  /// Le partage **fonctionne hors ligne** : le lien est connu (05-HORS-LIGNE,
  /// écran 24). C'est l'ouverture de la source externe qui ne fonctionne pas.
  Future<void> _partager(WidgetRef ref, ArticleHub article) {
    return ref
        .read(partageurDeLienProvider)
        .partager(
          texte: '${article.titre}\n${VfRoutes.hubArticle(article.slug)}',
        );
  }

  Widget _erreur(BuildContext context, WidgetRef ref, Object erreur) {
    if (erreur is NotFoundFailure) {
      return ReplIntrouvable(
        titre: 'Cet article n est pas disponible',
        libelleSortie: 'Revenir au Hub',
        onSortir: () => context.go(VfRoutes.hub),
      );
    }
    return EtatEchec(
      echec: erreur is Failure ? erreur : const UnexpectedFailure(),
      onReessayer: () async => ref.invalidate(articleProvider(slug)),
      messageHorsLigne:
          'Cet article n a pas encore ete consulte sur cet appareil : il ne '
          'peut pas être lu hors ligne.',
    );
  }

  Widget _contenu(BuildContext context, WidgetRef ref, EtatArticle etat) {
    final ArticleHub article = etat.article;
    final String? anciennete = VfFormats.anciennete(article.publieLe);

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        VfSpacing.screenInline,
        VfSpacing.screenTopExtra,
        VfSpacing.screenInline,
        VfSpacing.screenBottomExtra,
      ),
      children: <Widget>[
        if (etat.recupereLe != null) ...<Widget>[
          VfStaleDataBanner(
            lueLe: etat.recupereLe!,
            tone: VfStaleTone.echeance,
            onRetry: () async => ref.invalidate(articleProvider(slug)),
          ),
          const SizedBox(height: VfSpacing.stack),
        ],

        VfCover(
          gradientKey: article.slug,
          imageUrl: article.couvertureUrl,
          // 130 : la mesure de l'en-tete d'article (ecran 24), rendue possible
          // par le parametre du socle (arbitrage n° 134).
          height: 130,
          overlay: article.categorie == null
              ? null
              : Center(
                  child: Text(
                    article.categorie!.toUpperCase(),
                    style: const TextStyle(
                      fontFamily: VfFonts.display,
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: VfColors.surface,
                    ),
                    semanticsLabel: article.categorie,
                  ),
                ),
        ),
        const SizedBox(height: VfSpacing.stack),

        // LE VERDICT EST ANNONCE AVANT LE TITRE (accessibilite de l'ecran 24).
        Row(
          children: <Widget>[
            VfHubVerdictBadge(article: article, compact: false),
            const Spacer(),
            if (anciennete != null) Text(anciennete, style: VfTextStyles.tXs),
          ],
        ),
        const SizedBox(height: VfSpacing.x12),
        Text(article.titre, style: VfTextStyles.hLg.copyWith(height: 1.2)),
        if (article.chapo != null) ...<Widget>[
          const SizedBox(height: VfSpacing.x10),
          Text(
            article.chapo!,
            style: VfTextStyles.tMd.copyWith(fontWeight: FontWeight.w600),
          ),
        ],

        if (article.sourcePrimaire != null) ...<Widget>[
          const SizedBox(height: VfSpacing.stack),
          VfSourceTile(source: article.sourcePrimaire!),
        ],

        if (article.corps != null) ...<Widget>[
          const SizedBox(height: VfSpacing.stack),
          VfProseBody(
            html: article.corps!,
            onLinkTap: (String url) => ouvrirLienDeProse(ref, url),
          ),
        ],
        const SizedBox(height: VfSpacing.x12),
        Text(methode, style: VfTextStyles.prose),

        const SizedBox(height: VfSpacing.x22),
        VfAcademyPromo(
          // Par la NAVIGATION, jamais par un import du parcours M3 (§8.1,
          // regle 4).
          onVoirCatalogue: () => context.push(VfRoutes.catalogue),
        ),

        const SizedBox(height: VfSpacing.stack),
        VfWhatsAppShareButton(onPressed: () => _partager(ref, article)),
      ],
    );
  }
}

class _Squelette extends StatelessWidget {
  const _Squelette();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      children: const <Widget>[
        VfSkeleton(height: 112, borderRadius: VfRadii.lgAll),
        SizedBox(height: VfSpacing.stack),
        SqueletteDeCarte(lignes: 5),
      ],
    );
  }
}
