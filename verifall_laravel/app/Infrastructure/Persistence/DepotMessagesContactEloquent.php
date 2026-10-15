<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Core\Application\Contracts\DepotMessagesContact;
use App\Enums\StatutMessageContact;
use App\Models\ContactMessage;
use App\Models\User;
use App\Support\DataTables\PageDeResultats;
use App\Support\DataTables\RequeteDataTables;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class DepotMessagesContactEloquent implements DepotMessagesContact
{
    /** Clé publique → colonne SQL. Tout ce qui n'y figure pas n'atteint jamais `orderBy()`. */
    private const COLONNES_SQL = [
        'expediteur' => 'name',
        'statut' => 'status',
        'nature' => 'kind',
        'recuLe' => 'created_at',
    ];

    /** @return PageDeResultats<ContactMessage> */
    public function pourTableau(RequeteDataTables $requete): PageDeResultats
    {
        $total = ContactMessage::query()->count();

        $filtree = ContactMessage::query();
        $this->filtrerPourTableau($filtree, $requete);

        $nombreFiltre = $requete->restreint() ? (clone $filtree)->count() : $total;

        if ($requete->tri === []) {
            $filtree->latest('created_at');
        } else {
            foreach ($requete->tri as ['colonne' => $cle, 'sens' => $sens]) {
                $filtree->orderBy(self::COLONNES_SQL[$cle], $sens);
            }
        }

        $lignes = $filtree
            ->skip($requete->debut)
            ->take($requete->longueur)
            ->get();

        return new PageDeResultats(
            total: $total,
            filtres: $nombreFiltre,
            lignes: $lignes,
            tronque: $requete->export && $nombreFiltre > $requete->longueur,
        );
    }

    /**
     * @param  Builder<ContactMessage>  $requete
     */
    private function filtrerPourTableau(Builder $requete, RequeteDataTables $criteres): void
    {
        $filtres = $criteres->filtres;

        if ($criteres->recherche !== '') {
            $motif = '%'.$criteres->recherche.'%';

            $requete->where(function (Builder $ou) use ($motif): void {
                $ou->where('name', 'like', $motif)
                    ->orWhere('email', 'like', $motif)
                    ->orWhere('organisation', 'like', $motif)
                    ->orWhere('message', 'like', $motif);
            });
        }

        if (isset($filtres['statut'])) {
            $requete->where('status', $filtres['statut']);
        }

        if (isset($filtres['nature'])) {
            $requete->where('kind', $filtres['nature']);
        }

        if (isset($filtres['recu_du'])) {
            $requete->where('created_at', '>=', Carbon::createFromFormat('Y-m-d', $filtres['recu_du'])?->startOfDay());
        }

        if (isset($filtres['recu_au'])) {
            $requete->where('created_at', '<=', Carbon::createFromFormat('Y-m-d', $filtres['recu_au'])?->endOfDay());
        }
    }

    /** @param array<string, mixed> $donnees */
    public function enregistrer(array $donnees): ContactMessage
    {
        return ContactMessage::query()->create([
            'uuid' => (string) Str::uuid(),
            'kind' => $donnees['kind'] ?? 'contact',
            'name' => $donnees['name'],
            'email' => mb_strtolower(trim((string) $donnees['email'])),
            'phone' => $donnees['phone'] ?? null,
            'organisation' => $donnees['organisation'] ?? null,
            'message' => $donnees['message'],
            'donation_amount' => $donnees['donation_amount'] ?? null,
            'donation_recurrence' => $donnees['donation_recurrence'] ?? null,
            'status' => StatutMessageContact::Nouveau->value,
            'ip_hash' => $donnees['ip_hash'] ?? null,
        ]);
    }

    public function parUuid(string $uuid): ?ContactMessage
    {
        return ContactMessage::query()->where('uuid', $uuid)->first();
    }

    public function envoisRecents(string $empreinteIp, int $minutes): int
    {
        return ContactMessage::query()
            ->where('ip_hash', $empreinteIp)
            ->where('created_at', '>=', now()->subMinutes($minutes))
            ->count();
    }

    /** @return LengthAwarePaginator<int, ContactMessage> */
    public function paginer(
        ?string $recherche,
        ?StatutMessageContact $statut,
        int $parPage,
        ?string $nature = null,
    ): LengthAwarePaginator {
        return ContactMessage::query()
            ->with('traitePar:id,name')
            ->when(
                $recherche !== null && $recherche !== '',
                static function (Builder $requete) use ($recherche): void {
                    /*
                     | `LIKE` sans `LOWER()` : la collation par défaut de MySQL
                     | est déjà insensible à la casse, et envelopper la colonne
                     | dans une fonction interdirait à l'index de servir.
                     */
                    $motif = '%'.$recherche.'%';

                    $requete->where(function (Builder $ou) use ($motif): void {
                        $ou->where('name', 'like', $motif)
                            ->orWhere('email', 'like', $motif)
                            ->orWhere('organisation', 'like', $motif)
                            ->orWhere('message', 'like', $motif);
                    });
                },
            )
            ->when($statut !== null, static fn (Builder $r): Builder => $r->where('status', $statut->value))
            ->when($nature !== null && $nature !== '', static fn (Builder $r): Builder => $r->where('kind', $nature))
            ->latest('created_at')
            ->paginate($parPage);
    }

    /** @return array<string, int> */
    public function compteurs(): array
    {
        $parStatut = ContactMessage::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $compteurs = ['total' => array_sum($parStatut)];

        foreach (StatutMessageContact::cases() as $cas) {
            $compteurs[$cas->value] = (int) ($parStatut[$cas->value] ?? 0);
        }

        return $compteurs;
    }

    public function changerStatut(
        ContactMessage $message,
        StatutMessageContact $statut,
        User $acteur,
        ?string $note = null,
    ): ContactMessage {
        $message->forceFill([
            'status' => $statut->value,
            'handled_by' => $acteur->id,
            'handled_at' => now(),
            'handling_note' => $note,
        ])->save();

        return $message->refresh();
    }
}
