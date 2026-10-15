import 'dart:ui' show Color;

import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/theme/vf_colors.dart';
import 'package:verifall_app/domain/entities/verification_libelle.dart';
import 'package:verifall_app/domain/entities/verification_statut.dart';
import 'package:verifall_app/presentation/features/verify/widgets/pipeline_timeline.dart';
import 'package:verifall_app/presentation/features/verify/widgets/verdict_tone.dart';
import 'package:verifall_app/presentation/shared/vf_timeline.dart';
import 'package:verifall_app/presentation/shared/vf_verdict_badge.dart';

void main() {
  group('La correspondance verdict -> ton, etablie contre le serveur réel', () {
    // Les quatre couleurs viennent de `VerdictFactCheck::couleurBarre()` :
    // #16A34A, #DC2626, #D97706, #2563EB. Ce test les VERROUILLE — c'est la
    // regle de couleur qui a deja coute une correction cote web (n. 32).
    const Map<String, ({VfVerdictTone ton, Color couleur})> attendu =
        <String, ({VfVerdictTone ton, Color couleur})>{
          'true': (ton: VfVerdictTone.vrai, couleur: VfColors.verdictTrue),
          'false': (ton: VfVerdictTone.faux, couleur: VfColors.verdictFalse),
          'misleading': (
            ton: VfVerdictTone.trompeur,
            couleur: VfColors.verdictInvestigate,
          ),
          'unverifiable': (ton: VfVerdictTone.enCours, couleur: VfColors.info),
        };

    for (final MapEntry<String, ({VfVerdictTone ton, Color couleur})> cas
        in attendu.entries) {
      test('« ${cas.key} » a sa couleur et son icone', () {
        final ValeurLibelle verdict = ValeurLibelle(
          valeur: cas.key,
          libelle: 'libelle du serveur',
        );
        expect(TonDuVerdict.pour(verdict), equals(cas.value.ton));
        // LA BARRE DE FIABILITE PREND LA COULEUR DU VERDICT : « une barre verte
        // sous un non verifiable dit tout va bien la ou il n'y a aucune
        // assurance ».
        expect(
          TonDuVerdict.couleurDeLaBarre(verdict),
          equals(cas.value.couleur),
        );
        expect(cas.value.ton.icon, isNotNull);
      });
    }

    test('les quatre valeurs du contrat sont toutes couvertes', () {
      expect(TonDuVerdict.valeursConnues, unorderedEquals(attendu.keys));
    });

    test('une valeur inattendue ne fait pas tomber l ecran', () {
      // C'est l'equivalent mobile du `match` sans branche par defaut
      // (arbitrage n. 38) : cote web, ajouter un cas faisait planter une page
      // entiere, a l'execution seulement.
      const ValeurLibelle inconnu = ValeurLibelle(
        valeur: 'un_verdict_ajoute_plus_tard',
        libelle: 'Nouveau verdict',
      );
      expect(TonDuVerdict.pour(inconnu), equals(VfVerdictTone.inconnu));
    });

    test('un verdict absent rend le ton neutre, pas un verdict par defaut', () {
      expect(TonDuVerdict.pour(null), equals(VfVerdictTone.inconnu));
    });

    test('une analyse en cours prend le ton de son STATUT', () {
      expect(
        TonDuVerdict.pourLeStatut(StatutVerification.enCours),
        equals(VfVerdictTone.enCours),
      );
      // Une reprise programmee et un arbitrage sont des ATTENTES, pas des
      // echecs : l'orange d'investigation, comme cote web.
      expect(
        TonDuVerdict.pourLeStatut(StatutVerification.repriseProgrammee),
        equals(VfVerdictTone.trompeur),
      );
      expect(
        TonDuVerdict.pourLeStatut(StatutVerification.arbitrageHumain),
        equals(VfVerdictTone.trompeur),
      );
      expect(
        TonDuVerdict.pourLeStatut(StatutVerification.echouee),
        equals(VfVerdictTone.faux),
      );
    });
  });

  group('Les statuts du serveur', () {
    test('une reprise programmee compte comme « en marche »', () {
      // « Du point de vue de la personne, sa demande est toujours en cours.
      // L'exclure du compte en cours lui ferait croire qu'elle s'est
      // volatilisee. »
      expect(StatutVerification.repriseProgrammee.estEnMarche, isTrue);
      expect(StatutVerification.enFile.estEnMarche, isTrue);
      expect(StatutVerification.terminee.estEnMarche, isFalse);
    });

    test('un statut inconnu ne casse rien', () {
      expect(
        StatutVerification.parse('un_statut_de_demain'),
        equals(StatutVerification.inconnu),
      );
    });

    test('le remboursement n est CERTAIN que sur un échec definitif', () {
      // `refunded_at` n'est pas serialise : affirmer un remboursement qu'on ne
      // peut pas lire serait un rapport creux. Seul le chemin `renoncer()`
      // rembourse sans condition.
      expect(StatutVerification.echouee.remboursementCertain, isTrue);
      expect(StatutVerification.terminee.remboursementCertain, isFalse);
      expect(
        StatutVerification.repriseProgrammee.remboursementCertain,
        isFalse,
      );
    });
  });

  group('La barre de progression NE MENT PAS', () {
    test('les etats sont une fonction PURE de l avancement serveur', () {
      // Deux appels avec le meme avancement donnent le meme resultat : il n'y a
      // aucune minuterie, aucun etat interne, rien qui puisse avancer seul.
      final List<VfTimelineStep> a = PipelineDAnalyse.etats(
        avancement: 40,
        enMarche: true,
      );
      final List<VfTimelineStep> b = PipelineDAnalyse.etats(
        avancement: 40,
        enMarche: true,
      );
      expect(
        a.map((VfTimelineStep s) => s.state),
        equals(b.map((VfTimelineStep s) => s.state)),
      );
    });

    test('a 0 %, aucune étape n est declaree franchie', () {
      final List<VfTimelineStep> etapes = PipelineDAnalyse.etats(
        avancement: 0,
        enMarche: true,
      );
      expect(
        etapes.where((VfTimelineStep s) => s.state == VfTimelineStepState.fait),
        isEmpty,
      );
      // La premiere est ACTIVE : le serveur a bien pris la demande en charge.
      expect(etapes.first.state, equals(VfTimelineStepState.actif));
    });

    test('a 100 %, toutes les étapes sont franchies', () {
      final List<VfTimelineStep> etapes = PipelineDAnalyse.etats(
        avancement: 100,
        enMarche: false,
      );
      expect(
        etapes.every((VfTimelineStep s) => s.state == VfTimelineStepState.fait),
        isTrue,
      );
    });

    test('une analyse ARRETEE n a aucune étape qui bat', () {
      // Le battement dirait qu'il se passe quelque chose. Sur une reprise
      // programmee ou un echec, il ne se passe rien.
      final List<VfTimelineStep> etapes = PipelineDAnalyse.etats(
        avancement: 33,
        enMarche: false,
      );
      expect(
        etapes.where(
          (VfTimelineStep s) => s.state == VfTimelineStepState.actif,
        ),
        isEmpty,
      );
    });

    test('la progression est monotone : elle ne recule jamais seule', () {
      int franchies(int avancement) => PipelineDAnalyse.etats(
        avancement: avancement,
        enMarche: true,
      ).where((VfTimelineStep s) => s.state == VfTimelineStepState.fait).length;

      int precedent = 0;
      for (int a = 0; a <= 100; a += 5) {
        final int courant = franchies(a);
        expect(courant, greaterThanOrEqualTo(precedent));
        precedent = courant;
      }
    });
  });
}
