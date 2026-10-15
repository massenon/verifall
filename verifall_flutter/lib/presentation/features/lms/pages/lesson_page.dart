import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/lesson_content.dart';
import '../../../../domain/entities/lesson_summary.dart';
import '../../../../domain/repositories/course_repository.dart';
import '../../../../domain/usecases/lms/compteur_de_lecture.dart';
import '../../../shared/shared.dart';
import '../providers/course_provider.dart';
import '../providers/lesson_provider.dart';
import '../widgets/lesson_video.dart';
import '../widgets/lms_banners.dart';

/// Écran 16 — Leçon (`#/lesson`).
///
/// # La durée active minimale, et qui en décide
///
/// Le simulateur annonce 45 secondes. **Le contrat rend la valeur par le
/// serveur** : `meta.secondes_requises`. « Ne pas coder 45. »
///
/// Le compteur local ne tourne que sur `AppLifecycleState.resumed` — c'est une
/// **déclaration**, pas la vérité : le serveur retient le plus petit des deux.
/// « Le client peut annoncer moins ; il ne peut jamais annoncer plus. »
///
/// Le bouton reste **visible mais désactivé**, avec un libellé qui dit
/// pourquoi — « Encore 20 s de lecture », jamais un bouton grisé muet.
class LessonPage extends ConsumerStatefulWidget {
  const LessonPage({required this.slug, required this.leconUuid, super.key});

  final String slug;
  final String leconUuid;

  @override
  ConsumerState<LessonPage> createState() => _LessonPageState();
}

class _LessonPageState extends ConsumerState<LessonPage>
    with WidgetsBindingObserver {
  CompteurDeLecture? _compteur;
  int _secondes = 0;

  CleDeLecon get _cle => CleDeLecon(widget.slug, widget.leconUuid);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// Le point exact de la règle : **le compteur ne tourne qu'au premier plan**.
  ///
  /// C'est un test de cycle de vie, pas un test d'horloge (06-TESTS §6.3).
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _compteur?.reprendre();
    } else {
      _compteur?.mettreEnPause();
    }
  }

  void _brancherLeCompteur(int secondesRequises) {
    if (_compteur != null) {
      return;
    }
    final CompteurDeLecture compteur = ref.read(
      lessonTimerProvider((cle: _cle, requises: secondesRequises)),
    );
    _compteur = compteur;
    compteur.flux.listen((int valeur) {
      if (mounted) {
        setState(() => _secondes = valeur);
      }
    });
    compteur.demarrer();
  }

  @override
  Widget build(BuildContext context) {
    final AsyncValue<LeconLue> lecon = ref.watch(lessonProvider(_cle));
    final AsyncValue<FicheLue> fiche = ref.watch(courseProvider(widget.slug));
    final List<LessonSummary> lecons =
        fiche.value?.fiche.lecons ?? const <LessonSummary>[];
    final int index = lecons.indexWhere(
      (LessonSummary l) => l.uuid == widget.leconUuid,
    );

    return Scaffold(
      appBar: VfAppBar(
        title: index >= 0 && lecons.isNotEmpty
            ? 'Leçon ${index + 1}/${lecons.length}'
            : 'Leçon',
      ),
      body: lecon.when(
        loading: () => const _SqueletteDeProse(),
        error: (Object erreur, StackTrace _) =>
            _ErreurDeLecon(erreur: erreur, slug: widget.slug),
        data: (LeconLue lue) {
          _brancherLeCompteur(lue.lecon.secondesRequises);
          return _Contenu(
            lue: lue,
            slug: widget.slug,
            index: index,
            lecons: lecons,
            secondesLues: _secondes,
          );
        },
      ),
    );
  }
}

class _Contenu extends ConsumerWidget {
  const _Contenu({
    required this.lue,
    required this.slug,
    required this.index,
    required this.lecons,
    required this.secondesLues,
  });

