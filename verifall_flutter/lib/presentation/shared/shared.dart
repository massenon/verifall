/// Les composants partagés du design system.
///
/// **Aucun parcours ne les possède** (§8.1, règle 3) : « si deux parcours ont
/// besoin du même widget, il appartient au socle. Un composant dupliqué dans
/// deux dossiers diverge en une semaine. »
///
/// Un composant qui manque **ne se crée pas dans un parcours** : c'est une
/// demande adressée au responsable du socle.
library;

export 'vf_app_bar.dart';
export 'vf_app_drawer.dart';
export 'vf_bottom_nav.dart';
export 'vf_bottom_sheet.dart';
export 'vf_buttons.dart';
export 'vf_card.dart';
export 'vf_confetti.dart';
export 'vf_cover.dart';
export 'vf_display.dart';
export 'vf_drawer.dart';
export 'vf_inputs.dart';
export 'vf_points_pill.dart';
export 'vf_prose_body.dart';
export 'vf_shell_scaffold.dart';
export 'vf_stale_banner.dart';
export 'vf_system_pages.dart';
export 'vf_timeline.dart';
export 'vf_toast.dart';
export 'vf_verdict_badge.dart';
