import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/core/theme/vf_colors.dart';
import 'package:verifall_app/data/models/community/opportunite_model.dart';
import 'package:verifall_app/domain/entities/opportunity_entity.dart';
import 'package:verifall_app/domain/usecases/community/ouvrir_une_candidature.dart';
import 'package:verifall_app/presentation/features/community/support/echeance_m4.dart';
import 'package:verifall_app/presentation/features/community/widgets/hub_widgets.dart';

/// **L'archivage à J+7 est affiché, jamais calculé.**
///
/// > « `data.expiree` et `data.jours_restants` viennent du serveur. L'archivage
/// > est une commande planifiée ; le client ne l'applique **jamais** lui-même —
/// > une horloge de téléphone mal réglée ferait disparaître une offre encore
/// > ouverte, ou pire, présenterait comme candidatable une offre fermée. »
/// > (§10.5 du contrat)
void main() {
  Opportunite offre({
    bool expiree = false,
    int? joursRestants,
    DateTime? dateLimite,
    bool verifiee = true,
    String? lien = 'insae.bj/recrutements',
    String type = 'cdd',
  }) {
    return OpportuniteModel.fromJson(<String, Object?>{
      'slug': 'chargee-de-communication',
      'titre': 'Chargee de communication digitale',
      'expiree': expiree,
      'verifiee': verifiee,
      'jours_restants': joursRestants,
      'date_limite': dateLimite?.toIso8601String(),
      'type': type,
      'type_libelle': 'CDD',
      'scope': 'benin',
      'lien_candidature': lien,
    });
  }

  group('L état d échéance vient du serveur', () {
    test('une horloge locale ne peut pas fermer une offre ouverte', () {
      // La date limite est DEPASSEE selon l'horloge du telephone, mais le
      // serveur dit que l'offre est ouverte. C'est le serveur qui gagne.
      final Opportunite o = offre(
        dateLimite: DateTime(2020),
        joursRestants: 12,
      );
      expect(o.expiree, isFalse);
      expect(o.etatEcheance, EtatEcheance.lointaine);
      expect(o.candidatureOuverte, isTrue);
    });

    test('une horloge locale ne peut pas rouvrir une offre fermee', () {
      final Opportunite o = offre(
        expiree: true,
        dateLimite: DateTime(2999),
        joursRestants: 3,
      );
      expect(o.etatEcheance, EtatEcheance.depassee);
      expect(
        o.candidatureOuverte,
        isFalse,
        reason:
            'Presenter comme candidatable une offre fermee est le cas que le '
            'contrat nomme explicitement.',
      );
    });

    test('les cinq etats suivent jours_restants', () {
      expect(offre(joursRestants: 19).etatEcheance, EtatEcheance.lointaine);
      expect(offre(joursRestants: 8).etatEcheance, EtatEcheance.lointaine);
      expect(offre(joursRestants: 7).etatEcheance, EtatEcheance.proche);
      expect(offre(joursRestants: 2).etatEcheance, EtatEcheance.proche);
      expect(offre(joursRestants: 1).etatEcheance, EtatEcheance.demain);
      expect(offre(joursRestants: 0).etatEcheance, EtatEcheance.aujourdHui);
      expect(offre(expiree: true).etatEcheance, EtatEcheance.depassee);
    });

    test('sans compte a rebours servi, l ecran n en invente pas', () {
      final Opportunite o = offre(dateLimite: DateTime(2026, 9, 12));
      expect(o.etatEcheance, EtatEcheance.inconnue);
      final LibelleEcheance? libelle = LibelleEcheance.pour(o);
      expect(libelle!.texte, 'Clôture le 12/09/2026');
      expect(
        libelle.texte.contains('J-'),
        isFalse,
        reason: 'Inventer un decompte serait pire que ne rien dire.',
      );
    });

    test('sans date ni compte a rebours, aucun libelle du tout', () {
      expect(LibelleEcheance.pour(offre()), isNull);
    });
  });

  group('Les libelles et couleurs du tableau de l ecran 22', () {
    test('plus de 7 jours : ardoise', () {
      final LibelleEcheance l = LibelleEcheance.pour(
        offre(joursRestants: 19, dateLimite: DateTime(2026, 9, 12)),
      )!;
      expect(l.texte, 'Clôture le 12/09/2026 · J-19');
      expect(l.couleur, VfColors.slate2);
    });

    test('2 a 7 jours : orange', () {
      final LibelleEcheance l = LibelleEcheance.pour(
        offre(joursRestants: 4, dateLimite: DateTime(2026, 8, 29)),
      )!;
      expect(l.couleur, VfColors.verdictInvestigate);
      expect(l.pourLecteurDEcran, 'Clôture dans 4 jours');
    });

    test('demain et dernier jour : rouge', () {
      expect(
        LibelleEcheance.pour(offre(joursRestants: 1))!.texte,
        'Clôture demain',
      );
      expect(
        LibelleEcheance.pour(offre(joursRestants: 0))!.texte,
        'Dernier jour',
      );
      expect(
        LibelleEcheance.pour(offre(joursRestants: 0))!.couleur,
        VfColors.brandRed,
      );
    });

    test('depassee : la periode de grace est affichee, pas calculee', () {
      final LibelleEcheance l = LibelleEcheance.pour(
        offre(
          expiree: true,
          joursRestants: 4,
          dateLimite: DateTime(2026, 8, 21),
        ),
      )!;
      expect(l.texte, 'Clôturée le 21/08/2026 · archivage dans 4 j');
    });

    test('depassee sans compte a rebours : la date seule', () {
      final LibelleEcheance l = LibelleEcheance.pour(
        offre(expiree: true, dateLimite: DateTime(2026, 8, 21)),
      )!;
      expect(l.texte, 'Clôturée le 21/08/2026');
    });
  });

  group('La taxonomie et ses familles visuelles', () {
    test('les quatre familles suivent le simulateur', () {
      expect(TypeOpportunite.cdi.famille, FamilleOpportunite.contrat);
      expect(TypeOpportunite.alternance.famille, FamilleOpportunite.contrat);
      expect(TypeOpportunite.bourse.famille, FamilleOpportunite.etude);
      expect(TypeOpportunite.financement.famille, FamilleOpportunite.etude);
      expect(TypeOpportunite.conference.famille, FamilleOpportunite.evenement);
      expect(TypeOpportunite.volontariat.famille, FamilleOpportunite.autre);
      // Les deux valeurs que le contrat ajoute au simulateur prennent le style
      // de base, faute de classe visuelle dans la maquette.
      expect(TypeOpportunite.appel.famille, FamilleOpportunite.autre);
      expect(TypeOpportunite.autre.famille, FamilleOpportunite.autre);
    });

    test('un type inconnu ne fait pas tomber la lecture', () {
      final Opportunite o = offre(type: 'un-type-de-demain');
      expect(o.type, isNull);
      expect(o.typeLibelle, 'CDD');
    });

    testWidgets('la pastille affiche le libelle du serveur, jamais la valeur', (
      WidgetTester tester,
    ) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: Scaffold(
            body: VfTypeChip(
              libelle: 'CDD',
              famille: FamilleOpportunite.contrat,
            ),
          ),
        ),
      );
      expect(find.text('CDD'), findsOneWidget);
    });
  });

  group('La candidature', () {
    test('elle est refusee sur une offre clôturée', () async {
      final _OuvreurEspion espion = _OuvreurEspion();
      final bool ouvert = await OuvrirUneCandidature(espion)(
        offre(expiree: true),
      );

      expect(ouvert, isFalse);
      expect(
        espion.ouvertures,
        isEmpty,
        reason: 'Une offre clôturée n a aucun chemin vers la candidature.',
      );
    });

    test('elle est refusee quand aucun lien n est servi', () async {
      final _OuvreurEspion espion = _OuvreurEspion();
      expect(await OuvrirUneCandidature(espion)(offre(lien: null)), isFalse);
      expect(espion.ouvertures, isEmpty);
    });

    test('un lien sans schema est complete en https, jamais en http', () async {
      final _OuvreurEspion espion = _OuvreurEspion();
      await OuvrirUneCandidature(espion)(offre());
      expect(espion.ouvertures.single.scheme, 'https');
      expect(espion.ouvertures.single.host, 'insae.bj');
    });

    test('un schema exotique est refuse', () {
      expect(OuvrirUneCandidature.normaliser('javascript:alert(1)'), isNull);
      expect(OuvrirUneCandidature.normaliser('file:///etc/passwd'), isNull);
    });

    test('le bouton annonce le DOMAINE, pas l URL entiere', () {
      expect(
        OuvrirUneCandidature.domaine('insae.bj/recrutements'),
        'insae.bj',
        reason:
            'Une URL entiere, tronquee par le bouton, cacherait justement la '
            'partie qu il faut reconnaitre.',
      );
      expect(OuvrirUneCandidature.domaine(null), isNull);
    });
  });

  group('La pastille « verifie »', () {
    test('elle ne se deduit d aucun autre champ', () {
      // Une offre parfaitement remplie, ouverte, avec un lien : elle n est pas
      // « verifiee » pour autant.
      final Opportunite o = offre(verifiee: false, joursRestants: 12);
      expect(o.verifiee, isFalse);
      expect(o.candidatureOuverte, isTrue);
    });
  });
}

class _OuvreurEspion implements OuvreurDeLienExterne {
  final List<Uri> ouvertures = <Uri>[];

  @override
  Future<bool> ouvrirEnOngletSysteme(Uri url) async {
    ouvertures.add(url);
    return true;
  }
}
