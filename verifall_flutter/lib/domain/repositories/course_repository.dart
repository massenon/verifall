import '../entities/course_detail.dart';
import '../entities/course_enrollment.dart';
import '../entities/course_level.dart';
import '../entities/course_summary.dart';
import '../entities/lesson_content.dart';

/// Une page de catalogue, avec son curseur.
///
/// POURQUOI le curseur remonte jusqu'au domaine : il est **opaque** et se
/// renvoie tel quel. L'écran ne le lit jamais ; il le repasse. « Le jour où
/// l'ordre de tri change, un client qui en dépendait casse en silence. »
class PageDeCatalogue {
  const PageDeCatalogue({
    required this.cours,
    required this.curseurSuivant,
    required this.aPlus,
  });

  final List<CourseSummary> cours;
  final String? curseurSuivant;
  final bool aPlus;
}

/// Le filtre du catalogue.
///
/// **Il part au serveur, toujours** (arbitrage n° 82) : « on ne filtre jamais
/// côté client une liste paginée. Filtrer localement ne filtre que la page déjà
/// chargée ; à la deuxième page, l'utilisateur voit apparaître des éléments qui
/// ne correspondent pas à sa sélection. »
///
/// Et le corollaire, aussi coûteux : **tous les paramètres repartent avec le
/// curseur**. « Le second appel fautif ne renvoie pas d'erreur : il rend la
/// page suivante du catalogue non filtré. » C'est pour cela que ce type est un
/// objet et non deux arguments qu'on oublie l'un des deux.
class FiltreCatalogue {
  const FiltreCatalogue({this.niveau, this.recherche});

  final NiveauCours? niveau;
  final String? recherche;

  static const FiltreCatalogue aucun = FiltreCatalogue();

  bool get estVide =>
      niveau == null && (recherche == null || recherche!.isEmpty);

  /// La clé de cache. Une combinaison de filtres = une liste différente, donc
  /// une entrée de cache différente.
  String get cle => '${niveau?.valeurTechnique ?? ''}|${recherche ?? ''}';

  /// Les paramètres de requête, à renvoyer **à l'identique** avec le curseur.
  ///
  /// Un filtre absent s'exprime en **omettant** le paramètre (contrat §3.6).
  /// [NiveauCours.inconnu] n'est jamais envoyé : `level` est une énumération
  /// fermée, et une valeur hors liste répond `422`.
  Map<String, Object?> get parametres => <String, Object?>{
    if (niveau != null && niveau != NiveauCours.inconnu)
      'level': niveau!.valeurTechnique,
    if (recherche != null && recherche!.trim().isNotEmpty)
      'q': recherche!.trim(),
  };
}

/// Le dépôt de l'Académie.
///
/// POURQUOI une interface dans `domain/` : « `domain/` ne dépend de rien. Ni de
/// Flutter, ni de Dio, ni de Drift » (§1.2). C'est ce qui permet de tester la
/// coupure du réseau sans base native et sans serveur.
abstract interface class CourseRepository {
  /// La première page du catalogue. **Lue du cache d'abord, du réseau
  /// ensuite** (05-HORS-LIGNE §5.4).
  Future<CatalogueLu> catalogue(FiltreCatalogue filtre);

  /// Une page suivante. **Elle exige le réseau** : un curseur est une position
  /// dans l'ordre du serveur, il ne se rejoue pas sur un cache local.
  Future<PageDeCatalogue> pageSuivante(FiltreCatalogue filtre, String curseur);

  /// La fiche d'un cours, cache d'abord.
  Future<FicheLue> fiche(String slug);

  /// Toutes mes inscriptions. Paginée au contrat ; le dépôt suit les curseurs
  /// jusqu'au bout, la liste étant courte par nature.
  Future<List<CourseEnrollment>> mesInscriptions();

  /// « Reprendre où j'en étais ». Rend `null` quand il n'y a rien à reprendre.
  Future<CourseResume?> reprendre();

