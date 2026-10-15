<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Les thématiques sous lesquelles une vérification est rangée.
 *
 * ## Une liste CLOSE, et courte
 *
 * Le prototype affiche « Général 427 · Politique 29 · Santé 12 · Arnaque 6 ».
 * Le panneau n'a d'intérêt que si les colonnes se comparent d'une semaine à
 * l'autre : une taxonomie ouverte, où chaque analyse invente son étiquette,
 * produirait quatre cents thèmes à une occurrence et zéro information.
 *
 * `General` est le défaut assumé, pas un fourre-tout honteux : la plupart des
 * rumeurs ne relèvent d'aucun domaine particulier, et les ranger de force
 * ailleurs fausserait les trois autres compteurs.
 *
 * ## Pourquoi « Arnaque » figure ici alors qu'elle n'est pas un domaine
 *
 * Les trois autres disent DE QUOI parle la rumeur ; « Arnaque » dit ce qu'elle
 * CHERCHE À FAIRE. L'incohérence est délibérée et vient du prototype : sur une
 * plateforme béninoise, la fraude — faux recrutements, faux transferts
 * d'argent, faux gains — est le motif de signalement le plus fréquent, et le
 * plus urgent à voir monter. Le noyer dans « Général » reviendrait à masquer
 * une vague de fraude au moment où elle commence.
 */
enum ThematiqueVerification: string
{
    case General = 'general';
    case Politique = 'politique';
    case Sante = 'sante';
    case Arnaque = 'arnaque';
    case Economie = 'economie';
    case Securite = 'securite';

    public function libelle(): string
    {
        return match ($this) {
            self::General => 'Général',
            self::Politique => 'Politique',
            self::Sante => 'Santé',
            self::Arnaque => 'Arnaque',
            self::Economie => 'Économie',
            self::Securite => 'Sécurité',
        };
    }

    /**
     * Les mots qui rangent une affirmation sous cette thématique.
     *
     * Sans accents et en minuscules : la comparaison se fait sur un texte
     * normalisé, pour qu'« économie » et « economie » tombent au même endroit.
     * Le vocabulaire est volontairement béninois et ouest-africain là où c'est
     * pertinent — « momo », « kkiapay », « cnss » n'apparaîtraient dans aucune
     * liste générique, et ce sont ceux qui reviennent le plus.
     *
     * @return list<string>
     */
    public function motsCles(): array
    {
        return match ($this) {
            // Pas de mots-clés : c'est le défaut, atteint quand aucun autre
            // thème ne l'emporte.
            self::General => [],

            self::Politique => [
                'president', 'ministre', 'gouvernement', 'election', 'depute',
                'assemblee', 'parti', 'candidat', 'scrutin', 'vote', 'campagne',
                'opposition', 'mandat', 'constitution', 'referendum', 'maire',
                'prefet', 'senat', 'coup d etat', 'putsch', 'cedeao',
            ],

            self::Sante => [
                'vaccin', 'virus', 'maladie', 'epidemie', 'hopital', 'medecin',
                'medicament', 'traitement', 'cancer', 'paludisme', 'covid',
                'cholera', 'grossesse', 'oms', 'sante', 'contagion', 'symptome',
                'remede', 'tisane', 'pharmacie', 'mpox', 'lassa',
            ],

            self::Arnaque => [
                'arnaque', 'escroquerie', 'fraude', 'faux recrutement',
                'fausse offre', 'faux concours', 'faux gain',
                'gagnez', 'gagner de l argent', 'cadeau', 'tirage au sort',
                'loterie', 'heritage', 'transfert d argent', 'momo',
                'mobile money', 'moov money', 'mtn money', 'celtiis',
                'kkiapay', 'code otp', 'mot de passe', 'cliquez ici',
                'lien whatsapp', 'investissement garanti', 'crypto',
                'bitcoin', 'doublez', 'placement', 'ponzi', 'visa gratuit',
            ],

            self::Economie => [
                'economie', 'prix', 'inflation', 'carburant', 'essence', 'taxe', 'impot',
                'salaire', 'smig', 'banque', 'franc cfa', 'fcfa', 'budget',
                'douane', 'importation', 'exportation', 'marche', 'coton',
                'port', 'cnss', 'retraite', 'subvention',
            ],

            self::Securite => [
                'securite', 'attaque', 'attentat', 'terroriste', 'djihadiste', 'braquage',
                'enlevement', 'kidnapping', 'police', 'gendarmerie', 'armee',
                'militaire', 'frontiere', 'insecurite', 'couvre-feu',
                'manifestation', 'emeute', 'incendie', 'accident',
            ],
        };
    }
}
