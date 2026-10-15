import '../../../data/models/api_enums.dart';
import '../../entities/thread_entity.dart' show IssueEcriture;
import '../../repositories/forum_repository.dart';
import '../../repositories/hub_repository.dart';

/// Ce qu'on signale : un sujet du forum, ou une offre du Hub.
///
/// POURQUOI les deux passent par le même cas d'usage : le contrat leur donne
/// **le même corps et la même liste fermée de motifs**. Deux chemins de code
/// séparés, c'est deux occasions d'oublier un motif d'un côté.
enum CibleSignalement { sujet, opportunite }

/// Signaler un contenu.
///
/// # Signaler n'est pas voter — arbitrage n° 52
///
/// C'est la phrase qui change ce que l'écran doit dire :
///
/// > « Cinq personnes qui signalent le même message ne le rendent pas cinq fois
/// > plus répréhensible ; il n'y a **aucun masquage automatique** au-delà d'un
/// > seuil, parce que ce serait offrir un bouton de censure à n'importe quel
/// > groupe organisé. Un humain tranche, toujours. »
///
/// D'où [avertissementAvantEnvoi], affiché **avant** l'envoi et non après :
/// « promettre un retrait qui n'aura pas lieu décourage celui qui a signalé de
/// bonne foi ».
class SignalerUnContenu {
  /// Les deux depots, dans cet ordre : forum puis Hub.
  const SignalerUnContenu(this._forum, this._hub);

  /// Les **six** motifs du contrat, dans l'ordre où il les donne (§10.5).
  ///
  /// POURQUOI la liste est reprise de `MotifSignalement` — l'énumération du
  /// socle — et non réécrite : elle y est déjà fermée, et deux listes finiraient
  /// par diverger.
  static const List<MotifSignalement> motifs = MotifSignalement.values;

  /// Ce que la feuille dit **avant** l'envoi.
  static const String avertissementAvantEnvoi =
      'Un membre de l equipe examine chaque signalement. Il n y a pas de '
      'retrait automatique : signaler n est pas voter.';

  /// La borne haute de `detail` au contrat (§10.5).
  static const int longueurMaximaleDetail = 1000;

  final ForumRepository _forum;
  final HubRepository _hub;

  /// « Signaler deux fois ne crée qu'une ligne » : l'unicité est en base, donc
  /// l'appel est rejouable depuis la file hors ligne — sans clé d'idempotence,
  /// et c'est ce que `PendingOpKind.signaler` déclare.
  Future<IssueEcriture> call({
    required CibleSignalement cible,
    required String slug,
    required MotifSignalement motif,
    String? detail,
  }) {
    return switch (cible) {
      CibleSignalement.sujet => _forum.signalerSujet(
        slug: slug,
        motif: motif,
        detail: detail,
      ),
      CibleSignalement.opportunite => _hub.signalerOpportunite(
        slug: slug,
        motif: motif,
        detail: detail,
      ),
    };
  }
}

/// Le libellé français des six motifs.
///
/// # Pourquoi ces mots sont écrits ici, et ce que ça coûte
///
/// Le contrat donne les six **valeurs techniques** et rappelle que « les
/// libellés affichables voyagent avec elles comme pour toute énumération de
/// l'API ». Mais une feuille de signalement est un **formulaire** : elle doit
/// afficher ses options *avant* le moindre appel, et aucun endpoint ne les sert.
/// Le simulateur, lui, ne montre pas la feuille — il se contente d'un toast.
///
/// Les six libellés n'ont donc **aucune source**. Ils sont écrits ici, en
/// traduction littérale des valeurs, et la réserve est consignée dans
/// `docs/ARBITRAGES.md` : soit l'API les sert (`meta.motifs`), soit le porteur
/// du projet valide cette rédaction.
///
/// Ce n'est pas la même chose que fabriquer le libellé d'un verdict : là, une
/// valeur inconnue arriverait du serveur et le client mentirait. Ici, la liste
/// est fermée au contrat et le client n'invente aucune option — seulement les
/// mots pour les dire.
extension LibelleMotif on MotifSignalement {
  String get libelle => switch (this) {
    MotifSignalement.desinformation => 'Desinformation',
    MotifSignalement.haine => 'Haine',
    MotifSignalement.arnaque => 'Arnaque',
    MotifSignalement.spam => 'Spam',
    MotifSignalement.donnees => 'Donnees personnelles',
    MotifSignalement.autre => 'Autre',
  };
}
