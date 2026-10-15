import 'dart:ui' as ui;

import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';

/// Le contenu éditorial d'une diapositive.
///
/// **Il se reprend tel quel du simulateur** : numéro, mot-clé, accroche,
/// citation, signature, sous-titre et les deux mots de la trace. C'est la parole
/// du produit, pas une donnée de démonstration — la règle « jamais les données du
/// simulateur en dur » vise les verdicts, les offres et les articles, pas la
/// copie de l'écran d'accueil.
@immutable
class OnboardingSlide {
  const OnboardingSlide({
    required this.numero,
    required this.motCle,
    required this.accroche,
    required this.citation,
    required this.signature,
    required this.sousTitre,
    required this.traceHaut,
    required this.traceBas,
    required this.visuel,
  });

  final String numero;
  final String motCle;
  final String accroche;
  final String citation;
  final String signature;
  final String sousTitre;

  /// Le mot en capitales au-dessus du point : `CLAIM`, `SOURCE`, `EVIDENCE`,
  /// `VERDICT`.
  final String traceHaut;

  /// Le verbe sous le point : `Identifier`, `Questionner`, `Vérifier`,
  /// `Comprendre`.
  final String traceBas;

  /// Le chemin d'une **ressource locale**. Jamais une URL.
  final String visuel;
}

/// Les quatre diapositives, dans l'ordre du prototype `web/connexion.html`.
///
/// Les visuels sont les quatre `images/slider/verifall_fact_news_step_{1..4}.webp`
/// du prototype web, copiés dans `assets/onboarding/`. **Ce sont des ressources
/// locales** : « jamais un chargement réseau au premier lancement ». C'est le
/// premier écran que voit quelqu'un qui vient d'installer l'application, souvent
/// sur une connexion médiocre.
const List<OnboardingSlide> slidesOnboarding = <OnboardingSlide>[
  OnboardingSlide(
    numero: '01',
    motCle: 'IDENTIFIER',
    accroche: 'Comprendre avant de croire.',
    citation:
        'Savoir distinguer le vrai du faux est un super-pouvoir numérique. '
        'Apprenez les réflexes essentiels pour ne plus jamais vous faire manipuler.',
    signature: 'Vérif’All Lab',
    sousTitre: 'Boussole de l’information',
    traceHaut: 'CLAIM',
    traceBas: 'Identifier',
    visuel: 'assets/onboarding/slide_01.webp',
  ),
  OnboardingSlide(
    numero: '02',
    motCle: 'QUESTIONNER',
    accroche: 'Qui parle, et pourquoi ?',
    citation:
        'Qui parle ? Une source fiable cite toujours ses origines. Si le site est '
        'inconnu ou l’auteur anonyme, utilisez notre outil de vérification de source.',
    signature: 'Vérifiez l’origine',
    sousTitre: 'Ne partagez pas l’inconnu',
    traceHaut: 'SOURCE',
    traceBas: 'Questionner',
    visuel: 'assets/onboarding/slide_02.webp',
  ),
  OnboardingSlide(
    numero: '03',
    motCle: 'VÉRIFIER',
    accroche: 'L’émotion n’est pas une preuve.',
    citation:
        'Si une info provoque une colère ou une joie intense, méfiez-vous. La '
        'désinformation joue sur vos émotions pour court-circuiter votre logique.',
    signature: 'Gardez la tête froide',
    sousTitre: 'L’émotion n’est pas une preuve',
    traceHaut: 'EVIDENCE',
    traceBas: 'Vérifier',
    visuel: 'assets/onboarding/slide_03.webp',
  ),
  OnboardingSlide(
    numero: '04',
    motCle: 'COMPRENDRE',
    accroche: 'La forme trahit souvent le fond.',
    citation:
        'Un communiqué officiel truffé de fautes ? C’est souvent une arnaque. La '
        'crédibilité d’une information passe aussi par la qualité de sa rédaction.',
    signature: 'Soyez attentifs aux détails',
    sousTitre: 'La forme trahit souvent le fond',
    traceHaut: 'VERDICT',
    traceBas: 'Comprendre',
    visuel: 'assets/onboarding/slide_04.webp',
  ),
];

/// Le fond d'une diapositive : le visuel **traité en texture**, sous son voile.
///
/// # Pourquoi le visuel est flouté
///
/// C'est une décision assumée, écrite en commentaire dans le simulateur : les
/// affiches du slider web sont très typographiques ; « en portrait, sous une
/// copie superposée, une affiche lisible devient un fond illisible ». D'où
/// `blur(3px) saturate(.8) brightness(.92)` et `scale(1.12)` — l'échelle
/// compense le bord flou.
///
/// # Pourquoi le repli est le fond forêt uni
///
/// Le seul risque de cet écran est un visuel manquant dans le paquet. Un carré
/// gris ferait croire à un chargement qui n'arrive pas ; le fond forêt est
/// exactement ce que le voile produit déjà par-dessus.
class AuthSlideBackground extends StatelessWidget {
  const AuthSlideBackground({required this.slide, super.key});

