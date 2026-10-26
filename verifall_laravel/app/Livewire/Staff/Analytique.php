<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Application\Analytique\IndicateursLibres;
use App\Core\Application\Contracts\DepotAnalytique;
use App\Core\Marketing\Seo\DonneesGoogle;
use App\Core\Marketing\Seo\GoogleIndisponible;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Analytique : Académie, IA de vérification, Revenus et quotas, Business
 * Intelligence (indicateurs choisis dans une liste fermée) et SEO (Search
 * Console, GA4, via le compte de service saisi dans Paramètres › SEO).
 *
 * Réservé à `analytics.view`. Rien n'est simulé : une mesure absente s'affiche
 * comme telle (« aucune donnée »), jamais comme un zéro rassurant ni un chiffre
 * d'exemple. Un Google non configuré ou en panne le dit, il ne retombe pas sur
 * des chiffres d'exemple.
 */
#[Layout('components.layouts.staff', ['title' => "Analytique · Vérif'All"])]
final class Analytique extends Component
{
    #[Url(as: 'vue', except: 'academie')]
    public string $vue = 'academie';

    #[Url(as: 'indicateur', except: 'verifications')]
    public string $indicateur = 'verifications';

    #[Url(as: 'jours', except: 30)]
    public int $duree = 30;

    public function mount(): void
    {
        abort_unless(auth('staff')->user()?->can('analytics.view'), 403);

        if (! in_array($this->vue, ['academie', 'ia', 'revenus', 'bi', 'seo'], true)) {
            $this->vue = 'academie';
        }

        if (! array_key_exists($this->indicateur, IndicateursLibres::CATALOGUE)) {
            $this->indicateur = 'verifications';
        }

        if (! in_array($this->duree, IndicateursLibres::DUREES, true)) {
            $this->duree = 30;
        }
    }

    /** Un changement d'indicateur ou de durée venu du navigateur est borné comme au montage. */
    public function updated(string $propriete): void
    {
        if ($propriete === 'indicateur' && ! array_key_exists($this->indicateur, IndicateursLibres::CATALOGUE)) {
            $this->indicateur = 'verifications';
        }

        if ($propriete === 'duree' && ! in_array($this->duree, IndicateursLibres::DUREES, true)) {
            $this->duree = 30;
        }
    }

    public function exporterLaSerie(IndicateursLibres $indicateurs): StreamedResponse
    {
        $serie = $indicateurs->serie($this->indicateur, $this->duree);

        return response()->streamDownload(function () use ($serie): void {
            $sortie = fopen('php://output', 'wb');
            fputcsv($sortie, ['jour', $serie['libelle'].($serie['unite'] !== '' ? ' ('.$serie['unite'].')' : '')], ';', '"', '');
            foreach ($serie['jours'] as $point) {
                fputcsv($sortie, [$point['jour'], $point['valeur']], ';', '"', '');
            }
            fclose($sortie);
        }, "indicateur-{$this->indicateur}-{$this->duree}j.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(DepotAnalytique $depot, IndicateursLibres $indicateurs, DonneesGoogle $google): View
    {
        $seo = null;

        if ($this->vue === 'seo') {
            $seo = ['statut' => $google->statut(), 'erreur' => null, 'gsc' => null, 'ga4' => null];

            foreach (['gsc' => 'searchConsole', 'ga4' => 'analytics'] as $cle => $methode) {
                if (! $seo['statut'][$cle]) {
                    continue;
                }

                try {
                    $seo[$cle] = $google->{$methode}();
                } catch (GoogleIndisponible $e) {
                    $seo[$cle.'_erreur'] = $e->getMessage();
                }
            }
        }

        return view('livewire.staff.analytique', [
            'seo' => $seo,
            'serie' => $this->vue === 'bi' ? $indicateurs->serie($this->indicateur, $this->duree) : null,
            'academie' => $this->vue === 'academie' ? $depot->academie() : null,
            'moteurs' => $this->vue === 'ia' ? $depot->moteurs() : null,
            'revenus' => $this->vue === 'revenus' ? $depot->revenus() : null,
        ]);
    }
}
