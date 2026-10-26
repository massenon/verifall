<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Contracts\DepotAnalytique;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Analytique : Académie, IA de vérification, Revenus et quotas.
 *
 * Réservé à `analytics.view`. Rien n'est simulé : une mesure absente s'affiche
 * comme telle (« aucune donnée »), jamais comme un zéro rassurant ni un chiffre
 * d'exemple. La Business Intelligence (indicateurs libres) et le SEO (Search
 * Console, GA4) ne sont pas ici : ils demandent des accès qui n'existent pas.
 */
#[Layout('components.layouts.staff', ['title' => "Analytique · Vérif'All"])]
final class Analytique extends Component
{
    #[Url(as: 'vue', except: 'academie')]
    public string $vue = 'academie';

    public function mount(): void
    {
        abort_unless(auth('staff')->user()?->can('analytics.view'), 403);

        if (! in_array($this->vue, ['academie', 'ia', 'revenus'], true)) {
            $this->vue = 'academie';
        }
    }

    public function render(DepotAnalytique $depot): View
    {
        return view('livewire.staff.analytique', [
            'academie' => $this->vue === 'academie' ? $depot->academie() : null,
            'moteurs' => $this->vue === 'ia' ? $depot->moteurs() : null,
            'revenus' => $this->vue === 'revenus' ? $depot->revenus() : null,
        ]);
    }
}
