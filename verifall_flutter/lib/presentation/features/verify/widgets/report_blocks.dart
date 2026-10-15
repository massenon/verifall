import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/verification_entite.dart';
import '../../../../domain/entities/verification_libelle.dart';
import '../../../../domain/entities/verification_rapport.dart';
import '../../../shared/vf_card.dart';
import '../../../shared/vf_display.dart';
import '../../../shared/vf_toast.dart';
import '../../../shared/vf_verdict_badge.dart';
import 'verdict_tone.dart';

/// Le bloc de verdict — carte centrée bordée de la couleur du verdict.
///
/// **La barre de fiabilité prend la couleur du verdict** (arbitrage n° 32). Une
/// barre verte sous un « non vérifiable » dit « tout va bien » là où il n'y a
/// aucune assurance ; sous un « faux », elle félicite le lecteur d'avoir trouvé
/// une intox. Un test verrouille cette couleur.
class BlocDeVerdict extends StatelessWidget {
  const BlocDeVerdict({required this.verification, super.key});

  final Verification verification;

  @override
  Widget build(BuildContext context) {
    final VerificationRapport rapport = verification.rapport!;
    final VfVerdictTone tone = TonDuVerdict.pour(rapport.verdict);
    final int? confiance = rapport.confiance;

    return VfCard(
      border: tone.color.withValues(alpha: 0.33),
      padding: const EdgeInsets.symmetric(
        horizontal: VfSpacing.x16 + 2,
        vertical: VfSpacing.x26,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: <Widget>[
          Container(
            width: 62,
            height: 62,
            decoration: BoxDecoration(
              color: tone.color.withValues(alpha: 0.10),
              shape: BoxShape.circle,
            ),
            child: Icon(tone.icon, size: 31, color: tone.color),
          ),
          const SizedBox(height: VfSpacing.x12),
          // Le verdict est annonce EN PREMIER, en toutes lettres, et c'est le
          // LIBELLE DU SERVEUR — jamais un mot fabrique cote client.
          if (rapport.verdict != null)
            VfVerdictBadge(libelle: rapport.verdict!.libelle, tone: tone)
          else
            // Un rapport sans verdict tranche existe : on le dit, on n'invente
            // pas de mot a la place.
            Text(
              verification.statutLibelle ?? 'Verdict non rendu',
              style: VfTextStyles.tSm.copyWith(color: VfColors.slate2),
            ),
          const SizedBox(height: VfSpacing.x10 + 1),
          Text(
            verification.apercu,
            textAlign: TextAlign.center,
            style: VfTextStyles.hMd,
          ),
          if (confiance != null) ...<Widget>[
            const SizedBox(height: VfSpacing.x9),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 260),
              child: Column(
                children: <Widget>[
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: <Widget>[
                      // `Flexible` et non un `Text` nu : a 200 % de taille de
                      // texte — un reglage d'accessibilite courant — le libelle
                      // et le pourcentage ne tiennent plus cote a cote, et une
                      // ligne qui deborde masque justement le pourcentage.
                      Flexible(
                        child: Text(
                          'Indice de fiabilite',
                          style: VfTextStyles.tXs,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ),
                      const SizedBox(width: VfSpacing.x8),
                      Text(
                        '$confiance %',
                        style: VfTextStyles.tXs.copyWith(
                          color: VfColors.ink,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: VfSpacing.x4 + 1),
                  VfProgressBar(
                    value: confiance / 100,
                    color: TonDuVerdict.couleurDeLaBarre(rapport.verdict),
                    semanticsLabel: 'Indice de fiabilite',
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: VfSpacing.x14),
          Text(_pied(), textAlign: TextAlign.center, style: VfTextStyles.tXs),
        ],
      ),
    );
  }

  String _pied() {
    final List<String> morceaux = <String>[
      if (verification.soumiseLe != null) _date(verification.soumiseLe!),
      if (verification.type.libelle.isNotEmpty) verification.type.libelle,
      'Ref. ${verification.reference}',
    ];
    return morceaux.join(' · ');
  }

  static String _date(DateTime quand) {
    final DateTime local = quand.toLocal();
    String d(int v) => v < 10 ? '0$v' : '$v';
    return '${d(local.day)}/${d(local.month)}/${local.year}';
  }
}

/// Une carte de rapport : titre + contenu, la forme commune de tous les blocs.
class CarteDeRapport extends StatelessWidget {
  const CarteDeRapport({
    required this.titre,
    required this.child,
    this.fond,
    this.bordure,
    super.key,
  });

  final String titre;
  final Widget child;
  final Color? fond;
  final Color? bordure;

  @override
  Widget build(BuildContext context) => VfCard(
    background: fond ?? VfColors.surface,
    border: bordure ?? VfColors.line,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(titre, style: VfTextStyles.hSm),
        const SizedBox(height: VfSpacing.x9),
        child,
      ],
    ),
  );
}

/// « 📥 Votre contenu soumis » — citation à filet gauche, en italique.
class CarteDuContenuSoumis extends StatelessWidget {
  const CarteDuContenuSoumis({
    required this.affirmation,
    this.contexte,
    super.key,
  });

  final String affirmation;
  final String? contexte;

  @override
  Widget build(BuildContext context) {
    final String? ctx = contexte;
    return CarteDeRapport(
      titre: '📥 Votre contenu soumis',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          _Citation(texte: affirmation),
          if (ctx != null && ctx.trim().isNotEmpty) ...<Widget>[
            const SizedBox(height: VfSpacing.x10),
            Text('Contexte fourni : $ctx', style: VfTextStyles.tXs),
          ],
        ],
      ),
    );
  }
}

class _Citation extends StatelessWidget {
  const _Citation({required this.texte});

