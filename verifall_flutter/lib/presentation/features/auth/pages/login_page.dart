import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_theme.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/usecases/auth/regles_de_saisie.dart';
import '../../../shared/shared.dart';
import '../providers/etat_auth.dart';
import '../providers/login_controller.dart';
import '../widgets/auth_chrome.dart';

/// Écran 02 — connexion.
///
/// # Ce que le contrat a changé par rapport au simulateur
///
/// Le simulateur enchaîne sur l'écran de code avec `purpose: login`. **Le
/// contrat rend un jeton directement** : il n'existe aucune vérification en deux
/// étapes obligatoire à la connexion (réserve A.10, arbitrage n° 84). Réussir sa
/// connexion ouvre donc la session, et la garde de route pose le tableau de
/// bord.
///
/// # La case « Rester connecté » n'est pas ici, et c'est délibéré
///
/// Le simulateur en pose une, cochée par défaut. Le contrat n'a **aucun**
/// paramètre correspondant : le jeton dure trente jours de façon **glissante**,
/// prolongée par le serveur à chaque appel — c'est-à-dire que « rester
/// connecté » est déjà le comportement, pour tout le monde, sans option. Une
/// case qui ne pilote rien est un mensonge à l'écran ; elle est retirée et
/// l'écart est consigné (arbitrage n° 86).
class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key});

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

class _LoginPageState extends ConsumerState<LoginPage> {
  final TextEditingController _email = TextEditingController();
  final TextEditingController _motDePasse = TextEditingController();

  @override
  void dispose() {
    _email.dispose();
    _motDePasse.dispose();
    super.dispose();
  }

  Future<void> _soumettre() async {
    // La seule validation locale de cet ecran : la forme de l'adresse. Le mot de
    // passe n'est PAS valide ici — sur un ecran de connexion, il a ete choisi
    // sous une politique qui a pu changer depuis, et refuser localement un mot
    // de passe correct empecherait quelqu'un d'entrer chez lui.
    if (!ReglesDeSaisie.emailValide(_email.text)) {
      VfToast.error(context, 'Entrez une adresse e-mail valide.');
      return;
    }

    final bool ouverte = await ref
        .read(loginControllerProvider.notifier)
        .connexion(email: _email.text, motDePasse: _motDePasse.text);

    if (!mounted) {
      return;
    }
    if (ouverte) {
      // La garde de route renvoie deja un connecte hors des ecrans
      // d'authentification ; on pose la destination explicitement pour que le
      // parcours d'authentification ne reste pas dans la pile.
      context.go(VfRoutes.dashboard);
      return;
    }

    final EtatAuth etat = ref.read(loginControllerProvider);
    if (etat.aUneErreurGlobale) {
      // Le message du serveur est deja redige en francais : on l'affiche TEL
      // QUEL. Sur un verrouillage, il contient deja le delai.
      VfToast.error(context, etat.messageGlobal!);
    }
  }

  @override
  Widget build(BuildContext context) {
    final EtatAuth etat = ref.watch(loginControllerProvider);
    final bool verrouille = etat.envoiEnCours;

    return AnnotatedRegion<SystemUiOverlayStyle>(
      // Le fond foret impose une barre d'etat claire.
      value: VfTheme.statusBarOnDark,
      child: Scaffold(
        backgroundColor: VfColors.forest,
        // Le clavier ne doit pas rogner la carte : on laisse la vue defiler.
        resizeToAvoidBottomInset: true,
        body: SafeArea(
          child: LayoutBuilder(
            builder: (BuildContext context, BoxConstraints contraintes) {
              return SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(22, 0, 22, 20),
                child: ConstrainedBox(
                  constraints: BoxConstraints(
                    minHeight: contraintes.maxHeight - 20,
                  ),
                  // POURQUOI `IntrinsicHeight` : la colonne porte un `Expanded`
                  // — c'est lui qui pousse l'en-tete au centre vertical, comme
                  // le `flex:1` du simulateur — et un `Expanded` exige une
                  // hauteur bornee, ce qu'une vue defilante ne donne pas.
                  child: IntrinsicHeight(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: <Widget>[
                        // L'en-tete est pousse au centre vertical, comme dans le
                        // simulateur (flex:1 + justify-content:center).
                        const Expanded(
                          child: Center(
                            child: AuthBrandHeader(
                              titre: 'Content de vous revoir.',
                              phrase:
                                  'Connectez-vous pour vérifier, apprendre et '
                                  'alerter votre communauté.',
                            ),
                          ),
                        ),
                        _CarteConnexion(
                          email: _email,
                          motDePasse: _motDePasse,
                          etat: etat,
                          verrouille: verrouille,
                          onSoumettre: _soumettre,
                        ),
                        const SizedBox(height: VfSpacing.x16),
                        Text(
                          'En continuant, vous acceptez la politique de '
                          'confidentialité.\nConsentements RGPD recueillis à la '
                          'création du compte.',
                          textAlign: TextAlign.center,
                          style: VfTextStyles.tXs.copyWith(
                            height: 1.6,
                            color: VfColors.surface.withValues(alpha: 0.5),
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }
}

class _CarteConnexion extends StatelessWidget {
  const _CarteConnexion({
    required this.email,
    required this.motDePasse,
    required this.etat,
    required this.verrouille,
    required this.onSoumettre,
  });

  final TextEditingController email;
  final TextEditingController motDePasse;
  final EtatAuth etat;
  final bool verrouille;
  final Future<void> Function() onSoumettre;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      padding: const EdgeInsets.all(18),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          VfTextField(
            label: 'Adresse e-mail',
            controller: email,
            enabled: !verrouille,
            keyboardType: TextInputType.emailAddress,
            autofillHints: const <String>[AutofillHints.username],
            textInputAction: TextInputAction.next,
            errorText: etat.erreurDe('email'),
          ),
          const SizedBox(height: VfSpacing.stack),
          VfTextField(
            label: 'Mot de passe',
            controller: motDePasse,
            enabled: !verrouille,
            obscureText: true,
            autofillHints: const <String>[AutofillHints.password],
            textInputAction: TextInputAction.done,
            errorText: etat.erreurDe('mot_de_passe'),
          ),
          const SizedBox(height: VfSpacing.stack),
          Align(
            alignment: Alignment.centerRight,
            child: AuthLinkButton(
              label: 'Mot de passe oublié ?',
              onPressed: verrouille
                  ? null
                  : () => context.push(VfRoutes.forgotPassword),
            ),
          ),
          const SizedBox(height: VfSpacing.x8),
          VfPrimaryButton(
            label: 'Se connecter',
            busy: verrouille,
            onPressed: verrouille ? null : () => onSoumettre(),
          ),
          const SizedBox(height: VfSpacing.stack),
          AuthFooterLine(
            question: 'Pas encore de compte ?',
            label: 'Créer un compte citoyen',
            onPressed: () => context.push(VfRoutes.register),
          ),
        ],
      ),
    );
  }
}
