import 'dart:ui' show Color;

import '../../../../domain/entities/verification_libelle.dart';
import '../../../../domain/entities/verification_statut.dart';
import '../../../shared/vf_verdict_badge.dart';

/// **La correspondance `valeur technique -> ton d'affichage`, établie une fois.**
///
/// # La correspondance elle-même a rejoint le socle
///
/// M4 en avait écrit une seconde, et les deux divergeaient sur `unverifiable`
/// (arbitrage n° 131). `VfVerdictTones.pour` est désormais la seule ; ce fichier
/// garde ce qui est **propre à M2** et que le socle n'a pas à connaître : le
/// couple `ValeurLibelle`, le ton d'un **statut** d'analyse, et la couleur de la
/// barre de fiabilité.
///
/// # Contre quoi elle a été établie
///
/// Contre l'implémentation servie, `App\Enums\VerdictFactCheck` : quatre valeurs,
/// `true` · `false` · `misleading` · `unverifiable`, et **pas une de plus**. Les
/// couleurs ne sont pas choisies ici non plus : elles sont reprises de
/// `VerdictFactCheck::couleurBarre()`, qui les tient du design system —
/// `#16A34A`, `#DC2626`, `#D97706`, `#2563EB`, soit exactement
/// `VfColors.verdictTrue`, `verdictFalse`, `verdictInvestigate` et `info`.
///
/// # Le cas qui mérite une phrase : `unverifiable`
///
/// Le serveur lui donne le bleu `--vinfo`, la même teinte que « EN COURS ». Ce
/// n'est pas une approximation : « non vérifiable » n'est **pas** un échec,
/// c'est un résultat honnête quand les preuves manquent, et le bleu dit
/// « information » là où le vert dirait « tout va bien » et le rouge « intox ».
/// `VfVerdictTone.enCours` porte cette teinte ; on l'emploie donc pour les deux,
/// et le **libellé** — qui vient du serveur — fait la différence à l'écran.
///
/// # Et la règle de couleur qui a déjà coûté une correction
///
/// **La barre de fiabilité prend la couleur du verdict** (arbitrage n° 32) :
/// « une barre verte sous un non vérifiable dit *tout va bien* là où il n'y a
/// aucune assurance ; sous un faux, elle félicite le lecteur d'avoir trouvé une
/// intox. » [couleurDeLaBarre] est la seule source de cette couleur, et un test
/// la verrouille.
abstract final class TonDuVerdict {
  /// Les quatre valeurs servies. Exposées pour le test paramétré qui vérifie
  /// que chacune a bien sa couleur et son icône (06-TESTS §6.3).
  static const List<String> valeursConnues = VfVerdictTones.valeursServies;

  /// Le ton d'un verdict.
  ///
  /// Une valeur inattendue rend [VfVerdictTone.inconnu] : le libellé du serveur
  /// s'affiche quand même, dans un style neutre. C'est l'équivalent mobile du
  /// `match` sans branche par défaut (arbitrage n° 38) — côté web, ajouter un
  /// cas faisait planter une page entière, à l'exécution seulement.
  static VfVerdictTone pour(ValeurLibelle? verdict) =>
      VfVerdictTones.pour(verdict?.valeur);

  /// Le ton d'une **analyse sans verdict** — c'est son statut qui parle.
  ///
  /// « Les analyses en cours apparaissent avec leur **état** à la place du
  /// verdict : un tiret laisserait croire à un échec » (arbitrage n° 31).
  static VfVerdictTone pourLeStatut(StatutVerification statut) =>
      switch (statut) {
        StatutVerification.enFile ||
        StatutVerification.enCours => VfVerdictTone.enCours,
        // Une reprise programmee et un arbitrage humain sont deux ATTENTES, pas
        // deux echecs : l'orange d'investigation, comme cote web
        // (StatutVerification::badge()).
        StatutVerification.repriseProgrammee ||
        StatutVerification.arbitrageHumain => VfVerdictTone.trompeur,
        StatutVerification.echouee => VfVerdictTone.faux,
        StatutVerification.terminee => VfVerdictTone.vrai,
        StatutVerification.inconnu => VfVerdictTone.inconnu,
      };

  /// **La couleur de la barre de fiabilité.** Verrouillée par un test.
  static Color couleurDeLaBarre(ValeurLibelle? verdict) => pour(verdict).color;
}
