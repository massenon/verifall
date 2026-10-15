<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\XpLogRepositoryInterface;
use App\Models\User;
use App\Models\XpLog;

final class EloquentXpLogRepository implements XpLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributs
     */
    public function enregistrer(array $attributs): XpLog
    {
        return XpLog::create($attributs);
    }

    /**
     * @return array<string, int>
     */
    public function repartition(User $utilisateur): array
    {
        /*
         | Borné au propriétaire ICI, et pas dans l'appelant : c'est à cet
         | endroit qu'une fuite se produirait. Même discipline que le journal
         | des unités.
         |
         | Pas de cache : quelqu'un qui vient de finir un cours ouvre cet écran
         | pour y voir ses points. Servir une version d'il y a cinq minutes
         | ferait croire à une récompense perdue.
         */
        return XpLog::where('user_id', $utilisateur->id)
            ->selectRaw('reason, SUM(delta) as total')
            ->groupBy('reason')
            ->pluck('total', 'reason')
            ->map(static fn ($total): int => (int) $total)
            ->all();
    }

    public function somme(User $utilisateur): int
    {
        return (int) XpLog::where('user_id', $utilisateur->id)->sum('delta');
    }
}