  final OnboardingSlide slide;

  /// `saturate(.8)` puis `brightness(.92)`, écrits en matrice.
  ///
  /// Les coefficients de luminance sont ceux de la spécification des filtres
  /// CSS (0.213 / 0.715 / 0.072), pour que le rendu soit celui du simulateur et
  /// non « quelque chose d'approchant ».
  static const ColorFilter filtreTexture = ColorFilter.matrix(<double>[
    0.775192, 0.13156, 0.013248, 0, 0, //
    0.039192, 0.86756, 0.013248, 0, 0, //
    0.039192, 0.13156, 0.749248, 0, 0, //
    0, 0, 0, 1, 0, //
  ]);

  /// Le voile net posé au-dessus du visuel flou : sombre en haut pour la
  /// légende, sombre en bas pour la citation, plus ouvert au centre.
  static const LinearGradient voile = LinearGradient(
    begin: Alignment.topCenter,
    end: Alignment.bottomCenter,
    colors: <Color>[
      Color.fromRGBO(5, 22, 15, 0.86),
      Color.fromRGBO(5, 22, 15, 0.55),
      Color.fromRGBO(6, 42, 29, 0.72),
      Color.fromRGBO(6, 59, 42, 0.95),
      Color.fromRGBO(6, 59, 42, 0.99),
    ],
    stops: <double>[0, 0.24, 0.54, 0.80, 1],
  );

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: VfColors.forest,
      child: Stack(
        fit: StackFit.expand,
        children: <Widget>[
          ImageFiltered(
            imageFilter: ui.ImageFilter.blur(sigmaX: 3, sigmaY: 3),
            child: ColorFiltered(
              colorFilter: filtreTexture,
              child: Transform.scale(
                scale: 1.12,
                child: Image.asset(
                  slide.visuel,
                  fit: BoxFit.cover,
                  // Le repli, et il est explicite : le fond foret uni, jamais un
                  // carre gris.
                  errorBuilder:
                      (BuildContext context, Object error, StackTrace? stack) =>
                          const ColoredBox(color: VfColors.forest),
                ),
              ),
            ),
          ),
          const DecoratedBox(decoration: BoxDecoration(gradient: voile)),
        ],
      ),
    );
  }
}

/// La légende du haut : le numéro, le mot-clé, l'accroche.
class AuthSlideCaption extends StatelessWidget {
  const AuthSlideCaption({required this.slide, super.key});

  final OnboardingSlide slide;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Row(
          crossAxisAlignment: CrossAxisAlignment.baseline,
          textBaseline: TextBaseline.alphabetic,
          children: <Widget>[
            Text(
              slide.numero,
              style: VfTextStyles.kpiValue.copyWith(
                fontSize: 44,
                height: 1,
                color: VfColors.surface.withValues(alpha: 0.24),
              ),
            ),
            const SizedBox(width: 11),
            Flexible(
              child: Text(
                slide.motCle,
                style: VfTextStyles.eyebrow.copyWith(
                  fontSize: 12,
                  letterSpacing: 12 * 0.22,
                  color: VfColors.primary,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 2),
        Text(
          slide.accroche,
          style: VfTextStyles.tSm.copyWith(
            color: VfColors.surface.withValues(alpha: 0.72),
          ),
        ),
      ],
    );
  }
}

/// La citation, la signature et le sous-titre.
class AuthSlideQuote extends StatelessWidget {
  const AuthSlideQuote({required this.slide, super.key});

  final OnboardingSlide slide;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          // Les guillemets francais font partie de la composition du simulateur.
          '« ${slide.citation} »',
          style: VfTextStyles.body.copyWith(
            fontSize: 21,
            height: 1.35,
            fontWeight: FontWeight.w300,
            color: VfColors.surface,
          ),
        ),
        const SizedBox(height: 18),
        Text(
          slide.signature,
          style: VfTextStyles.kpiValue.copyWith(
            fontSize: 16,
            height: 1.2,
            fontWeight: FontWeight.w700,
            color: VfColors.surface,
          ),
        ),
        Text(
          slide.sousTitre,
          style: VfTextStyles.tSm.copyWith(color: VfColors.onForestMuted),
        ),
      ],
    );
  }
}

