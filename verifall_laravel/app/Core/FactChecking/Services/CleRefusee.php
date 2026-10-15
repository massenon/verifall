<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use RuntimeException;

/**
 * Le fournisseur a refusé la CLÉ elle-même : 401 ou 403.
 *
 * ## Pourquoi ce n'est pas un {@see QuotaEpuise}
 *
 * Les deux étaient traités ensemble, et ils n'ont rien à voir :
 *
 *   · un **quota** (429) se recharge tout seul. Mettre la clé au repos cinq
 *     minutes puis la reprendre est exactement la bonne conduite ;
 *   · une **clé refusée** (401/403) ne se recharge jamais. Elle a été révoquée,
 *     mal recopiée, ou n'a pas les droits demandés. La reposer cinq minutes
 *     revient à la réessayer indéfiniment, toutes les cinq minutes, jusqu'à ce
 *     que quelqu'un regarde — et rien ne le signalait.
 *
 * La documentation de Gemini le dit dans ces termes : « Do not retry on client
 * errors (like 400 or 403) as they indicate issues like invalid API keys. »
 *
 * ## Ce que la distinction change concrètement
 *
 * {@see RotationDeCles} met une clé refusée au repos BEAUCOUP plus longtemps, et
 * le journalise en `error` plutôt qu'en `warning`. Une clé morte cesse ainsi de
 * consommer un tour de rotation à chaque analyse, et son remplacement devient
 * une ligne qu'on peut voir plutôt qu'une lenteur qu'on subit.
 */
final class CleRefusee extends RuntimeException {}