  final LeconLue lue;
  final String slug;
  final int index;
  final List<LessonSummary> lecons;
  final int secondesLues;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final LessonContent contenu = lue.lecon;
    final AsyncValue<IssueDeValidation?> validation = ref.watch(
      validationDeLeconProvider,
    );
    final IssueDeValidation? issue = validation.value;
    final bool acquise =
        contenu.lecon.estAcquise || (issue?.confirmee ?? false);
    final int manquantes = contenu.secondesRequises - secondesLues;

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        VfSpacing.screenInline,
        VfSpacing.screenTopExtra,
        VfSpacing.screenInline,
        VfSpacing.screenBottomExtra,
      ),
      children: <Widget>[
        if (lue.vientDuCache) ...<Widget>[
          VfStaleDataBanner(lueLe: lue.lueLe),
          const SizedBox(height: VfSpacing.stack),
        ],
        if (lecons.isNotEmpty && index >= 0)
          VfProgressBar(value: (index + 1) / lecons.length, thin: true),
        const SizedBox(height: VfSpacing.stack),
        VfEyebrow('${contenu.lecon.minutes} min'),
        const SizedBox(height: VfSpacing.x6),
        Text(contenu.lecon.titre, style: VfTextStyles.hLg),
        const SizedBox(height: VfSpacing.stack),
        LessonVideo(
          videoUrl: contenu.lecon.videoUrl,
          minutes: contenu.lecon.minutes,
          cleDeDegrade: slug,
        ),
        const SizedBox(height: VfSpacing.stack),
        _Corps(html: contenu.contenuHtml),
        const SizedBox(height: VfSpacing.stack),
        _BoutonDeValidation(
          slug: slug,
          leconUuid: contenu.lecon.uuid,
          acquise: acquise,
          secondesLues: secondesLues,
          secondesManquantes: manquantes > 0 ? manquantes : 0,
          enCours: validation.isLoading,
          issue: issue,
        ),
        const SizedBox(height: VfSpacing.stackSm),
        _BoutonDeSuite(
          slug: slug,
          index: index,
          lecons: lecons,
          // Le bouton de suite est desactive tant que la lecon courante n'est
          // pas VALIDEE PAR LE SERVEUR. Une validation en file ne l'ouvre pas :
          // « ne jamais faire croire qu'une ecriture a abouti ».
          actif: acquise,
        ),
      ],
    );
  }
}

/// Le corps de la leçon.
///
/// Rendu par `VfProseBody`, donc en **liste blanche de balises**. « La
/// purification à l'écriture protège de ce qui est en base, pas de ce qui y
/// entrerait un jour par une autre porte. »
///
/// Les liens s'ouvrent en **onglet système**, jamais en WebView maison : « moins
/// de surface d'attaque, session isolée, et le citoyen voit le vrai domaine dans
/// la barre d'adresse avant de saisir quoi que ce soit ».
class _Corps extends StatelessWidget {
  const _Corps({required this.html});

  final String? html;

  @override
  Widget build(BuildContext context) {
    final String? contenu = html;
    if (contenu == null || contenu.trim().isEmpty) {
      return VfCard(
        child: Text(
          'Le texte de cette leçon n a pas encore ete publie.',
          style: VfTextStyles.tSm,
        ),
      );
    }
    return VfCard(
      child: VfProseBody(
        html: contenu,
        onLinkTap: (String url) {
          final Uri? cible = Uri.tryParse(url);
          if (cible != null) {
            launchUrl(cible, mode: LaunchMode.externalApplication);
          }
        },
      ),
    );
  }
}

class _BoutonDeValidation extends ConsumerWidget {
  const _BoutonDeValidation({
    required this.slug,
    required this.leconUuid,
    required this.acquise,
    required this.secondesLues,
    required this.secondesManquantes,
    required this.enCours,
    required this.issue,
  });

  final String slug;
  final String leconUuid;
  final bool acquise;
  final int secondesLues;
  final int secondesManquantes;
  final bool enCours;
  final IssueDeValidation? issue;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    if (acquise) {
      return VfGhostButton(
        label: 'Leçon terminée',
        icon: Icons.check_circle_rounded,
        expanded: true,
        onPressed: null,
      );
    }

    final bool peutValider = secondesManquantes == 0;
    final String? messageDuServeur = issue?.messageDuServeur;

