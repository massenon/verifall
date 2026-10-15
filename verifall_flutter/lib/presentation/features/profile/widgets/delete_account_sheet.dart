import 'package:flutter/material.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/settings_reglages.dart';
import '../../../shared/shared.dart';

/// La feuille de suppression de compte — **en deux temps**.
///
/// # Ce que la maquette montrait, et ce que le contrat impose
///
/// Le simulateur demande de **taper son nom exact**, puis supprime. Le contrat
/// impose autre chose sur le même `DELETE /me` (réserve A.12) :
///
/// 1. le **mot de confirmation du contrat** — pas le nom de la personne — plus
///    le **mot de passe** ; le serveur répond `202` et envoie un code par
///    courriel. **Rien n'est supprimé** ;
/// 2. le même couple **plus le code** ; le serveur programme la suppression.
///
/// > « Mot de passe et code : le premier prouve qu'on connaît le compte, le
/// > second qu'on tient la boîte. Un téléphone volé déverrouillé passe le
/// > premier, jamais les deux. »
///
/// # Pourquoi la friction est assumée
///
/// « Effacer un compte est irréversible passé trente jours ; c'est le geste qui
/// mérite le plus de friction de toute l'application. »
class VfDeleteAccountSheet extends StatefulWidget {
  const VfDeleteAccountSheet({
    required this.onDemanderLeCode,
    required this.onConfirmer,
    super.key,
  });

  /// Premier temps. Rend l'étape atteinte, ou lève un `Failure`.
  final Future<SuppressionCompte> Function(
    String confirmation,
    String motDePasse,
  )
  onDemanderLeCode;

  /// Second temps.
  final Future<SuppressionCompte> Function(
    String confirmation,
    String motDePasse,
    String code,
  )
  onConfirmer;

  @override
  State<VfDeleteAccountSheet> createState() => _VfDeleteAccountSheetState();
}

class _VfDeleteAccountSheetState extends State<VfDeleteAccountSheet> {
  final TextEditingController _confirmation = TextEditingController();
  final TextEditingController _motDePasse = TextEditingController();
  final TextEditingController _code = TextEditingController();

  /// Vrai une fois le `202` reçu : le code est parti par courriel.
  bool _codeEnvoye = false;

  bool _enCours = false;
  Map<String, String> _erreurs = const <String, String>{};
  String? _messageGeneral;

  @override
  void dispose() {
    _confirmation.dispose();
    _motDePasse.dispose();
    _code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(
        VfSpacing.x16,
        0,
        VfSpacing.x16,
        VfSpacing.x22,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Text(
            _codeEnvoye
                ? 'Un code a six chiffres vient de partir vers votre adresse '
                      'e-mail. Saisissez-le pour confirmer.'
                : 'Cette action ferme votre compte. Vos donnees sont '
                      'conservees trente jours, puis anonymisees.',
            style: VfTextStyles.tSm,
          ),
          const SizedBox(height: VfSpacing.x14),
          if (!_codeEnvoye) ...<Widget>[
            VfTextField(
              label: 'Tapez $motDeConfirmationSuppression',
              controller: _confirmation,
              errorText: _erreurs['confirmation'],
              helperText: 'Le mot exact attendu par le serveur, en majuscules.',
            ),
            const SizedBox(height: VfSpacing.stackSm),
            VfTextField(
              label: 'Votre mot de passe',
              controller: _motDePasse,
              obscureText: true,
              errorText: _erreurs['mot_de_passe'],
            ),
          ] else
            VfTextField(
              label: 'Code recu par e-mail',
              controller: _code,
              keyboardType: TextInputType.number,
              errorText: _erreurs['code'],
            ),
          if (_messageGeneral != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x10),
            Text(
              _messageGeneral!,
              style: VfTextStyles.tSm.copyWith(color: VfColors.brandRed),
            ),
          ],
          const SizedBox(height: VfSpacing.x14),
          Row(
            children: <Widget>[
              Expanded(
                child: VfGhostButton(
                  label: 'Annuler',
                  expanded: true,
                  onPressed: _enCours
                      ? null
                      : () => Navigator.of(context).pop(),
                ),
              ),
              const SizedBox(width: VfSpacing.x9),
              Expanded(
                child: VfDangerButton(
                  label: _codeEnvoye ? 'Confirmer' : 'Continuer',
                  expanded: true,
                  busy: _enCours,
                  onPressed: _enCours ? null : _envoyer,
                ),
              ),
            ],
          ),
          const SizedBox(height: VfSpacing.x12),
          // Dit AVANT le geste, pas apres : quelqu'un qui hesite doit savoir
          // comment revenir en arriere, et l'application ne le permettra pas.
          Text(
            'Pour annuler, vous devrez utiliser le lien signe recu par '
            'courriel : l annulation ne passe pas par l application, car elle '
            'doit rester possible quand on ne peut plus se connecter.',
            style: VfTextStyles.tXs,
          ),
        ],
      ),
    );
  }

  Future<void> _envoyer() async {
    setState(() {
      _enCours = true;
      _erreurs = const <String, String>{};
      _messageGeneral = null;
    });

    try {
      if (!_codeEnvoye) {
        final SuppressionCompte etape = await widget.onDemanderLeCode(
          _confirmation.text,
          _motDePasse.text,
        );
        if (!mounted) {
          return;
        }
        // Le passage au second temps ne se declenche QUE sur l'etape rendue par
        // le serveur. Le deduire d'un code HTTP ou d'une absence d'erreur
        // ferait avancer l'ecran sur une réponse qu'on n'a pas lue.
        setState(() {
          _codeEnvoye = etape.etape == EtapeSuppression.codeEnvoye;
          _enCours = false;
        });
        return;
      }

      final SuppressionCompte resultat = await widget.onConfirmer(
        _confirmation.text,
        _motDePasse.text,
        _code.text,
      );
      if (mounted) {
        Navigator.of(context).pop(resultat);
      }
    } on ValidationFailure catch (echec) {
      _afficherLesErreurs(echec);
    } on Failure catch (echec) {
      if (mounted) {
        setState(() {
          _enCours = false;
          _messageGeneral =
              echec.serverMessage ?? 'La suppression n a pas pu aboutir.';
        });
      }
    }
  }

  void _afficherLesErreurs(ValidationFailure echec) {
    if (!mounted) {
      return;
    }
    setState(() {
      _enCours = false;
      // `details` est indexe par CHAMP ENVOYE : c'est la forme exacte du
      // contrat, et elle se rend telle quelle sous le champ concerne.
      _erreurs = <String, String>{
        for (final MapEntry<String, List<String>> e
            in echec.fieldErrors.entries)
          if (e.value.isNotEmpty) e.key: e.value.first,
      };
      _messageGeneral = _erreurs.isEmpty ? echec.serverMessage : null;
    });
  }
}
