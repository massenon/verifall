import '../../../shared/vf_timeline.dart';

/// La chronologie « Traçabilité de l'analyse » de l'écran 10.
///
/// # Les cinq étapes viennent du simulateur, et c'est légitime
///
/// Ce sont des **textes éditoriaux** — « la parole du produit », que la
/// documentation cite explicitement comme contre-exemple aux données de
/// démonstration : *« les cinq étapes du pipeline d'analyse […] se transposent
/// telles quelles »*.
///
/// # L'état des étapes vient du serveur, et c'est impératif
///
/// > « Le prototype fait défiler ses étapes sur des minuteurs aléatoires. Ici
/// > elles sont posées par l'orchestrateur au moment où il les franchit : si
/// > l'analyse cale sur les moteurs, l'affichage cale avec elle. Une barre qui
/// > avance toute seule pendant qu'un service est en panne ment à qui la
/// > regarde. » (arbitrage n° 28)
///
/// [etats] est donc une fonction **pure** de `data.avancement`. Aucune minuterie,
/// aucun `Timer`, aucune interpolation : le seul mouvement possible est celui
/// que le serveur a déclaré.
///
/// # Le décalage cinq / six, et pourquoi il est consigné
///
/// L'orchestrateur réel franchit **six** étapes (`App\Enums\EtapePipeline`) et
/// n'en publie que la courante (`etape`, `etape_libelle`) : la liste complète
/// n'est dans aucune réponse. On ne peut donc ni la recopier sans la deviner, ni
/// aligner les libellés un à un. La chronologie affiche les cinq étapes
/// éditoriales, **positionnées par le pourcentage du serveur**, et l'en-tête de
/// l'écran affiche le libellé exact de l'étape courante — celui-là est servi.
/// Voir `docs/ARBITRAGES.md` : la demande est de publier la liste des étapes.
abstract final class PipelineDAnalyse {
  /// Les cinq étapes du simulateur, titre et explication.
  static const List<({String titre, String detail})> etapes =
      <({String titre, String detail})>[
        (
          titre: 'Reception et empreinte du contenu',
          detail: 'Hash SHA-256, horodatage, mise en file',
        ),
        (
          titre: 'Extraction et normalisation',
          detail: 'OCR, transcription, decoupage en affirmations',
        ),
        (
          titre: 'Recoupement des sources ouvertes',
          detail: 'Bases IFCN, portails officiels, archives web',
        ),
        (
          titre: 'Analyse par les moteurs IA',
          detail: 'Orchestrateur multi-modeles + score de confiance',
        ),
        (
          titre: 'Revue editoriale et verdict',
          detail: 'Controle humain, redaction du rapport',
        ),
      ];

  /// Les étapes, avec leur état, déduites **du seul avancement serveur**.
  ///
  /// [enMarche] distingue « l'étape suivante est en cours » de « l'analyse
  /// s'est arrêtée là ». Une reprise programmée ou un échec ne doivent pas
  /// laisser un point qui bat : le battement dirait qu'il se passe quelque
  /// chose.
  static List<VfTimelineStep> etats({
    required int avancement,
    required bool enMarche,
  }) {
    final int total = etapes.length;
    final double atteint = (avancement.clamp(0, 100) / 100) * total;

    return <VfTimelineStep>[
      for (int i = 0; i < total; i++)
        VfTimelineStep(
          label: etapes[i].titre,
          detail: etapes[i].detail,
          state: switch (i) {
            // Franchie : le serveur a depasse la fraction correspondante.
            _ when i + 1 <= atteint => VfTimelineStepState.fait,
            // Courante : la premiere non franchie, et seulement si l'analyse
            // tourne encore.
            _ when i <= atteint && enMarche => VfTimelineStepState.actif,
            _ => VfTimelineStepState.aVenir,
          },
        ),
    ];
  }
}
