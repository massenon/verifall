import 'package:flutter/material.dart';

import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/gamification_recompenses.dart';
import '../../../../domain/entities/profile_moi.dart';
import '../../../shared/shared.dart';

/// L'en-tête du profil : avatar de 72, nom, ligne « profession · ville », deux
/// pastilles.
///
/// # Ce que « rôle » veut dire ici
///
/// Le simulateur affiche « `<rôle>` · `<ville>` ». Le **rôle au sens RBAC n'est
/// jamais sérialisé par l'API** : « publier "untel est modérateur" sur chaque
/// message dresserait la liste nominative de l'équipe de modération » (§9 du
/// contrat). Ce que le prototype appelle rôle — « Journaliste », « Étudiante » —
/// est la **profession déclarée**, qui elle est servie par `MoiResource`. C'est
/// donc elle qu'on affiche, et la ligne disparaît si elle n'est pas renseignée
/// plutôt que d'inventer un intitulé.
class VfProfileHeader extends StatelessWidget {
  const VfProfileHeader({required this.moi, this.niveau, super.key});

  final Moi moi;

  /// Le palier vient de `GET /me/gamification`. Absent, les pastilles de niveau
  /// ne s'affichent pas : « 500 XP par niveau » est un calcul serveur, et le
  /// refaire ici afficherait un jour un niveau différent de celui de l'écran
  /// Récompenses.
  final NiveauCitoyen? niveau;

  @override
  Widget build(BuildContext context) {
    final String? sousTitre = _sousTitre();
    return VfCard(
      padding: const EdgeInsets.symmetric(
        horizontal: VfSpacing.x16,
        vertical: VfSpacing.x22,
      ),
      child: Column(
        children: <Widget>[
          // Les initiales sont DECORATIVES : le nom complet est lu juste a cote,
          // et les faire annoncer ferait entendre « A D Adderly Doe ».
          VfAvatar(
            initials: moi.initiales,
            size: VfAvatarSize.grand,
            prim: true,
          ),
          const SizedBox(height: VfSpacing.x12),
          Text(
            moi.nomAffichage,
            style: VfTextStyles.hMd,
            textAlign: TextAlign.center,
          ),
          if (sousTitre != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x4),
            Text(
              sousTitre,
              style: VfTextStyles.tSm,
              textAlign: TextAlign.center,
            ),
          ],
          if (niveau != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x10),
            Wrap(
              spacing: VfSpacing.x6,
              runSpacing: VfSpacing.x6,
              alignment: WrapAlignment.center,
              children: <Widget>[
                VfTag(
                  label: 'Niveau ${niveau!.rang} · ${niveau!.nom}',
                  variant: VfTagVariant.prim,
                ),
                VfTag(label: '${niveau!.xp} XP', variant: VfTagVariant.xp),
              ],
            ),
          ],
        ],
      ),
    );
  }

  String? _sousTitre() {
    final List<String> morceaux = <String>[
      if (moi.profession != null && moi.profession!.isNotEmpty) moi.profession!,
      if (moi.ville != null && moi.ville!.isNotEmpty) moi.ville!,
    ];
    return morceaux.isEmpty ? null : morceaux.join(' · ');
  }
}

/// Le formulaire « Informations personnelles ».
///
/// # Trois écarts à la maquette, et leur raison
///
/// 1. **« Nom affiché » devient « Prénom » et « Nom ».** `PATCH /me` accepte
///    `prenom` et `nom` séparément (réserve A.11). Découper une chaîne unique
///    sur l'espace pour deviner les deux donnerait un résultat faux au premier
///    nom composé — et c'est le nom de quelqu'un.
/// 2. **L'adresse e-mail reste affichée, mais en lecture seule.** Le bloc garde
///    sa place — le prototype fait foi sur la mise en page — et une mention
///    discrète dit pourquoi et où la changer. Aucun endpoint de changement
///    n'existe en v1 (arbitrage n° 6, réserve C.8).
/// 3. **Ni « Profession » ni « Téléphone ».** Les deux sont modifiables par le
///    contrat mais n'ont pas de champ dans la maquette ; en ajouter deux serait
///    une addition d'interface, pas une transposition. La réserve A.11 reste
///    ouverte sur ce point.
class VfProfileForm extends StatefulWidget {
  const VfProfileForm({
    required this.moi,
    required this.onEnregistrer,
    this.enCours = false,
    this.erreurs = const <String, String>{},
    super.key,
  });

