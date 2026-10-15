<?php

declare(strict_types=1);

namespace App\Core\Marketing\Seo;

use App\Enums\VerdictFactCheck;

/**
 * Traduit un verdict Vérif'All en note `ClaimReview` de schema.org.
 *
 * ## Pourquoi cette classe existe plutôt qu'un `match` en ligne
 *
 * Parce que cette correspondance est une DÉCLARATION PUBLIQUE. `ClaimReview`
 * est le balisage que Google lit pour afficher « Selon Vérif'All : FAUX »
 * directement dans ses résultats, et que les plateformes s'échangent. Se
 * tromper d'échelle ici, c'est publier le contraire de ce qu'on a vérifié.
 *
 * ## L'échelle, et le piège qu'elle recèle
 *
 * `ratingValue` va de `worstRating` à `bestRating` et mesure **la véracité de
 * l'affirmation examinée**, pas la qualité de notre travail. 1 = l'affirmation
 * est fausse, 5 = elle est vraie. Le prototype le fait dans ce sens :
 * `ratingValue: 1` avec `alternateName: "FAUX"`.
 *
 * Le cas délicat est « non vérifiable ». Il ne vaut PAS 3 sur 5 : une note
 * médiane dit « à moitié vrai », ce qui est une affirmation sur le fond. Or nous
 * n'affirmons rien — nous disons que la vérification n'a pas pu aboutir.
 * Google prévoit exactement ce cas : on omet la note chiffrée et on ne garde
 * que le libellé. C'est pourquoi `valeur` est nullable.
 */
final readonly class NoteDuVerdict
{
    private function __construct(
        public ?int $valeur,
        public string $libelle,
    ) {}

    public static function depuis(VerdictFactCheck|string|null $verdict): self
    {
        $cas = $verdict instanceof VerdictFactCheck
            ? $verdict
            : VerdictFactCheck::tryFrom((string) $verdict);

        return match ($cas) {
            VerdictFactCheck::Vrai => new self(5, 'VRAI'),
            VerdictFactCheck::Trompeur => new self(2, 'TROMPEUR'),
            VerdictFactCheck::Faux => new self(1, 'FAUX'),
            // Aucune note : ne rien affirmer vaut mieux qu'affirmer « à moitié ».
            VerdictFactCheck::NonVerifiable => new self(null, 'NON VÉRIFIABLE'),
            null => new self(null, 'NON VÉRIFIABLE'),
        };
    }
}
