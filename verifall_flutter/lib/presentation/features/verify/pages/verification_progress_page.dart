import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/verification_entite.dart';
import '../../../../domain/entities/verification_statut.dart';
import '../../../shared/vf_app_bar.dart';
import '../../../shared/vf_buttons.dart';
import '../../../shared/vf_card.dart';
import '../../../shared/vf_display.dart';
import '../../../shared/vf_timeline.dart';
import '../providers/verify_providers.dart';
import '../widgets/pipeline_timeline.dart';

/// Écran 10 · **Analyse en cours** — `#/verifying`.
///
/// # La règle la plus importante de cet écran
///
/// > « Le prototype fait défiler ses étapes sur des minuteurs aléatoires. Ici
/// > elles sont posées par l'orchestrateur au moment où il les franchit : si
/// > l'analyse cale sur les moteurs, l'affichage cale avec elle. » (n° 28)
///
/// Il n'y a donc **aucune** animation de progression dans ce fichier : la barre
/// affiche `data.avancement`, et rien d'autre.
///
/// # La promesse du simulateur, corrigée
///
/// Le simulateur écrit : *« Vous pouvez quitter cet écran : une notification
/// vous préviendra dès que le verdict est prêt. »* **Aucune notification ne
/// partira en v1** — il n'existe ni magasin de notifications, ni FCM. Le texte
/// est donc remplacé par celui de la réserve C.2, qui est vrai :
/// « L'analyse continue même si vous quittez cet écran. Retrouvez-la dans Mes
/// vérifications. »
class VerificationProgressPage extends ConsumerWidget {
  const VerificationProgressPage({required this.uuid, super.key});

  final String uuid;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final EtatDuSuivi etat = ref.watch(suiviProvider(uuid));

    // La bascule suivi -> rapport se fait sur la NULLITE de `data.rapport`,
    // jamais sur sa vacuite. Et EN REMPLACEMENT : revenir en arriere sur une
    // analyse achevee n'aurait pas de sens.
    ref.listen<EtatDuSuivi>(suiviProvider(uuid), (
      EtatDuSuivi? avant,
      EtatDuSuivi apres,
    ) {
      if (apres.verification?.aUnRapport ?? false) {
        context.pushReplacement(VfRoutes.report(uuid));
      }
    });

    return Scaffold(
      backgroundColor: VfColors.canvas,
      // Pas de barre basse : l'ecran de suivi n'est pas un onglet.
      appBar: const VfAppBar(title: 'Analyse en cours'),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          VfSpacing.screenInline,
          VfSpacing.screenTopExtra,
          VfSpacing.screenInline,
          VfSpacing.screenBottomExtra,
        ),
        children: _corps(context, ref, etat),
      ),
    );
  }

  List<Widget> _corps(BuildContext context, WidgetRef ref, EtatDuSuivi etat) {
    final Verification? v = etat.verification;

    if (v == null) {
      if (etat.echec != null) {
        return <Widget>[_echec(context, ref, etat.echec!)];
      }
      // Chronologie en SQUELETTE, jamais une etape 1 supposee : supposer une
      // etape, c'est afficher un avancement que le serveur n'a pas declare.
      return const <Widget>[_Squelette()];
    }

    final bool enMarche = v.statut.estEnMarche;

    return <Widget>[
      _EnTete(verification: v),
      const SizedBox(height: VfSpacing.stack),
      VfCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text('Tracabilite de l analyse', style: VfTextStyles.hSm),
            const SizedBox(height: VfSpacing.stack),
            VfTimeline(
              steps: PipelineDAnalyse.etats(
                avancement: v.avancement,
                enMarche: enMarche,
              ),
            ),
          ],
        ),
      ),
      const SizedBox(height: VfSpacing.stack),
      ..._etatParticulier(context, ref, v, etat),
    ];
  }

  /// Les états que le simulateur ne montre pas et que le back produit.
  List<Widget> _etatParticulier(
    BuildContext context,
    WidgetRef ref,
    Verification v,
    EtatDuSuivi etat,
  ) {
    switch (v.statut) {
      // Arbitrage n. 35 : trop peu de moteurs disponibles. Ce n'est PAS un
      // echec, et l'ecran ne doit pas le presenter comme tel.
      case StatutVerification.repriseProgrammee:
        return <Widget>[
          _Encart(
            couleur: VfColors.verdictInvestigate,
            icone: Icons.schedule_rounded,
            // Le libelle vient du serveur (`statut_libelle`).
            titre: v.statutLibelle ?? 'Reprise programmee',
            texte:
                'Les moteurs d analyse n ont pas pu répondre. L analyse '
                'reprendra d elle-meme ; vous n avez rien a faire.\n\n'
                'Le serveur ne publie pas l heure de la prochaine tentative : '
                'cet ecran ne l invente pas.',
          ),
        ];

      // Apres epuisement des tentatives, le serveur REND LES UNITES. « Un ecran
      // d'echec sans mention du remboursement est proscrit. »
      case StatutVerification.echouee:
        return <Widget>[
          _Encart(
            couleur: VfColors.brandRed,
            icone: Icons.error_outline_rounded,
            titre: v.statutLibelle ?? 'Analyse interrompue',
            texte: v.coutUnites != null && v.coutUnites! > 0
                ? 'Vos ${v.coutUnites} unite(s) vous ont ete rendues.'
                : 'Aucune unite n a ete debitee.',
          ),
          const SizedBox(height: VfSpacing.stack),
          VfGhostButton(
            label: 'Revenir a mes vérifications',
            onPressed: () => context.go(VfRoutes.myVerifications),
          ),
        ];

      default:
        if (etat.tropLong) {
          // Arbitrage n. 28 : un blocage prolonge SE DIT, et l'ecran propose de
          // revenir plus tard plutot que de sonder indefiniment.
          return <Widget>[
            _Encart(
              couleur: VfColors.info,
              icone: Icons.hourglass_bottom_rounded,
              titre: 'L analyse prend plus de temps que prevu',
              texte:
                  'Elle continue cote serveur. Vous pouvez revenir plus tard : '
                  'retrouvez-la dans Mes vérifications.',
            ),
            const SizedBox(height: VfSpacing.stack),
            VfGhostButton(
              label: 'Vérifier maintenant',
              onPressed: () => ref.read(suiviProvider(uuid).notifier).relire(),
            ),
          ];
        }
        return <Widget>[
          _Encart(
            couleur: VfColors.info,
            icone: Icons.info_outline_rounded,
            titre: null,
            // LE TEXTE CORRIGE (reserve C.2). La promesse du simulateur — « une
            // notification vous previendra » — ne peut pas etre tenue en v1 :
            // ni magasin de notifications, ni FCM. Celle-ci est vraie.
            texte:
                'L analyse continue meme si vous quittez cet ecran. '
                'Retrouvez-la dans Mes vérifications.',
          ),
          if (etat.echec != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x10),
            // Une coupure passagere ne fait pas disparaitre le dernier etat
            // connu : « l'ecran reste consultable avec le dernier etat connu ».
            Text(
              'Connexion perdue — dernier état connu affiche.',
              style: VfTextStyles.tXs.copyWith(color: VfColors.slate2),
            ),
          ],
        ];
    }
  }

  Widget _echec(BuildContext context, WidgetRef ref, Object echec) {
    final bool introuvable = echec is NotFoundFailure;
    return VfEmptyState(
      kind: VfEmptyKind.rienEncore,
      icon: introuvable ? Icons.help_outline_rounded : Icons.wifi_off_rounded,
      title: introuvable
          // Un 404 ne veut PAS dire « supprime » : il veut dire « pas pour
          // vous, ou pas la » (contrat 9).
          ? 'Cette analyse n est pas disponible'
          : 'Impossible de lire l avancement',
      message: introuvable
          ? 'Elle a peut-être ete lancee depuis un autre compte.'
          : 'L état d une analyse en cours ne se lit jamais depuis le cache : '
                'il demande le réseau.',
      action: VfGhostButton(
        label: introuvable ? 'Mes vérifications' : 'Réessayer',
        expanded: false,
        onPressed: introuvable
            ? () => context.go(VfRoutes.myVerifications)
            : () => ref.read(suiviProvider(uuid).notifier).relire(),
      ),
    );
  }
}

