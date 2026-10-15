import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_svg/flutter_svg.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import 'vf_display.dart';

/// Les quatre dégradés nommés du simulateur (§2.7).
///
/// POURQUOI ils existent : dans le simulateur, ils **remplacent des images**.
/// En production ce sont des couvertures distantes, et le dégradé devient le
/// repli — jamais un carré gris.
enum VfCoverGradient {
  a(<Color>[VfColors.forest, VfColors.primary]),
  b(<Color>[VfColors.info, VfColors.xp]),
  c(<Color>[VfColors.verdictInvestigate, VfColors.brandRed]),
  d(<Color>[VfColors.primaryDark, VfColors.verdictTrue]);

  const VfCoverGradient(this.colors);

  final List<Color> colors;

  /// Choisit un dégradé stable à partir d'une clé (slug, uuid).
  ///
  /// POURQUOI stable et non aléatoire : une couverture qui change de couleur à
  /// chaque rafraîchissement donne l'impression que la liste s'est réordonnée.
  static VfCoverGradient forKey(String key) =>
      VfCoverGradient.values[key.hashCode.abs() %
          VfCoverGradient.values.length];
}

/// `.cover` — hauteur 112 (86 en variante brève), ou celle que l'écran demande.
///
/// # Pourquoi une hauteur libre a été ajoutée
///
/// Deux parcours l'ont demandée, chacun avec des maquettes à la main : la fiche
/// d'un cours mesure 140 et le bloc vidéo d'une leçon 150 (M3, arbitrage n° 105),
/// la carte à la une du Hub 120 et l'en-tête d'un article 130 (M4, arbitrage
/// n° 92). M3 avait enveloppé le composant dans un `SizedBox`, M4 avait assumé
/// l'écart de 8 à 18 points. Deux parcours, donc le socle. Arbitrage n° 134.
///
/// [height] **ne remplace pas** [brief] : la variante brève est un état nommé du
/// design system, la hauteur libre est une mesure de maquette. Les mélanger
/// ferait disparaître la première.
///
/// Le cache HTTP ne sert **qu'aux ressources immuables** : couvertures et
/// images, via `cached_network_image`. Pas de mise en cache des réponses JSON
/// par le transport — « la cohérence deviendrait ingérable entre deux caches »
/// (04-RESEAU §4.12).
class VfCover extends StatelessWidget {
  const VfCover({
    required this.gradientKey,
    this.imageUrl,
    this.brief = false,
    this.height,
    this.overlay,
    super.key,
  });

  /// La clé qui choisit le dégradé de repli : le slug ou l'uuid de la ressource.
  final String gradientKey;

  /// L'adresse de la couverture distante. `null` = pas de couverture, le
  /// dégradé suffit.
  final String? imageUrl;

  /// Variante brève : hauteur 86.
  final bool brief;

  /// La hauteur voulue par la maquette, quand elle diffère des deux hauteurs
  /// nommées. `null` = celle du design system.
  final double? height;

  /// Ce qui se pose par-dessus : badge de verdict, durée, catégorie.
  final Widget? overlay;

  static const double _height = 112;
  static const double _briefHeight = 86;

  @override
  Widget build(BuildContext context) {
    final double hauteur = height ?? (brief ? _briefHeight : _height);
    final VfCoverGradient gradient = VfCoverGradient.forKey(gradientKey);

    return ClipRRect(
      borderRadius: VfRadii.lgAll,
      child: SizedBox(
        height: hauteur,
        width: double.infinity,
        child: Stack(
          fit: StackFit.expand,
          children: <Widget>[
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: gradient.colors,
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
              ),
            ),
            if (imageUrl != null && imageUrl!.isNotEmpty)
              if (estSvgPourCouverture(imageUrl!))
                // Les couvertures de cours/articles sont, côté API, des SVG
                // (placeholders générés) : `CachedNetworkImage` ne décode pas
                // ce format et retombait en silence sur le dégradé, couverture
                // JAMAIS affichée. `SvgPicture.network` met en cache ses
                // propres requêtes (doc du paquet) : le repli reste cohérent
                // avec le PNG/JPEG ci-dessous sans dupliquer la logique de cache.
                SvgPicture.network(
                  imageUrl!,
                  fit: BoxFit.cover,
                  width: double.infinity,
                  height: hauteur,
                  placeholderBuilder: (BuildContext context) => VfSkeleton(
                    height: hauteur,
                    borderRadius: BorderRadius.zero,
                  ),
                  errorBuilder:
                      (BuildContext context, Object error, StackTrace stack) =>
                          const SizedBox.shrink(),
                )
              else
                CachedNetworkImage(
                  imageUrl: imageUrl!,
                  fit: BoxFit.cover,
                  // Le squelette est le placeholder par defaut de TOUT chargement,
                  // « y compris des couvertures distantes » (§2.7).
                  placeholder: (BuildContext context, String url) => VfSkeleton(
                    height: hauteur,
                    borderRadius: BorderRadius.zero,
                  ),
                  // Sur echec, on garde le degrade : un carre gris casse la page,
                  // un degrade la tient.
                  errorWidget:
                      (BuildContext context, String url, Object error) =>
                          const SizedBox.shrink(),
                ),
            if (overlay != null) Positioned.fill(child: overlay!),
          ],
        ),
      ),
    );
  }
}

/// L'adresse se termine-t-elle par `.svg`, requête ignorée ?
///
/// Les couvertures de ce projet sont soit des PNG/JPEG distants, soit des SVG
/// générés côté serveur (`assets/images/{courses,hub}/*.svg`) — jamais les
/// deux pour une même ressource. L'extension suffit donc à choisir le
/// décodeur, sans dépendre d'un en-tête `Content-Type` que l'API ne fournit
/// pas forcément de façon fiable.
///
/// Public et non préfixée `_` uniquement pour que le test de routage
/// (`passe_integration_test.dart`) vérifie la décision sans déclencher un
/// vrai chargement réseau — `TestWidgetsFlutterBinding` fausse tout HTTP en
/// 400, ce qui ferait échouer un test passant par le widget complet.
@visibleForTesting
bool estSvgPourCouverture(String url) =>
    Uri.tryParse(url)?.path.toLowerCase().endsWith('.svg') ?? false;
