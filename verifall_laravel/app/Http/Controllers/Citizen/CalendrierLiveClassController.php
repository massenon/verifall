<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Models\LiveClass;
use Symfony\Component\HttpFoundation\Response;

/**
 * « Ajouter au calendrier » — un fichier iCalendar pour une Live Class.
 *
 * Le prototype composait ce fichier en JavaScript. Ici c'est le serveur : la
 * date de la séance appartient à la base, et un fuseau recalculé côté
 * navigateur décale le rendez-vous d'une heure deux fois par an.
 *
 * Les heures sont écrites en UTC (suffixe `Z`), la seule forme qu'aucun agenda
 * n'interprète de travers.
 */
final class CalendrierLiveClassController
{
    public function __invoke(LiveClass $liveClass): Response
    {
        abort_unless($liveClass->is_published, 404);

        $debut = $liveClass->starts_at->clone()->utc();
        $fin = $debut->clone()->addMinutes($liveClass->minutes);

        $lignes = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Verifall//LiveClass//FR',
            'BEGIN:VEVENT',
            'UID:'.$liveClass->uuid.'@verifall.com',
            'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z'),
            'DTSTART:'.$debut->format('Ymd\THis\Z'),
            'DTEND:'.$fin->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->echapper($liveClass->title),
            'DESCRIPTION:'.$this->echapper(
                "Live Class Vérif'All — ".($liveClass->course?->title ?? 'séance ouverte')
                .($liveClass->host !== null ? ' (mentor : '.$liveClass->host->name.')' : '')
            ),
            'LOCATION:'.$this->echapper((string) $liveClass->join_url),
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return response(implode("\r\n", $lignes), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="verifall-live-class.ics"',
        ]);
    }

    /**
     * Échappement iCalendar (RFC 5545).
     *
     * Une virgule non échappée coupe la valeur en deux et l'agenda affiche un
     * titre tronqué — un « Q&R, images IA » deviendrait « Q&R ».
     */
    private function echapper(string $valeur): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", ',', ';'],
            ['\\\\', '\\n', '\\n', '\\,', '\\;'],
            $valeur,
        );
    }
}
