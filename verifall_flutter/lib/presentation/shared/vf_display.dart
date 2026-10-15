import 'package:flutter/material.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_motion.dart';
import '../../core/theme/vf_typography.dart';

/// `.tag` — trois variantes, et il n'y en a pas d'autres (§2.7).
enum VfTagVariant {
  neutre(VfColors.line, VfColors.slate2),

  /// Violet des points d'expérience.
  xp(Color(0x1F7C3AED), VfColors.xp),

  /// Vert clair.
  prim(VfColors.primarySoft, VfColors.primaryDark);

  const VfTagVariant(this.background, this.foreground);

  final Color background;
  final Color foreground;
}

/// `.tag` — une pastille d'étiquette.
class VfTag extends StatelessWidget {
  const VfTag({
    required this.label,
    this.variant = VfTagVariant.neutre,
    this.icon,
    super.key,
  });

  final String label;
  final VfTagVariant variant;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: VfSpacing.x8,
        vertical: VfSpacing.x4,
      ),
      decoration: BoxDecoration(
        color: variant == VfTagVariant.neutre
            ? VfColors.canvas
            : variant.background,
        borderRadius: VfRadii.pillAll,
        border: variant == VfTagVariant.neutre
            ? Border.all(color: VfColors.line)
            : null,
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          if (icon != null) ...<Widget>[
            Icon(icon, size: 13, color: variant.foreground),
            const SizedBox(width: VfSpacing.x4),
          ],
          Text(
            label,
            style: VfTextStyles.tXs.copyWith(
              color: variant.foreground,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

/// `.kpi` — valeur 24/800, libellé 11.
///
/// POURQUOI la grille est imposée à deux colonnes avec gouttière 10 : c'est ce
/// que fait le simulateur, et un KPI seul sur une ligne change complètement la
/// densité de l'écran d'accueil.
class VfKpiCard extends StatelessWidget {
  const VfKpiCard({
    required this.value,
    required this.label,
    this.icon,
    this.accent = VfColors.ink,
    super.key,
  });

  final String value;
  final String label;
  final IconData? icon;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      // La valeur AVANT le libelle : c'est l'ordre dans lequel l'information a
      // du sens a l'oreille.
      label: '$value $label',
      excludeSemantics: true,
      child: Container(
        constraints: const BoxConstraints(minHeight: 76),
        padding: const EdgeInsets.all(VfSpacing.x12),
        decoration: BoxDecoration(
          color: VfColors.surface,
          borderRadius: VfRadii.lgAll,
          border: Border.all(color: VfColors.line),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          mainAxisAlignment: MainAxisAlignment.center,
          children: <Widget>[
            if (icon != null) ...<Widget>[
              Icon(icon, size: 18, color: accent),
              const SizedBox(height: VfSpacing.x6),
            ],
            Text(value, style: VfTextStyles.kpiValue.copyWith(color: accent)),
            const SizedBox(height: VfSpacing.x4),
            Text(
              label,
              style: VfTextStyles.kpiLabel,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
        ),
      ),
    );
  }
}

/// La grille de KPI : **toujours** deux colonnes, gouttière 10 (§2.7).
class VfKpiGrid extends StatelessWidget {
  const VfKpiGrid({required this.children, super.key});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      crossAxisCount: 2,
      crossAxisSpacing: VfSpacing.x10,
      mainAxisSpacing: VfSpacing.x10,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      childAspectRatio: 2.2,
      children: children,
    );
  }
}

/// `.progress` — hauteur 8 (6 en variante fine), piste `--line`, rayon plein.
class VfProgressBar extends StatelessWidget {
  const VfProgressBar({
    required this.value,
    this.thin = false,
    this.color = VfColors.primary,
    this.semanticsLabel,
    super.key,
  });

  /// Entre 0 et 1. **Vient du serveur** : « ne jamais recalculer ce que le
  /// serveur calcule — avancement, verrou de leçon, score, solde, verdict »
  /// (§12 du contrat).
  final double value;

  final bool thin;
  final Color color;
  final String? semanticsLabel;

  @override
  Widget build(BuildContext context) {
    final double height = thin ? 6 : 8;
    return Semantics(
      label: semanticsLabel,
      value: '${(value * 100).round()} %',
      child: ClipRRect(
        borderRadius: VfRadii.pillAll,
        child: TweenAnimationBuilder<double>(
          tween: Tween<double>(begin: 0, end: value.clamp(0, 1)),
          duration: VfMotion.reducedMotion(context)
              ? Duration.zero
              : VfMotion.progress,
          curve: VfMotion.curve,
          builder: (BuildContext context, double animated, Widget? child) =>
              SizedBox(
                height: height,
                child: LinearProgressIndicator(
                  value: animated,
                  minHeight: height,
                  backgroundColor: VfColors.line,
                  valueColor: AlwaysStoppedAnimation<Color>(color),
                ),
              ),
        ),
      ),
    );
  }
}

/// `.avatar` — trois tailles : 34, 42, 72.
enum VfAvatarSize {
  petit(34),
  moyen(42),
  grand(72);

  const VfAvatarSize(this.diameter);

  final double diameter;
}

/// `.avatar` — initiales, variante `prim` sur fond vert clair.
class VfAvatar extends StatelessWidget {
  const VfAvatar({
    required this.initials,
    this.size = VfAvatarSize.moyen,
    this.prim = false,
    super.key,
  });

  /// **Les initiales viennent du serveur** pour un auteur du forum
  /// (`AuteurResource.initiales`) : les recalculer donnerait deux résultats
  /// différents sur un nom composé.
  final String initials;

  final VfAvatarSize size;
  final bool prim;

  @override
  Widget build(BuildContext context) {
    return ExcludeSemantics(
      child: Container(
        width: size.diameter,
        height: size.diameter,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: prim ? VfColors.primarySoft : VfColors.sidebarBg,
          shape: BoxShape.circle,
        ),
        child: Text(
          initials,
          style: VfTextStyles.hSm.copyWith(
            color: prim ? VfColors.primaryDark : VfColors.surface,
            fontSize: size.diameter * 0.36,
          ),
        ),
      ),
    );
  }
}

