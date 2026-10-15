import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../data/datasources/remote/community/ouvreur_de_lien_systeme.dart';
import '../../../../domain/entities/reply_entity.dart';
import '../../../../domain/entities/thread_entity.dart';
import '../../../../domain/usecases/community/repondre_a_un_sujet.dart';
import '../../../shared/shared.dart';
import '../providers/forum_providers.dart';
import '../support/liens_m4.dart';
import '../widgets/etats_m4.dart';
import '../widgets/feuille_signalement.dart';
import '../widgets/forum_widgets.dart';

/// Écran 21 — **Sujet du forum**.
///
/// # Deux zones, deux libellés, et ils ne se rencontrent jamais
///
/// Le bandeau dit « VERIF ALL CONFIRME » ; le panneau dit « Que pense la
/// communaute ? ». Le premier n'apparaît **que** si `data.verdict_officiel` est
/// présent ; le second **que** si `data.votes` l'est. Un sondage unanime
/// n'allume pas le bandeau, et un verdict n'écrit pas dans le sondage
/// (arbitrage n° 43).
///
/// # Le vote est optimiste, puis réconcilié
///
/// L'écran affiche le choix aussitôt, et se range à ce que le serveur renvoie —
/// « sans clignoter ». C'est sans danger : « la réponse du serveur fait foi,
/// l'unicité est en base ».
class ThreadPage extends ConsumerStatefulWidget {
  const ThreadPage({required this.slug, super.key});

  /// **Un slug**, jamais un entier (§4 du contrat).
  final String slug;

  @override
  ConsumerState<ThreadPage> createState() => _ThreadPageState();
}

class _ThreadPageState extends ConsumerState<ThreadPage> {
  final TextEditingController _reponse = TextEditingController();
  bool _envoiEnCours = false;

  @override
  void dispose() {
    _reponse.dispose();
    super.dispose();
  }

  SujetNotifier get _notifier => ref.read(sujetProvider(widget.slug).notifier);

  Future<void> _voter(AvisCommunaute avis) async {
    try {
      await _notifier.voter(avis);
    } on Failure catch (echec) {
      if (!mounted) {
        return;
      }
      // « Vote refuse : l'interface revient a l'etat serveur, avec le message
      // recu. » Le retour a l'etat precedent est fait par le notifier.
      VfToast.error(
        context,
        echec.hasServerMessage
            ? echec.serverMessage!
            : 'Votre vote n a pas pu être enregistre.',
      );
    }
  }

  Future<void> _repondre() async {
    final String? erreur = RepondreAUnSujet.valider(_reponse.text);
    if (erreur != null) {
      VfToast.error(context, erreur);
      return;
    }
    setState(() => _envoiEnCours = true);
    try {
      await _notifier.repondre(_reponse.text);
      if (mounted) {
        _reponse.clear();
        VfToast.show(context, message: 'Réponse publiee.');
      }
    } on NetworkFailure {
      if (mounted) {
        VfToast.error(
          context,
          'Répondre demande une connexion. Votre texte est conserve.',
        );
      }
    } on Failure catch (echec) {
      if (mounted) {
        // Un fil verrouille repond « 422 action_impossible » : le message du
        // serveur s'affiche TEL QUEL, sans reformulation. « La moderation a
        // decide ; contourner sa decision par l'API serait une faille. »
        VfToast.error(
          context,
          echec.hasServerMessage
              ? echec.serverMessage!
              : 'La réponse n a pas pu être publiee.',
        );
      }
    } finally {
      if (mounted) {
        setState(() => _envoiEnCours = false);
      }
    }
  }

  Future<void> _signaler() async {
    final SignalementSaisi? saisi = await FeuilleSignalement.ouvrir(context);
    if (saisi == null || !mounted) {
      return;
    }
    try {
      final IssueEcriture issue = await _notifier.signaler(
        motif: saisi.motif,
        detail: saisi.detail,
      );
      if (!mounted) {
        return;
      }
      VfToast.show(
        context,
        message: issue == IssueEcriture.envoyee
            ? 'Signalement transmis a la moderation.'
            // « Ne jamais faire croire qu'une ecriture a abouti » (§5.7).
            : 'Signalement en attente d envoi. Il partira des le retour du '
                  'reseau.',
      );
    } on Failure catch (echec) {
      if (mounted) {
        VfToast.error(
          context,
          echec.hasServerMessage
              ? echec.serverMessage!
              : 'Le signalement n a pas pu être transmis.',
        );
      }
    }
  }

  Future<void> _partager(SujetForum sujet) async {
    await ref
        .read(partageurDeLienProvider)
        .partager(texte: '${sujet.titre}\n${VfRoutes.forumThread(sujet.slug)}');
  }

  @override
  Widget build(BuildContext context) {
    final AsyncValue<EtatSujet> etat = ref.watch(sujetProvider(widget.slug));

    return Scaffold(
      backgroundColor: VfColors.canvas,
      body: Column(
        children: <Widget>[
          VfAppBar(
            // « Titre = la categorie du sujet ». Sans categorie servie, le titre
            // retombe sur le nom de l'ecran plutot que sur une rubrique
            // fabriquee.
            title:
                etat.value?.detail.sujet.categorieLibelle ?? 'Sujet du forum',
            actions: <Widget>[
              VfIconButton(
                icon: Icons.flag_outlined,
                tooltip: 'Signaler ce sujet',
                onPressed: etat.hasValue ? _signaler : null,
              ),
            ],
          ),
          Expanded(
            child: etat.when(
              loading: () => const _SqueletteDeSujet(),
              error: (Object erreur, StackTrace _) => _erreur(erreur),
              data: _contenu,
            ),
          ),
        ],
      ),
    );
  }

