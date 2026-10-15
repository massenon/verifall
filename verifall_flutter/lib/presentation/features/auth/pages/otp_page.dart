import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/auth_otp_purpose.dart';
import '../../../shared/shared.dart';
import '../providers/otp_controller.dart';
import '../widgets/auth_chrome.dart';
import 'reset_password_page.dart';

/// Écran 05 — le code à six chiffres.
///
/// # Un seul écran, deux usages
///
/// `signup` active un compte neuf ; `reset` ouvre le choix d'un nouveau mot de
/// passe. Le troisième usage du simulateur — `login` — n'existe pas : le contrat
/// ne demande aucun second facteur à la connexion (réserve A.10, arbitrage
/// n° 84).
///
/// # Aucun compte à rebours, et c'est la même raison qu'à l'écran 04
///
/// Les routes de code répondent **toujours pareil, même plafond saturé**. Il n'y
/// a donc aucun délai à lire, et un délai calculé sur l'horloge du téléphone
/// mentirait. « Renvoyer » reste disponible en permanence.
///
/// # Le compteur de tentatives n'est pas affiché
///
/// Le simulateur le décrit ; **le serveur ne le sert pas**. Il rend trois
/// messages distincts — code incorrect, code expiré, trop de tentatives — et
/// l'écran les affiche tels quels. Fabriquer « il vous reste 3 essais » à partir
/// d'un compteur local reviendrait à afficher un chiffre qu'on n'a pas.
class OtpPage extends ConsumerStatefulWidget {
  const OtpPage({required this.purpose, required this.email, super.key});

  final AuthOtpPurpose purpose;

  /// L'adresse destinataire, rappelée à l'écran. Elle vient du chemin.
  final String email;

  @override
  ConsumerState<OtpPage> createState() => _OtpPageState();
}

class _OtpPageState extends ConsumerState<OtpPage> {
  Future<void> _confirmer() async {
    final bool ouvert = await ref
        .read(otpControllerProvider.notifier)
        .confirmer(widget.email);

    if (!mounted || !ouvert) {
      return;
    }

    switch (widget.purpose) {
      case AuthOtpPurpose.signup:
        // Les confettis sont poses dans la superposition RACINE avant de
        // naviguer : ils survivent au changement d'ecran et se retirent seuls.
        // POURQUOI ici et pas sur le tableau de bord : la celebration appartient
        // au geste qui vient d'aboutir, et le tableau de bord appartient a un
        // autre parcours — lui demander d'afficher des confettis pour un
        // evenement qu'il n'a pas vu serait un couplage entre deux parcours.
        _feterLaCreationDuCompte(context);
        // Le toast est pose AVANT la navigation : `ScaffoldMessenger` vit
        // au-dessus du navigateur, donc le message survit au changement
        // d'ecran — mais le contexte de CETTE page, lui, ne survit pas.
        //
        // Les 10 unites de bienvenue sont creditees PAR LE SERVEUR ; le client
        // lit `data.utilisateur.unites`, il ne l'additionne pas. Le toast et les
        // confettis ne font que s'en faire l'echo.
        VfToast.show(
          context,
          message: 'Compte créé — 10 unités de bienvenue ajoutées.',
        );
        // Le tableau de bord est pose EN RACINE : l'utilisateur ne doit pas
        // pouvoir revenir en arriere dans le parcours d'authentification.
        context.go(VfRoutes.dashboard);
      case AuthOtpPurpose.reset:
        // Le code est verifie ; il faut maintenant choisir le mot de passe. Le
        // code voyage par `extra`, jamais par l'URL.
        context.pushReplacement(
          VfRoutes.resetPassword,
          extra: NouveauMotDePasseArguments(
            email: widget.email,
            code: ref.read(otpControllerProvider).code,
          ),
        );
    }
  }

