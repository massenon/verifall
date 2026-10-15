<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Core\Identity\Services\ExportDonneesService;
use App\Mail\ExportPretMail;
use App\Models\DataExport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Construit l'archive de portabilité hors de la requête HTTP.
 *
 * Rassembler et compresser les données d'un compte peut prendre plusieurs
 * secondes ; le faire pendant la requête ferait attendre — puis expirer — la
 * page. La personne reçoit un courriel lorsque l'archive est prête.
 */
final class PreparerExportDonnees implements ShouldQueue
{
    use Queueable;

    /** Trois tentatives : un échec de disque est souvent passager. */
    public int $tries = 3;

    public function __construct(public readonly int $exportId) {}

    public function handle(ExportDonneesService $exports): void
    {
        $export = DataExport::with('user')->find($this->exportId);

        // La demande a pu être annulée — ou le compte supprimé — entre-temps.
        if ($export === null || $export->user === null) {
            return;
        }

        $exports->construire($export);

        Mail::to($export->user->email)->send(new ExportPretMail($export));
    }

    /**
     * Un échec doit se VOIR dans l'interface : sans cela, la page afficherait
     * « préparation en cours » indéfiniment.
     */
    public function failed(?Throwable $exception): void
    {
        DataExport::where('id', $this->exportId)
            ->update(['status' => DataExport::ECHEC]);
    }
}
