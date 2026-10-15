import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/data/models/televersement.dart';
import 'package:verifall_app/domain/entities/verification_brouillon.dart';
import 'package:verifall_app/domain/entities/verification_type_contenu.dart';
import 'package:verifall_app/domain/usecases/verify/rythme_du_sondage.dart';
import 'package:verifall_app/domain/usecases/verify/valider_la_saisie.dart';

void main() {
  group('Validation AVANT l envoi', () {
    test('un texte de moins de 10 caractères est refuse localement', () {
      final ErreurDeSaisie? refus = ValiderLaSaisie.brouillon(
        const VerificationBrouillon(saisie: 'court'),
      );
      expect(refus, isNotNull);
      expect(refus!.message, contains('10'));
    });

    test('une adresse mal formee est refusee', () {
      expect(
        ValiderLaSaisie.brouillon(
          const VerificationBrouillon(
            type: TypeContenu.lien,
            saisie: 'pas-une-adresse',
          ),
        ),
        isNotNull,
      );
      expect(
        ValiderLaSaisie.brouillon(
          const VerificationBrouillon(
            type: TypeContenu.lien,
            saisie: 'https://exemple.com/article-suspect',
          ),
        ),
        isNull,
      );
    });

    test('un type de fichier sans fichier est refuse, et le dit', () {
      final ErreurDeSaisie? refus = ValiderLaSaisie.brouillon(
        const VerificationBrouillon(type: TypeContenu.image),
      );
      expect(refus!.message, contains('fichier'));
    });

    test('les plafonds sont ceux du CONTRAT, et le message les cite', () {
      // 10 Mo image, 100 Mo video, 25 Mo audio : ils viennent du socle
      // (`TypeTeleversement`), qui les tient du contrat. Les redeclarer
      // ailleurs en ferait une seconde source de verite.
      expect(TypeTeleversement.image.maxBytes, equals(10 * 1024 * 1024));
      expect(TypeTeleversement.video.maxBytes, equals(100 * 1024 * 1024));
      expect(TypeTeleversement.audio.maxBytes, equals(25 * 1024 * 1024));

      final ErreurDeSaisie? refus = ValiderLaSaisie.fichierChoisi(
        TypeTeleversement.image,
        const FichierChoisi(
          chemin: '/prive/x.jpg',
          nom: 'x.jpg',
          octets: 11 * 1024 * 1024,
        ),
      );
      // « Envoyer 120 Mo pour se faire refuser apres trois minutes est le
      // pire scenario possible » : on refuse AVANT, avec le plafond en clair.
      expect(refus, isNotNull);
      expect(refus!.message, contains('10 Mo'));
    });

    test('un format refuse cite les formats attendus', () {
      final ErreurDeSaisie? refus = ValiderLaSaisie.fichierChoisi(
        TypeTeleversement.image,
        const FichierChoisi(chemin: '/prive/x.gif', nom: 'x.gif', octets: 1024),
      );
      expect(refus!.message, contains('jpg'));
    });

    test('la contestation exige 10 caractères, pas les 20 du simulateur', () {
      // « Un formulaire ne doit pas exiger plus que ce qui sera accepte » : le
      // contrat en demande 10.
      expect(ValiderLaSaisie.detailMinimum, equals(10));
      expect(ValiderLaSaisie.detailMaximum, equals(1000));
      expect(ValiderLaSaisie.contestation('court'), isNotNull);
      expect(
        ValiderLaSaisie.contestation('Ce verdict me semble faux.'),
        isNull,
      );
    });

    test('le refus de contestation compte EXACTEMENT ce qui manque', () {
      final ErreurDeSaisie? refus = ValiderLaSaisie.contestation('12345');
      expect(refus!.message, contains('5'));
    });
  });

  group('Le brouillon', () {
    test('changer de type EFFACE le fichier', () {
      // « Un fichier audio n'a rien a faire dans une analyse d'image. »
      const VerificationBrouillon avec = VerificationBrouillon(
        type: TypeContenu.image,
        contexte: 'vu sur WhatsApp',
        fichier: FichierChoisi(
          chemin: '/prive/x.jpg',
          nom: 'x.jpg',
          octets: 1024,
        ),
      );
      final VerificationBrouillon apres = avec.changerDeType(TypeContenu.audio);
      expect(apres.fichier, isNull);
      // Le contexte, lui, reste : il ne depend pas du type.
      expect(apres.contexte, equals('vu sur WhatsApp'));
    });

    test('les trois types de fichier exigent un televersement', () {
      expect(TypeContenu.image.exigeUnFichier, isTrue);
      expect(TypeContenu.video.exigeUnFichier, isTrue);
      expect(TypeContenu.audio.exigeUnFichier, isTrue);
      expect(TypeContenu.texte.exigeUnFichier, isFalse);
      expect(TypeContenu.lien.exigeUnFichier, isFalse);
    });

    test('les cinq valeurs techniques sont celles du serveur', () {
      expect(
        TypeContenu.values.map((TypeContenu t) => t.wireValue),
        equals(<String>['text', 'url', 'image', 'video', 'audio']),
      );
    });
  });

  group('Le rythme du sondage', () {
    test('trois secondes en nominal : la cadence pour laquelle le '
        'plafond de lecture a ete dimensionne', () {
      expect(RythmeDuSondage.nominal, equals(const Duration(seconds: 3)));
    });

    test('sous 10 % de quota, le client S ESPACE DE LUI-MEME', () {
      // « Un plafond sans en-tetes est un mur invisible » : on ralentit AVANT
      // de se faire econduire, plutot que d'afficher une erreur au pire moment.
      expect(
        RythmeDuSondage.prochain(quotaFaible: true),
        greaterThan(RythmeDuSondage.prochain(quotaFaible: false)),
      );
    });
  });
}
