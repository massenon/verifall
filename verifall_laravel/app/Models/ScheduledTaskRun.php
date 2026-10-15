<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Une exécution du planificateur.
 *
 * ## Ce que cette table prouve, et que rien d'autre ne prouvait
 *
 * `verifall:diagnostic` compte les tâches DÉCLARÉES et rappelle qu'aucune ne
 * s'exécute sans une entrée cron appelant `schedule:run`. Il ne peut pas dire
 * si ce cron existe : rien ne l'atteste côté application.
 *
 * Une ligne ici atteste. Et son ABSENCE atteste aussi : une tâche déclarée
 * dont aucune exécution n'est enregistrée, c'est un cron absent.
 */
final class ScheduledTaskRun extends Model
{
    public const SUCCES = 'success';

    public const ECHEC = 'failed';

    protected $fillable = ['task', 'status', 'duration_ms', 'output', 'ran_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['ran_at' => 'datetime', 'duration_ms' => 'integer'];
    }

    public function aEchoue(): bool
    {
        return $this->status === self::ECHEC;
    }
}