/// La **trace de vérification** — la pagination du carrousel, et elle enseigne
/// quelque chose.
///
/// Quatre points reliés par un trait, chacun surmonté d'un mot en capitales et
/// sous-titré d'un verbe : `CLAIM/Identifier · SOURCE/Questionner ·
/// EVIDENCE/Vérifier · VERDICT/Comprendre`. C'est la méthode du produit, posée
/// là où d'autres mettent quatre pastilles muettes.
///
/// POURQUOI chaque point est un bouton avec son libellé complet : « CLAIM :
/// Identifier, étape 1 sur 4 ». Une pastille de pagination sans libellé est
/// inatteignable au lecteur d'écran.
class AuthVerificationTrace extends StatelessWidget {
  const AuthVerificationTrace({
    required this.slides,
    required this.index,
    required this.onSelected,
    super.key,
  });

  final List<OnboardingSlide> slides;
  final int index;
  final ValueChanged<int> onSelected;

  @override
  Widget build(BuildContext context) {
    final List<Widget> enfants = <Widget>[];
    for (int i = 0; i < slides.length; i++) {
      if (i > 0) {
        enfants.add(
          Expanded(
            child: Container(
              height: 2,
              color: VfColors.surface.withValues(alpha: 0.18),
            ),
          ),
        );
      }
      enfants.add(
        _TracePoint(
          slide: slides[i],
          actif: i == index,
          position: i + 1,
          total: slides.length,
          onTap: () => onSelected(i),
        ),
      );
    }
    return Row(children: enfants);
  }
}

class _TracePoint extends StatelessWidget {
  const _TracePoint({
    required this.slide,
    required this.actif,
    required this.position,
    required this.total,
    required this.onTap,
  });

  final OnboardingSlide slide;
  final bool actif;
  final int position;
  final int total;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: actif,
      label:
          '${slide.traceHaut} : ${slide.traceBas}, étape $position sur $total',
      excludeSemantics: true,
      child: GestureDetector(
        onTap: onTap,
        behavior: HitTestBehavior.opaque,
        child: ConstrainedBox(
          // La cible tactile fait 44 de haut meme si le dessin en fait moins :
          // la regle des 44 vaut aussi pour une pagination.
          constraints: const BoxConstraints(
            minWidth: 52,
            minHeight: VfTouch.minimum,
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Text(
                slide.traceHaut,
                style: VfTextStyles.eyebrow.copyWith(
                  fontSize: 8.5,
                  letterSpacing: 8.5 * 0.14,
                  color: actif
                      ? VfColors.primary
                      : VfColors.surface.withValues(alpha: 0.45),
                ),
              ),
              const SizedBox(height: 5),
              Container(
                width: 11,
                height: 11,
                margin: const EdgeInsets.symmetric(vertical: 4),
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: actif
                      ? VfColors.primary
                      : VfColors.surface.withValues(alpha: 0.3),
                  boxShadow: actif
                      ? <BoxShadow>[
                          BoxShadow(
                            color: VfColors.primary.withValues(alpha: 0.28),
                            spreadRadius: 4,
                          ),
                        ]
                      : null,
                ),
              ),
              const SizedBox(height: 5),
              Text(
                slide.traceBas,
                style: VfTextStyles.tXs.copyWith(
                  fontSize: 9.5,
                  fontWeight: FontWeight.w600,
                  color: actif
                      ? VfColors.surface
                      : VfColors.surface.withValues(alpha: 0.5),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// Le second bouton du carrousel : contour blanc à 30 %, sur fond forêt.
///
/// POURQUOI il n'utilise pas `VfOutlinedButton` : la variante cerclée du design
/// system est **verte sur fond clair**. Celle-ci est blanche sur fond sombre, et
/// c'est un style d'écran — le simulateur l'écrit lui-même en attribut `style`
/// sur ce bouton précis, pas comme une variante du DS. En inventer une sixième
/// dans `VfButtonVariant` aurait été modifier le socle pour un seul emploi.
class AuthOutlineOnForestButton extends StatelessWidget {
  const AuthOutlineOnForestButton({
    required this.label,
    required this.onPressed,
    super.key,
  });

  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      child: GestureDetector(
        onTap: onPressed,
        behavior: HitTestBehavior.opaque,
        child: Container(
          width: double.infinity,
          constraints: const BoxConstraints(minHeight: VfTouch.buttonMinHeight),
          alignment: Alignment.center,
          padding: const EdgeInsets.symmetric(
            horizontal: VfTouch.buttonInlinePadding,
            vertical: VfSpacing.x12,
          ),
          decoration: BoxDecoration(
            borderRadius: VfRadii.mdAll,
            border: Border.all(color: VfColors.surface.withValues(alpha: 0.3)),
          ),
          child: Text(
            label,
            textAlign: TextAlign.center,
            style: VfTextStyles.button.copyWith(color: VfColors.surface),
          ),
        ),
      ),
    );
  }
}