    return Column(
      children: <Widget>[
        VfPrimaryButton(
          // Le bouton DIT CE QUI MANQUE. « Jamais un bouton grise muet. »
          label: peutValider
              ? 'Marquer comme terminée'
              : 'Encore $secondesManquantes s de lecture',
          expanded: true,
          busy: enCours,
          onPressed: peutValider && !enCours
              ? () => _valider(context, ref)
              : null,
        ),
        if (issue?.enFile ?? false) ...<Widget>[
          const SizedBox(height: VfSpacing.x10),
          // « Une lecon en attente n'est pas presentee comme validee. »
          EncartReseauRequis(
            titre: 'Validation en attente d envoi',
            raison:
                'Elle partira des le retour du réseau. La leçon reste '
                'affichee comme non validee tant que le serveur ne l a pas '
                'confirmee.',
            icone: Icons.cloud_upload_outlined,
          ),
        ],
        if (messageDuServeur != null && !(issue?.enFile ?? false)) ...<Widget>[
          const SizedBox(height: VfSpacing.x10),
          Text(
            // Le message du SERVEUR, affiche tel quel : il dit combien de
            // secondes sont necessaires (§ `422 action_impossible`).
            messageDuServeur,
            style: VfTextStyles.tXs.copyWith(color: VfColors.verdictFalse),
          ),
        ],
      ],
    );
  }

  Future<void> _valider(BuildContext context, WidgetRef ref) async {
    final IssueDeValidation? issue = await ref
        .read(validationDeLeconProvider.notifier)
        .valider(
          cle: CleDeLecon(slug, leconUuid),
          // Le compteur local est une DECLARATION. Le serveur retiendra le plus
          // petit des deux — sa mesure et celle-ci.
          secondes: secondesLues,
        );
    if (!context.mounted || issue == null) {
      return;
    }
    if (issue.confirmee) {
      VfToast.show(context, message: 'Leçon validee.');
    } else if (issue.enFile) {
      VfToast.show(context, message: 'Validation en attente d envoi.');
    } else if (issue.messageDuServeur != null) {
      VfToast.show(
        context,
        message: issue.messageDuServeur!,
        variant: VfToastVariant.erreur,
      );
    }
  }
}

class _BoutonDeSuite extends StatelessWidget {
  const _BoutonDeSuite({
    required this.slug,
    required this.index,
    required this.lecons,
    required this.actif,
  });

  final String slug;
  final int index;
  final List<LessonSummary> lecons;
  final bool actif;

  @override
  Widget build(BuildContext context) {
    final bool derniere = index < 0 || index + 1 >= lecons.length;
    if (derniere) {
      return VfGhostButton(
        label: 'Passer au quiz final',
        expanded: true,
        onPressed: actif ? () => context.push(VfRoutes.quiz(slug)) : null,
      );
    }
    final LessonSummary suivante = lecons[index + 1];
    return VfGhostButton(
      label: 'Leçon suivante',
      icon: Icons.arrow_forward_rounded,
      expanded: true,
      onPressed: actif
          ? () => context.pushReplacement(VfRoutes.lesson(slug, suivante.uuid))
          : null,
    );
  }
}

class _SqueletteDeProse extends StatelessWidget {
  const _SqueletteDeProse();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      children: const <Widget>[
        VfSkeleton(height: 6, width: double.infinity),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 26, width: 240),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 150, width: double.infinity),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 220, width: double.infinity),
      ],
    );
  }
}

/// L'erreur d'ouverture d'une leçon.
///
/// « Leçon verrouillée atteinte par lien profond : retour à la fiche du cours
/// **avec la raison**. » Le message du serveur — « Terminez la leçon précédente
/// pour ouvrir celle-ci » — est affiché tel quel.
class _ErreurDeLecon extends StatelessWidget {
  const _ErreurDeLecon({required this.erreur, required this.slug});

  final Object erreur;
  final String slug;

  @override
  Widget build(BuildContext context) {
    final bool verrouillee = erreur is ForbiddenFailure;
    final String? message = erreur is Failure
        ? (erreur as Failure).serverMessage
        : null;

    return Padding(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      child: VfEmptyState(
        kind: VfEmptyKind.aucunResultat,
        icon: verrouillee ? Icons.lock_rounded : Icons.cloud_off_rounded,
        title: verrouillee ? 'Leçon verrouillee' : 'Leçon indisponible',
        message:
            message ??
            'Cette leçon n est pas encore dans le cache de cet appareil, et '
                'le réseau ne repond pas.',
        action: VfGhostButton(
          label: 'Revenir au cours',
          onPressed: () => context.go(VfRoutes.course(slug)),
        ),
      ),
    );
  }
}
