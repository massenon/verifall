<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\Faq;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * La page des questions fréquentes.
 *
 * ## Pourquoi un contrôleur, alors que les huit autres pages statiques
 * ## se servent par `Route::view`
 *
 * Parce qu'elle ne l'est plus. Les dix-sept questions étaient déroulées à la
 * main dans le gabarit et lues dans `lang/{fr,en}/faq.php` : corriger une
 * réponse demandait un développeur et un déploiement, pour un texte qui change
 * à chaque question nouvelle du support.
 *
 * Elles vivent désormais en base, et `Staff\GestionFaq` les tient — l'écran que
 * le menu d'administration promettait depuis l'origine tout en pointant sur
 * CETTE page.
 *
 * ## La langue vient de la requête, avec un repli
 *
 * Le scope `pourLaLangue()` rend les questions de la langue demandée, et se
 * replie sur les autres pour celles qui n'ont pas encore de traduction. Une
 * question ajoutée en français et pas encore traduite reste ainsi lisible sur
 * la version anglaise, plutôt que d'y disparaître : une FAQ incomplète vaut
 * mieux qu'une FAQ trouée.
 */
final class FaqController
{
    public function __invoke(): View
    {
        return view('public.faq', [
            'title' => __('faq.questions_frequentes')." · Vérif'All",
            'questions' => Faq::query()->pourLaLangue(App::getLocale())->get(),
        ]);
    }
}
