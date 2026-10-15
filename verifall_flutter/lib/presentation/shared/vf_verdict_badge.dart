import 'package:flutter/material.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_typography.dart';

/// Le **style** d'un verdict, pas sa valeur technique.
///
/// # Pourquoi ce n'est pas l'énumération de l'API
///
/// Le contrat pose une règle qui change la façon d'écrire ce composant :
///
/// > « Valeur technique et libellé français voyagent ensemble
/// > (`"verdict": "false"`, `"verdict_libelle": "Faux"`). **Le client affiche le
/// > libellé et branche sur la valeur ; il ne fabrique jamais le libellé
/// > lui-même.** » (§8)
///
/// Le contrat ne publie pas la liste complète des valeurs techniques de
/// `verdict` ; en figer une ici reviendrait à deviner une forme de réponse. Ce
/// composant reçoit donc **le libellé du serveur** et un ton d'affichage, et le
/// parcours M2 établit la correspondance `valeur -> ton` en un seul endroit,
/// contre le serveur réel.
///
/// # Pourquoi il existe un ton [inconnu]
///
/// C'est l'équivalent mobile du piège du `match` sans branche par défaut
/// (arbitrage n° 38) : côté web, ajouter un cas faisait planter une page
/// entière, à l'exécution seulement. Ici, une valeur inattendue affiche le
/// libellé reçu avec un style neutre — l'écran tient, et un test paramétré
/// vérifie que chaque valeur connue a bien sa couleur et son icône (06-TESTS
/// §6.3).
enum VfVerdictTone {
  /// `--vtrue`.
  vrai(VfColors.verdictTrue, Icons.verified_rounded),

  /// `--vfalse`.
  faux(VfColors.verdictFalse, Icons.gpp_bad_rounded),

  /// `--vinvest`.
  trompeur(VfColors.verdictInvestigate, Icons.warning_rounded),

  /// `--vinfo`.
  enCours(VfColors.info, Icons.pending_rounded),

  /// Une valeur que cette version du client ne connaît pas encore.
  inconnu(VfColors.slate2, Icons.help_outline_rounded);

  const VfVerdictTone(this.color, this.icon);

  final Color color;
  final IconData icon;
}

/// `.vbadge-*` — fond à 12 % de la couleur, texte à 100 %, icône dédiée.
///
/// POURQUOI l'icône **et** le mot : « les états ne reposent jamais sur la seule
/// couleur » (§2.9). Sur une plateforme de fact-checking, confondre VRAI et FAUX
/// parce qu'on distingue mal le vert du rouge n'est pas un défaut d'affichage.
class VfVerdictBadge extends StatelessWidget {
  const VfVerdictBadge({
    required this.libelle,
    required this.tone,
    this.compact = false,
    super.key,
  });

  /// **Le libellé rendu par le serveur** (`verdict_libelle`), affiché tel quel.
  final String libelle;

  final VfVerdictTone tone;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Verdict : $libelle',
      excludeSemantics: true,
      child: Container(
        padding: EdgeInsets.symmetric(
          horizontal: compact ? VfSpacing.x8 : VfSpacing.x10,
          vertical: compact ? VfSpacing.x4 : VfSpacing.x6,
        ),
        decoration: BoxDecoration(
          color: tone.color.withValues(alpha: 0.12),
          borderRadius: VfRadii.pillAll,
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Icon(tone.icon, size: compact ? 14 : 16, color: tone.color),
            const SizedBox(width: VfSpacing.x6),
            Text(
              libelle.toUpperCase(),
              style: (compact ? VfTextStyles.tXs : VfTextStyles.tSm).copyWith(
                color: tone.color,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.4,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// **La correspondance `verdict` -> ton d'affichage, établie une seule fois.**
///
/// # Pourquoi elle a rejoint le socle
///
/// Le socle la confiait au parcours M2 « en un seul endroit ». Deux parcours
/// l'ont écrite : M2 contre le serveur (`TonDuVerdict`, arbitrage n° 118) et M4
/// contre le contrat (`VerdictM4`, arbitrage n° 92) — et **elles ne rendaient pas
/// la même couleur pour `unverifiable`**. Un même verdict prenait donc deux
/// teintes selon l'écran ; c'est le cas d'école de la règle 3, et c'est visible
/// par l'utilisateur. Arbitrage n° 131.
///
/// # Contre quoi elle est établie
///
/// Contre l'énumération servie, `App\Enums\VerdictFactCheck` : quatre valeurs,
/// `true` · `false` · `misleading` · `unverifiable`, et pas une de plus. Les
/// couleurs viennent de `VerdictFactCheck::couleurBarre()` — `#16A34A`,
/// `#DC2626`, `#D97706`, `#2563EB` — soit exactement `VfColors.verdictTrue`,
/// `verdictFalse`, `verdictInvestigate` et `info`.
///
/// # Le cas qui a fait diverger les deux parcours : `unverifiable`
///
/// M4 lui donnait le gris neutre, en faisant valoir qu'« invérifiable est un
/// verdict rendu, pas une analyse en attente » — l'argument est juste, et
/// l'écran ne doit pas laisser croire qu'un résultat va encore arriver.
///
/// C'est pourtant le **bleu** qui est retenu, parce que le serveur le publie :
/// `couleurBarre()` rend `#2563EB` et `badge()` rend `vbadge-progress`. Ce n'est
/// pas une approximation de sa part — « non vérifiable n'est pas un échec, c'est
/// un résultat honnête quand les preuves manquent », et le bleu dit
/// « information » là où le vert dirait « tout va bien » et le rouge « intox ».
///
/// Ce qui sépare les deux cas à l'écran, c'est le **libellé** — « Non vérifiable »
/// contre « En cours » —, et il vient du serveur (`verdict_libelle`) : le client
/// ne le fabrique pas. La crainte de M4 est donc levée par le contrat lui-même.
abstract final class VfVerdictTones {
  /// Les quatre valeurs servies. Exposées pour le test paramétré qui vérifie que
  /// chacune a bien sa couleur et son icône (06-TESTS §6.3).
  static const List<String> valeursServies = <String>[
    'true',
    'false',
    'misleading',
    'unverifiable',
  ];

  /// Le ton d'une valeur technique de verdict.
  ///
  /// Une valeur inattendue rend [VfVerdictTone.inconnu] : le libellé du serveur
  /// s'affiche quand même, dans un style neutre. C'est l'équivalent mobile du
  /// `match` sans branche par défaut (arbitrage n° 38) — côté web, ajouter un
  /// cas faisait planter une page entière, à l'exécution seulement.
  static VfVerdictTone pour(String? valeur) => switch (valeur) {
    'true' => VfVerdictTone.vrai,
    'false' => VfVerdictTone.faux,
    'misleading' => VfVerdictTone.trompeur,
    'unverifiable' => VfVerdictTone.enCours,
    _ => VfVerdictTone.inconnu,
  };
}
