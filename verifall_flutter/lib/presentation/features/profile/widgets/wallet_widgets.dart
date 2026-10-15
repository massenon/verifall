import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/wallet_portefeuille.dart';
import '../../../shared/shared.dart';

/// La carte de solde — fond forêt, solde en 50/800 blanc.
///
/// # Ce qui a été retiré de la maquette, et pourquoi
///
/// Le simulateur affiche une pastille « 🎁 +10 unités offertes le 1ᵉʳ septembre ».
/// La dotation mensuelle existe — elle **crédite, elle ne remet pas à zéro**
/// (arbitrage n° 16) — mais ni son montant ni sa date ne sont servis par
/// `GET /me/wallet`. Les écrire en dur ferait de l'application la source d'un
/// barème commercial : il faudrait publier sur les magasins pour le changer, et
/// entre-temps l'écran annoncerait un cadeau qui n'arrive pas.
///
/// La pastille **reste**, à sa place, et annonce l'ajout sans chiffrer ce que le
/// serveur ne chiffre pas.
class VfBalanceCard extends StatelessWidget {
  const VfBalanceCard({required this.solde, super.key});

  final int solde;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      background: VfColors.forest,
      border: VfColors.forest,
      padding: const EdgeInsets.symmetric(
        horizontal: VfSpacing.x16,
        vertical: VfSpacing.x22 + 2,
      ),
      child: Semantics(
        // Le solde est annonce EN TOUTES LETTRES avec son unite : « 34 » seul,
        // lu par une synthese vocale, ne veut rien dire.
        label: 'Solde disponible : $solde unités de vérification.',
        excludeSemantics: true,
        child: Column(
          children: <Widget>[
            Text(
              'SOLDE DISPONIBLE',
              style: VfTextStyles.eyebrow.copyWith(
                color: VfColors.surface.withValues(alpha: 0.6),
              ),
            ),
            const SizedBox(height: VfSpacing.x10),
            Text(
              '$solde',
              style: const TextStyle(
                fontFamily: VfFonts.display,
                fontSize: 50,
                fontWeight: FontWeight.w800,
                height: 1,
                color: VfColors.surface,
              ),
            ),
            const SizedBox(height: VfSpacing.x4),
            Text(
              'unités de vérification',
              style: VfTextStyles.tSm.copyWith(color: VfColors.onForestMuted),
            ),
            const SizedBox(height: VfSpacing.x12),
            // La pastille reste COURTE : `VfTag` est une ligne qui ne se
            // renvoie pas, et une phrase entiere la ferait deborder. Le detail
            // passe juste en dessous, en texte, ou il peut aller a la ligne.
            const VfTag(
              label: 'Dotation mensuelle',
              variant: VfTagVariant.prim,
              icon: Icons.card_giftcard_rounded,
            ),
            const SizedBox(height: VfSpacing.x8),
            Text(
              // Elle CREDITE, elle ne remet pas a zero (arbitrage n. 16). Ni
              // le montant ni la date ne sont servis : on ne les invente pas.
              'Des unités vous sont ajoutees chaque mois, en plus de votre '
              'solde.',
              textAlign: TextAlign.center,
              style: VfTextStyles.tXs.copyWith(color: VfColors.onForestMuted),
            ),
          ],
        ),
      ),
    );
  }
}

/// Une ligne de pack.
class VfPackTile extends StatelessWidget {
  const VfPackTile({required this.pack, required this.onTap, super.key});

  final PackUnites pack;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label:
          '${pack.unites} unités pour ${pack.prixFcfa} francs CFA, '
          'soit ${pack.prixParUnite} francs par unite.',
      excludeSemantics: true,
      child: VfCard(
        tight: true,
        onTap: onTap,
        child: Row(
          children: <Widget>[
            Container(
              width: VfAvatarSize.moyen.diameter,
              height: VfAvatarSize.moyen.diameter,
              alignment: Alignment.center,
              decoration: const BoxDecoration(
                color: VfColors.primarySoft,
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.bolt,
                size: 20,
                color: VfColors.primaryDark,
              ),
            ),
            const SizedBox(width: VfSpacing.x12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    '${pack.unites} unités',
                    style: VfTextStyles.tMd.copyWith(
                      color: VfColors.ink,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  Text(
                    '${pack.prixFcfa} FCFA · ${pack.prixParUnite} FCFA l unite',
                    style: VfTextStyles.tXs,
                  ),
                ],
              ),
            ),
            // Le serveur ne rend qu'un booleen « mis en avant » : le texte de
            // l'accroche du prototype (« Populaire », « Meilleur prix ») n'est
            // pas servi, et on ne le fabrique pas. Le libelle du pack, lui,
            // vient du serveur.
            if (pack.misEnAvant && pack.libelle.isNotEmpty) ...<Widget>[
              const SizedBox(width: VfSpacing.x8),
              VfTag(label: pack.libelle, variant: VfTagVariant.prim),
            ],
            const SizedBox(width: VfSpacing.x4),
            const Icon(
              Icons.chevron_right_rounded,
              size: 20,
              color: VfColors.slate2,
            ),
          ],
        ),
      ),
    );
  }
}

/// Une ligne du relevé.
class VfTransactionTile extends StatelessWidget {
  const VfTransactionTile({required this.mouvement, super.key});

  final MouvementUnite mouvement;

