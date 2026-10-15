/// Les chemins de navigation, dictés par le simulateur (§1.4).
///
/// POURQUOI ils sont centralisés : `go_router` navigue par nom **ou** par
/// chemin, et un chemin écrit à la main dans un écran est un lien mort dès que
/// le chemin bouge. Les cinq parcours s'appellent entre eux par ces constantes —
/// « les dépendances fonctionnelles se résolvent par la navigation, jamais par
/// un import croisé entre deux dossiers `features/` » (§8.1, règle 4).
///
/// POURQUOI aucun identifiant auto-incrémenté n'apparaît : la règle du chantier
/// vaut pour les routes mobiles — `:uuid`, `:slug`, `:code`. Les trois routes du
/// simulateur qui portaient un entier (`/forum/:id`, `/hub/opportunites/:id`,
/// `/hub-infos/:id`) sont ici en `:slug`, parce que **le contrat d'API a tranché**
/// : sujet, brève et opportunité sont identifiés par un slug (§4 du contrat).
abstract final class VfRoutes {
  // --- Amorcage et authentification (M1) ----------------------------------
  /// Le splash, **qui n'apparaît qu'une fois** : drapeau `onboarding_seen` en
  /// stockage sécurisé ; ensuite l'application ouvre directement sur la
  /// connexion ou le tableau de bord.
  static const String splash = '/';

  static const String login = '/connexion';
  static const String register = '/inscription';
  static const String forgotPassword = '/mot-de-passe-oublie';
  static const String otp = '/otp';
  static const String resetPassword = '/mot-de-passe/reinitialiser';

  /// Les écrans d'authentification, pour la garde « connecté qui rouvre un écran
  /// d'authentification → son tableau de bord ».
  static const Set<String> authPaths = <String>{
    login,
    register,
    forgotPassword,
    otp,
    resetPassword,
  };

  // --- Accueil et verification (M2) ---------------------------------------
  static const String dashboard = '/dashboard';
  static const String notifications = '/notifications';
  static const String verify = '/verifier';
  static const String myVerifications = '/mes-verifications';
  static const String tools = '/outils';

  static String verification(String uuid) => '/verification/$uuid';
  static String report(String uuid) => '/rapport/$uuid';

  // --- Academie (M3) -------------------------------------------------------
  static const String catalogue = '/catalogue';
  static const String liveClasses = '/live-classes';

  static String course(String slug) => '/cours/$slug';
  static String lesson(String slug, String uuid) => '/cours/$slug/lecon/$uuid';
  static String quiz(String slug) => '/cours/$slug/quiz';

  /// Le certificat est identifié par son **code public**, pas par un uuid :
  /// « il est conçu pour être lu à voix haute et recopié » (§4 du contrat).
  static String certificate(String code) => '/certificat/$code';

  // --- Communaute et Hub (M4) ----------------------------------------------
  static const String forum = '/forum';
  static const String hub = '/hub';

  static String forumThread(String slug) => '/forum/$slug';
  static String hubOpportunity(String slug) => '/hub/opportunites/$slug';
  static String hubArticle(String slug) => '/hub-infos/$slug';

  // --- Compte (M5) ---------------------------------------------------------
  static const String profile = '/profil';
  static const String wallet = '/unites';
  static const String rewards = '/recompenses';
  static const String stats = '/statistiques';
  static const String referrals = '/parrainage';
  static const String settings = '/reglages';

  /// L'état « compte en cours de suppression ».
  ///
  /// POURQUOI le socle déclare le chemin sans écrire l'écran : le contrat impose
  /// de traiter ce cas **au niveau du routeur** et non écran par écran (§1.7,
  /// 04-RESEAU §4.3), donc la garde vit ici. Mais la page appartient au parcours
  /// qui la dessine (M5), exactement comme `/connexion` appartient à M1. Tant
  /// qu'elle n'existe pas, `errorBuilder` prend le relais.
  static const String accountBeingDeleted = '/compte-en-suppression';

  /// L'écran bloquant du `426 client_obsolete`, porté par le socle.
  ///
  /// POURQUOI le socle et pas un parcours : il n'appartient à aucun des trente
  /// écrans du simulateur, et il « doit exister avant qu'on en ait besoin — le
  /// jour où le plancher est relevé, on ne peut plus déployer de code sur les
  /// téléphones concernés » (04-RESEAU §4.6).
  static const String updateRequired = '/mise-a-jour';

  /// Les chemins atteignables **sans** session ouverte.
  static const Set<String> publicPaths = <String>{
    splash,
    login,
    register,
    forgotPassword,
    otp,
    resetPassword,
    updateRequired,
  };
}

/// Le schéma de lien profond (§1.4).
///
/// Deux cas sont nommés par le simulateur : `verifall://cours/{slug}` et
/// `verifall://hub/{slug}`.
///
/// POURQUOI un lien profond doit se résoudre **application fermée**, et retomber
/// sur un repli explicite : une opportunité est archivée à J+7, une brève peut
/// être retirée. Un lien partagé sur WhatsApp survit plus longtemps que son
/// contenu.
abstract final class VfDeepLinks {
  static const String scheme = 'verifall';

  /// Traduit un lien profond en chemin interne, ou rend `null` si le lien
  /// n'appartient pas à l'application.
  ///
  /// POURQUOI la traduction est ici et pas dans `go_router` : elle est testable
  /// sans démarrer un routeur, et c'est le genre de code qu'on veut couvert par
  /// un test plutôt que par un essai manuel avec un téléphone.
  static String? resolve(Uri uri) {
    if (uri.scheme != scheme) {
      return null;
    }
    // `verifall://cours/le-slug` : l'hote porte le premier segment.
    final List<String> segments = <String>[
      if (uri.host.isNotEmpty) uri.host,
      ...uri.pathSegments.where((String s) => s.isNotEmpty),
    ];
    if (segments.isEmpty) {
      return VfRoutes.dashboard;
    }
    return switch (segments) {
      ['cours', final String slug, ...] => VfRoutes.course(slug),
      ['hub', 'opportunites', final String slug, ...] =>
        VfRoutes.hubOpportunity(slug),
      ['hub', final String slug, ...] => VfRoutes.hubArticle(slug),
      ['rapport', final String uuid, ...] => VfRoutes.report(uuid),
      ['certificat', final String code, ...] => VfRoutes.certificate(code),
      _ => '/${segments.join('/')}',
    };
  }
}
