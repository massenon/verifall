import 'dart:ui' as ui show PathMetric;

import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/verification_brouillon.dart';
import '../../../../domain/entities/verification_type_contenu.dart';
import '../../../../data/models/televersement.dart';

/// L'indicateur d'étapes de l'écran 09 : `CONTENU · SOURCES · PREUVES ·
/// VERDICT`, le premier en vert.
///
/// **Il ne progresse pas sur cet écran** : il situe l'action dans la démarche.
///
/// POURQUOI quatre libellés et non cinq : le simulateur en dessine quatre, son
/// arbre de widgets en annonce cinq (réserve D.9). On transpose **ce qui est à
/// l'écran**. Les cinq étapes du pipeline, elles, sont celles de l'écran 10.
class IndicateurDEtapes extends StatelessWidget {
  const IndicateurDEtapes({super.key});

  static const List<String> etapes = <String>[
    'CONTENU',
    'SOURCES',
    'PREUVES',
    'VERDICT',
  ];

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label:
          'Demarche de vérification : contenu, sources, preuves, verdict. '
          'Vous etes a l étape contenu.',
      excludeSemantics: true,
      child: Row(
        children: <Widget>[
          for (int i = 0; i < etapes.length; i++) ...<Widget>[
            if (i > 0)
              const Expanded(
                child: Padding(
                  padding: EdgeInsets.symmetric(horizontal: VfSpacing.x6),
                  child: SizedBox(
                    height: 1,
                    child: ColoredBox(color: VfColors.line),
                  ),
                ),
              ),
            Text(
              etapes[i],
              style: VfTextStyles.tXs.copyWith(
                fontSize: 9.5,
                letterSpacing: 1.0,
                fontWeight: FontWeight.w700,
                color: i == 0 ? VfColors.primary : VfColors.slate2,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

/// La grille de sélection du type — 3 × 2, cinq tuiles.
///
/// # Ce qui a disparu par rapport au simulateur, et pourquoi
///
/// **La pastille rouge de coût.** Le simulateur affiche `1 pt`, `2 pts`,
/// `5 pts` sur chaque tuile parce qu'il doit fonctionner seul. Le contrat est
/// catégorique : *« le coût n'est jamais envoyé par le client : il est lu du
/// type »*, et le barème n'est exposé par **aucun** endpoint. L'afficher
/// reviendrait à annoncer un prix que le serveur ne pratique peut-être pas — et
/// une plateforme qui annonce 2 unités puis en débite 5 a menti.
///
/// Voir `docs/ARBITRAGES.md` : la demande faite au serveur est d'ajouter le
/// tarif à un endpoint existant. Le jour où il arrive, la pastille revient.
class SelecteurDeType extends StatelessWidget {
  const SelecteurDeType({
    required this.choisi,
    required this.onChoisi,
    super.key,
  });

  final TypeContenu choisi;
  final ValueChanged<TypeContenu> onChoisi;

  static const Map<TypeContenu, IconData> icones = <TypeContenu, IconData>{
    TypeContenu.texte: Icons.article_outlined,
    TypeContenu.lien: Icons.link_rounded,
    TypeContenu.image: Icons.image_outlined,
    TypeContenu.video: Icons.videocam_outlined,
    TypeContenu.audio: Icons.mic_none_rounded,
  };

  @override
  Widget build(BuildContext context) {
    return GridView.count(
      crossAxisCount: 3,
      crossAxisSpacing: VfSpacing.x8,
      mainAxisSpacing: VfSpacing.x8,
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      childAspectRatio: 1.25,
      children: <Widget>[
        for (final TypeContenu type in TypeContenu.values)
          _Tuile(
            type: type,
            selectionne: type == choisi,
            onTap: () => onChoisi(type),
          ),
      ],
    );
  }
}

class _Tuile extends StatelessWidget {
  const _Tuile({
    required this.type,
    required this.selectionne,
    required this.onTap,
  });

  final TypeContenu type;
  final bool selectionne;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final Color premierPlan = selectionne ? VfColors.surface : VfColors.ink;
    return Semantics(
      button: true,
      selected: selectionne,
      // Le libelle dit le TYPE, et rien de plus : le cout n'etant pas servi,
      // l'annoncer a la synthese vocale serait aussi faux qu'a l'ecran.
      label: 'Analyser un contenu de type ${type.libelleSimulateur}',
      excludeSemantics: true,
      child: Material(
        color: selectionne ? VfColors.primary : VfColors.surface,
        borderRadius: VfRadii.mdAll,
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: onTap,
          child: Container(
            decoration: BoxDecoration(
              borderRadius: VfRadii.mdAll,
              border: Border.all(
                color: selectionne ? VfColors.primary : VfColors.line,
              ),
            ),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                Icon(
                  SelecteurDeType.icones[type],
                  size: 22,
                  color: premierPlan,
                ),
                const SizedBox(height: VfSpacing.x6),
                Text(
                  type.libelleSimulateur,
                  style: VfTextStyles.tXs.copyWith(
                    color: premierPlan,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// La zone de dépôt d'un média — bordure pointillée, rayon 16.
///
/// La **ligne technique porte le plafond réel**, pris du socle
/// (`TypeTeleversement`), qui le tient du contrat. Le simulateur annonce
/// « 10 Mo max » pour l'image et **rien** pour la vidéo et l'audio : « sans
/// quoi quelqu'un attend trois minutes pour se faire refuser ».
class ZoneDeDepot extends StatelessWidget {
  const ZoneDeDepot({
    required this.type,
    required this.fichier,
    required this.onChoisir,
    super.key,
  });

  final TypeContenu type;
  final FichierChoisi? fichier;
  final VoidCallback onChoisir;

  @override
  Widget build(BuildContext context) {
    final _TexteDeDepot texte = _TexteDeDepot.pour(type);
    final TypeTeleversement? plafond = type.televersement;
    final FichierChoisi? choisi = fichier;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        Semantics(
          button: true,
          label: texte.invite,
          excludeSemantics: true,
          child: Material(
            color: VfColors.canvas,
            borderRadius: VfRadii.lgAll,
            clipBehavior: Clip.antiAlias,
            child: InkWell(
              onTap: onChoisir,
              child: DottedBorderBox(
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: VfSpacing.x16,
                    vertical: VfSpacing.x22,
                  ),
                  child: Column(
                    children: <Widget>[
                      Icon(texte.icone, size: 26, color: VfColors.slate2),
                      const SizedBox(height: VfSpacing.x8),
                      Text(
                        texte.invite,
                        textAlign: TextAlign.center,
                        style: VfTextStyles.tSm.copyWith(
                          color: VfColors.ink,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: VfSpacing.x4),
                      Text(
                        plafond == null
                            ? texte.detail
                            : '${texte.formats} · '
                                  '${plafond.maxBytes ~/ (1024 * 1024)} Mo max · '
                                  '${texte.detail}',
                        textAlign: TextAlign.center,
                        style: VfTextStyles.tXs,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
        if (choisi != null) ...<Widget>[
          const SizedBox(height: VfSpacing.x9),
          Text(
            '✓ ${choisi.nom} — ${choisi.poidsLisible} — pret pour l analyse',
            style: VfTextStyles.tSm.copyWith(
              color: VfColors.verdictTrue,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ],
    );
  }
}

/// Les textes de la zone de dépôt, repris du simulateur.
///
/// Ce sont des textes **éditoriaux** — « la parole du produit » — et à ce titre
/// ils se transposent tels quels. Ce ne sont pas des données.
class _TexteDeDepot {
  const _TexteDeDepot({
    required this.icone,
    required this.invite,
    required this.formats,
    required this.detail,
  });

  final IconData icone;
  final String invite;
  final String formats;
  final String detail;

  static _TexteDeDepot pour(TypeContenu type) => switch (type) {
    TypeContenu.image => const _TexteDeDepot(
      icone: Icons.cloud_upload_outlined,
      invite: 'Prendre une photo ou choisir dans la galerie',
      formats: 'JPG, PNG, WEBP',
      detail: 'recherche inversee + metadonnees + detection IA',
    ),
    TypeContenu.video => const _TexteDeDepot(
      icone: Icons.smart_display_outlined,
      invite: 'Enregistrer ou importer une vidéo',
      formats: 'MP4, WEBM, MOV',
      detail: 'extraction d images cles · OCR · recherche inversee',
    ),
    TypeContenu.audio => const _TexteDeDepot(
      icone: Icons.graphic_eq_rounded,
      invite: 'Enregistrer une note vocale ou importer un fichier',
      formats: 'MP3, WAV, OGG, M4A',
      detail: 'transcription · analyse semantique · detection de voix clonee',
    ),
    TypeContenu.texte || TypeContenu.lien => const _TexteDeDepot(
      icone: Icons.upload_file_outlined,
      invite: 'Choisir un fichier',
      formats: '',
      detail: '',
    ),
  };
}

/// La bordure pointillée de la zone de dépôt.
///
/// POURQUOI un `CustomPaint` maison : Flutter n'a pas de `border-style:
/// dashed`. La reproduire évite que la zone ne se confonde avec une carte
/// ordinaire — c'est ce pointillé qui dit « déposez ici ».
class DottedBorderBox extends StatelessWidget {
  const DottedBorderBox({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context) =>
      CustomPaint(painter: _DottedPainter(), child: child);
}

class _DottedPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final Paint pinceau = Paint()
      ..color = VfColors.line
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.5;

    final RRect contour = RRect.fromRectAndRadius(
      Offset.zero & size,
      const Radius.circular(VfRadii.lg),
    );
    final Path chemin = Path()..addRRect(contour);

    for (final ui.PathMetric metrique in chemin.computeMetrics()) {
      double position = 0;
      while (position < metrique.length) {
        canvas.drawPath(metrique.extractPath(position, position + 5), pinceau);
        position += 9;
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

/// L'encart de coût de l'écran 09.
///
/// # Ce qu'il dit, et ce qu'il ne dit plus
///
/// Le simulateur écrit : *« Cette vérification consommera 2 unités — il vous
/// restera 32 unités. »* Les deux nombres sont fabriqués localement.
///
/// Le premier n'est **servi par aucun endpoint** : le barème vit dans
/// `config/factcheck.costs` côté serveur et n'est jamais sérialisé. Le second
/// s'en déduirait par soustraction, ce que la règle interdit — « le client ne
/// débite ni ne crédite jamais d'unité » (§5.9).
///
/// L'encart annonce donc **le solde réel** et **le fait que le coût est fixé par
/// le serveur**. Il reste ce qu'il doit être : le lieu où l'on apprend qu'on va
/// payer, **avant** le bouton d'envoi — « personne ne doit découvrir un débit
/// après l'avoir déclenché ».
class EncartDeCout extends StatelessWidget {
  const EncartDeCout({required this.solde, super.key});

  /// `null` = pas encore relu. On affiche un tiret, **jamais un zéro**.
  final int? solde;

  @override
  Widget build(BuildContext context) {
    final String phrase = solde == null
        ? 'Le cout de cette vérification est fixe par le serveur et vous sera '
              'confirme au lancement. Votre solde est en cours de lecture.'
        : 'Le cout de cette vérification est fixe par le serveur et vous sera '
              'confirme au lancement. Solde actuel : $solde unite'
              '${solde! > 1 ? 's' : ''}.';

    return Semantics(
      liveRegion: true,
      label: phrase,
      excludeSemantics: true,
      child: Container(
        padding: const EdgeInsets.all(VfSpacing.x16),
        decoration: BoxDecoration(
          color: VfColors.surface,
          borderRadius: VfRadii.lgAll,
          border: Border.all(color: VfColors.line),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            const Icon(Icons.toll, size: 20, color: VfColors.primary),
            const SizedBox(width: VfSpacing.x10),
            Expanded(child: Text(phrase, style: VfTextStyles.tXs)),
          ],
        ),
      ),
    );
  }
}
