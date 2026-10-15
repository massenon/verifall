<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\FactChecking\ValueObjects\Preuve;

/**
 * Combien de VOIX distinctes parlent, pas combien de citations existent.
 *
 * Phase 5. Le défaut qu'elle referme : « 5 sources consultées » peut être
 * cinq articles du MÊME quotidien, cinq pages du même portail — une seule
 * rédaction, comptée cinq fois. Ce n'est pas un recoupement, c'est un écho.
 *
 * Elle s'appuie sur le domaine ENREGISTRABLE de {@see DomaineDeLaSource},
 * déjà le dénominateur de la déduplication (phase 5) : deux preuves du même
 * éditeur sous des sous-domaines de présentation différents ne sont pas deux
 * voix non plus.
 *
 * ## Sa limite assumée
 *
 * Deux quotidiens DISTINCTS qui republient la MÊME dépêche d'agence (AFP,
 * Reuters) restent comptés comme deux voix ici : détecter une syndication de
 * CONTENU exigerait de comparer les textes, pas seulement les domaines, et
 * cela n'existe pas dans ce dépôt. Le signal reste correct pour le cas de
 * loin le plus fréquent — un même site cité plusieurs fois — et c'est lui
 * que {@see FactCheckScoringService} (phase 8)
 * consulte pour ne pas confondre consensus et corroboration.
 */
final class IndependanceDesSources
{
    public function __construct(
        private readonly DomaineDeLaSource $domaines = new DomaineDeLaSource,
    ) {}

    /**
     * Le nombre d'ÉDITEURS distincts derrière un lot de preuves.
     *
     * @param  list<Preuve>  $preuves
     */
    public function nombreDeVoixIndependantes(array $preuves): int
    {
        return count(array_unique(array_map(
            fn (Preuve $preuve): string => $this->voix($preuve),
            $preuves,
        )));
    }

    /**
     * Tout le lot vient-il d'UNE SEULE voix ?
     *
     * Un lot vide n'est une voix unique de personne : il n'y a rien à
     * corroborer, ce qui est différent d'un écho.
     *
     * @param  list<Preuve>  $preuves
     */
    public function uneSeuleVoix(array $preuves): bool
    {
        return $preuves !== [] && $this->nombreDeVoixIndependantes($preuves) === 1;
    }

    /** La clé d'identité d'une voix — le domaine enregistrable, ou l'URL à défaut. */
    private function voix(Preuve $preuve): string
    {
        $affiche = $this->domaines->pour($preuve->url, $preuve->nomSource);

        return $this->domaines->domaineEnregistrable($affiche) ?? $affiche;
    }
}
