<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Partner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Reprend ce qui vivait en dur — sans rien retaper.
 *
 * ## La FAQ vient des FICHIERS DE LANGUE, pas d'un copier-coller
 *
 * Les dix-sept questions existent dans `lang/fr/faq.php` et `lang/en/faq.php`,
 * sous une convention nette : une clé `x` porte la question, une clé
 * `reponse_x` porte la réponse. Le semeur les apparie mécaniquement.
 *
 * Les recopier à la main, ce serait dix-sept occasions de faire une faute dans
 * un texte relu, et de perdre la correspondance français/anglais. C'est le même
 * raisonnement que `OutilsSeeder`, qui extrait ses quinze URL du prototype
 * plutôt que de les retaper.
 *
 * ## Les partenaires viennent du carrousel de l'accueil
 *
 * Six logos, leurs URL et leurs chemins d'images, relevés depuis
 * `public/accueil.blade.php`. Ceux-là ne peuvent pas s'extraire
 * mécaniquement — le carrousel est du HTML écrit à la main — mais chaque
 * valeur est vérifiable ligne à ligne dans le gabarit d'origine.
 */
final class RessourcesEtCommunicationSeeder extends Seeder
{
    /**
     * Les clés de `lang/*\/faq.php` qui portent une question, dans l'ordre
     * d'affichage du prototype.
     *
     * @var list<array{cle: string, rubrique: string}>
     */
    private const QUESTIONS = [
        ['cle' => 'qu_est_ce_que_verif_all', 'rubrique' => 'La plateforme'],
        ['cle' => 'pourquoi_dois_je_creer_un', 'rubrique' => 'La plateforme'],
        ['cle' => 'est_ce_que_l_utilisation_est_gratuite', 'rubrique' => 'La plateforme'],
        ['cle' => 'verif_all_est_il_un_organe_du', 'rubrique' => 'La plateforme'],
        ['cle' => 'comment_verifier_un_lien_recu_sur_whatsapp', 'rubrique' => 'Vérifier'],
        ['cle' => 'puis_je_verifier_une_image_ou_une', 'rubrique' => 'Vérifier'],
        ['cle' => 'que_signifie_le_score_de_confiance', 'rubrique' => 'Vérifier'],
        ['cle' => 'comment_l_ia_detecte_t_elle_les', 'rubrique' => 'Vérifier'],
        ['cle' => 'c_est_quoi_un_deepfake', 'rubrique' => 'Vérifier'],
        ['cle' => 'combien_de_temps_prend_une_verification_humaine', 'rubrique' => 'Vérifier'],
        ['cle' => 'mes_donnees_signalees_sont_elles_privees', 'rubrique' => 'Données'],
        ['cle' => 'mes_donnees_personnelles_sont_elles_protegees', 'rubrique' => 'Données'],
        ['cle' => 'pourquoi_verifier_mon_compte', 'rubrique' => 'Données'],
        ['cle' => 'que_se_passe_t_il_si_j', 'rubrique' => 'Données'],
        ['cle' => 'qui_sont_les_mentors_de_la_plateforme', 'rubrique' => 'Académie'],
        ['cle' => 'comment_obtenir_mon_certificat_de_fact_checker', 'rubrique' => 'Académie'],
        ['cle' => 'qu_est_ce_qu_un_fact_checker', 'rubrique' => 'Académie'],
    ];

    /**
     * Le carrousel de `public/accueil.blade.php`, relevé ligne à ligne.
     *
     * @var list<array{nom: string, logo: string, url: string}>
     */
    private const PARTENAIRES = [
        ['nom' => 'Fondation de France', 'logo' => 'assets/images/partners/fondationdefrance.jpg', 'url' => 'https://www.fondationdefrance.org/fr/'],
        ['nom' => 'Aix Marseille Université', 'logo' => 'assets/images/partners/univ-amu.png', 'url' => 'https://www.univ-amu.fr/'],
        ['nom' => 'Union internationale des télécommunications', 'logo' => 'assets/images/partners/itu-int.png', 'url' => 'https://www.itu.int/fr/Pages/default.aspx'],
        ['nom' => 'CEMAAC Bénin', 'logo' => 'assets/images/partners/leolagrange.jpg', 'url' => 'https://leolagrange.bj/'],
        ['nom' => 'Fondation EVZ', 'logo' => 'assets/images/partners/fondation-evz.png', 'url' => 'https://www.evz.de/'],
        ['nom' => 'Office franco-allemand pour la Jeunesse', 'logo' => 'assets/images/partners/ofaj.png', 'url' => 'https://www.ofaj.org/'],
    ];

    public function run(): void
    {
        $this->semerLaFaq();
        $this->semerLesPartenaires();
    }

    private function semerLaFaq(): void
    {
        $rang = 0;

        foreach (self::QUESTIONS as $entree) {
            $rang += 10;

            foreach (['fr', 'en'] as $langue) {
                $question = trans('faq.'.$entree['cle'], [], $langue);
                $reponse = trans('faq.reponse_'.$entree['cle'], [], $langue);

                /*
                 | `trans()` rend la CLÉ quand la traduction manque. Une ligne
                 | qui vaudrait « faq.xxx » afficherait cette chaîne au public :
                 | on saute plutôt que de semer un texte cassé.
                 */
                if ($question === 'faq.'.$entree['cle'] || $reponse === 'faq.reponse_'.$entree['cle']) {
                    continue;
                }

                /*
                 | LE TEXTE, PAS LE BALISAGE. Les valeurs de `lang/*\/faq.php`
                 | portent le BOUTON D'ACCORDÉON entier — la version d'origine
                 | du gabarit les rendait en `{!! !!}`. Semées telles quelles,
                 | elles affichaient `<button class="accordion-button"…` en clair
                 | dans l'écran d'administration, et auraient fait de même en
                 | public une fois le texte échappé.
                 |
                 | La séparation est nette : la base porte la question, la vue
                 | porte l'accordéon.
                 */
                $question = $this->texteSeul($question);
                $reponse = $this->texteSeul($reponse);

                Faq::updateOrCreate(
                    ['locale' => $langue, 'group_key' => $entree['cle']],
                    [
                        'uuid' => (string) Str::uuid(),
                        'category' => $langue === 'fr' ? $entree['rubrique'] : $entree['rubrique'],
                        'question' => $question,
                        'answer' => $reponse,
                        'sort_order' => $rang,
                        'is_published' => true,
                    ],
                );
            }
        }
    }

    /**
     * Ramène une valeur de fichier de langue à son texte nu.
     *
     * `strip_tags` retire le balisage ; le chevron « ▾ » de l'accordéon est
     * une icône, pas un mot, et n'a rien à faire dans une question.
     */
    private function texteSeul(string $valeur): string
    {
        return trim(str_replace('▾', '', strip_tags($valeur)));
    }

    private function semerLesPartenaires(): void
    {
        $rang = 0;

        foreach (self::PARTENAIRES as $partenaire) {
            $rang += 10;

            Partner::updateOrCreate(
                ['name' => $partenaire['nom']],
                [
                    'uuid' => (string) Str::uuid(),
                    'logo_path' => $partenaire['logo'],
                    'url' => $partenaire['url'],
                    'sort_order' => $rang,
                    'is_published' => true,
                ],
            );
        }
    }
}
