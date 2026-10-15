/// L'expurgation des journaux.
///
/// POURQUOI la redaction se fait **à la source** et pas au moment d'écrire
/// (§1.6) : dès qu'une valeur sensible existe dans un objet destiné au journal,
/// elle finira par sortir — par une exception non capturée, par un `toString()`
/// de bibliothèque tierce, par une pile d'appels envoyée à la télémétrie.
///
/// Ce qui ne sort jamais, et la liste est absolue (§1.6, 07-LIVRAISON §7.6) :
/// jeton, mot de passe, code à usage unique, **contenu soumis à vérification**,
/// adresse e-mail, téléphone, nom.
///
/// Le contenu soumis est le plus piégeux : c'est du texte utilisateur, souvent
/// nominatif, et c'est le cœur de ce que la plateforme promet de protéger.
abstract final class LogRedactor {
  /// Le marqueur laissé à la place d'une valeur retirée.
  ///
  /// POURQUOI on laisse une trace plutôt que de supprimer la clé : voir qu'un
  /// champ existait aide à diagnostiquer ; ne plus le voir du tout fait croire à
  /// un champ manquant côté serveur.
  static const String mask = '[expurge]';

  /// Les clés dont la **valeur** est toujours retirée, quelle que soit sa forme.
  ///
  /// Elles sont comparées en minuscules et sans séparateur, pour attraper
  /// `motDePasse`, `mot_de_passe` et `mot-de-passe` du même coup.
  static const Set<String> sensitiveKeys = <String>{
    'token',
    'accesstoken',
    'authorization',
    'bearer',
    'password',
    'motdepasse',
    'motdepasseconfirmation',
    'passwordconfirmation',
    'code',
    'otp',
    'secret',
    'email',
    'courriel',
    'telephone',
    'phone',
    'numero',
    'nom',
    'prenom',
    'affirmation', // le contenu soumis a verification
    'contexte',
    'corps',
    'contenu',
    'detail',
    'bio',
    'idempotencykey',
    'cursor', // opaque : le journaliser inviterait a le lire
  };

  /// Les en-têtes retirés d'un journal de requête.
  ///
  /// En pratique le socle ne journalise **aucun** en-tête (04-RESEAU §4.13) ;
  /// cette liste existe pour les cas où un diagnostic en exige un, et elle rend
  /// alors l'oubli impossible.
  static const Set<String> sensitiveHeaders = <String>{
    'authorization',
    'cookie',
    'set-cookie',
    'idempotency-key',
    'x-csrf-token',
  };

  static String _normalise(String key) =>
      key.toLowerCase().replaceAll(RegExp(r'[^a-z0-9]'), '');

  static bool isSensitiveKey(String key) =>
      sensitiveKeys.contains(_normalise(key));

  static bool isSensitiveHeader(String name) =>
      sensitiveHeaders.contains(name.toLowerCase());

  /// Expurge récursivement une structure JSON.
  ///
  /// La récursion s'arrête à [maxDepth] : un cycle ou un objet très profond dans
  /// un journal coûte plus cher que ce qu'il apprend.
  static Object? redact(Object? value, {int maxDepth = 6}) {
    if (maxDepth <= 0) {
      return mask;
    }
    if (value is Map) {
      return <String, Object?>{
        for (final MapEntry<Object?, Object?> entry in value.entries)
          '${entry.key}': isSensitiveKey('${entry.key}')
              ? mask
              : redact(entry.value, maxDepth: maxDepth - 1),
      };
    }
    if (value is Iterable) {
      return <Object?>[
        for (final Object? item in value) redact(item, maxDepth: maxDepth - 1),
      ];
    }
    if (value is String) {
      return redactFreeText(value);
    }
    return value;
  }

  /// Expurge un texte libre : adresses e-mail, jetons porteurs, suites de
  /// chiffres assimilables à un code ou à un numéro.
  ///
  /// POURQUOI même le texte libre : un message d'erreur de bibliothèque tierce
  /// recopie volontiers l'URL complète, en-tête compris.
  static String redactFreeText(String input) {
    return input
        .replaceAll(_emailPattern, mask)
        .replaceAll(_bearerPattern, 'Bearer $mask')
        .replaceAll(_longDigitsPattern, mask);
  }

  static final RegExp _emailPattern = RegExp(
    r'[\w.+-]+@[\w-]+\.[\w.-]+',
    caseSensitive: false,
  );

  static final RegExp _bearerPattern = RegExp(
    r'Bearer\s+[A-Za-z0-9|._~+/-]+=*',
    caseSensitive: false,
  );

  /// Six chiffres ou plus : code OTP, numéro de téléphone, référence.
  static final RegExp _longDigitsPattern = RegExp(r'\b\d{6,}\b');
}