  final String texte;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.only(left: VfSpacing.x12),
    decoration: const BoxDecoration(
      border: Border(left: BorderSide(color: VfColors.line, width: 3)),
    ),
    child: Text(
      texte,
      style: VfTextStyles.prose.copyWith(fontStyle: FontStyle.italic),
    ),
  );
}

/// « 🔍 Sources et preuves consultées » — les plus fiables d'abord, même ordre
/// que la carte web (`RapportResource::preuves`).
///
/// Phase 11 côté API : avant le 9 octobre 2026, aucune route n'exposait les
/// preuves consultées, et l'application ne pouvait pas afficher ce que l'écran
/// web montre depuis l'origine — audit Flutter/Laravel du 8 octobre 2026.
class CarteSourcesEtPreuves extends StatelessWidget {
  const CarteSourcesEtPreuves({required this.preuves, super.key});

  final List<Preuve> preuves;

  @override
  Widget build(BuildContext context) => CarteDeRapport(
    titre: '🔍 Sources et preuves consultées',
    child: preuves.isEmpty
        ? Text(
            'Aucune source n a pu etre consultee pour cette analyse.',
            style: VfTextStyles.tSm.copyWith(color: VfColors.slate2),
          )
        : Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              for (final Preuve preuve in preuves)
                Padding(
                  padding: const EdgeInsets.only(bottom: VfSpacing.x10),
                  child: _LignePreuve(preuve: preuve),
                ),
            ],
          ),
  );
}

/// La correspondance `PositionPreuve -> ton`, établie contre
/// `App\Enums\PositionPreuve::badge()` (`supports`/`refutes`/`neutral`/
/// `contested`).
///
/// Distincte de `TonDuVerdict`/`VfVerdictTones` : celles-ci sont établies
/// contre `VerdictFactCheck`, un espace de valeurs **différent**. Réutiliser
/// l'une pour l'autre ferait retomber `supports`/`refutes`/`neutral`/
/// `contested` sur le ton `inconnu`, faute de correspondre à `true`/`false`/
/// `misleading`/`unverifiable`.
abstract final class _TonDeLaPosition {
  static VfVerdictTone pour(String? valeur) => switch (valeur) {
    'supports' => VfVerdictTone.vrai,
    'refutes' => VfVerdictTone.faux,
    'neutral' => VfVerdictTone.enCours,
    'contested' => VfVerdictTone.trompeur,
    _ => VfVerdictTone.inconnu,
  };
}