  final Moi moi;

  /// Rend la modification, **avec les seuls champs modifiés**.
  final ValueChanged<ModificationProfil> onEnregistrer;

  final bool enCours;

  /// Les messages `422`, indexés par **le nom du champ envoyé** — c'est la forme
  /// exacte de `error.details`.
  final Map<String, String> erreurs;

  @override
  State<VfProfileForm> createState() => _VfProfileFormState();
}

class _VfProfileFormState extends State<VfProfileForm> {
  late final TextEditingController _prenom = TextEditingController(
    text: widget.moi.prenom,
  );
  late final TextEditingController _nom = TextEditingController(
    text: widget.moi.nom,
  );
  late final TextEditingController _ville = TextEditingController(
    text: widget.moi.ville ?? '',
  );
  late final TextEditingController _bio = TextEditingController(
    text: widget.moi.bio ?? '',
  );

  @override
  void dispose() {
    _prenom.dispose();
    _nom.dispose();
    _ville.dispose();
    _bio.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text('Informations personnelles', style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.x12),
          VfTextField(
            label: 'Prenom',
            controller: _prenom,
            errorText: widget.erreurs['prenom'],
            textInputAction: TextInputAction.next,
          ),
          const SizedBox(height: VfSpacing.stackSm),
          VfTextField(
            label: 'Nom',
            controller: _nom,
            errorText: widget.erreurs['nom'],
            textInputAction: TextInputAction.next,
          ),
          const SizedBox(height: VfSpacing.stackSm),
          // Le champ RESTE a sa place, desactive. Le retirer ferait chercher une
          // fonction qu'on croit perdue ; le laisser modifiable ferait echouer
          // un enregistrement que le contrat n'accepte pas.
          VfTextField(
            label: 'Adresse e-mail',
            controller: TextEditingController(text: widget.moi.email),
            enabled: false,
            helperText:
                'Elle sert a vous connecter et a recuperer votre compte. '
                'Sa modification se fait sur le site Vérif All, après '
                'vérification de la nouvelle adresse.',
          ),
          const SizedBox(height: VfSpacing.stackSm),
          VfTextField(
            label: 'Ville',
            controller: _ville,
            errorText: widget.erreurs['ville'],
            textInputAction: TextInputAction.next,
          ),
          const SizedBox(height: VfSpacing.stackSm),
          ListenableBuilder(
            listenable: _bio,
            builder: (BuildContext context, Widget? child) => VfTextArea(
              label: 'Bio',
              value: _bio.text,
              controller: _bio,
              onChanged: (_) {},
              // 280 et non 240 : c'est la borne du contrat (`bio` max:280).
              // Afficher 240 refuserait localement ce que le serveur accepte.
              maxLength: 280,
              minLines: 3,
              errorText: widget.erreurs['bio'],
            ),
          ),
          const SizedBox(height: VfSpacing.x12),
          VfPrimaryButton(
            label: 'Enregistrer',
            expanded: true,
            busy: widget.enCours,
            onPressed: widget.enCours ? null : _envoyer,
          ),
        ],
      ),
    );
  }

  /// N'envoie **que ce qui a changé**.
  ///
  /// POURQUOI : `PATCH` veut dire « ne change que ce que j'envoie ». Envoyer les
  /// quatre champs à chaque fois transformerait l'enregistrement en écrasement,
  /// et deux téléphones ouverts sur le même compte s'écraseraient l'un l'autre
  /// des valeurs qu'aucun des deux n'a touchées.
  void _envoyer() {
    final String villeAvant = widget.moi.ville ?? '';
    final String bioAvant = widget.moi.bio ?? '';
    widget.onEnregistrer(
      ModificationProfil(
        prenom: _prenom.text.trim() == widget.moi.prenom
            ? null
            : _prenom.text.trim(),
        nom: _nom.text.trim() == widget.moi.nom ? null : _nom.text.trim(),
        ville: _ville.text.trim() == villeAvant ? null : _ville.text.trim(),
        bio: _bio.text.trim() == bioAvant ? null : _bio.text.trim(),
      ),
    );
  }
}
