/// L'usage du code à six chiffres — **deux, et pas trois**.
///
/// # Pourquoi `login` n'existe pas
///
/// Le simulateur enchaîne systématiquement `connexion → code → tableau de bord`
/// par un paramètre `purpose: login`. **Le contrat ne le fait pas** :
/// `POST /auth/login` rend un jeton directement, et il n'existe aucune route qui
/// exigerait un second facteur après une connexion réussie (§10.1 du contrat).
///
/// La réserve A.10 est tranchée dans ce sens — arbitrage n° 84 : coder le
/// parcours du contrat, qui est servi, testé, et ne promet rien de faux. Un
/// écran de code affiché après une connexion déjà valide aurait attendu un code
/// que personne n'envoie.
///
/// Il reste donc **un seul écran** pour **deux** usages, servis par ce
/// paramètre : c'est le choix du simulateur, et il tient toujours — trois
/// écrans identiques auraient dérivé.
enum AuthOtpPurpose {
  /// Activation d'un compte neuf, juste après `POST /auth/register`.
  ///
  /// C'est aussi la destination d'un `403 compte_inactif` reçu par n'importe
  /// quel autre parcours : le code n'a jamais été validé, et l'écran reprend la
  /// saisie avec l'adresse déjà connue.
  signup('signup'),

  /// Réinitialisation du mot de passe, après `POST /auth/password/forgot`.
  reset('reset');

  const AuthOtpPurpose(this.wireValue);

  /// La valeur telle qu'elle voyage dans la barre d'adresse interne
  /// (`/otp?purpose=…`). Elle ne part **jamais** au serveur : les trois routes de
  /// code ne prennent qu'un `email` et, pour `verify`, un `code`.
  final String wireValue;

  /// POURQUOI un repli plutôt qu'une exception : le paramètre vient d'une URL,
  /// donc éventuellement d'un lien profond mal formé. Faire tomber l'écran
  /// d'activation sur une faute de frappe serait pire que l'ouvrir sur l'usage
  /// le plus fréquent.
  static AuthOtpPurpose parse(String? raw) {
    for (final AuthOtpPurpose purpose in AuthOtpPurpose.values) {
      if (purpose.wireValue == raw) {
        return purpose;
      }
    }
    return AuthOtpPurpose.signup;
  }

  /// Le titre de l'écran, repris **mot pour mot** du simulateur.
  String get titre => switch (this) {
    AuthOtpPurpose.signup => 'Confirmez votre adresse',
    AuthOtpPurpose.reset => 'Vérifions que c’est bien vous',
  };

  /// La phrase sous le titre, reprise mot pour mot du simulateur.
  String get phrase => switch (this) {
    AuthOtpPurpose.signup =>
      'Nous avons envoyé un code à 6 chiffres pour valider votre adresse e-mail.',
    AuthOtpPurpose.reset =>
      'Saisissez le code reçu par e-mail pour choisir un nouveau mot de passe.',
  };
}
