<?php

declare(strict_types=1);

namespace App\Core\Marketing\Emails;

use App\Models\EmailTemplate;
use Illuminate\Support\Facades\Cache;

/**
 * Le point de passage de TOUS les courriels.
 *
 * ## Ce qui change par rapport à `GabaritsDEmail`
 *
 * L'ancien service ne servait qu'une chose : l'objet. Le corps vivait dans un
 * gabarit Blade, hors de portée de l'administration. Celui-ci sert l'objet, le
 * titre, le corps, le libellé du bouton — et dit si l'envoi est allumé.
 *
 * ## Le cache, et le piège qu'il tend
 *
 * Les gabarits sont lus à chaque envoi et changent une fois par trimestre : les
 * mettre en cache est évident. Ce qui l'est moins, c'est que **l'oubli du cache
 * doit être fait à CHAQUE écriture**, y compris par le semis. Un gabarit
 * corrigé qui continue de partir dans son ancienne version pendant trente
 * minutes est le genre de défaut qu'on met une journée à croire.
 *
 * ## La substitution des marqueurs
 *
 * `{{ prenom }}` et non `{$prenom}` ni `%s` : c'est la forme que l'écran
 * affiche, celle qu'on reconnaît d'un coup d'œil, et elle tolère l'espace.
 * Un marqueur qu'aucune variable ne remplit est **retiré** plutôt que laissé
 * tel quel — recevoir « Bonjour {{ prenom }}, » est pire que « Bonjour, ».
 */
final class RenduDeGabarit
{
    private const CLE_CACHE = 'gabarits-email.tous';

    private const DUREE_MINUTES = 30;

    /**
     * L'objet à employer : celui de l'administration, ou celui du code.
     *
     * `$defaut` est TOUJOURS calculé par l'appelant, même quand une surcharge
     * existe. C'est voulu : il porte souvent une traduction ou une variable, et
     * le résoudre paresseusement obligerait chaque appelant à passer une
     * fermeture pour un gain nul.
     *
     * @param  array<string, string|int|null>  $variables
     */
    public function sujet(string $cle, string $defaut, array $variables = []): string
    {
        $surcharge = $this->gabarit($cle)['subject'] ?? null;

        $texte = $surcharge !== null && trim((string) $surcharge) !== ''
            ? (string) $surcharge
            : $defaut;

        return $this->substituer($texte, $variables);
    }

    /**
     * Le titre affiché EN TÊTE du message.
     *
     * Distinct de l'objet, et pas par coquetterie : la ligne de la boîte de
     * réception doit tenir en cinquante caractères et se lire hors contexte,
     * le titre de la lettre s'adresse à quelqu'un qui l'a déjà ouverte.
     *
     * @param  array<string, string|int|null>  $variables
     */
    public function titre(string $cle, string $defaut, array $variables = []): string
    {
        $surcharge = $this->gabarit($cle)['heading'] ?? null;

        return $this->substituer(
            $surcharge !== null && trim((string) $surcharge) !== '' ? (string) $surcharge : $defaut,
            $variables,
        );
    }

    /**
     * Le corps, marqueurs remplacés. `null` quand aucun corps n'est enregistré.
     *
     * Le `null` compte : il laisse l'appelant retomber sur son gabarit Blade
     * d'origine. C'est ce qui permet de faire passer les envois par les
     * gabarits UN PAR UN, sans une migration qui casserait tout le jour où elle
     * oublierait une variable.
     *
     * @param  array<string, string|int|null>  $variables
     */
    public function corps(string $cle, array $variables = []): ?string
    {
        $corps = $this->gabarit($cle)['body'] ?? null;

        if ($corps === null || trim((string) $corps) === '') {
            return null;
        }

        return $this->substituer((string) $corps, $variables);
    }

    /** @param array<string, string|int|null> $variables */
    public function libelleDuBouton(string $cle, array $variables = []): ?string
    {
        $libelle = $this->gabarit($cle)['cta_label'] ?? null;

        return $libelle !== null && trim((string) $libelle) !== ''
            ? $this->substituer((string) $libelle, $variables)
            : null;
    }

    /**
     * Cet envoi est-il allumé ?
     *
     * Un événement VERROUILLÉ répond toujours oui, quelle que soit la ligne en
     * base : un code à usage unique qu'un réglage peut couper est un compte
     * qu'on ne peut plus créer, et une suspension dont on n'informe personne
     * n'est pas une sanction — c'est une panne, du point de vue de la personne.
     */
    public function estAllume(string $cle): bool
    {
        if (CatalogueDesCourriels::estVerrouille($cle)) {
            return true;
        }

        $gabarit = $this->gabarit($cle);

        // Aucune ligne en base : on suit ce que le catalogue déclare. Un semis
        // qui n'a pas encore tourné ne doit pas éteindre la plateforme.
        return $gabarit === null
            ? CatalogueDesCourriels::evenement($cle)['parDefautActif']
            : (bool) $gabarit['is_active'];
    }

    /** Vide le cache. À appeler à CHAQUE écriture, semis compris. */
    public function oublier(): void
    {
        Cache::forget(self::CLE_CACHE);
    }

    /**
     * Remplace `{{ marqueur }}` par sa valeur, et RETIRE ceux qui restent.
     *
     * @param  array<string, string|int|null>  $variables
     */
    private function substituer(string $texte, array $variables): string
    {
        foreach ($variables as $nom => $valeur) {
            $texte = preg_replace(
                '/\{\{\s*'.preg_quote((string) $nom, '/').'\s*\}\}/u',
                (string) $valeur,
                $texte,
            ) ?? $texte;
        }

        /*
         | Ce qui reste est un marqueur que l'envoi ne fournit pas — une faute de
         | frappe, ou un gabarit rédigé avant qu'une variable disparaisse. On le
         | retire : « Bonjour {{ prenom }}, » est pire que « Bonjour, », et
         | surtout il part tel quel dans toutes les boîtes.
         |
         | L'écran refuse déjà les marqueurs inconnus à l'enregistrement ; ceci
         | est le filet, pas la garde.
         */
        return trim((string) preg_replace('/\{\{[^}]*\}\}/u', '', $texte));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function gabarit(string $cle): ?array
    {
        return $this->tous()[$cle] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function tous(): array
    {
        /** @var array<string, array<string, mixed>> */
        return Cache::remember(
            self::CLE_CACHE,
            now()->addMinutes(self::DUREE_MINUTES),
            static fn (): array => EmailTemplate::query()
                ->get(['key', 'subject', 'heading', 'body', 'cta_label', 'is_active'])
                ->keyBy('key')
                ->map(static fn (EmailTemplate $g): array => [
                    'subject' => $g->subject,
                    'heading' => $g->heading,
                    'body' => $g->body,
                    'cta_label' => $g->cta_label,
                    'is_active' => $g->is_active,
                ])
                ->all(),
        );
    }
}
