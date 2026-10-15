import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'vf_colors.dart';
import 'vf_metrics.dart';
import 'vf_typography.dart';

/// Le thème de l'application.
///
/// # Une seule palette, et elle est claire
///
/// « L'application reste en thème clair, conforme au DS » — le simulateur expose
/// un bouton « Thème », mais son propre bandeau précise qu'il ne concerne que le
/// banc d'essai (§2). **Ne pas écrire de palette sombre, pas même « au cas
/// où »** : une seconde palette non validée finit toujours par s'afficher chez
/// quelqu'un.
///
/// # Material 3 n'est pas le design du produit
///
/// Material 3 n'est que le socle de widgets par-dessus lequel le thème Vérif'All
/// est posé (§1.1). Tout ce qui suit consiste à **neutraliser** les partis pris
/// de Material — teintes de surface, ondulations, hauteurs par défaut — pour que
/// les composants `Vf*` rendent ce que rend le prototype web.
abstract final class VfTheme {
  static ThemeData light() {
    final ColorScheme scheme = ColorScheme.fromSeed(
      seedColor: VfColors.primary,
      // Les couleurs cles sont imposees, pas derivees : une teinte generee par
      // Material serait « proche » de la charte, et proche ne suffit pas quand
      // le meme bouton existe deja sur le web.
      primary: VfColors.primary,
      onPrimary: VfColors.surface,
      secondary: VfColors.forest,
      onSecondary: VfColors.surface,
      error: VfColors.brandRed,
      onError: VfColors.surface,
      surface: VfColors.surface,
      onSurface: VfColors.ink,
      outline: VfColors.line,
    );

    final TextTheme textTheme = TextTheme(
      displayLarge: VfTextStyles.hLg,
      headlineMedium: VfTextStyles.hLg,
      titleLarge: VfTextStyles.hMd,
      titleMedium: VfTextStyles.hSm,
      bodyLarge: VfTextStyles.body,
      bodyMedium: VfTextStyles.tMd,
      bodySmall: VfTextStyles.tSm,
      labelSmall: VfTextStyles.tXs,
      labelLarge: VfTextStyles.button,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      // Le fond general du produit, `--canvas`. Material poserait sinon une
      // surface teintee par l'algorithme de couleur.
      scaffoldBackgroundColor: VfColors.canvas,
      fontFamily: VfFonts.body,
      textTheme: textTheme,
      primaryTextTheme: textTheme,

      // POURQUOI on eteint la teinte de surface : Material 3 assombrit une
      // surface a mesure qu'elle s'eleve. Le design system ne connait qu'un
      // blanc, tenu par une bordure de 1 (§2.2).
      cardTheme: const CardThemeData(
        color: VfColors.surface,
        surfaceTintColor: VfColors.surface,
        elevation: 0,
        margin: EdgeInsets.zero,
        shape: RoundedRectangleBorder(borderRadius: VfRadii.lgAll),
      ),

      appBarTheme: const AppBarTheme(
        backgroundColor: VfColors.surface,
        surfaceTintColor: VfColors.surface,
        foregroundColor: VfColors.ink,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        toolbarHeight: VfChrome.appBarHeight,
        titleTextStyle: VfTextStyles.hMd,
        // Barre d'etat claire sur fond clair. Elle est basculee explicitement
        // sur le splash, la connexion et toute superposition sombre — et il
        // faut la REMETTRE en fermant la superposition, sinon on laisse des
        // icones blanches sur fond blanc (§2.5).
        systemOverlayStyle: statusBarOnLight,
      ),

      dividerTheme: const DividerThemeData(
        color: VfColors.line,
        thickness: 1,
        space: 1,
      ),

      // Le rendu par defaut des boutons Material. Les composants `Vf*` les
      // habillent ; ce theme sert de filet pour tout bouton pose sans eux.
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: VfColors.primary,
          foregroundColor: VfColors.surface,
          disabledBackgroundColor: VfColors.primary.withValues(alpha: 0.45),
          disabledForegroundColor: VfColors.surface.withValues(alpha: 0.9),
          minimumSize: const Size(0, VfTouch.buttonMinHeight),
          padding: const EdgeInsets.symmetric(
            horizontal: VfTouch.buttonInlinePadding,
          ),
          textStyle: VfTextStyles.button,
          elevation: 0,
          shape: const RoundedRectangleBorder(borderRadius: VfRadii.mdAll),
        ),
      ),

      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: VfColors.primary,
          side: const BorderSide(color: VfColors.primary),
          minimumSize: const Size(0, VfTouch.buttonMinHeight),
          padding: const EdgeInsets.symmetric(
            horizontal: VfTouch.buttonInlinePadding,
          ),
          textStyle: VfTextStyles.button,
          shape: const RoundedRectangleBorder(borderRadius: VfRadii.mdAll),
        ),
      ),

      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: VfColors.primary,
          textStyle: VfTextStyles.button,
          minimumSize: const Size(0, VfTouch.minimum),
        ),
      ),

      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          foregroundColor: VfColors.ink,
          minimumSize: const Size.square(VfTouch.iconButtonSize),
          shape: const CircleBorder(),
        ),
      ),

      iconTheme: const IconThemeData(color: VfColors.ink, size: 22),

      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: VfColors.surface,
        // L'etiquette flottante est le comportement du champ `.field` : posee
        // dans le champ, elle remonte et retrecit au focus ou des qu'il y a du
        // texte (§2.7).
        floatingLabelBehavior: FloatingLabelBehavior.auto,
        labelStyle: VfTextStyles.tMd.copyWith(color: VfColors.slate2),
        hintStyle: VfTextStyles.tMd.copyWith(color: VfColors.slate2),
        errorStyle: VfTextStyles.tSm.copyWith(color: VfColors.brandRed),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: VfSpacing.x14,
          vertical: VfSpacing.x12,
        ),
        border: const OutlineInputBorder(
          borderRadius: VfRadii.mdAll,
          borderSide: BorderSide(color: VfColors.line),
        ),
        enabledBorder: const OutlineInputBorder(
          borderRadius: VfRadii.mdAll,
          borderSide: BorderSide(color: VfColors.line),
        ),
        focusedBorder: const OutlineInputBorder(
          borderRadius: VfRadii.mdAll,
          borderSide: BorderSide(color: VfColors.primary, width: 1.5),
        ),
        errorBorder: const OutlineInputBorder(
          borderRadius: VfRadii.mdAll,
          borderSide: BorderSide(color: VfColors.brandRed),
        ),
        focusedErrorBorder: const OutlineInputBorder(
          borderRadius: VfRadii.mdAll,
          borderSide: BorderSide(color: VfColors.brandRed, width: 1.5),
        ),
      ),

      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: VfColors.surface,
        surfaceTintColor: VfColors.surface,
        elevation: 0,
        showDragHandle: false,
        shape: RoundedRectangleBorder(borderRadius: VfRadii.sheet),
      ),

      snackBarTheme: SnackBarThemeData(
        backgroundColor: VfColors.ink,
        contentTextStyle: VfTextStyles.tMd.copyWith(color: VfColors.surface),
        behavior: SnackBarBehavior.floating,
        elevation: 0,
        shape: const RoundedRectangleBorder(borderRadius: VfRadii.mdAll),
      ),

      chipTheme: ChipThemeData(
        backgroundColor: VfColors.surface,
        selectedColor: VfColors.primary,
        side: const BorderSide(color: VfColors.line),
        labelStyle: VfTextStyles.tSm.copyWith(color: VfColors.ink),
        shape: const StadiumBorder(),
        padding: const EdgeInsets.symmetric(horizontal: VfSpacing.x12),
      ),

      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: VfColors.primary,
        linearTrackColor: VfColors.line,
        linearMinHeight: 8,
      ),

      listTileTheme: const ListTileThemeData(
        minVerticalPadding: VfTouch.menuRowVerticalPadding,
        contentPadding: EdgeInsets.symmetric(
          horizontal: VfTouch.menuRowInlinePadding,
        ),
        titleTextStyle: VfTextStyles.body,
        subtitleTextStyle: VfTextStyles.tSm,
      ),

      // POURQUOI on coupe l'ondulation : le prototype n'en a pas, et une
      // ondulation Material sur une carte a bordure fine se voit deborder du
      // rayon. Les composants `Vf*` posent leur propre etat de contact a 82 %
      // d'opacite (§2.7).
      splashFactory: NoSplash.splashFactory,
      highlightColor: Colors.transparent,
    );
  }

  /// Barre d'état claire, sur fond clair.
  static const SystemUiOverlayStyle statusBarOnLight = SystemUiOverlayStyle(
    statusBarColor: Color(0x00000000),
    statusBarIconBrightness: Brightness.dark,
    statusBarBrightness: Brightness.light,
  );

  /// Barre d'état blanche, sur fond sombre.
  ///
  /// Employée sur le splash, la connexion, et dès qu'une superposition sombre
  /// est ouverte : tiroir, feuille, voile. **La remettre en fermant la
  /// superposition** — un oubli laisse des icônes blanches sur fond blanc.
  static const SystemUiOverlayStyle statusBarOnDark = SystemUiOverlayStyle(
    statusBarColor: Color(0x00000000),
    statusBarIconBrightness: Brightness.light,
    statusBarBrightness: Brightness.dark,
  );

  /// Le facteur d'échelle du texte, borné.
  ///
  /// POURQUOI on le borne au lieu de le laisser libre : « le texte doit suivre
  /// le réglage d'accessibilité du téléphone, mais les cartes doivent rester
  /// lisibles à 200 % » (§2.3). Au-delà, les hauteurs minimales du simulateur ne
  /// suffisent plus et la mise en page se disloque. On accompagne l'échelle
  /// jusqu'à 2,0 — la valeur que les tests de capture vérifient — et on s'arrête
  /// là plutôt que de rendre un écran illisible.
  static const double maxTextScaleFactor = 2.0;
}
