import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/routes_auth.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/auth_otp_purpose.dart';
import '../../../../domain/usecases/auth/regles_de_saisie.dart';
import '../../../shared/shared.dart';
import '../providers/etat_auth.dart';
import '../providers/password_reset_provider.dart';
import '../widgets/auth_chrome.dart';

/// Écran 04 — mot de passe oublié.
///
/// # L'écran le plus contre-intuitif des six
///
/// Il ne dit **jamais** si l'adresse existe. Il ne dit jamais non plus que le
/// plafond est atteint. Le contrat va plus loin que le simulateur :
/// `otp/request` et `password/forgot` « répondent toujours pareil, que le compte
/// existe ou non, **et même quand le plafond est saturé** » — un 429 visible
/// dirait « cette adresse intéresse quelqu'un ».
///
/// Trois conséquences, toutes visibles dans le code ci-dessous :
///
/// 1. le message affiché est **toujours le même**, écrit ici et non reçu ;
/// 2. le temps de réponse est égalisé par un plancher, côté contrôleur : « une
///    différence de latence énumère les comptes aussi sûrement qu'un message » ;
/// 3. il n'y a **aucun compte à rebours** sur cet écran, ni sur le suivant.
///
/// Le seul échec affiché est celui de la **forme** de l'adresse, et l'absence de
/// réseau.
class ForgotPasswordPage extends ConsumerStatefulWidget {
  const ForgotPasswordPage({super.key});

  /// Le message unique, **écrit par l'écran**.
  ///
  /// POURQUOI il n'est pas lu de la réponse : le brancher sur ce que le serveur
  /// rend inviterait un jour quelqu'un à le faire varier. Ici, il ne peut pas
  /// varier.
  static const String messageIndifferencie =
      'Si un compte existe, un code vient d’être envoyé.';

  @override
  ConsumerState<ForgotPasswordPage> createState() => _ForgotPasswordPageState();
}

class _ForgotPasswordPageState extends ConsumerState<ForgotPasswordPage> {
  final TextEditingController _email = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  Future<void> _envoyer() async {
    if (!ReglesDeSaisie.emailValide(_email.text)) {
      VfToast.error(context, 'Entrez une adresse e-mail valide.');
      return;
    }
    final String adresse = _email.text.trim();
    final bool poursuivre = await ref
        .read(passwordResetProvider.notifier)
        .demanderUnCode(adresse);

    if (!mounted) {
      return;
    }
    if (!poursuivre) {
      final String? message = ref.read(passwordResetProvider).messageGlobal;
      if (message != null) {
        VfToast.error(context, message);
      }
      return;
    }

    VfToast.show(context, message: ForgotPasswordPage.messageIndifferencie);
    context.push(cheminOtp(purpose: AuthOtpPurpose.reset, email: adresse));
  }

  @override
  Widget build(BuildContext context) {
    final EtatAuth etat = ref.watch(passwordResetProvider);
    final bool verrouille = etat.envoiEnCours;

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: const VfAppBar(title: 'Mot de passe oublié'),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          VfSpacing.screenInline,
          VfSpacing.screenTopExtra,
          VfSpacing.screenInline,
          VfSpacing.screenBottomExtra,
        ),
        children: <Widget>[
          const AuthIconBadge(icon: Icons.lock_reset_rounded),
          const SizedBox(height: VfSpacing.stack),
          Text('Réinitialiser mon mot de passe', style: VfTextStyles.hLg),
          const SizedBox(height: VfSpacing.stackSm),
          Text(
            'Indiquez l’adresse e-mail de votre compte. Nous vous envoyons un '
            'code à 6 chiffres pour prouver que c’est bien vous.',
            style: VfTextStyles.tSm.copyWith(color: VfColors.slate2),
          ),
          const SizedBox(height: VfSpacing.stack),
          VfTextField(
            label: 'Adresse e-mail',
            controller: _email,
            enabled: !verrouille,
            keyboardType: TextInputType.emailAddress,
            autofillHints: const <String>[AutofillHints.email],
            textInputAction: TextInputAction.done,
            errorText: etat.erreurDe('email'),
          ),
          const SizedBox(height: VfSpacing.stack),
          VfPrimaryButton(
            label: 'Envoyer le code',
            busy: verrouille,
            onPressed: verrouille ? null : _envoyer,
          ),
          const SizedBox(height: VfSpacing.stack),
          const AuthInfoCard(
            icon: Icons.info_rounded,
            texte:
                'Par sécurité, la réponse est toujours la même, que l’adresse '
                'existe ou non : cela évite de révéler qui possède un compte.',
          ),
          const SizedBox(height: VfSpacing.stack),
          VfGhostButton(
            label: 'Retour à la connexion',
            onPressed: () => context.go(VfRoutes.login),
          ),
        ],
      ),
    );
  }
}