  @override
  Widget build(BuildContext context) {
    final EtatOtp etat = ref.watch(otpControllerProvider);
    final bool verrouille = etat.formulaire.envoiEnCours;

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: const VfAppBar(title: 'Vérification'),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          VfSpacing.screenInline,
          VfSpacing.screenTopExtra,
          VfSpacing.screenInline,
          VfSpacing.screenBottomExtra,
        ),
        children: <Widget>[
          const AuthIconBadge(icon: Icons.mark_email_read_rounded),
          const SizedBox(height: VfSpacing.stack),
          Text(widget.purpose.titre, style: VfTextStyles.hLg),
          const SizedBox(height: VfSpacing.stackSm),
          Text(
            widget.purpose.phrase,
            style: VfTextStyles.tSm.copyWith(color: VfColors.slate2),
          ),
          const SizedBox(height: VfSpacing.stack),
          _RappelAdresse(email: widget.email),
          const SizedBox(height: VfSpacing.x6),
          VfOtpField(
            hasError: etat.enErreur,
            onChanged: ref.read(otpControllerProvider.notifier).majCode,
            // Le collage d'un code complet remplit les six cases ET declenche la
            // verification : c'est le geste réel de quelqu'un qui revient de sa
            // boite mail.
            onCompleted: (String code) {
              ref.read(otpControllerProvider.notifier).majCode(code);
            },
          ),
          if (etat.messageDuCode != null) ...<Widget>[
            const SizedBox(height: VfSpacing.stackSm),
            Text(
              // Le message vient du SERVEUR : il distingue lui-meme « code
              // incorrect », « code expire » et « trop de tentatives ».
              etat.messageDuCode!,
              style: VfTextStyles.tSm.copyWith(color: VfColors.brandRed),
            ),
          ],
          const SizedBox(height: VfSpacing.stack),
          VfPrimaryButton(
            label: 'Confirmer',
            busy: verrouille,
            // Code incomplet : bouton inactif, mais toujours visible et lisible.
            onPressed: etat.complet && !verrouille ? _confirmer : null,
          ),
          const SizedBox(height: VfSpacing.stackSm),
          _LigneRenvoi(
            verrouille: verrouille,
            onRenvoyer: () async {
              await ref
                  .read(otpControllerProvider.notifier)
                  .renvoyer(widget.email);
              if (!context.mounted) {
                return;
              }
              final EtatOtp apres = ref.read(otpControllerProvider);
              VfToast.show(
                context,
                message: apres.formulaire.aUneErreurGlobale
                    ? apres.formulaire.messageGlobal!
                    : 'Nouveau code envoyé — vérifiez vos indésirables.',
                variant: apres.formulaire.aUneErreurGlobale
                    ? VfToastVariant.erreur
                    : VfToastVariant.neutre,
              );
            },
          ),
          const SizedBox(height: VfSpacing.stack),
          const AuthInfoCard(
            icon: Icons.shield_rounded,
            accent: VfColors.primaryDark,
            surFondVert: true,
            texte:
                'Vérif’All ne vous demandera jamais ce code par message, par '
                'téléphone ou sur le forum.',
          ),
        ],
      ),
    );
  }
}

/// Pose les confettis dans la superposition racine, et les retire tout seul.
///
/// POURQUOI un `OverlayEntry` et non un widget dans l'arbre de la page : la page
/// est démontée dans la milliseconde qui suit — on navigue. Un widget de la page
/// disparaîtrait avec elle.
///
/// `VfConfetti` se coupe déjà sous `disableAnimations` : « une pluie de
/// confettis chez quelqu'un qui a demandé à couper les animations n'est pas une
/// célébration ».
void _feterLaCreationDuCompte(BuildContext context) {
  final OverlayState? superposition = Overlay.maybeOf(
    context,
    rootOverlay: true,
  );
  if (superposition == null) {
    return;
  }
  late final OverlayEntry entree;
  entree = OverlayEntry(
    builder: (BuildContext context) => const IgnorePointer(child: VfConfetti()),
  );
  superposition.insert(entree);
  // La piece la plus lente met 2,7 s ; on retire un peu apres.
  //
  // POURQUOI le garde `mounted` : l'application peut avoir ete quittee entre
  // temps, et retirer une entree deja retiree leve. Une celebration ne doit
  // jamais faire tomber l'application.
  Future<void>.delayed(const Duration(seconds: 3), () {
    if (entree.mounted) {
      entree.remove();
    }
  });
}

class _RappelAdresse extends StatelessWidget {
  const _RappelAdresse({required this.email});

  final String email;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        const ExcludeSemantics(
          child: Icon(
            Icons.alternate_email_rounded,
            size: 17,
            color: VfColors.primary,
          ),
        ),
        const SizedBox(width: VfSpacing.x6),
        Expanded(
          child: Text(
            email,
            style: VfTextStyles.tSm.copyWith(
              color: VfColors.ink,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ],
    );
  }
}

class _LigneRenvoi extends StatelessWidget {
  const _LigneRenvoi({required this.verrouille, required this.onRenvoyer});

  final bool verrouille;
  final VoidCallback onRenvoyer;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: <Widget>[
        Flexible(
          child: Text(
            'Pas reçu ? Vérifiez vos indésirables ·',
            style: VfTextStyles.tXs.copyWith(color: VfColors.slate2),
          ),
        ),
        // Le bouton reste actif meme apres un renvoi : aucun delai n'est
        // lisible, donc aucun delai n'est impose. Le plafond reel est cote
        // serveur, et il est muet.
        AuthLinkButton(
          label: 'Renvoyer',
          onPressed: verrouille ? null : onRenvoyer,
        ),
      ],
    );
  }
}