  /// Mon avancement sur un cours.
  Future<CourseProgress> avancement(String slug);

  /// S'inscrire. **Idempotent par service** côté serveur : un rejeu depuis la
  /// file hors ligne ne débite pas deux fois.
  ///
  /// Rend `null` quand l'acte a été **mis en file** faute de réseau — l'écran
  /// doit alors dire que le débit n'est pas encore effectué.
  Future<CourseEnrollment?> sInscrire(String slug);

  /// Le contenu d'une leçon. **Attention** : un appel réseau ici démarre le
  /// chronomètre serveur.
  Future<LeconLue> lecon(String coursSlug, String leconUuid);

  /// Valider une leçon. [secondes] est une **déclaration** : le serveur retient
  /// le plus petit des deux.
  ///
  /// Rend `false` quand l'acte est parti en file — l'écran ne marque alors
  /// **pas** la leçon comme validée : « ne jamais marquer localement une
  /// complétion non confirmée ».
  Future<bool> terminerLecon(String coursSlug, String leconUuid, int secondes);

  /// Marque un cours comme **explicitement téléchargé**.
  ///
  /// « C'est le seul cache explicitement demandé par l'utilisateur » (§5.11) :
  /// il ne s'évapore pas comme le reste, et il ne disparaît que sur demande.
  Future<void> marquerTelecharge(String slug);

  /// Supprime un cours téléchargé : sa fiche, ses leçons, ses compteurs.
  ///
  /// « Un cours téléchargé doit pouvoir se supprimer » (§5.11).
  Future<void> supprimerTelechargement(String slug);

  /// Le poids **réellement** stocké pour un cours, en octets.
  ///
  /// C'est une mesure, pas une estimation : « sur un forfait facturé au
  /// mégaoctet, un téléchargement silencieux est une facture ».
  Future<int> octetsStockes(String slug);

  /// Les secondes de lecture active déjà comptées pour une leçon.
  ///
  /// POURQUOI c'est persisté : le serveur lui-même ne remet pas son
  /// chronomètre à zéro quand on rouvre une leçon, « sans cela, quitter et
  /// rouvrir suffirait à effacer le temps déjà passé — et punirait exactement
  /// les gens qui lisent en plusieurs fois, dans le transport ».
  Future<int> secondesLues(String leconUuid);

  /// Enregistre le compteur de lecture active. C'est une **déclaration**, pas
  /// la vérité : le serveur retient le plus petit des deux (§7.4).
  Future<void> enregistrerSecondesLues(String leconUuid, int secondes);
}

/// Le catalogue lu, avec ce qu'il faut pour être honnête à l'écran.
class CatalogueLu {
  const CatalogueLu({
    required this.page,
    required this.lueLe,
    required this.vientDuCache,
  });

  final PageDeCatalogue page;
  final DateTime lueLe;

  /// Vrai quand le réseau n'a pas répondu et que l'écran affiche le cache : il
  /// doit alors poser le bandeau « Données du `<date>` ».
  final bool vientDuCache;
}

/// La fiche lue.
class FicheLue {
  const FicheLue({
    required this.fiche,
    required this.lueLe,
    required this.vientDuCache,
    required this.leconsHorsLigne,
    required this.telechargeLe,
  });

  final CourseDetail fiche;
  final DateTime lueLe;
  final bool vientDuCache;

  /// Les uuid des leçons dont le **contenu** est disponible localement.
  ///
  /// Une leçon absente de cet ensemble porte « Disponible avec une connexion »,
  /// **pas un cadenas nu** (05-HORS-LIGNE §5.11).
  final Set<String> leconsHorsLigne;

  /// Quand le cours a été téléchargé à la demande, ou `null`.
  final DateTime? telechargeLe;
}

/// Une leçon lue.
class LeconLue {
  const LeconLue({
    required this.lecon,
    required this.lueLe,
    required this.vientDuCache,
  });

  final LessonContent lecon;
  final DateTime lueLe;
  final bool vientDuCache;
}
