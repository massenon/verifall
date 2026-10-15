<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une question de quiz.
 *
 * L'explication n'est pas décorative : un quiz qui note sans expliquer
 * n'apprend rien. C'est elle qui transforme une erreur en leçon.
 */
final class Question extends Model
{
    protected $fillable = ['uuid', 'quiz_id', 'position', 'prompt', 'explanation'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * Liaison par `uuid`, comme tout modèle atteignable de l'extérieur.
     *
     * ## Comment ce manque a été trouvé — et pourquoi il fallait le combler
     *
     * `ChecklistSecuriteTest` rapproche les modèles des routes par leur NOM :
     * il a signalé `Question` dès l'apparition de la route
     * `Staff\BanqueDeQuestions`, dont le nom contient « Question ». Le
     * rapprochement est approximatif — ce n'est PAS ce modèle-là que la route
     * sert, mais `QuestionBankItem`.
     *
     * Restait la vraie question : `Question` doit-il se lier par `id` ? Non.
     * La règle 4 du chantier est sans exception (« jamais d'identifiant
     * auto-incrémenté exposé »), la colonne `uuid` existe depuis la migration
     * d'origine, et cet énoncé est servi aux citoyens qui passent le quiz. Le
     * déclarer coûte une ligne et ferme la porte avant qu'une route l'ouvre.
     *
     * Desserrer la garde aurait été l'autre voie : elle aurait alors cessé de
     * voir les modèles réellement liés dont le nom ressemble à celui d'un écran.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return BelongsTo<Quiz, $this>
     */
    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    /**
     * @return HasMany<Answer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class)->orderBy('position');
    }

    public function bonneReponse(): ?Answer
    {
        return $this->answers()->where('is_correct', true)->first();
    }
}
