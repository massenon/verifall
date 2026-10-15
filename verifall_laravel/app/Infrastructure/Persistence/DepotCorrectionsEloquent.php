<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotCorrections;
use App\Models\VerdictRevision;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

final class DepotCorrectionsEloquent implements DepotCorrections
{
    /**
     * Quinze minutes.
     *
     * L'historique d'une vérification est lu à chaque affichage de sa page et
     * change quelques fois par an ; le cache est ici pour la page publique,
     * pas pour le back-office. Il est oublié dès qu'une correction est
     * enregistrée, donc la fenêtre de péremption réelle est nulle.
     */
    private const TTL_MINUTES = 15;

    /**
     * @param  array<string, mixed>  $attributs
     */
    public function enregistrer(array $attributs): VerdictRevision
    {
        $revision = VerdictRevision::create($attributs);

        Cache::forget($this->cleContenu(
            (string) $revision->revisable_type,
            (string) $revision->revisable_id,
        ));

        return $revision;
    }

    /**
     * @return LengthAwarePaginator<int, VerdictRevision>
     */
    public function journal(int $parPage = 15): LengthAwarePaginator
    {
        return VerdictRevision::query()
            // Le lien vers la vérification corrigée est calculé pour chaque
            // ligne : sans ce chargement, une page de quinze corrections
            // ferait quinze requêtes de plus.
            ->with('contenu')
            ->latest('created_at')
            ->latest('id')
            ->paginate($parPage);
    }

    /**
     * @return Collection<int, VerdictRevision>
     */
    public function pour(Model $contenu): Collection
    {
        /** @var Collection<int, VerdictRevision> */
        return Cache::remember(
            $this->cle($contenu),
            now()->addMinutes(self::TTL_MINUTES),
            static fn (): Collection => VerdictRevision::query()
                ->where('revisable_type', $contenu->getMorphClass())
                ->where('revisable_id', $contenu->getKey())
                ->latest('created_at')
                ->latest('id')
                ->get(),
        );
    }

    public function nombrePour(Model $contenu): int
    {
        return $this->pour($contenu)->count();
    }

    public function oublierLeCache(Model $contenu): void
    {
        Cache::forget($this->cle($contenu));
    }

    private function cle(Model $contenu): string
    {
        return $this->cleContenu($contenu->getMorphClass(), (string) $contenu->getKey());
    }

    private function cleContenu(string $type, string $identifiant): string
    {
        // Le type est haché : une classe pleinement qualifiée contient des
        // antislashs, que certains magasins de cache traitent en séparateurs.
        return 'corrections.contenu.'.md5($type).'.'.$identifiant;
    }
}
