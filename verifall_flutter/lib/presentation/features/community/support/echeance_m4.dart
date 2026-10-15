import 'package:flutter/widgets.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../domain/entities/opportunity_entity.dart';
import '../../../../core/format/vf_formats.dart';

/// Le libellé d'échéance d'une opportunité : le texte, la couleur, et ce que le
/// lecteur d'écran entend.
///
/// # Ce que cette classe fait, et ce qu'elle ne fait surtout pas
///
/// Elle **met en forme** `data.expiree` et `data.jours_restants`. Elle ne les
/// calcule pas, ne les corrige pas, et ne les déduit d'aucune date.
///
/// > « `data.expiree` et `data.jours_restants` viennent du serveur. L'archivage
/// > est une commande planifiée ; **le client ne l'applique jamais lui-même** —
/// > une horloge de téléphone mal réglée ferait disparaître une offre encore
/// > ouverte, ou pire, présenterait comme candidatable une offre fermée. »
/// > (§10.5 du contrat)
///
/// Le simulateur, lui, compare `Date.now()` à un nombre de jours : il tourne
/// sans serveur. C'est la première ligne de la réserve E de la section 9 — « ce
/// que le simulateur calcule et qui appartient au serveur ».
///
/// # Les cinq formules, et d'où elles viennent
///
/// Elles sont le tableau de l'écran 22, mot pour mot. L'accentuation est retirée
/// pour rester alignée sur les chaînes d'interface du socle (voir
/// `docs/ARBITRAGES.md`).
@immutable
class LibelleEcheance {
  const LibelleEcheance({
    required this.texte,
    required this.couleur,
    required this.pourLecteurDEcran,
  });

  /// Ce qui s'affiche à côté de l'icône `event`.
  final String texte;

  /// La couleur, **jamais seule porteuse de sens** : le texte dit déjà tout.
  /// « Les états ne reposent jamais sur la seule couleur » (§2.9).
  final Color couleur;

  /// « L'échéance est annoncée en toutes lettres, la couleur seule ne suffit
  /// pas » (03-ECRANS-05 §3.22, accessibilité).
  final String pourLecteurDEcran;

  /// Construit le libellé d'une offre. `null` quand le serveur n'a donné ni
  /// date ni compte à rebours : **ne rien afficher vaut mieux qu'inventer une
  /// échéance**.
  static LibelleEcheance? pour(Opportunite offre) {
    final String? date = offre.dateLimite == null
        ? null
        : VfFormats.dateCourte(offre.dateLimite!.toLocal());
    final int? jours = offre.joursRestants;

    return switch (offre.etatEcheance) {
      EtatEcheance.depassee => LibelleEcheance(
        texte: <String>[
          date == null ? 'Clôturée' : 'Clôturée le $date',
          // « archivage dans n j » n'apparait que si le serveur donne un compte
          // a rebours positif. Un nombre negatif — qui signifierait autre chose
          // — n'est pas retourne contre lui-meme : on se tait.
          if (jours != null && jours >= 0) 'archivage dans $jours j',
        ].join(' · '),
        couleur: VfColors.brandRed,
        pourLecteurDEcran: jours != null && jours >= 0
            ? 'Offre clôturée, archivage dans $jours jours'
            : 'Offre clôturée',
      ),
      EtatEcheance.aujourdHui => const LibelleEcheance(
        texte: 'Dernier jour',
        couleur: VfColors.brandRed,
        pourLecteurDEcran: 'Dernier jour pour candidater',
      ),
      EtatEcheance.demain => const LibelleEcheance(
        texte: 'Clôture demain',
        couleur: VfColors.brandRed,
        pourLecteurDEcran: 'Clôture demain',
      ),
      EtatEcheance.proche => LibelleEcheance(
        texte: _clotureLe(date, jours),
        couleur: VfColors.verdictInvestigate,
        pourLecteurDEcran: 'Clôture dans $jours jours',
      ),
      EtatEcheance.lointaine => LibelleEcheance(
        texte: _clotureLe(date, jours),
        couleur: VfColors.slate2,
        pourLecteurDEcran: 'Clôture dans $jours jours',
      ),
      EtatEcheance.inconnue =>
        date == null
            ? null
            : LibelleEcheance(
                texte: 'Clôture le $date',
                couleur: VfColors.slate2,
                pourLecteurDEcran: 'Clôture le $date',
              ),
    };
  }

  static String _clotureLe(String? date, int? jours) {
    return <String>[
      if (date != null) 'Clôture le $date' else 'Clôture prochainement',
      if (jours != null) 'J-$jours',
    ].join(' · ');
  }
}
