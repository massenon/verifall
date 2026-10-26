import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/auth_otp_purpose.dart';
import '../../../../domain/usecases/auth/regles_de_saisie.dart';
import '../../../shared/shared.dart';
import '../../../../core/router/routes_auth.dart';
import '../providers/signup_form_provider.dart';
import '../widgets/auth_chrome.dart';

/// Écran 03 — créer un compte citoyen.
///
/// # Le formulaire ne correspond plus exactement à la maquette, et c'est décidé
///
/// Le simulateur a « Nom et prénom » en un champ, un champ « Ville » et pas de
/// profession. `POST /auth/register` attend `prenom` **et** `nom` séparés, exige
/// `profession` et `telephone`, et **n'accepte pas `ville`** — elle se règle
/// ensuite par `PATCH /me`.
///
/// Réserve A.11, tranchée par l'arbitrage n° 85 : le contrat l'emporte, parce
/// qu'un champ que le serveur ignore est une saisie perdue en silence. La
/// disposition reste celle de la maquette — « Profession » prend la place de
/// « Ville », « Prénom » et « Nom » celle de « Nom et prénom ».
///
/// # Les consentements sont la seule addition structurelle au prototype
///
/// Trois cases **granulaires, jamais pré-cochées**, horodatées séparément
/// (RGPD art. 7). L'arbitrage n° 3 dit pourquoi : la conformité l'emporte sur la
/// fidélité visuelle, sur ce point et seulement celui-là.
class SignupPage extends ConsumerWidget {
  const SignupPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final EtatInscription etat = ref.watch(signupFormProvider);
    final SignupFormController controleur = ref.read(
      signupFormProvider.notifier,
    );
    final bool verrouille = etat.formulaire.envoiEnCours;

    Future<void> soumettre() async {
      final ResultatInscription resultat = await controleur.soumettre();
      if (!context.mounted) {
        return;
      }
      switch (resultat) {
        case InscriptionAConfirmer(:final inscription):
          // `register` NE REND PAS de jeton : le compte existe mais n'est pas
          // active. Le parcours enchaine obligatoirement sur la saisie du code.
          context.go(
            cheminOtp(purpose: AuthOtpPurpose.signup, email: inscription.email),
          );
        case InscriptionRefusee():
          // L'état est RELU après l'appel : celui capture par `build` est celui
          // d'avant l'envoi, et il ne porte pas encore l'echec.
          final String? message = ref
              .read(signupFormProvider)
              .formulaire
              .messageGlobal;
          if (message != null && message.trim().isNotEmpty) {
            // Un toast rouge qui NOMME ce qui manque — jamais « formulaire
            // invalide ». Les erreurs par champ, elles, se rendent sous le
            // champ concerne et ne passent pas par ici.
            VfToast.error(context, message);
          }
      }
    }

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: const VfAppBar(title: 'Créer un compte'),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(
          VfSpacing.screenInline,
          VfSpacing.screenTopExtra,
          VfSpacing.screenInline,
          VfSpacing.screenBottomExtra,
        ),
        children: <Widget>[
          Text('Rejoignez la boussole', style: VfTextStyles.hLg),
          const SizedBox(height: VfSpacing.x6),
          Text(
            'Un compte citoyen gratuit, 10 unités de vérification offertes à '
            'l’inscription.',
            style: VfTextStyles.tSm.copyWith(color: VfColors.slate2),
          ),
          const SizedBox(height: VfSpacing.stack),
          _CarteChamps(
            etat: etat,
            controleur: controleur,
            verrouille: verrouille,
          ),
          const SizedBox(height: VfSpacing.stack),
          _CarteConsentements(
            etat: etat,
            controleur: controleur,
            verrouille: verrouille,
          ),
          const SizedBox(height: VfSpacing.stack),
          VfPrimaryButton(
            label: 'Créer mon compte',
            busy: verrouille,
            onPressed: verrouille ? null : soumettre,
          ),
          const SizedBox(height: VfSpacing.stackSm),
          AuthFooterLine(
            question: 'Déjà inscrit ?',
            label: 'Se connecter',
            onPressed: () => context.go(VfRoutes.login),
          ),
        ],
      ),
    );
  }
}

class _CarteChamps extends StatelessWidget {
  const _CarteChamps({
    required this.etat,
    required this.controleur,
    required this.verrouille,
  });

  final EtatInscription etat;
  final SignupFormController controleur;
  final bool verrouille;

