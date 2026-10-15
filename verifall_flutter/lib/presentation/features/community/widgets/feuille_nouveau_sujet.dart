import 'package:flutter/material.dart';

import '../../../../core/theme/vf_metrics.dart';
import '../../../../domain/usecases/community/publier_un_sujet.dart';
import '../../../shared/shared.dart';

/// Ce que la feuille « Nouveau sujet » renvoie quand on publie.
@immutable
class BrouillonDeSujet {
  const BrouillonDeSujet({required this.titre, required this.corps});

  final String titre;
  final String corps;
}

/// La feuille inférieure « Nouveau sujet ».
///
/// Deux champs, exactement ceux du simulateur : **Titre de votre sujet** (8
/// caractères minimum) et **Décrivez ce qui vous semble douteux…**, puis un
/// bouton **Publier**.
///
/// # Ce qui n'y est pas, et pourquoi
///
/// Le contrat accepte deux champs de plus à la publication — `category` (slug de
/// rubrique) et `lien_signale` (l'URL qu'on signale). **Le simulateur ne les
/// montre pas**, et « le simulateur l'emporte sur l'écran ». Les ajouter serait
/// inventer deux champs de formulaire ; le dépôt les expose, l'écran ne les
/// remplit pas. La réserve est consignée dans `docs/ARBITRAGES.md`.
class FeuilleNouveauSujet extends StatefulWidget {
  const FeuilleNouveauSujet({super.key});

  /// Ouvre la feuille et rend le brouillon, ou `null` si elle est refermée.
  static Future<BrouillonDeSujet?> ouvrir(BuildContext context) {
    return VfBottomSheet.show<BrouillonDeSujet>(
      context: context,
      title: 'Nouveau sujet',
      builder: (BuildContext _) => const FeuilleNouveauSujet(),
    );
  }

  @override
  State<FeuilleNouveauSujet> createState() => _FeuilleNouveauSujetState();
}

class _FeuilleNouveauSujetState extends State<FeuilleNouveauSujet> {
  final TextEditingController _titre = TextEditingController();
  final TextEditingController _corps = TextEditingController();
  String? _erreurTitre;

  @override
  void dispose() {
    _titre.dispose();
    _corps.dispose();
    super.dispose();
  }

  void _publier() {
    // La SEULE validation locale : le minimum que la maquette annonce. Tout le
    // reste arrive en « 422 validation » avec `details`, et se rend sous le
    // champ concerne — deux jeux de regles finiraient par diverger.
    final String? erreur = PublierUnSujet.valider(_titre.text);
    if (erreur != null) {
      setState(() => _erreurTitre = erreur);
      VfToast.error(context, erreur);
      return;
    }
    Navigator.of(context).pop(
      BrouillonDeSujet(titre: _titre.text.trim(), corps: _corps.text.trim()),
    );
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
        children: <Widget>[
          VfTextField(
            label: 'Titre de votre sujet',
            controller: _titre,
            maxLength: PublierUnSujet.longueurMaximaleTitre,
            errorText: _erreurTitre,
            textInputAction: TextInputAction.next,
            onChanged: (_) {
              if (_erreurTitre != null) {
                setState(() => _erreurTitre = null);
              }
            },
          ),
          const SizedBox(height: VfSpacing.x10),
          VfTextArea(
            label: 'Décrivez ce qui vous semble douteux',
            controller: _corps,
            value: _corps.text,
            maxLength: PublierUnSujet.longueurMaximaleCorps,
            onChanged: (_) => setState(() {}),
          ),
          const SizedBox(height: VfSpacing.x12),
          VfPrimaryButton(label: 'Publier', onPressed: _publier),
        ],
      ),
    );
  }
}