class _LignePreuve extends StatelessWidget {
  const _LignePreuve({required this.preuve});

  final Preuve preuve;

  @override
  Widget build(BuildContext context) {
    final String? adresse = preuve.url;
    final Uri? lien = adresse == null ? null : Uri.tryParse(adresse);
    final ValeurLibelle? position = preuve.position;

    return Container(
      padding: const EdgeInsets.all(VfSpacing.x12),
      decoration: BoxDecoration(
        color: VfColors.canvas,
        borderRadius: VfRadii.mdAll,
        border: Border.all(color: VfColors.line),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Expanded(
                child: _Nom(nom: preuve.nom, lien: lien),
              ),
              if (position != null) ...<Widget>[
                const SizedBox(width: VfSpacing.x8),
                VfVerdictBadge(
                  libelle: position.libelle,
                  tone: _TonDeLaPosition.pour(position.valeur),
                  compact: true,
                ),
              ],
            ],
          ),
          const SizedBox(height: VfSpacing.x4),
          Text(
            _ligneMeta(preuve),
            style: VfTextStyles.tXs.copyWith(color: VfColors.slate2),
          ),
        ],
      ),
    );
  }

  static String _ligneMeta(Preuve preuve) {
    final List<String> morceaux = <String>[
      if (preuve.domaine != null && preuve.domaine!.isNotEmpty) preuve.domaine!,
      if (preuve.publieeLe != null) _date(preuve.publieeLe!),
    ];
    final String fiabilite = 'Fiabilite ${preuve.fiabilite} %';
    return morceaux.isEmpty
        ? fiabilite
        : '${morceaux.join(' · ')} · $fiabilite';
  }

  static String _date(DateTime quand) {
    final DateTime local = quand.toLocal();
    String d(int v) => v < 10 ? '0$v' : '$v';
    return '${d(local.day)}/${d(local.month)}/${local.year}';
  }
}

/// Le nom de la source — un simple libellé si aucune adresse n'est servie, un
/// lien **en onglet système** sinon (jamais une WebView maison, même raison
/// que `certificate_page.dart`).
class _Nom extends StatelessWidget {
  const _Nom({required this.nom, required this.lien});

  final String nom;
  final Uri? lien;

  @override
  Widget build(BuildContext context) {
    final TextStyle style = VfTextStyles.tSm.copyWith(
      fontWeight: FontWeight.w700,
      color: VfColors.ink,
    );

    if (lien == null) {
      return Text(nom, style: style);
    }

    return InkWell(
      onTap: () => _ouvrir(context, lien!),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Flexible(
            child: Text(nom, style: style, overflow: TextOverflow.ellipsis),
          ),
          const SizedBox(width: VfSpacing.x4),
          const Icon(
            Icons.open_in_new_rounded,
            size: 14,
            color: VfColors.slate2,
          ),
        ],
      ),
    );
  }

  Future<void> _ouvrir(BuildContext context, Uri lien) async {
    final bool ouvert = await launchUrl(
      lien,
      mode: LaunchMode.externalApplication,
    );
    if (!ouvert && context.mounted) {
      VfToast.error(context, 'Cette source n a pas pu etre ouverte.');
    }
  }
}

