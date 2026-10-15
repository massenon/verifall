import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/usecases/auth/regles_de_saisie.dart';
import '../../../shared/shared.dart';
import '../providers/etat_auth.dart';
import '../providers/password_reset_provider.dart';

/// Ce que l'écran 06 reçoit de l'écran 05.
///
/// # Pourquoi le code ne voyage pas dans l'URL
///
/// Un code à usage unique dans un chemin interne finit dans un lien profond, une
/// capture d'écran ou un journal de navigation. Il voyage donc par `extra`, qui
/// **ne survit pas** à un lien profond — et c'est exactement ce qu'on veut :
/// arriver ici sans être passé par la demande de code n'a aucun sens, et l'écran
/// renvoie alors vers l'écran 04.
@immutable
class NouveauMotDePasseArguments {
  const NouveauMotDePasseArguments({required this.email, required this.code});

  final String email;
  final String code;

  /// POURQUOI rien n'est repris : cette classe porte un code à usage unique.
  @override
  String toString() => 'NouveauMotDePasseArguments(<masqués>)';
}

/// Écran 06 — nouveau mot de passe.
///
/// # Ce que cet appel fait vraiment
///
/// `POST /auth/password/reset` révoque **tous** les jetons d'API, **toutes** les
/// sessions web et **tous** les cookies « se souvenir de moi ». L'arbitrage
/// n° 69 précise que l'inclusion des jetons d'API est volontaire : « un téléphone
/// volé garde son jeton indéfiniment ; ne fermer que les sessions du navigateur
/// laissait ouverte la porte la plus difficile à refermer ».
///
/// Le client purge donc son stockage sécurisé et son cache local **avant**
/// d'installer le jeton neuf — `OuvrirSession` s'en charge, exactement comme à
/// la déconnexion.
///
/// # L'étiquette annonce 12
///
/// Le simulateur écrit « 8 caractères minimum ». La politique réelle est 12
/// (arbitrage n° 10) et le serveur refusera en dessous. Le client ne vérifie que
/// **la longueur** et l'égalité des deux champs ; la casse, le chiffre, le
/// symbole et les fuites connues sont serveur.
class ResetPasswordPage extends ConsumerStatefulWidget {
  const ResetPasswordPage({this.arguments, super.key});

  final NouveauMotDePasseArguments? arguments;

  @override
  ConsumerState<ResetPasswordPage> createState() => _ResetPasswordPageState();
}

class _ResetPasswordPageState extends ConsumerState<ResetPasswordPage> {
  final TextEditingController _motDePasse = TextEditingController();
  final TextEditingController _confirmation = TextEditingController();

  @override
  void dispose() {
    _motDePasse.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  Future<void> _enregistrer() async {
    final NouveauMotDePasseArguments? arguments = widget.arguments;
    if (arguments == null) {
      return;
    }
    if (!ReglesDeSaisie.motDePasseAssezLong(_motDePasse.text)) {
      VfToast.error(
        context,
        '${ReglesDeSaisie.longueurMotDePasse} caractères minimum.',
      );
      return;
    }
    if (_motDePasse.text != _confirmation.text) {
      VfToast.error(context, 'Les deux mots de passe ne correspondent pas.');
      return;
    }

    final bool ouverte = await ref
        .read(passwordResetProvider.notifier)
        .reinitialiser(
          email: arguments.email,
          code: arguments.code,
          motDePasse: _motDePasse.text,
        );

    if (!mounted) {
      return;
    }
    if (ouverte) {
      // Le toast d'abord : `ScaffoldMessenger` survit au changement d'ecran,
      // le contexte de cette page non.
      VfToast.show(
        context,
        message: 'Mot de passe modifié — autres sessions déconnectées.',
      );
      context.go(VfRoutes.dashboard);
      return;
    }

    final EtatAuth etat = ref.read(passwordResetProvider);
    // Un code faux ou expire revient en `422 validation` sur le champ `code` —
    // qui n'est PAS a l'ecran. Il se dit donc en toast, avec le message du
    // serveur, qui distingue lui-meme « incorrect » et « expire ».
    final String? messageDuCode = etat.erreurDe('code');
    if (messageDuCode != null) {
      VfToast.error(context, messageDuCode);
      return;
    }
    if (etat.aUneErreurGlobale) {
      VfToast.error(context, etat.messageGlobal!);
    }
  }

  @override
  Widget build(BuildContext context) {
    final NouveauMotDePasseArguments? arguments = widget.arguments;

    // Arrive ici sans code — lien profond, reprise d'une pile perimee. On ne
    // montre pas un formulaire qui ne peut pas aboutir : on renvoie a la
    // demande de code.
    if (arguments == null) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          context.go(VfRoutes.forgotPassword);
        }
      });
      return const Scaffold(backgroundColor: VfColors.canvas);
    }

    final EtatAuth etat = ref.watch(passwordResetProvider);
    final bool verrouille = etat.envoiEnCours;

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: const VfAppBar(title: 'Nouveau mot de passe'),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          VfSpacing.screenInline,
          VfSpacing.screenTopExtra,
          VfSpacing.screenInline,
          VfSpacing.screenBottomExtra,
        ),
        children: <Widget>[
          Text('Choisissez un nouveau mot de passe', style: VfTextStyles.hLg),
          const SizedBox(height: VfSpacing.stackSm),
          Text(
            '${ReglesDeSaisie.longueurMotDePasse} caractères minimum. Évitez '
            'un mot de passe déjà utilisé ailleurs.',
            style: VfTextStyles.tSm.copyWith(color: VfColors.slate2),
          ),
          const SizedBox(height: VfSpacing.stack),
          VfTextField(
            label: 'Nouveau mot de passe',
            controller: _motDePasse,
            enabled: !verrouille,
            obscureText: true,
            autofillHints: const <String>[AutofillHints.newPassword],
            textInputAction: TextInputAction.next,
            errorText: etat.erreurDe('mot_de_passe'),
          ),
          const SizedBox(height: VfSpacing.stack),
          VfTextField(
            label: 'Confirmer le mot de passe',
            controller: _confirmation,
            enabled: !verrouille,
            obscureText: true,
            autofillHints: const <String>[AutofillHints.newPassword],
            textInputAction: TextInputAction.done,
          ),
          const SizedBox(height: VfSpacing.stack),
          VfPrimaryButton(
            label: 'Enregistrer et se connecter',
            busy: verrouille,
            onPressed: verrouille ? null : _enregistrer,
          ),
          const SizedBox(height: VfSpacing.stack),
          const AuthInfoCardSessions(),
        ],
      ),
    );
  }
}

/// L'encart orange de l'écran 06.
///
/// POURQUOI il dit « toutes vos autres sessions » et pas « toutes vos sessions » :
/// celle de ce téléphone se rouvre immédiatement avec le jeton que la réponse
/// rend. L'écran doit dire la vérité sur ce que fait le bouton.
class AuthInfoCardSessions extends StatelessWidget {
  const AuthInfoCardSessions({super.key});

  @override
  Widget build(BuildContext context) => VfCard(
    tight: true,
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        const ExcludeSemantics(
          child: Icon(
            Icons.devices_rounded,
            size: 19,
            color: VfColors.verdictInvestigate,
          ),
        ),
        const SizedBox(width: VfSpacing.x9),
        Expanded(
          child: Text(
            'Par sécurité, toutes vos autres sessions seront déconnectées.',
            style: VfTextStyles.tXs.copyWith(color: VfColors.slate2),
          ),
        ),
      ],
    ),
  );
}
