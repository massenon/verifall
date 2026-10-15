<?php

declare(strict_types=1);

namespace App\Core\Application\Reglages;

/**
 * La DÉFINITION d'un réglage — pas sa valeur.
 *
 * ## Pourquoi un catalogue déclaratif plutôt qu'un formulaire écrit à la main
 *
 * L'écran des paramètres porte une soixantaine de champs répartis en six
 * onglets. Écrits à la main, cela ferait soixante propriétés Livewire,
 * soixante règles de validation et soixante blocs de gabarit — et surtout
 * quatre endroits à mettre à jour pour ajouter un réglage. Le quatrième est
 * toujours oublié.
 *
 * Ici, un réglage se déclare UNE fois. Le formulaire, la validation, le
 * chiffrement, le masquage et la purge du journal en découlent.
 *
 * ## Le champ qui compte : `secret`
 *
 * Il ne décrit pas une intention, il déclenche trois comportements :
 * chiffrement au repos, valeur jamais renvoyée au navigateur, purge dans le
 * journal d'audit. Un mot de passe SMTP déclaré sans lui serait en clair dans
 * chaque sauvegarde de la base.
 */
final readonly class ReglageDefini
{
    /**
     * @param  string  $cle  la clé technique, telle qu'on l'appelle dans le code
     * @param  string  $libelle  ce que lit la personne qui remplit le champ
     * @param  TypeDeReglage  $type  décide du contrôle affiché ET de la validation
     * @param  string  $groupe  l'onglet où il apparaît
     * @param  string|null  $aide  la phrase sous le champ — ce qu'on ne devine pas
     * @param  string|null  $exemple  le `placeholder`, jamais une valeur par défaut
     * @param  string|null  $defaut  employé quand rien n'a été saisi
     * @param  bool  $secret  chiffré, masqué, purgé du journal
     * @param  list<array{valeur: string, libelle: string}>  $choix  pour un type Choix
     * @param  list<string>  $reglesSupplementaires  au-delà de ce que le type impose
     */
    public function __construct(
        public string $cle,
        public string $libelle,
        public TypeDeReglage $type,
        public string $groupe,
        public ?string $aide = null,
        public ?string $exemple = null,
        public ?string $defaut = null,
        public bool $secret = false,
        public array $choix = [],
        public array $reglesSupplementaires = [],
    ) {}

    /**
     * Les règles de validation, déduites du type puis complétées.
     *
     * Déduites plutôt que répétées : un champ d'URL qui oublierait la règle
     * `url` accepterait « javascript:alert(1) », et ce lien finirait dans le
     * pied de page du site public.
     *
     * @return list<string>
     */
    public function regles(): array
    {
        return array_values(array_unique(array_merge(
            $this->type->reglesDeBase(),
            $this->reglesSupplementaires,
        )));
    }
}
