import 'package:flutter/foundation.dart';

/// Les trois consentements RGPD de l'écran 03, **séparés et jamais pré-cochés**.
///
/// # Pourquoi trois booléens et pas une carte de chaînes
///
/// Le contrat attend trois champs nommés — `cgu`, `confidentialite`,
/// `newsletter?` — et les deux premiers sont validés en `accepted` côté serveur.
/// Une carte libre aurait laissé passer une quatrième clé inventée, et le
/// serveur l'aurait ignorée en silence.
///
/// # Pourquoi aucun constructeur ne permet de pré-cocher
///
/// « Un consentement RGPD pré-coché n'est pas un consentement » (art. 7). Les
/// trois champs valent `false` par défaut et il n'existe pas de fabrique qui en
/// pose un à `true` sans passage par l'interface. C'est la même précaution que
/// `VfConsentTile`, qui n'a pas non plus de valeur par défaut à vrai.
///
/// # Pourquoi le refus de la lettre d'information compte aussi
///
/// L'arbitrage n° 11 en fait un **consentement tracé**, pas une préférence
/// d'interface : le serveur enregistre le refus comme il enregistre l'accord.
/// [newsletter] part donc toujours dans le corps, y compris à `false`.
@immutable
class AuthConsentements {
  const AuthConsentements({
    this.cgu = false,
    this.confidentialite = false,
    this.newsletter = false,
  });

  /// « J'accepte les conditions d'utilisation et la politique de
  /// confidentialité. » — **obligatoire**.
  final bool cgu;

  /// « J'autorise le traitement de mes contenus soumis aux seules fins de
  /// vérification. » — **obligatoire**.
  final bool confidentialite;

  /// « Je souhaite recevoir le résumé hebdomadaire des vérifications par
  /// e-mail. » — facultatif.
  final bool newsletter;

  /// Les deux obligatoires bloquent l'envoi. C'est l'écran qui applique la
  /// règle ; cette propriété est le seul endroit où elle est écrite.
  bool get obligatoiresAcceptes => cgu && confidentialite;

  AuthConsentements copyWith({
    bool? cgu,
    bool? confidentialite,
    bool? newsletter,
  }) => AuthConsentements(
    cgu: cgu ?? this.cgu,
    confidentialite: confidentialite ?? this.confidentialite,
    newsletter: newsletter ?? this.newsletter,
  );

  @override
  bool operator ==(Object other) =>
      other is AuthConsentements &&
      other.cgu == cgu &&
      other.confidentialite == confidentialite &&
      other.newsletter == newsletter;

  @override
  int get hashCode => Object.hash(cgu, confidentialite, newsletter);
}
