/// Les chemins de l'API v1, tels que le contrat les liste (§10).
///
/// POURQUOI ils sont centralisés au socle plutôt que dispersés dans les dépôts :
/// « ce qui n'y est pas n'existe pas ». Un chemin écrit à la main dans un écran
/// est un chemin qu'on ne peut plus auditer contre le contrat, et c'est ainsi
/// qu'apparaît un appel à un endpoint qui n'a jamais existé.
///
/// **Les cinq appels que le simulateur déclare et que l'API ne sert pas** ne
/// figurent pas ici, et c'est délibéré (§11) : les trois routes de
/// notifications, le retour d'utilité d'un rapport, et le PDF d'un certificat.
/// Les écrire « en attendant » produirait un client qui appelle du vide.
abstract final class ApiEndpoints {
  /// La version majeure est **dans l'URL** (§6.1) : elle appartient au contrat,
  /// pas à la configuration d'environnement.
  static const String versionPrefix = '/api/v1';

  // --- 10.1 Identite, sans jeton ------------------------------------------
  static const String login = '/auth/login';
  static const String register = '/auth/register';
  static const String otpRequest = '/auth/otp/request';

  /// `otp/request` et `otp/resend` sont **le même geste** ; deux URI parce que
  /// l'écran a un bouton « renvoyer ».
  static const String otpResend = '/auth/otp/resend';
  static const String otpVerify = '/auth/otp/verify';
  static const String passwordForgot = '/auth/password/forgot';
  static const String passwordReset = '/auth/password/reset';

  // --- 10.2 Mon compte ------------------------------------------------------
  static const String logout = '/auth/logout';
  static const String me = '/me';
  static const String settings = '/me/settings';
  static const String export = '/me/export';

  // --- 10.3 Verifier --------------------------------------------------------
  static const String uploads = '/uploads';
  static const String verifications = '/verifications';
  static const String myVerifications = '/me/verifications';

  static String verification(String uuid) => '/verifications/$uuid';
  static String verificationContest(String uuid) =>
      '/verifications/$uuid/contest';

  // --- 10.4 Academie --------------------------------------------------------
  static const String courses = '/courses';
  static const String enrollments = '/me/enrollments';
  static const String coursesResume = '/me/courses/resume';
  static const String certificates = '/me/certificates';

  static String course(String slug) => '/courses/$slug';
  static String courseEnroll(String slug) => '/courses/$slug/enroll';
  static String courseLesson(String slug, String uuid) =>
      '/courses/$slug/lessons/$uuid';
  static String courseQuiz(String slug) => '/courses/$slug/quiz';
  static String lessonComplete(String uuid) => '/me/lessons/$uuid/complete';
  static String quizAttempt(String uuid) => '/me/quiz/$uuid/attempt';
  static String courseProgress(String slug) => '/me/courses/$slug/progress';

  /// Le certificat est identifié par son **code public**, pas par un uuid : il
  /// est « conçu pour être lu à voix haute et recopié » (§4).
  static String certificate(String code) => '/me/certificates/$code';

  // --- 10.5 Communaute, Hub, outils, seances --------------------------------
  static const String forumThreads = '/forum/threads';
  static const String hubArticles = '/hub/articles';
  static const String hubOpportunities = '/hub/opportunities';
  static const String tools = '/tools';
  static const String liveClasses = '/live-classes';

  static String forumThread(String slug) => '/forum/threads/$slug';
  static String forumThreadVote(String slug) => '/forum/threads/$slug/vote';
  static String forumThreadReplies(String slug) =>
      '/forum/threads/$slug/replies';
  static String forumThreadReport(String slug) => '/forum/threads/$slug/report';
  static String hubArticle(String slug) => '/hub/articles/$slug';
  static String hubOpportunity(String slug) => '/hub/opportunities/$slug';
  static String hubOpportunityReport(String slug) =>
      '/hub/opportunities/$slug/report';
  static String liveClassRemind(String uuid) => '/live-classes/$uuid/remind';

  // --- 10.6 Accueil, statistiques, unites, recompenses, parrainage ----------
  /// **Un agrégat**, et c'est le point : la première image doit tenir sous
  /// 400 ms, et six allers-retours sur un réseau mobile béninois feraient de
  /// l'écran d'accueil le plus lent de l'application (§10.6).
  static const String dashboard = '/me/dashboard';
  static const String stats = '/me/stats';
  static const String wallet = '/me/wallet';
  static const String walletTopUp = '/me/wallet/topup';
  static const String walletTransactions = '/me/wallet/transactions';
  static const String gamification = '/me/gamification';
  static const String referrals = '/me/referrals';
  static const String referralsInvite = '/me/referrals/invite';

  /// Les quatre listes que le contrat déclare **non paginées** (§3.3).
  ///
  /// POURQUOI la liste vit dans le code : « ne pas leur écrire de pagination —
  /// un curseur qui ne se déclenche jamais est du code mort » (04-RESEAU §4.4).
  static const Set<String> unpaginatedCollections = <String>{
    tools,
    liveClasses,
    certificates,
    gamification,
  };
}