  @override
  Widget build(BuildContext context) {
    final bool credit = mouvement.estUnCredit;
    final String signe = credit ? '+' : '';
    return Semantics(
      // Le signe est ANNONCE : il ne repose pas sur la seule couleur.
      label:
          '${mouvement.libelle}, '
          '${credit
              ? 'plus'
              : mouvement.estNonFacturee
              ? ''
              : 'moins'} '
          '${mouvement.delta.abs()} unités.',
      excludeSemantics: true,
      child: VfNavTile(
        label: mouvement.libelle,
        description: _detail(),
        icon: credit
            ? Icons.add_circle_outline_rounded
            : Icons.remove_circle_outline_rounded,
        onTap: null,
        trailing: Text(
          '$signe${mouvement.delta}',
          style: VfTextStyles.tSm.copyWith(
            fontWeight: FontWeight.w700,
            color: credit ? VfColors.verdictTrue : VfColors.ink,
          ),
        ),
      ),
    );
  }

  String? _detail() {
    final List<String> morceaux = <String>[
      if (mouvement.date != null) _date(mouvement.date!),
      // « Non facturee » explique une ligne a zero (arbitrage n. 25). Sans
      // cette mention, une gratuite passerait pour un bogue.
      if (mouvement.estNonFacturee) 'Non facturee',
      if (!mouvement.estNonFacturee && mouvement.motifLibelle != null)
        mouvement.motifLibelle!,
    ];
    return morceaux.isEmpty ? null : morceaux.join(' · ');
  }

  String _date(DateTime date) {
    final DateTime local = date.toLocal();
    String deuxChiffres(int n) => n.toString().padLeft(2, '0');
    return '${deuxChiffres(local.day)}/${deuxChiffres(local.month)}/'
        '${local.year}';
  }
}

/// La feuille de recharge.
///
/// # Ce que la feuille du simulateur faisait, et ce qu'elle fait maintenant
///
/// Le simulateur fait **choisir** entre MTN MoMo, Moov Money et Celtiis Cash.
/// Le contrat attend `pack` et `numero`, et précise que « l'opérateur est déduit
/// du numéro par le serveur » (réserve C.7). Le choix affiché n'aurait donc
/// aucun effet fonctionnel : trois boutons qui mènent tous au même appel, avec
/// une valeur que le serveur ignore.
///
/// La feuille demande donc **le numéro**, et nomme les trois opérateurs comme
/// repère — c'est l'option « la moins éloignée de la maquette » de la réserve.
/// Les couleurs de marque du prototype ne sont pas reprises : le design system
/// n'en donne pas les valeurs, et les deviner reviendrait à inventer un jeton.
class VfTopUpSheet extends StatefulWidget {
  const VfTopUpSheet({required this.pack, required this.onPayer, super.key});

  final PackUnites pack;

  /// Rend le numéro saisi. La feuille ne connaît ni montant ni opérateur : ce
  /// qui n'existe pas dans cette signature ne peut pas partir dans la requête.
  final Future<void> Function(String numero) onPayer;

  @override
  State<VfTopUpSheet> createState() => _VfTopUpSheetState();
}

class _VfTopUpSheetState extends State<VfTopUpSheet> {
  final TextEditingController _numero = TextEditingController();
  bool _enCours = false;
  String? _erreur;

  @override
  void dispose() {
    _numero.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(
        VfSpacing.x16,
        0,
        VfSpacing.x16,
        VfSpacing.x22,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(
            'Montant : ${widget.pack.prixFcfa} FCFA pour '
            '${widget.pack.unites} unités.',
            style: VfTextStyles.tSm,
          ),
          const SizedBox(height: VfSpacing.x12),
          VfTextField(
            label: 'Numéro Mobile Money',
            controller: _numero,
            keyboardType: TextInputType.phone,
            errorText: _erreur,
            helperText:
                'MTN MoMo, Moov Money ou Celtiis Cash : votre opérateur est '
                'reconnu a partir du numéro.',
          ),
          const SizedBox(height: VfSpacing.x14),
          VfPrimaryButton(
            label: 'Continuer',
            expanded: true,
            busy: _enCours,
            onPressed: _enCours ? null : _payer,
          ),
          const SizedBox(height: VfSpacing.x12),
          Text(
            'Redirection vers l application de l opérateur, puis retour '
            'automatique. Votre solde est mis a jour quand l opérateur '
            'confirme le paiement.',
            style: VfTextStyles.tXs,
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Future<void> _payer() async {
    setState(() {
      _enCours = true;
      _erreur = null;
    });
    try {
      await widget.onPayer(_numero.text);
    } finally {
      if (mounted) {
        setState(() => _enCours = false);
      }
    }
  }

  /// Affiche l'erreur du serveur sous le champ.
  void afficherErreur(String message) {
    if (mounted) {
      setState(() => _erreur = message);
    }
  }
}

/// L'encart de coût.
///
/// # Pourquoi il ne porte plus de chiffres
///
/// Le simulateur écrit « texte et lien 1 unité, image 2 unités, vidéo et audio
/// 5 unités ». Ce barème fait partie des **données de démonstration à ne jamais
/// transposer** (09-RESERVES §B, écran 09) : aucun endpoint ne le sert, et le
/// figer dans l'application ferait afficher un prix faux le jour où il change,
/// sans possibilité de correction sans passer par les magasins.
///
/// L'encart reste, à sa place, et dit ce qui est vrai : le coût dépend du type
/// de contenu, et c'est le serveur qui l'annonce.
class VfCostNotice extends StatelessWidget {
  const VfCostNotice({super.key});

  @override
  Widget build(BuildContext context) {
    return VfCard(
      tight: true,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Icon(
            Icons.info_outline_rounded,
            size: 19,
            color: VfColors.info,
          ),
          const SizedBox(width: VfSpacing.x9),
          Expanded(
            child: Text(
              'Une vérification coute des unités, et le montant depend du type '
              'de contenu soumis. Le cout exact est celui que le serveur '
              'annonce au moment de l envoi.',
              style: VfTextStyles.tXs,
            ),
          ),
        ],
      ),
    );
  }
}