/// « 🛡️ Hate Speech Indicator ».
///
/// # Ce que la carte affiche, et ce qu'elle n'affiche plus
///
/// Le simulateur écrit une phrase rassurante en dur : *« Aucun signal de
/// rhétorique hostile… »*. Elle serait **fausse** dès que l'indice dépasse zéro.
/// L'analyseur en produit une (`indice_haine_note`) : c'est elle qui s'affiche,
/// et quand elle manque, ce sont les motifs relevés. Rien n'est écrit à sa place.
///
/// La carte **disparaît** si `indice_haine` est nul : « un indicateur qu'on ne
/// mesure pas ne s'affiche pas ».
///
/// La ligne « 🔒 Privacy Protected » est **éditoriale** — c'est une promesse du
/// produit sur sa propre méthode, pas une mesure. Elle reste.
class CarteIndiceDeHaine extends StatelessWidget {
  const CarteIndiceDeHaine({required this.rapport, super.key});

  final VerificationRapport rapport;

  @override
  Widget build(BuildContext context) {
    final int indice = rapport.indiceHaine!;
    // Le ton suit l'indice : vert a zero, orange des qu'un signal est releve.
    // Une carte verte sous un indice de 40 % dirait le contraire de la mesure.
    final bool aucunSignal = indice == 0 && rapport.indiceHaineMotifs.isEmpty;
    final Color teinte = aucunSignal
        ? VfColors.verdictTrue
        : VfColors.verdictInvestigate;

    return CarteDeRapport(
      titre: '🛡️ Hate Speech Indicator',
      fond: teinte.withValues(alpha: 0.05),
      bordure: teinte.withValues(alpha: 0.35),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          VfTag(
            label: 'HSI $indice %',
            variant: aucunSignal ? VfTagVariant.prim : VfTagVariant.neutre,
          ),
          const SizedBox(height: VfSpacing.x8),
          if (rapport.indiceHaineNote != null)
            Text(rapport.indiceHaineNote!, style: VfTextStyles.tXs)
          else if (rapport.indiceHaineMotifs.isNotEmpty)
            Text(
              'Signaux releves : ${rapport.indiceHaineMotifs.join(', ')}.',
              style: VfTextStyles.tXs,
            ),
          const SizedBox(height: VfSpacing.x8),
          Text(
            '🔒 Privacy Protected — zero donnee sur l auteur, analyse sans '
            'état. Le contenu est juge, jamais la personne.',
            style: VfTextStyles.tXs,
          ),
        ],
      ),
    );
  }
}

/// « 💡 Pourquoi ce résultat ? ».
///
/// **Ce bloc ne disparaît jamais.** Quand la synthèse rédigée manque, on affiche
/// la réserve du serveur ; quand les deux manquent, on le dit — un bloc absent
/// laisserait croire que le rapport n'explique rien, un bloc vide serait pire.
class CartePourquoiCeResultat extends StatelessWidget {
  const CartePourquoiCeResultat({required this.rapport, super.key});

  final VerificationRapport rapport;

  @override
  Widget build(BuildContext context) {
    final String? synthese = rapport.synthese;
    final String? reserve = rapport.reserve;

    return CarteDeRapport(
      titre: '💡 Pourquoi ce résultat ?',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          if (synthese != null && synthese.trim().isNotEmpty)
            Text(synthese, style: VfTextStyles.prose)
          else
            Text(
              'La synthese redigee n est pas disponible pour ce rapport.',
              style: VfTextStyles.prose.copyWith(color: VfColors.slate2),
            ),
          if (reserve != null && reserve.trim().isNotEmpty) ...<Widget>[
            const SizedBox(height: VfSpacing.x12),
            // La reserve du SERVEUR : c'est elle qui porte, en toutes lettres,
            // << Vos unites vous sont rendues >> et << arbitrage humain >>. La
            // reformuler cote client risquerait d'annoncer un remboursement qui
            // n'a pas eu lieu.
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(VfSpacing.x12),
              decoration: BoxDecoration(
                color: VfColors.canvas,
                borderRadius: VfRadii.mdAll,
                border: Border.all(color: VfColors.line),
              ),
              child: Text(reserve, style: VfTextStyles.tSm),
            ),
          ],
          const SizedBox(height: VfSpacing.x12),
          _BlocTechnique(rapport: rapport),
        ],
      ),
    );
  }
}