  Widget _erreur(Object erreur) {
    if (erreur is NotFoundFailure) {
      // « Ecran de repli qui n'affirme rien » : le 404 couvre aussi « pas a
      // vous ». Le libelle ne dit jamais « supprime ».
      return ReplIntrouvable(
        titre: 'Cette discussion n est pas disponible',
        libelleSortie: 'Revenir au forum',
        onSortir: () => context.go(VfRoutes.forum),
      );
    }
    return EtatEchec(
      echec: erreur is Failure ? erreur : const UnexpectedFailure(),
      onReessayer: () async => ref.invalidate(sujetProvider(widget.slug)),
      messageHorsLigne:
          'Ce sujet n a pas encore ete consulte sur cet appareil : il ne peut '
          'pas être lu hors ligne.',
    );
  }

  Widget _contenu(EtatSujet etat) {
    final SujetForum sujet = etat.detail.sujet;
    final RepartitionVotes? votes = sujet.votes;

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        VfSpacing.screenInline,
        VfSpacing.screenTopExtra,
        VfSpacing.screenInline,
        VfSpacing.screenBottomExtra,
      ),
      children: <Widget>[
        if (etat.recupereLe != null) ...<Widget>[
          VfStaleDataBanner(
            lueLe: etat.recupereLe!,
            tone: VfStaleTone.echeance,
            onRetry: () async => ref.invalidate(sujetProvider(widget.slug)),
          ),
          const SizedBox(height: VfSpacing.stack),
        ],

        // 1. LE BANDEAU OFFICIEL, s'il existe, EN TETE — il est lu avant le
        //    titre parce que c'est l'information la plus importante.
        if (sujet.verdictOfficiel != null) ...<Widget>[
          VfOfficialVerdictBanner(verdict: sujet.verdictOfficiel!),
          const SizedBox(height: VfSpacing.stack),
        ],

        VfAuthorRow(auteur: sujet.auteur, publieLe: sujet.publieLe),
        const SizedBox(height: VfSpacing.stack),
        Text(sujet.titre, style: VfTextStyles.hMd.copyWith(height: 1.3)),
        const SizedBox(height: VfSpacing.x10),
        // Les liens du corps sortent en ONGLET SYSTEME : c'est la
        // transposition mobile de `nofollow noopener ugc`.
        VfProseBody(
          html: sujet.corps,
          onLinkTap: (String url) => ouvrirLienDeProse(ref, url),
        ),

        // 2. LE SONDAGE, dans sa propre carte, sous son propre titre.
        if (votes != null) ...<Widget>[
          const SizedBox(height: VfSpacing.stack),
          VfVotePanel(
            votes: votes,
            monVote: sujet.monVote,
            provisoire: etat.voteProvisoire,
            onVoter: _voter,
          ),
        ],

        const SizedBox(height: VfSpacing.stack),
        VfReactionRow(
          nbJaime: sujet.nbJaime,
          onPartager: () => _partager(sujet),
        ),

        const SizedBox(height: VfSpacing.x22),
        Text(
          '${etat.detail.nbReponsesAffichees} réponses',
          style: VfTextStyles.hSm,
        ),
        const SizedBox(height: VfSpacing.x10),
        for (final ReponseForum reponse in etat.detail.reponses)
          Padding(
            padding: const EdgeInsets.only(bottom: VfSpacing.stackSm),
            child: VfReplyTile(
              reponse: reponse,
              onLienExterne: (String url) => ouvrirLienDeProse(ref, url),
            ),
          ),

        const SizedBox(height: VfSpacing.x10),
        if (sujet.verrouille)
          // « Sujet ferme par la moderation : le composeur disparait et l'ecran
          // dit pourquoi. » L'ecran informe ; c'est le serveur qui protege.
          VfCard(
            tight: true,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                const Icon(
                  Icons.lock_outline_rounded,
                  size: 19,
                  color: VfColors.slate2,
                ),
                const SizedBox(width: VfSpacing.x9),
                const Expanded(
                  child: Text(
                    'La moderation a ferme cette discussion. Il n est plus '
                    'possible d y répondre.',
                    style: VfTextStyles.tSm,
                  ),
                ),
              ],
            ),
          )
        else
          VfCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: <Widget>[
                VfTextArea(
                  label: 'Votre réponse',
                  controller: _reponse,
                  value: _reponse.text,
                  maxLength: RepondreAUnSujet.longueurMaximale,
                  minLines: 4,
                  onChanged: (_) => setState(() {}),
                ),
                const SizedBox(height: VfSpacing.x10),
                VfPrimaryButton(
                  label: 'Publier ma réponse',
                  busy: _envoiEnCours,
                  onPressed: _envoiEnCours ? null : _repondre,
                ),
              ],
            ),
          ),
      ],
    );
  }
}

class _SqueletteDeSujet extends StatelessWidget {
  const _SqueletteDeSujet();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      children: const <Widget>[
        SqueletteDeCarte(lignes: 4),
        SizedBox(height: VfSpacing.stackSm),
        SqueletteDeCarte(),
      ],
    );
  }
}
