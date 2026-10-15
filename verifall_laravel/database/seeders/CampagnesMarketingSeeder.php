<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\TypeCampagne;
use App\Models\MarketingCampaign;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Les trois campagnes d'affichage fournies par le porteur.
 *
 * ## Ce qui a été repris tel quel
 *
 * Le slug, le titre, le texte, l'appel à l'action, la destination, la teinte,
 * la forme et l'état actif : tout vient de la liste transmise, au caractère
 * près. `newsletter-boom` est bien livrée **inactive**, comme demandé — c'est
 * un réglage, pas un oubli.
 *
 * ## Les deux écarts, et leur raison
 *
 * **Les chemins d'images.** La liste indiquait `landing/assets/images/...` et
 * l'extension `.png`. Sur ce dépôt, les visuels vivent sous
 * `assets/images/campaigns/` et existent en `.webp` — 22 à 50 Ko, contre 514 Ko
 * pour le seul `.png` disponible. Deux des trois `.png` n'existent d'ailleurs
 * pas du tout : les référencer aurait produit trois cadres vides.
 *
 * **Les délais d'ouverture.** La liste n'en donnait pas. Une fenêtre qui
 * s'ouvre à la seconde où la page s'affiche est écartée sans être lue — le
 * réflexe est de fermer avant d'avoir regardé. Huit secondes laissent voir la
 * page d'abord. Ce n'est pas une donnée inventée mais un défaut de la table,
 * modifiable écran par écran.
 */
final class CampagnesMarketingSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->campagnes() as $rang => $donnees) {
            $campagne = MarketingCampaign::firstOrNew(['slug' => $donnees['slug']]);

            $campagne->forceFill(array_merge($donnees, [
                'uuid' => $campagne->uuid ?? (string) Str::uuid(),
                'position' => $rang + 1,
            ]))->save();
        }

        $this->command?->info(sprintf(
            '  %d campagnes (%d diffusables).',
            MarketingCampaign::count(),
            MarketingCampaign::diffusable()->count(),
        ));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function campagnes(): array
    {
        return [
            [
                'slug' => 'whatsapp-africa',
                'title' => 'Alerte WhatsApp',
                'text' => 'Rejoignez notre chaîne WhatsApp pour recevoir les alertes démenties '
                    .'en temps réel sur votre mobile.',
                'cta' => "S'abonner gratuitement",
                'url' => 'https://whatsapp.com/channel/verifall-benin',
                'image' => 'assets/images/campaigns/whatsapp-factcheck.webp',
                'color' => 'success',
                'type' => TypeCampagne::Fenetre->value,
                'is_active' => true,
                'delay_seconds' => 8,
            ],
            [
                'slug' => 'democracy-alert',
                'title' => 'Défendez la Vérité',
                'text' => 'La désinformation tue la démocratie. Devenez un acteur du changement '
                    .'en vérifiant chaque info suspecte.',
                'cta' => 'Lancer un scan IA',
                'url' => '/verifier-une-information',
                'image' => 'assets/images/campaigns/democracy-alert.webp',
                'color' => 'primary',
                'type' => TypeCampagne::Bandeau->value,
                'is_active' => true,
                // Sans objet pour un bandeau : il s'insère dans le flux et
                // n'attend rien. La colonne garde son défaut.
                'delay_seconds' => 0,
            ],
            [
                'slug' => 'newsletter-boom',
                'title' => 'Le Boom des Fakes',
                'text' => 'Ne ratez rien ! Recevez chaque lundi le récapitulatif des pires intox '
                    .'détectées au Bénin durant la semaine.',
                'cta' => "M'inscrire à la veille",
                /*
                 | ANCRE ABSOLUE, et non `#newsletter-section`.
                 |
                 | Une ancre relative ne mène quelque part que si la page
                 | courante porte déjà le bloc. Or la fenêtre s'affiche sur
                 | n'importe quelle page publique : depuis les mentions
                 | légales, le bouton défilerait jusqu'à rien.
                 |
                 | Avec la barre oblique, le navigateur va à l'accueil puis
                 | défile jusqu'au bloc — depuis n'importe où. C'est ce qui
                 | permet de ne PAS poser le bloc sur les vingt-deux pages
                 | (arbitrage 166).
                 */
                'url' => '/#newsletter-section',
                'image' => 'assets/images/campaigns/newsletter-boom.webp',
                'color' => 'info',
                'type' => TypeCampagne::Fenetre->value,
                // Inactive, comme demandé.
                'is_active' => false,
                'delay_seconds' => 12,
            ],
        ];
    }
}
