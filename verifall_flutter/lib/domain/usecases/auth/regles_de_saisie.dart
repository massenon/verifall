/// Les seules validations que le client a le droit de faire.
///
/// # Pourquoi le client ne valide QUE la longueur du mot de passe
///
/// Le contrat est explicite : la politique réelle est **12 caractères minimum,
/// casse mixte, chiffre, symbole et absence des fuites connues**, et *« le
/// client se limite à vérifier la longueur avant l'envoi — le reste est
/// serveur »* (§10.1).
///
/// La raison est concrète : la liste des fuites connues n'est pas embarquée, et
/// une casse mixte contrôlée localement refuserait des mots de passe que le
/// serveur accepte. Une règle du client qui n'est pas exactement celle du
/// serveur est une règle fausse — et c'est toujours l'utilisateur qui paie.
///
/// # Pourquoi l'étiquette annonce 12 et pas 8
///
/// Le simulateur écrit « 8 caractères minimum » sur deux écrans. La règle
/// centralisée est 12 depuis l'arbitrage n° 10 : « un formulaire ne doit pas
/// promettre moins que ce qu'il exigera ». La réserve A.3 est tranchée dans ce
/// sens.
abstract final class ReglesDeSaisie {
  /// La longueur minimale, **et la seule chose que le client vérifie**.
  static const int longueurMotDePasse = 12;

  /// Le nombre de chiffres du code, fixé par l'arbitrage n° 4 : six chiffres,
  /// dix minutes, usage unique, cinq tentatives.
  static const int longueurCode = 6;

  /// Le motif minimal du simulateur, et rien de plus.
  ///
  /// POURQUOI il est aussi permissif : une expression rationnelle « complète »
  /// pour RFC 5322 rejette des adresses valides, et la vraie vérification est de
  /// toute façon l'envoi du code. Ce contrôle-ci n'existe que pour éviter un
  /// aller-retour sur une faute de frappe évidente.
  static final RegExp _formeEmail = RegExp(r'.+@.+\..+');

  static bool emailValide(String valeur) => _formeEmail.hasMatch(valeur.trim());

  /// **Longueur seulement.** Voir l'en-tête de cette classe.
  static bool motDePasseAssezLong(String valeur) =>
      valeur.length >= longueurMotDePasse;

  /// Un nom d'au moins deux caractères — la borne du serveur (`min:2`).
  static bool nomValide(String valeur) => valeur.trim().length >= 2;

  /// Le téléphone : au moins huit caractères, la borne du serveur (`min:8`).
  ///
  /// POURQUOI aucun format n'est imposé : le contrat n'en impose pas, et un
  /// masque « +229 XX XX XX XX » refuserait un numéro étranger valide.
  static bool telephoneValide(String valeur) => valeur.trim().length >= 8;

  static bool professionValide(String valeur) => valeur.trim().isNotEmpty;

  static final RegExp _formeCode = RegExp('^[0-9]{$longueurCode}\$');

  /// Le code est complet quand il porte ses six chiffres. Rien de plus n'est
  /// vérifiable localement : la validité, l'expiration et l'épuisement sont
  /// trois faits serveur.
  static bool codeComplet(String valeur) => _formeCode.hasMatch(valeur);

  /// L'étiquette du champ de mot de passe, écrite une seule fois pour que les
  /// deux écrans qui la portent ne puissent pas diverger.
  static const String etiquetteMotDePasse =
      'Mot de passe ($longueurMotDePasse caractères minimum)';
}
