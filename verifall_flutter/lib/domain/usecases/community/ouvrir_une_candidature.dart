import '../../entities/opportunity_entity.dart';

/// L'ouverture d'un lien hors de l'application.
///
/// POURQUOI une interface plutôt qu'un appel direct à `url_launcher` : c'est la
/// règle la plus importante de l'écran 23, et une règle qui ne se teste pas est
/// une règle qu'on finit par contourner. Avec cette interface, un test vérifie
/// **qu'aucune candidature ne s'ouvre sur une offre clôturée** sans lancer de
/// navigateur.
abstract interface class OuvreurDeLienExterne {
  /// Ouvre [url] en **onglet système**.
  ///
  /// « Jamais en WebView interne : le citoyen doit voir le vrai domaine dans la
  /// barre d'adresse avant de saisir ses données. » Sur une page qui met en
  /// garde contre les fausses offres, masquer le domaine serait une
  /// contradiction complète.
  Future<bool> ouvrirEnOngletSysteme(Uri url);
}

/// Ouvrir la candidature d'une offre.
///
/// # Les deux règles, et elles sont toutes les deux des règles de sécurité
///
/// 1. **Une offre clôturée n'a pas de bouton de candidature** — et si quelqu'un
///    l'appelait quand même, ce cas d'usage refuse. `data.expiree` vient du
///    serveur : « une horloge de téléphone mal réglée présenterait comme
///    candidatable une offre fermée ».
/// 2. **Onglet système, jamais de WebView maison.** Moins de surface d'attaque,
///    session isolée, et le vrai domaine visible.
class OuvrirUneCandidature {
  const OuvrirUneCandidature(this._ouvreur);

  final OuvreurDeLienExterne _ouvreur;

  /// Le domaine à afficher sur le bouton — « Postuler sur `<domaine>` ».
  ///
  /// POURQUOI le domaine et pas l'URL complète : le bouton annonce **où l'on
  /// va**, et c'est le domaine qu'il faut reconnaître. Une URL entière, tronquée
  /// par le bouton, cacherait justement la partie qui compte.
  static String? domaine(String? lien) {
    if (lien == null || lien.trim().isEmpty) {
      return null;
    }
    final Uri? uri = normaliser(lien);
    if (uri == null) {
      return null;
    }
    return uri.host.isEmpty ? null : uri.host;
  }

  /// Normalise un lien en `Uri` absolue.
  ///
  /// POURQUOI la tolérance sur le schéma : le simulateur écrit les liens sans
  /// schéma (`insae.bj/recrutements`). Un lien sans schéma n'est pas ouvrable ;
  /// on lui en ajoute un **explicitement chiffré**, jamais `http`.
  static Uri? normaliser(String lien) {
    final String brut = lien.trim();
    if (brut.isEmpty) {
      return null;
    }
    final Uri? uri = Uri.tryParse(
      brut.contains('://') ? brut : 'https://$brut',
    );
    if (uri == null || uri.host.isEmpty) {
      return null;
    }
    // Ni `javascript:`, ni `data:`, ni `file:` : sur un ecran qui met en garde
    // contre les arnaques, ouvrir n'importe quel schema serait une porte.
    return uri.scheme == 'https' || uri.scheme == 'http' ? uri : null;
  }

  /// `false` quand rien n'a été ouvert — offre clôturée, lien absent ou
  /// illisible. L'écran le dit alors, plutôt que de rester muet.
  Future<bool> call(Opportunite offre) async {
    if (offre.expiree) {
      // Cette branche ne devrait jamais etre atteinte : l'ecran ne dessine pas
      // le bouton. Elle existe parce qu'un ecran se redessine, et qu'une offre
      // peut expirer entre l'affichage et le tap.
      return false;
    }
    final Uri? url = offre.lienCandidature == null
        ? null
        : normaliser(offre.lienCandidature!);
    if (url == null) {
      return false;
    }
    return _ouvreur.ouvrirEnOngletSysteme(url);
  }
}