/// `.mrow` — une ligne de menu.
class VfNavTile extends StatelessWidget {
  const VfNavTile({
    required this.label,
    required this.onTap,
    this.icon,
    this.trailing,
    this.description,
    this.danger = false,
    super.key,
  });

  final String label;
  final String? description;
  final IconData? icon;
  final Widget? trailing;
  final VoidCallback? onTap;

  /// Variante `danger` en rouge.
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final Color color = danger ? VfColors.brandRed : VfColors.ink;
    return InkWell(
      onTap: onTap,
      child: Container(
        constraints: const BoxConstraints(minHeight: VfTouch.menuRowMinHeight),
        padding: const EdgeInsets.symmetric(
          horizontal: VfTouch.menuRowInlinePadding,
          vertical: VfTouch.menuRowVerticalPadding,
        ),
        child: Row(
          children: <Widget>[
            if (icon != null) ...<Widget>[
              Icon(icon, size: 20, color: danger ? color : VfColors.slate2),
              const SizedBox(width: VfSpacing.x12),
            ],
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(label, style: VfTextStyles.body.copyWith(color: color)),
                  if (description != null)
                    Text(description!, style: VfTextStyles.tSm),
                ],
              ),
            ),
            ?trailing,
          ],
        ),
      ),
    );
  }
}

/// `.mlist` — un filet **entre** les lignes, aucun après la dernière.
class VfMenuList extends StatelessWidget {
  const VfMenuList({required this.children, super.key});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: VfColors.surface,
        borderRadius: VfRadii.lgAll,
        border: Border.all(color: VfColors.line),
      ),
      child: ClipRRect(
        borderRadius: VfRadii.lgAll,
        child: Column(
          children: <Widget>[
            for (int i = 0; i < children.length; i++) ...<Widget>[
              children[i],
              // Aucun filet apres la derniere ligne : sinon il double la bordure
              // de la carte et se voit.
              if (i < children.length - 1) const Divider(height: 1),
            ],
          ],
        ),
      ),
    );
  }
}

/// Les deux états vides, et pourquoi ils sont **deux** (§2.7).
///
/// « Aucun résultat » et « rien encore fait » n'appellent pas la même action :
/// le premier invite à élargir la recherche, le second à commencer. Les
/// confondre, c'est proposer « effacer les filtres » à quelqu'un qui n'a jamais
/// rien fait (règle du chantier §7, arbitrage n° 31).
enum VfEmptyKind {
  /// Une recherche ou un filtre n'a rien donné.
  aucunResultat,

  /// La personne n'a encore rien fait.
  rienEncore,
}

/// `.empty` — icône 44 couleur `--line`, titre, phrase.
class VfEmptyState extends StatelessWidget {
  const VfEmptyState({
    required this.kind,
    required this.title,
    required this.message,
    this.icon,
    this.action,
    super.key,
  });

  final VfEmptyKind kind;
  final String title;
  final String message;
  final IconData? icon;

  /// L'action de sortie. Elle diffère selon [kind] — c'est tout l'intérêt de
  /// distinguer les deux.
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(VfSpacing.x26),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: <Widget>[
          Icon(
            icon ??
                (kind == VfEmptyKind.aucunResultat
                    ? Icons.search_off_rounded
                    : Icons.inbox_outlined),
            size: 44,
            color: VfColors.line,
          ),
          const SizedBox(height: VfSpacing.x14),
          Text(title, style: VfTextStyles.hSm, textAlign: TextAlign.center),
          const SizedBox(height: VfSpacing.x6),
          Text(message, style: VfTextStyles.tSm, textAlign: TextAlign.center),
          if (action != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x16),
            action!,
          ],
        ],
      ),
    );
  }
}

