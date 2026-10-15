<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use App\Models\LiveClass;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Les Live Classes du prototype (compte-user/live-classes.html).
 *
 * Les dates sont RELATIVES au jour du semis — « demain à 15:00 », « vendredi à
 * 18:30 » — parce qu'une démonstration dont les séances sont toutes passées ne
 * montre plus rien. Le prototype fait pareil : il calcule ses dates à
 * l'ouverture de la page.
 *
 * La séance « en direct » a démarré il y a un quart d'heure : c'est le seul
 * moyen de voir l'état « EN DIRECT » sans attendre le bon moment de la journée.
 */
final class LiveClassSeeder extends Seeder
{
    public function run(): void
    {
        $seances = [
            [
                'title' => 'Fact-checking avancé : cas pratiques béninois',
                'cours' => 'Introduction au fact-checking',
                'animateur' => 'Dr. Samuel A.',
                'debut' => now()->addDay()->setTime(15, 0),
                'minutes' => 60,
                'zoom' => 'https://zoom.us/j/8214479021',
            ],
            [
                'title' => 'Atelier OSINT : géolocaliser une photo en direct',
                'cours' => 'OSINT Fondamentaux',
                'animateur' => 'Atman Bouba',
                'debut' => now()->addDays(4)->setTime(18, 30),
                'minutes' => 90,
                'zoom' => 'https://zoom.us/j/8214479022',
            ],
            [
                'title' => 'Q&R — Détecter les images IA',
                'cours' => 'Détection des Deepfakes',
                'animateur' => 'Gloria Tchidime',
                'debut' => now()->subMinutes(15),
                'minutes' => 45,
                'zoom' => 'https://zoom.us/j/8214479023',
            ],
            [
                'title' => 'Introduction : vos premiers réflexes de vérification',
                'cours' => 'Introduction au fact-checking',
                'animateur' => 'Dr. Samuel A.',
                'debut' => now()->subDays(6)->setTime(15, 0),
                'minutes' => 60,
                'zoom' => null,
            ],
            [
                'title' => 'Métadonnées et archives web',
                'cours' => 'OSINT Fondamentaux',
                'animateur' => 'Atman Bouba',
                'debut' => now()->subDays(14)->setTime(18, 30),
                'minutes' => 75,
                'zoom' => null,
            ],
        ];

        foreach ($seances as $seance) {
            $cours = Course::where('title', 'like', $seance['cours'].'%')->first();
            $animateur = User::where('name', 'like', '%'.$seance['animateur'].'%')->first();

            LiveClass::updateOrCreate(
                ['title' => $seance['title']],
                [
                    'uuid' => (string) Str::uuid(),
                    'course_id' => $cours?->id,
                    'host_id' => $animateur?->id,
                    'description' => null,
                    'starts_at' => $seance['debut'],
                    'minutes' => $seance['minutes'],
                    'join_url' => $seance['zoom'],
                    'seats' => 100,
                    'is_published' => true,
                ],
            );
        }

        $this->command?->info('  '.LiveClass::count().' Live Classes.');
    }
}
