<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un compteur de visites, par famille de navigateur et par jour.
 *
 * ## Ce que cette table NE contient pas, et c'est le point
 *
 * Le panneau « Navigateurs — Analyse des visiteurs » demandait une analytique,
 * et une analytique se construit d'ordinaire sur des identifiants de visiteur,
 * des cookies, ou un service tiers. Rien de tout cela ici :
 *
 *   · pas d'adresse IP, même hachée ;
 *   · pas d'identifiant de visiteur ni de session ;
 *   · pas d'en-tête `User-Agent` brut — seulement la FAMILLE (« Chrome »), car
 *     un User-Agent complet porte version du système, du navigateur et parfois
 *     du matériel : conservé tel quel, il redevient une donnée personnelle ;
 *   · pas de page consultée, pas d'horodatage à la seconde ;
 *   · aucun appel à un tiers.
 *
 * Il ne reste qu'un entier par famille et par jour. **Aucun parcours n'est
 * reconstituable**, y compris par quelqu'un qui obtiendrait la table entière.
 * C'est ce qui permet de répondre à la question posée sans faire entrer de
 * données personnelles dans le projet — donc sans rien ajouter aux obligations
 * de `docs/SECURITE_RGPD.md`, ni de bandeau de consentement.
 */
final class VisitorHit extends Model
{
    protected $fillable = ['browser', 'day', 'hits'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'date',
            'hits' => 'integer',
        ];
    }
}