  @override
  Widget build(BuildContext context) {
    // Les cles des erreurs sont les NOMS DES CHAMPS ENVOYES : c'est ce que le
    // contrat garantit pour `details`, et c'est ce qui permet de poser le
    // message sous le bon champ. Une adresse deja prise se dit sur le champ de
    // l'adresse, pas dans un toast.
    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          VfTextField(
            label: 'Prénom',
            initialValue: etat.prenom,
            enabled: !verrouille,
            onChanged: controleur.majPrenom,
            autofillHints: const <String>[AutofillHints.givenName],
            textInputAction: TextInputAction.next,
            errorText: etat.formulaire.erreurDe('prenom'),
          ),
          const SizedBox(height: VfSpacing.stack),
          VfTextField(
            label: 'Nom',
            initialValue: etat.nom,
            enabled: !verrouille,
            onChanged: controleur.majNom,
            autofillHints: const <String>[AutofillHints.familyName],
            textInputAction: TextInputAction.next,
            errorText: etat.formulaire.erreurDe('nom'),
          ),
          const SizedBox(height: VfSpacing.stack),
          VfTextField(
            label: 'Adresse e-mail',
            initialValue: etat.email,
            enabled: !verrouille,
            onChanged: controleur.majEmail,
            keyboardType: TextInputType.emailAddress,
            autofillHints: const <String>[AutofillHints.email],
            textInputAction: TextInputAction.next,
            errorText: etat.formulaire.erreurDe('email'),
          ),
          const SizedBox(height: VfSpacing.stack),
          VfTextField(
            // L'etiquette ne dit plus « facultatif » : le contrat exige le
            // telephone (`required`, `min:8`). Un formulaire ne doit pas
            // promettre moins que ce qu'il exigera.
            label: 'Téléphone',
            initialValue: etat.telephone,
            enabled: !verrouille,
            onChanged: controleur.majTelephone,
            keyboardType: TextInputType.phone,
            autofillHints: const <String>[AutofillHints.telephoneNumber],
            textInputAction: TextInputAction.next,
            errorText: etat.formulaire.erreurDe('telephone'),
          ),
          const SizedBox(height: VfSpacing.stack),
          VfTextField(
            label: 'Profession',
            initialValue: etat.profession,
            enabled: !verrouille,
            onChanged: controleur.majProfession,
            textInputAction: TextInputAction.next,
            errorText: etat.formulaire.erreurDe('profession'),
          ),
          const SizedBox(height: VfSpacing.stack),
          VfTextField(
            // 12, et pas 8 : la regle centralisee est 12 depuis l'arbitrage
            // n° 10. Le client ne verifie QUE la longueur ; la casse, le
            // chiffre, le symbole et les fuites connues sont serveur.
            label: ReglesDeSaisie.etiquetteMotDePasse,
            initialValue: etat.motDePasse,
            enabled: !verrouille,
            onChanged: controleur.majMotDePasse,
            obscureText: true,
            autofillHints: const <String>[AutofillHints.newPassword],
            textInputAction: TextInputAction.next,
            errorText: etat.formulaire.erreurDe('mot_de_passe'),
          ),
          const SizedBox(height: VfSpacing.stack),
          VfTextField(
            label: 'Code de parrainage (facultatif)',
            initialValue: etat.codeParrainage,
            enabled: !verrouille,
            onChanged: controleur.majCodeParrainage,
            textInputAction: TextInputAction.done,
            errorText: etat.formulaire.erreurDe('code_parrainage'),
          ),
        ],
      ),
    );
  }
}

class _CarteConsentements extends StatelessWidget {
  const _CarteConsentements({
    required this.etat,
    required this.controleur,
    required this.verrouille,
  });

  final EtatInscription etat;
  final SignupFormController controleur;
  final bool verrouille;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const VfEyebrow('Vos consentements'),
          const SizedBox(height: VfSpacing.stackSm),
          _Consentement(
            texte:
                'J’accepte les conditions d’utilisation et la politique de '
                'confidentialité.',
            obligatoire: true,
            valeur: etat.consentements.cgu,
            onChanged: verrouille
                ? null
                : (bool v) => controleur.majConsentement(cgu: v),
          ),
          _Consentement(
            texte:
                'J’autorise le traitement de mes contenus soumis aux seules '
                'fins de vérification.',
            obligatoire: true,
            valeur: etat.consentements.confidentialite,
            onChanged: verrouille
                ? null
                : (bool v) => controleur.majConsentement(confidentialite: v),
          ),
          _Consentement(
            texte:
                'Je souhaite recevoir le résumé hebdomadaire des vérifications '
                'par e-mail.',
            obligatoire: false,
            valeur: etat.consentements.newsletter,
            onChanged: verrouille
                ? null
                : (bool v) => controleur.majConsentement(newsletter: v),
          ),
          _Consentement(
            texte:
                'J'autorise l'envoi de rapports de diagnostic anonymes pour '
                'améliorer la stabilité de l'application.',
            obligatoire: false,
            valeur: etat.consentementTelemetrie,
            onChanged: verrouille
                ? null
                : (bool v) => controleur.majConsentement(telemetrie: v),
          ),
          const SizedBox(height: VfSpacing.x6),
          Text(
            'Chaque consentement est enregistré séparément, avec sa date. Vous '
            'pouvez les retirer à tout moment depuis Réglages.',
            style: VfTextStyles.tXs.copyWith(color: VfColors.slate2),
          ),
        ],
      ),
    );
  }
}

class _Consentement extends StatelessWidget {
  const _Consentement({
    required this.texte,
    required this.obligatoire,
    required this.valeur,
    required this.onChanged,
  });

  final String texte;
  final bool obligatoire;
  final bool valeur;
  final ValueChanged<bool>? onChanged;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      // Le texte COMPLET est le libelle — jamais « case 1 ». Et l'asterisque
      // rouge est double du mot « obligatoire » : la couleur seule ne porte
      // rien.
      label: obligatoire ? '$texte (obligatoire)' : '$texte (facultatif)',
      excludeSemantics: true,
      checked: valeur,
      child: VfConsentTile(
        value: valeur,
        required: obligatoire,
        onChanged: onChanged ?? (_) {},
        child: Text.rich(
          TextSpan(
            style: VfTextStyles.tXs.copyWith(
              height: 1.5,
              color: VfColors.slate2,
            ),
            children: <InlineSpan>[
              TextSpan(text: texte),
              if (obligatoire)
                const TextSpan(
                  text: ' *',
                  style: TextStyle(
                    color: VfColors.brandRed,
                    fontWeight: FontWeight.w700,
                  ),
                )
              else
                const TextSpan(text: ' (facultatif)'),
            ],
          ),
        ),
      ),
    );
  }
}
