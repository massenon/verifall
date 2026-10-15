import 'package:flutter_test/flutter_test.dart';
import 'package:verifall_app/domain/entities/auth_consentements.dart';
import 'package:verifall_app/domain/entities/auth_otp_purpose.dart';
import 'package:verifall_app/domain/usecases/auth/regles_de_saisie.dart';

void main() {
  group('Le mot de passe', () {
    test('annonce 12 caractères, pas 8', () {
      expect(ReglesDeSaisie.longueurMotDePasse, 12);
      expect(ReglesDeSaisie.etiquetteMotDePasse, contains('12'));
      expect(ReglesDeSaisie.etiquetteMotDePasse, isNot(contains('8 ')));
    });

    test('n’est validé QUE sur sa longueur', () {
      // Douze caracteres, aucune majuscule, aucun chiffre, aucun symbole : le
      // client accepte. La politique reelle est serveur, et une regle du client
      // qui n'est pas exactement celle du serveur refuserait des mots de passe
      // valides.
      expect(ReglesDeSaisie.motDePasseAssezLong('abcdefghijkl'), isTrue);
      expect(ReglesDeSaisie.motDePasseAssezLong('Aa1!Aa1!Aa1'), isFalse);
    });
  });

  group('L’adresse', () {
    test(
      'accepte une forme minimale et refuse une évidente faute de frappe',
      () {
        expect(ReglesDeSaisie.emailValide('rita@exemple.bj'), isTrue);
        expect(ReglesDeSaisie.emailValide('  rita@exemple.bj  '), isTrue);
        expect(ReglesDeSaisie.emailValide('rita'), isFalse);
        expect(ReglesDeSaisie.emailValide('rita@exemple'), isFalse);
      },
    );
  });

  group('Le code', () {
    test('est complet à six chiffres, et seulement à six chiffres', () {
      expect(ReglesDeSaisie.longueurCode, 6);
      expect(ReglesDeSaisie.codeComplet('481200'), isTrue);
      expect(ReglesDeSaisie.codeComplet('48120'), isFalse);
      expect(ReglesDeSaisie.codeComplet('4812a0'), isFalse);
      expect(ReglesDeSaisie.codeComplet('4812000'), isFalse);
    });
  });

  group('Les consentements', () {
    test('ne sont JAMAIS pré-cochés', () {
      const AuthConsentements neufs = AuthConsentements();
      expect(neufs.cgu, isFalse);
      expect(neufs.confidentialite, isFalse);
      expect(neufs.newsletter, isFalse);
    });

    test('les deux obligatoires, et eux seuls, débloquent l’envoi', () {
      const AuthConsentements aucun = AuthConsentements();
      expect(aucun.obligatoiresAcceptes, isFalse);
      expect(
        aucun.copyWith(cgu: true).obligatoiresAcceptes,
        isFalse,
        reason: 'un seul des deux ne suffit pas',
      );
      expect(
        aucun.copyWith(newsletter: true).obligatoiresAcceptes,
        isFalse,
        reason: 'le facultatif ne remplace pas un obligatoire',
      );
      expect(
        aucun.copyWith(cgu: true, confidentialite: true).obligatoiresAcceptes,
        isTrue,
      );
    });
  });

  group('L’usage du code à six chiffres', () {
    test('n’a que deux valeurs : `login` n’existe pas', () {
      // Reserve A.10, tranchee par l'arbitrage n° 84 : `POST /auth/login` rend
      // un jeton directement, il n'y a aucun second facteur a la connexion.
      expect(AuthOtpPurpose.values, hasLength(2));
      expect(
        AuthOtpPurpose.values.map((AuthOtpPurpose p) => p.wireValue),
        <String>['signup', 'reset'],
      );
    });

    test('retombe sur l’activation quand le paramètre est inconnu', () {
      // Le parametre vient d'une URL : une faute de frappe ne doit pas faire
      // tomber l'ecran.
      expect(AuthOtpPurpose.parse('login'), AuthOtpPurpose.signup);
      expect(AuthOtpPurpose.parse(null), AuthOtpPurpose.signup);
      expect(AuthOtpPurpose.parse('reset'), AuthOtpPurpose.reset);
    });

    test('chaque usage a sa copie, reprise du simulateur', () {
      expect(AuthOtpPurpose.signup.titre, 'Confirmez votre adresse');
      expect(AuthOtpPurpose.reset.titre, 'Vérifions que c’est bien vous');
      for (final AuthOtpPurpose purpose in AuthOtpPurpose.values) {
        expect(purpose.phrase, isNotEmpty);
      }
    });
  });
}