/// `.skeleton` — dégradé 200 % qui balaie en 1,4 s.
///
/// C'est **le placeholder par défaut de tout chargement**, y compris des
/// couvertures distantes.
///
/// POURQUOI un squelette plutôt qu'un indicateur circulaire : il annonce la
/// forme de ce qui arrive. Le circulaire reste légitime aux trois endroits où le
/// cache est interdit — solde, analyse en cours, résultat de quiz — parce qu'il
/// n'y a alors rien à esquisser (05-HORS-LIGNE §5.3).
class VfSkeleton extends StatefulWidget {
  const VfSkeleton({
    this.width,
    this.height = 16,
    this.borderRadius = VfRadii.smAll,
    super.key,
  });

  final double? width;
  final double height;
  final BorderRadius borderRadius;

  @override
  State<VfSkeleton> createState() => _VfSkeletonState();
}

class _VfSkeletonState extends State<VfSkeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: VfMotion.skeleton,
  );

  @override
  void initState() {
    super.initState();
    _controller.repeat();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final Widget base = Container(
      width: widget.width,
      height: widget.height,
      decoration: BoxDecoration(
        color: VfColors.line,
        borderRadius: widget.borderRadius,
      ),
    );

    if (VfMotion.reducedMotion(context)) {
      return ExcludeSemantics(child: base);
    }

    return ExcludeSemantics(
      child: AnimatedBuilder(
        animation: _controller,
        builder: (BuildContext context, Widget? child) => ShaderMask(
          blendMode: BlendMode.srcATop,
          shaderCallback: (Rect bounds) => LinearGradient(
            begin: Alignment(-1 + _controller.value * 2, 0),
            end: Alignment(1 + _controller.value * 2, 0),
            colors: <Color>[VfColors.line, VfColors.canvas, VfColors.line],
          ).createShader(bounds),
          child: child,
        ),
        child: base,
      ),
    );
  }
}

/// `.chips` / `.chip` — défilement horizontal bord à bord, sans barre.
///
/// POURQUOI le débordement de 16 est reproduit : « c'est un détail visuel, mais
/// le reproduire évite la sensation de liste tronquée » (§2.2).
class VfChips extends StatelessWidget {
  const VfChips({
    required this.labels,
    required this.selectedIndex,
    required this.onSelected,
    super.key,
  });

  final List<String> labels;
  final int selectedIndex;
  final ValueChanged<int> onSelected;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: VfTouch.chipHeight,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        // Le debordement volontaire des marges de 16 : la liste va bord a bord.
        padding: const EdgeInsets.symmetric(horizontal: VfSpacing.x16),
        itemCount: labels.length,
        separatorBuilder: (BuildContext context, int index) =>
            const SizedBox(width: VfTouch.chipGutter),
        itemBuilder: (BuildContext context, int index) {
          final bool selected = index == selectedIndex;
          return Semantics(
            button: true,
            selected: selected,
            child: InkWell(
              borderRadius: VfRadii.pillAll,
              onTap: () => onSelected(index),
              child: Container(
                alignment: Alignment.center,
                padding: const EdgeInsets.symmetric(horizontal: VfSpacing.x14),
                decoration: BoxDecoration(
                  color: selected ? VfColors.primary : VfColors.surface,
                  borderRadius: VfRadii.pillAll,
                  border: Border.all(
                    color: selected ? VfColors.primary : VfColors.line,
                  ),
                ),
                child: Text(
                  labels[index],
                  style: VfTextStyles.tSm.copyWith(
                    color: selected ? VfColors.surface : VfColors.ink,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

/// `.votebar` — trois segments vrai / doute / faux, hauteur 8 (10 sur la fiche
/// d'un sujet).
///
/// # Ce que cette barre ne dit PAS
///
/// « **`data.verdict_officiel` n'apparaît que si le serveur a lié un verdict.**
/// Le bandeau officiel ne se déduit **jamais** des votes. Une communauté
/// convaincue à 90 % qu'une rumeur est vraie n'en fait pas une vérification — et
/// laisser l'interface le suggérer serait l'exact inverse de ce que ce produit
/// existe pour faire. » (§10.5 du contrat)
///
/// Les pourcentages sont **recalculés par le serveur** (`data.votes`) : ce
/// composant les affiche, il ne les calcule pas.
class VfVoteBar extends StatelessWidget {
  const VfVoteBar({
    required this.vraiPourcent,
    required this.doutePourcent,
    required this.fauxPourcent,
    this.tall = false,
    super.key,
  });

  final int vraiPourcent;
  final int doutePourcent;
  final int fauxPourcent;
  final bool tall;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label:
          'Avis de la communauté : $vraiPourcent pour cent vrai, '
          '$doutePourcent pour cent en doute, $fauxPourcent pour cent faux. '
          'Ce n est pas un verdict.',
      excludeSemantics: true,
      child: ClipRRect(
        borderRadius: VfRadii.pillAll,
        child: SizedBox(
          height: tall ? 10 : 8,
          child: Row(
            children: <Widget>[
              Expanded(
                flex: vraiPourcent.clamp(0, 100),
                child: const ColoredBox(color: VfColors.verdictTrue),
              ),
              Expanded(
                flex: doutePourcent.clamp(0, 100),
                child: const ColoredBox(color: VfColors.verdictInvestigate),
              ),
              Expanded(
                flex: fauxPourcent.clamp(0, 100),
                child: const ColoredBox(color: VfColors.verdictFalse),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
