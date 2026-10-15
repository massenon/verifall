<?php

declare(strict_types=1);

use App\Core\Identity\Notifications\AnalyseTerminee;
use App\Enums\StatutVerification;
use App\Enums\TypeContenu;
use App\Enums\VerdictFactCheck;
use App\Models\AnalysisReport;
use App\Models\Verification;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| « Verdict : FAUX — … » doit réellement paraître
|--------------------------------------------------------------------------
| `AnalyseTerminee::texte()` lisait `$verification->verdict` — un attribut
| qui n'existe pas sur `Verification` ; Eloquent rend `null` sans jamais
| lever, donc CETTE branche était toujours prise, verdict ou pas. Trouvé en
| traçant les 20 lecteurs de `AnalysisReport::verdict` pour le contrat V-8.
*/

function uneVerificationTerminee(): Verification
{
    return Verification::create([
        'uuid' => (string) Str::uuid(),
        'type' => TypeContenu::Texte->value,
        'status' => StatutVerification::Terminee->value,
        'claim' => 'Une affirmation à vérifier.',
        'cost_units' => 0,
    ]);
}

it('nomme le verdict du rapport, pas un attribut qui n\'existe pas', function (): void {
    $verification = uneVerificationTerminee();

    AnalysisReport::create([
        'uuid' => (string) Str::uuid(),
        'verification_id' => $verification->id,
        'verdict' => VerdictFactCheck::Faux->value,
        'confidence' => 80,
    ]);

    $texte = (new AnalyseTerminee($verification->fresh()))->texte();

    expect($texte)->toContain('Verdict :')
        ->and($texte)->toContain(VerdictFactCheck::Faux->libelle());
});

it('reste honnête quand le rapport n\'a vraiment aucun verdict', function (): void {
    $verification = uneVerificationTerminee();

    $texte = (new AnalyseTerminee($verification))->texte();

    expect($texte)->toBe('Votre analyse est terminée.');
});
