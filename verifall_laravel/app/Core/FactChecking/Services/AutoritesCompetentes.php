<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

/**
 * Quel genre d'autorité fait foi — phase 4 du plan en 13 phases.
 *
 * Objectif : « juridiction + domaine → autorités compétentes, sans liste
 * fermée ». Ce service lève C-06.1 et C-06.6 : `analyse.md` et `types/url.md`
 * disaient « gouv.bj » en dur, comme si toute vérification portait sur le
 * Bénin. Un portail de santé argentin, une autorité financière singapourienne
 * ou une fédération sportive japonaise n'avaient aucune des huit.
 *
 * ## Pourquoi une catégorie générique, jamais un nom de pays
 *
 * On ne rend jamais « le ministère de la Santé du Bénin » : on rend « le
 * ministère de la Santé (ou équivalent) ». C'est au MODÈLE de combiner cette
 * fonction avec la juridiction qu'il déduit du contenu — exactement comme
 * {@see DomaineDeLaSource::estInstitutionnel()} reconnaît la FORME d'un
 * suffixe institutionnel sans connaître la liste des pays qui le portent.
 *
 * ## Pourquoi un classement par mots-clés, pas une colonne persistée
 *
 * Le plan le dit explicitement : aucune migration n'est requise pour cette
 * phase, à la différence de la phase 3. La catégorie n'a de valeur qu'au
 * moment de composer le prompt ; la figer sur le claim ajouterait un état
 * qu'aucune autre partie du système n'a besoin de relire.
 */
final class AutoritesCompetentes
{
    /**
     * @var array<string, array{mots: list<string>, autorite: string}>
     */
    private const CATEGORIES = [
        'sante' => [
            'mots' => ['vaccin', 'hôpital', 'épidémie', 'médicament', 'maladie', 'santé publique', 'pandémie', 'clinique'],
            'autorite' => "le ministère de la Santé (ou équivalent) et l'autorité sanitaire nationale ; pour une portée internationale, l'Organisation mondiale de la Santé",
        ],
        'science' => [
            'mots' => ['étude scientifique', 'chercheurs', 'université', 'laboratoire', 'publication scientifique', 'recherche'],
            'autorite' => "l'institution de recherche ou l'organisme scientifique national compétent, et la publication à comité de lecture d'origine quand elle existe",
        ],
        'finance' => [
            'mots' => ['banque centrale', 'taux de change', 'inflation', 'bourse', 'régulateur financier', 'taxe', 'impôt', 'budget de l\'état'],
            'autorite' => 'la banque centrale ou le régulateur financier national, et le ministère des Finances',
        ],
        'sport' => [
            'mots' => ['championnat', 'fédération sportive', 'compétition sportive', 'match', 'tournoi', 'jeux olympiques'],
            'autorite' => 'la fédération sportive nationale ou internationale compétente pour cette discipline',
        ],
        'entreprise' => [
            'mots' => ['entreprise', 'société', 'registre du commerce', 'faillite', 'rachat', 'actionnaire', 'pdg'],
            'autorite' => "le registre du commerce national et l'autorité de régulation du secteur concerné",
        ],
        'elections' => [
            'mots' => ['élection', 'scrutin', 'candidat', 'vote', 'référendum', 'commission électorale'],
            'autorite' => 'la commission électorale nationale et la juridiction constitutionnelle compétente',
        ],
        'environnement' => [
            'mots' => ['climat', 'pollution', 'déforestation', 'biodiversité', 'environnement'],
            'autorite' => "le ministère de l'Environnement (ou équivalent) et l'agence nationale compétente",
        ],
    ];

    /**
     * Le type d'autorité à chercher, en une phrase générique.
     *
     * Une JURIDICTION INCONNUE ou un domaine qui ne correspond à aucune
     * catégorie reçoit la même réponse honnête : chercher l'institution
     * compétente, sans qu'aucun pays ne soit présumé par défaut. C'est le cas
     * `AC-009` de l'audit — fonctionner sur une juridiction absente des
     * listes historiques — tenu ici précisément parce qu'il n'y a pas de liste.
     */
    public function pour(string $affirmation): string
    {
        $minuscule = mb_strtolower($affirmation);

        foreach (self::CATEGORIES as $categorie) {
            foreach ($categorie['mots'] as $mot) {
                if (str_contains($minuscule, $mot)) {
                    return $categorie['autorite'];
                }
            }
        }

        return "l'institution publique ou l'organisme professionnel compétent pour le domaine de l'affirmation et pour la juridiction qu'elle désigne — jamais une seule juridiction par défaut";
    }
}
