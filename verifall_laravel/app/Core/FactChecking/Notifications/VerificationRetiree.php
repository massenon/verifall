<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Notifications;

use App\Core\Identity\Notifications\NotificationCitoyenne;
use App\Enums\CategorieNotification;
use App\Models\Verification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * « Cette vérification ne vous est plus confiée. »
 *
 * ## Pourquoi ce retrait est notifié, alors qu'on pourrait s'en passer
 *
 * Reprendre un dossier à quelqu'un sans le lui dire le laisse travailler sur
 * une analyse qui ne lui appartient plus : il rédige, il enquête, et découvre
 * en publiant que quelqu'un d'autre l'a fait. C'est du travail perdu, et le
 * genre d'incident qui fait cesser d'utiliser un back-office.
 *
 * ## Un seul canal, contrairement à l'assignation
 *
 * `database` seulement, pas de courriel. Une assignation demande une ACTION et
 * doit atteindre la personne où qu'elle soit ; un retrait ne demande rien —
 * il faut seulement que l'information soit là au retour. Un courriel de plus
 * pour « vous n'avez rien à faire » use l'attention qu'on veut garder pour
 * l'assignation.
 */
final class VerificationRetiree extends NotificationCitoyenne implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private readonly Verification $verification,
        private readonly string $auteur,
        private readonly string $nouveauTitulaire,
    ) {}

    public function texte(): string
    {
        return sprintf(
            '%s a confié à %s la vérification « %s » qui vous était attribuée.',
            $this->auteur,
            $this->nouveauTitulaire,
            mb_strimwidth(trim($this->verification->claim), 0, 70, '…'),
        );
    }

    public function icone(): string
    {
        return 'assignment_return';
    }

    public function categorie(): CategorieNotification
    {
        return CategorieNotification::Verification;
    }

    public function lien(): string
    {
        return route('staff.dashboard');
    }
}