/// La carte d'en-tête : rond vert, titre, barre d'avancement.
class _EnTete extends StatelessWidget {
  const _EnTete({required this.verification});

  final Verification verification;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      padding: const EdgeInsets.symmetric(
        horizontal: VfSpacing.x16,
        vertical: VfSpacing.x22 + 2,
      ),
      child: Column(
        children: <Widget>[
          Container(
            width: 64,
            height: 64,
            decoration: const BoxDecoration(
              color: VfColors.primarySoft,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.travel_explore_rounded,
              size: 31,
              color: VfColors.primary,
            ),
          ),
          const SizedBox(height: VfSpacing.x12),
          Text('Le laboratoire travaille', style: VfTextStyles.hMd),
          const SizedBox(height: VfSpacing.x6),
          Text(
            verification.apercu,
            textAlign: TextAlign.center,
            style: VfTextStyles.tSm,
          ),
          const SizedBox(height: VfSpacing.x16),
          VfProgressBar(
            value: verification.avancement / 100,
            semanticsLabel: 'Avancement de l analyse',
          ),
          const SizedBox(height: VfSpacing.x6 + 1),
          // « Etape N sur M · environ S s restantes » : ni M ni S ne sont
          // servis. Le serveur publie l'etape courante, son libelle et le
          // pourcentage — c'est exactement ce qui s'affiche, et rien de plus.
          // Un total invente ferait croire a une duree qu'on ne connait pas.
          Text(
            <String>[
              if (verification.etapeLibelle != null) verification.etapeLibelle!,
              '${verification.avancement} %',
            ].join(' · '),
            style: VfTextStyles.tXs,
          ),
        ],
      ),
    );
  }
}

class _Encart extends StatelessWidget {
  const _Encart({
    required this.couleur,
    required this.icone,
    required this.titre,
    required this.texte,
  });

  final Color couleur;
  final IconData icone;
  final String? titre;
  final String texte;

  @override
  Widget build(BuildContext context) => VfCard(
    tight: true,
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Icon(icone, size: 20, color: couleur),
        const SizedBox(width: VfSpacing.x9),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              if (titre != null) ...<Widget>[
                Text(
                  titre!,
                  style: VfTextStyles.tSm.copyWith(
                    color: VfColors.ink,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: VfSpacing.x4),
              ],
              Text(texte, style: VfTextStyles.tXs),
            ],
          ),
        ),
      ],
    ),
  );
}

class _Squelette extends StatelessWidget {
  const _Squelette();

  @override
  Widget build(BuildContext context) => const Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: <Widget>[
      VfSkeleton(height: 180),
      SizedBox(height: VfSpacing.stack),
      VfSkeleton(height: 260),
    ],
  );
}
