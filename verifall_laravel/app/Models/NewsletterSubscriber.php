<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un abonné à l'infolettre du lundi.
 *
 * Ce n'est PAS un compte : une adresse peut être abonnée sans que personne ne
 * se soit jamais inscrit sur la plateforme, et un citoyen inscrit n'est pas
 * abonné pour autant. Confondre les deux ferait envoyer une lettre commerciale
 * à qui n'a consenti qu'aux conditions d'utilisation.
 *
 * Trois dates racontent toute la vie de la ligne, et l'ordre compte :
 *
 *   · `created_at` — l'adresse a été SAISIE. Elle ne prouve rien : n'importe
 *     qui peut taper l'adresse de n'importe qui dans un formulaire public.
 *   · `confirmed_at` — le propriétaire de la boîte a cliqué. C'est CE moment,
 *     et lui seul, qui autorise l'envoi.
 *   · `unsubscribed_at` — il a demandé à sortir. La ligne est conservée pour
 *     pouvoir prouver le consentement passé et sa révocation, jamais pour
 *     réenvoyer.
 *
 * Les deux jetons sont DISTINCTS à dessein. Celui de désabonnement voyage dans
 * chaque courriel, donc dans chaque boîte de réception, chaque archive et
 * chaque capture d'écran partagée ; s'il servait aussi à confirmer, le
 * retrouver suffirait à valider un abonnement qu'on n'a jamais demandé.
 */
final class NewsletterSubscriber extends Model
{
    protected $fillable = [
        'uuid', 'email', 'name',
        'confirmation_token', 'confirmed_at',
        'unsubscribe_token', 'unsubscribed_at',
        'consent_ip_hash',
    ];

    /**
     * Les deux jetons sont masqués de toute sérialisation.
     *
     * Un `toArray()` inattendu — journal, réponse d'API, export — suffirait
     * sinon à publier de quoi confirmer un abonnement au nom d'autrui.
     *
     * @var list<string>
     */
    protected $hidden = ['confirmation_token', 'unsubscribe_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    /**
     * Jamais l'identifiant numérique en URL : il dit combien d'abonnés
     * existent, et permet de parcourir la liste par incrément.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<NewsletterDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(NewsletterDelivery::class);
    }

    /**
     * Reçoit-il l'infolettre ? Confirmé ET pas sorti.
     *
     * Les deux conditions ensemble : un désabonné garde sa date de
     * confirmation — c'est la preuve qu'il avait bien consenti, et elle ne doit
     * pas être effacée pour autant.
     */
    public function estDestinataire(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }
}
