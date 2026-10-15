<?php

declare(strict_types=1);

use App\Core\FactChecking\Adapters\ConstructeurDePrompt;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Enums\TypeContenu;

/*
|--------------------------------------------------------------------------
| Le prompt reçoit les consignes du TYPE de contenu
|--------------------------------------------------------------------------
|
| Les preuves qui font un rapport n'ont rien de commun d'un type à l'autre :
| le Journal officiel pour un texte, l'ancienneté du domaine pour un lien, les
| plaques et les uniformes pour une image. Un gabarit unique faisait chercher
| au modèle des « indices visuels » dans un message texte.
|
| Ces tests gardent deux choses : que les cinq fragments existent — un fichier
| renommé ne lève pas, donc rien d'autre ne le dirait — et qu'aucun marqueur
| ne reste non substitué dans le prompt envoyé.
|
*/

function sacDe(TypeContenu $type, string $affirmation = 'Une affirmation à vérifier.'): SacDePreuves
{
    return new SacDePreuves(
        affirmation: $affirmation,
        type: $type,
        contexte: null,
        cheminFichier: null,
    );
}

it('porte un fragment de consignes pour CHACUN des cinq types', function (TypeContenu $type): void {
    $chemin = resource_path('prompts/factcheck/types/'.$type->value.'.md');

    expect($chemin)->toBeFile(
        "Le fragment de consignes de {$type->value} est introuvable. "
        .'`ConstructeurDePrompt` ne lève pas quand il manque — pour ne pas faire '
        .'échouer une analyse payée en unités — donc ce test est le seul garde-fou.',
    );

    expect(trim((string) file_get_contents($chemin)))->not->toBe('');
})->with(fn () => TypeContenu::cases());

it('injecte bien le fragment du type demandé, et pas un autre', function (): void {
    $prompts = app(ConstructeurDePrompt::class);

    $texte = $prompts->pour(sacDe(TypeContenu::Texte));
    $lien = $prompts->pour(sacDe(TypeContenu::Lien));
    $image = $prompts->pour(sacDe(TypeContenu::Image));

    expect($texte)->toContain('TYPE DE CONTENU : TEXTE OU RUMEUR');
    expect($texte)->not->toContain('TYPE DE CONTENU : LIEN');

    expect($lien)->toContain('TYPE DE CONTENU : LIEN OU ADRESSE WEB');
    expect($lien)->not->toContain('TYPE DE CONTENU : IMAGE');

    expect($image)->toContain('TYPE DE CONTENU : IMAGE');
    expect($image)->not->toContain('TYPE DE CONTENU : TEXTE');
});

/*
 | Un marqueur oublié part tel quel chez le fournisseur. Le modèle lit alors
 | « {{CONSIGNES_DU_TYPE}} » comme du texte, et on paie l'appel pour un prompt
 | incomplet sans qu'aucune erreur ne le signale.
 */
it('ne laisse AUCUN marqueur non substitué dans le prompt envoyé', function (TypeContenu $type): void {
    $prompt = app(ConstructeurDePrompt::class)->pour(sacDe($type));

    expect($prompt)->not->toMatch('/\{\{[A-Z_]+\}\}/');
})->with(fn () => TypeContenu::cases());

it('impose la structure rédactionnelle et l’ancrage de la première phrase', function (): void {
    $prompt = app(ConstructeurDePrompt::class)->pour(sacDe(TypeContenu::Texte));

    // Verrou 2 de la directive : la première phrase ne peut pas tourner autour.
    expect($prompt)->toContain('CONTRAINTE D\'ALIGNEMENT');
    expect($prompt)->toContain('STRUCTURE IMPOSÉE DE reasoning_fr');
    expect($prompt)->toContain('Recoupement des sources et preuves matérielles');
    expect($prompt)->toContain('À retenir');
});

it('n’écrit aucune année en dur — le site doit survivre au 31 décembre', function (): void {
    /*
     | Une consigne qui nomme « 2025 » ou « 2026 » devient fausse le 1er
     | janvier, et personne ne pense à relire un prompt. L'ancrage temporel
     | passe par `{{DATE}}`, substitué à chaque appel.
     */
    $fichiers = glob(resource_path('prompts/factcheck/*.md')) ?: [];
    $fichiers = array_merge($fichiers, glob(resource_path('prompts/factcheck/types/*.md')) ?: []);

    expect($fichiers)->not->toBeEmpty();

    $fautifs = [];

    foreach ($fichiers as $fichier) {
        $contenu = (string) file_get_contents($fichier);

        /*
         | Les années citées comme DATE D'UN FAIT dans un exemple sont
         | légitimes — « la vidéo date de 2022 » illustre un recyclage. Ce qu'on
         | refuse, c'est une année employée comme REPÈRE DU PRÉSENT.
         */
        if (preg_match('/(?:nous sommes en|année courante|en cours)\D{0,20}20\d\d/iu', $contenu) === 1) {
            $fautifs[] = basename($fichier);
        }
    }

    expect($fautifs)->toBe([], 'Ancrage temporel figé dans : '.implode(', ', $fautifs));
});
