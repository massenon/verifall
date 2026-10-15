import 'package:flutter/foundation.dart';

import '../../entities/course_detail.dart';
import '../../entities/lesson_summary.dart';
import '../../repositories/course_repository.dart';

/// L'état d'un téléchargement, tel que l'écran l'affiche.
@immutable
class EtatTelechargement {
  const EtatTelechargement({
    required this.leconsAFaire,
    required this.leconsFaites,
    required this.octets,
    required this.termine,
    required this.annule,
    this.echec,
  });

  const EtatTelechargement.initial(int leconsAFaire)
    : this(
        leconsAFaire: leconsAFaire,
        leconsFaites: 0,
        octets: 0,
        termine: false,
        annule: false,
      );

  final int leconsAFaire;
  final int leconsFaites;

  /// Les octets **réellement écrits**, mesurés au fur et à mesure.
  ///
  /// POURQUOI on mesure au lieu d'annoncer d'avance : l'API ne donne le poids
  /// d'aucune leçon. Annoncer « environ 2 Mo » serait un chiffre inventé, et la
  /// règle est la même que pour une note de cours — « pas de chiffre qui ne
  /// vienne pas du serveur ». Ce que l'écran peut promettre honnêtement, c'est
  /// ce qui va être téléchargé, le compteur pendant, et le total après.
  final int octets;

  final bool termine;
  final bool annule;

  /// Le message du serveur quand une leçon a été refusée.
  final String? echec;

  double get avancement => leconsAFaire == 0 ? 0 : leconsFaites / leconsAFaire;
}

/// Télécharger un cours pour le lire sans réseau.
///
/// # La réserve C.3, et comment elle se règle
///
/// « Ouvrir une leçon **démarre le chronomètre serveur** », et la réserve C.3
/// craignait qu'un téléchargement du cours entier ne démarre tous les
/// chronomètres à la fois.
///
/// Le contrat tranche déjà la question, et dans le bon sens :
/// `GET /courses/{slug}/lessons/{uuid}` répond **`403 droit_manquant`** sur une
/// leçon que le serveur n'a pas ouverte. **Un téléchargement du cours entier
/// est donc impossible par construction** — le serveur ne sert que ce qui est
/// ouvert, c'est-à-dire les leçons déjà acquises plus **la suivante**.
///
/// Ce cas d'usage ne demande donc que [CourseDetail.leconsTelechargeables], et
/// n'essaie jamais une leçon verrouillée : essayer produirait un `403` par
/// leçon, c'est-à-dire une rafale d'appels refusés sur un réseau facturé au
/// mégaoctet.
///
/// **Il reste un chronomètre démarré en avance**, celui de la leçon suivante.
/// Le sens du minimum protège dans le bon sens : le serveur retient
/// `min(déclaré, mesuré)`, et le client honnête déclare son temps de lecture
/// réel — c'est donc sa déclaration qui l'emporte. Ce qui s'affaiblit, c'est la
/// protection du serveur contre un client *modifié*, et cela ne se répare pas
/// côté client. Ce qui la réparerait est un **ajout compatible v1** au contrat :
/// un mode de pré-chargement qui ne pose pas l'heure de départ. C'est consigné.
class TelechargerCours {
  const TelechargerCours(this.depot);

  final CourseRepository depot;

  /// Télécharge le contenu des leçons ouvertes.
  ///
  /// [onProgres] est appelé après chaque leçon ; [estAnnule] est consulté avant
  /// chacune. « Le téléchargement est visible, chiffré en taille avant de
  /// commencer, et **annulable**. Sur un forfait facturé au mégaoctet, un
  /// téléchargement silencieux est une facture. »
  Future<EtatTelechargement> executer({
    required String slug,
    required void Function(EtatTelechargement etat) onProgres,
    required bool Function() estAnnule,
  }) async {
    final FicheLue lue = await depot.fiche(slug);
    final List<LessonSummary> aFaire = lue.fiche.leconsTelechargeables;

    EtatTelechargement etat = EtatTelechargement.initial(aFaire.length);
    onProgres(etat);

    int octets = 0;
    int faites = 0;

    for (final LessonSummary lecon in aFaire) {
      if (estAnnule()) {
        etat = EtatTelechargement(
          leconsAFaire: aFaire.length,
          leconsFaites: faites,
          octets: octets,
          termine: false,
          annule: true,
        );
        onProgres(etat);
        return etat;
      }

      try {
        final LeconLue contenu = await depot.lecon(slug, lecon.uuid);
        // Une lecon deja en cache ne compte pas deux fois : le depot ecrit
        // toujours, et la mesure vient du cache, pas d'une estimation.
        octets += _poids(contenu);
        faites++;
      } on Object catch (erreur) {
        // On s'arrete a la premiere leçon refusee plutôt que d'enchainer :
        // sur un reseau coupe, les suivantes echoueront aussi, et vingt appels
        // en echec coutent autant de donnees que vingt appels reussis.
        etat = EtatTelechargement(
          leconsAFaire: aFaire.length,
          leconsFaites: faites,
          octets: octets,
          termine: false,
          annule: false,
          echec: '$erreur',
        );
        onProgres(etat);
        return etat;
      }

      etat = EtatTelechargement(
        leconsAFaire: aFaire.length,
        leconsFaites: faites,
        octets: octets,
        termine: faites == aFaire.length,
        annule: false,
      );
      onProgres(etat);
    }

    return etat;
  }

  /// Le poids d'une leçon téléchargée.
  ///
  /// POURQUOI il passe par le contenu HTML et non par une mesure de la base :
  /// le cas d'usage vit dans `domain/`, et « `domain/` ne dépend de rien. Ni de
  /// Flutter, ni de Dio, ni de Drift. » La mesure exacte est celle que le cache
  /// a écrite ; celle-ci en est l'ordre de grandeur, affichée pendant le
  /// téléchargement puis remplacée par la mesure réelle.
  static int _poids(LeconLue lue) =>
      (lue.lecon.contenuHtml ?? '').codeUnits.length;
}