/// Le bloc de code sombre du simulateur.
///
/// Il ne reprend **que des champs servis** : verdict, confiance, indice de
/// haine, motifs. Le simulateur y ajoute des `signals` inventés ; les recopier
/// publierait des preuves qui n'ont pas été consultées.
class _BlocTechnique extends StatelessWidget {
  const _BlocTechnique({required this.rapport});

  final VerificationRapport rapport;

  @override
  Widget build(BuildContext context) {
    final List<String> lignes = <String>[
      '{',
      if (rapport.verdict != null) '  "verdict": "${rapport.verdict!.valeur}",',
      if (rapport.confiance != null) '  "confiance": ${rapport.confiance},',
      if (rapport.indiceHaine != null) '  "hsi": ${rapport.indiceHaine},',
      if (rapport.indiceHaineMotifs.isNotEmpty)
        '  "hsi_motifs": ${rapport.indiceHaineMotifs}',
      '}',
    ];

    return Semantics(
      // « Le bloc de code structure est marque comme contenu technique et peut
      // etre saute » : on ne fait pas epeler du JSON a une synthese vocale.
      label: 'Bloc technique, contenu equivalent aux informations ci-dessus.',
      excludeSemantics: true,
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(VfSpacing.x12),
        decoration: BoxDecoration(
          color: VfColors.sidebarBg,
          borderRadius: VfRadii.mdAll,
        ),
        child: Text(
          lignes.join('\n'),
          style: VfTextStyles.mono.copyWith(color: VfColors.surface),
        ),
      ),
    );
  }
}

/// « Traçabilité de l'analyse » — les étapes **réellement franchies**.
///
/// POURQUOI elle est sur le rapport et non sur le suivi : `deroule` vit dans
/// `data.rapport`, que le serveur n'écrit qu'à la fin. Pendant l'analyse, seule
/// l'étape courante est publiée. Ici les libellés et les horodatages viennent du
/// serveur, un par un : rien n'est déduit.
class CarteTracabilite extends StatelessWidget {
  const CarteTracabilite({required this.deroule, super.key});

  final List<EtapeFranchie> deroule;

  @override
  Widget build(BuildContext context) => CarteDeRapport(
    titre: '🧭 Tracabilite de l analyse',
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        for (final EtapeFranchie etape in deroule)
          Padding(
            padding: const EdgeInsets.only(bottom: VfSpacing.x8),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                const Icon(
                  Icons.check_circle_rounded,
                  size: 18,
                  color: VfColors.primary,
                ),
                const SizedBox(width: VfSpacing.x9),
                Expanded(
                  child: Text(
                    etape.libelle,
                    style: VfTextStyles.tSm.copyWith(color: VfColors.ink),
                  ),
                ),
                if (etape.franchieLe != null)
                  Text(_heure(etape.franchieLe!), style: VfTextStyles.tXs),
              ],
            ),
          ),
      ],
    ),
  );

  static String _heure(DateTime quand) {
    final DateTime local = quand.toLocal();
    String d(int v) => v < 10 ? '0$v' : '$v';
    return '${d(local.hour)}:${d(local.minute)}';
  }
}

/// « ℹ️ Avertissement sur l'usage de l'IA » — texte éditorial, transposé tel
/// quel. C'est la parole du produit sur sa propre méthode.
class CarteAvertissementIA extends StatelessWidget {
  const CarteAvertissementIA({super.key});

  @override
  Widget build(BuildContext context) => VfCard(
    background: VfColors.canvas,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          'ℹ️ Avertissement sur l usage de l IA',
          style: VfTextStyles.hSm.copyWith(fontSize: 14),
        ),
        const SizedBox(height: VfSpacing.x6),
        Text(
          'Ce rapport est produit par un orchestrateur croisant recherche web '
          'et bases de fact-checking. En cas de doute majeur, demandez une '
          'contre-expertise humaine.',
          style: VfTextStyles.tXs,
        ),
      ],
    ),
  );
}
