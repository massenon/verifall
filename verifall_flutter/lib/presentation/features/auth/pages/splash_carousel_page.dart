import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_motion.dart';
import '../../../../core/theme/vf_theme.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../shared/shared.dart';
import '../providers/onboarding_provider.dart';
import '../widgets/auth_slide.dart';

/// Écran 01 — l'écran d'accueil, et **il ne s'affiche qu'une fois**.
///
/// # Aucun appel réseau
///
/// Les quatre visuels sont des ressources locales. C'est écrit noir sur blanc
/// dans le simulateur, et la raison est le cas d'usage principal du produit :
/// c'est le premier écran que voit quelqu'un qui vient d'installer
/// l'application, souvent sur une connexion médiocre. Un carrousel qui attend le
/// réseau pour s'afficher est un écran noir.
///
/// # Une seule fois
///
/// Le drapeau `onboarding_seen` vit en stockage sécurisé, aux côtés du jeton, et
/// il est posé **au moment où la personne quitte l'écran** — par l'un des trois
/// boutons de sortie. Le poser à l'affichage aurait fait disparaître le
/// carrousel après un lancement interrompu.
///
/// # L'avance automatique et le lecteur d'écran
///
/// Elle s'arrête sous `disableAnimations` et dès que le focus entre dans la
/// page : « un carrousel qui défile pendant qu'un lecteur d'écran lit la
/// diapositive précédente rend le contenu inatteignable ». Elle s'arrête aussi
/// définitivement au premier geste — quelqu'un qui pilote n'a plus besoin d'être
/// piloté.
class SplashCarouselPage extends ConsumerStatefulWidget {
  const SplashCarouselPage({super.key});

  /// L'intervalle du simulateur, à la centaine de millisecondes près.
  static const Duration intervalle = Duration(milliseconds: 4200);

  @override
  ConsumerState<SplashCarouselPage> createState() => _SplashCarouselPageState();
}

class _SplashCarouselPageState extends ConsumerState<SplashCarouselPage> {
  final PageController _pages = PageController();
  Timer? _minuteur;
  int _index = 0;
  bool _pilotageManuel = false;

  @override
  void dispose() {
    _minuteur?.cancel();
    _pages.dispose();
    super.dispose();
  }

  void _replanifier() {
    _minuteur?.cancel();
    if (_pilotageManuel || VfMotion.reducedMotion(context)) {
      return;
    }
    _minuteur = Timer(SplashCarouselPage.intervalle, () {
      if (!mounted) {
        return;
      }
      _allerA((_index + 1) % slidesOnboarding.length, manuel: false);
    });
  }

  void _allerA(int index, {required bool manuel}) {
    if (manuel) {
      _pilotageManuel = true;
      _minuteur?.cancel();
    }
    setState(() => _index = index);
    if (_pages.hasClients) {
      unawaited(
        _pages.animateToPage(
          index,
          duration: VfMotion.navigation,
          curve: VfMotion.curve,
        ),
      );
    }
    if (!manuel) {
      _replanifier();
    }
  }

  /// La sortie : on note que le carrousel a été vu, **puis** on navigue.
  Future<void> _sortirVers(String chemin) async {
    _minuteur?.cancel();
    await ref.read(marquerOnboardingVuProvider)();
    if (mounted) {
      context.go(chemin);
    }
  }

  @override
  Widget build(BuildContext context) {
    final AsyncValue<bool> vu = ref.watch(onboardingSeenProvider);

    // Deja vu : on ne montre RIEN et on passe la main. Pas d'indicateur
    // d'attente, pas de premiere diapositive entrapercue — le fond foret est
    // exactement ce que l'ecran suivant affiche aussi.
    if (vu.value ?? false) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          context.go(VfRoutes.login);
        }
      });
      return const _FondForet();
    }

    // En cours de lecture du stockage securise, ou lecture en echec : meme
    // rendu. Une lecture qui echoue doit montrer le carrousel, pas un ecran
    // d'erreur — au pire il s'affiche une fois de trop.
    if (vu.isLoading) {
      return const _FondForet();
    }

    _replanifier();

    return AnnotatedRegion<SystemUiOverlayStyle>(
      // Fond foret : barre d'etat claire.
      value: VfTheme.statusBarOnDark,
      child: Scaffold(
        backgroundColor: VfColors.forest,
        body: Focus(
          // Le focus qui entre dans la page arrete le defilement : c'est la
          // condition pour qu'un lecteur d'ecran puisse lire une diapositive
          // jusqu'au bout.
          onFocusChange: (bool aLeFocus) {
            if (aLeFocus) {
              _pilotageManuel = true;
              _minuteur?.cancel();
            }
          },
          child: Stack(
            fit: StackFit.expand,
            children: <Widget>[
              PageView.builder(
                controller: _pages,
                itemCount: slidesOnboarding.length,
                onPageChanged: (int i) {
                  if (i != _index) {
                    _allerA(i, manuel: true);
                  }
                },
                itemBuilder: (BuildContext context, int i) =>
                    AuthSlideBackground(slide: slidesOnboarding[i]),
              ),
              SafeArea(child: _Legende(this)),
            ],
          ),
        ),
      ),
    );
  }
}

/// Le fond forêt seul — le repli, et l'écran d'attente.
class _FondForet extends StatelessWidget {
  const _FondForet();

  @override
  Widget build(BuildContext context) =>
      const AnnotatedRegion<SystemUiOverlayStyle>(
        value: VfTheme.statusBarOnDark,
        child: Scaffold(
          backgroundColor: VfColors.forest,
          body: SizedBox.expand(),
        ),
      );
}

class _Legende extends StatelessWidget {
  const _Legende(this._etat);

  final _SplashCarouselPageState _etat;

  @override
  Widget build(BuildContext context) {
    final OnboardingSlide slide = slidesOnboarding[_etat._index];
    final bool derniere = _etat._index == slidesOnboarding.length - 1;

    return Padding(
      padding: const EdgeInsets.fromLTRB(24, 26, 24, 26),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Expanded(child: AuthSlideCaption(slide: slide)),
              // « Passer » est atteignable au premier Tab : c'est la sortie.
              TextButton(
                onPressed: () => _etat._sortirVers(VfRoutes.login),
                style: TextButton.styleFrom(
                  minimumSize: const Size(0, VfTouch.minimum),
                  foregroundColor: VfColors.surface,
                ),
                child: Text(
                  'Passer',
                  style: VfTextStyles.tSm.copyWith(
                    fontWeight: FontWeight.w600,
                    color: VfColors.surface.withValues(alpha: 0.72),
                  ),
                ),
              ),
            ],
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              AuthSlideQuote(slide: slide),
              const SizedBox(height: 22),
              AuthVerificationTrace(
                slides: slidesOnboarding,
                index: _etat._index,
                onSelected: (int i) => _etat._allerA(i, manuel: true),
              ),
              const SizedBox(height: 20),
              VfPrimaryButton(
                // Le libelle change sur la derniere diapositive : le bouton dit
                // ce qui va se passer.
                label: derniere ? 'Commencer' : 'Se connecter',
                onPressed: () => _etat._sortirVers(VfRoutes.login),
              ),
              const SizedBox(height: VfSpacing.x9),
              AuthOutlineOnForestButton(
                label: 'Créer un compte citoyen',
                onPressed: () => _etat._sortirVers(VfRoutes.register),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
