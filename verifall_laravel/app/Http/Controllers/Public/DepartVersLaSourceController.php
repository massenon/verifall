<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Models\HubArticle;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Compte un départ vers la source d'origine, puis y renvoie.
 *
 * C'est ce que mesure la colonne « Clics » du panneau « Performance Articles » :
 * pas les ouvertures de l'article, mais les personnes qui sont réellement
 * allées voir la preuve. Sur une plateforme de fact-checking, c'est cette
 * seconde chose qui dit si le travail sert.
 *
 * ## Ce n'est PAS une redirection ouverte
 *
 * La destination ne vient jamais de la requête : elle est lue en base, sur un
 * article identifié par son UUID, et renseignée par l'administration. Une route
 * qui accepterait une URL en paramètre serait un tremplin d'hameçonnage offert
 * à n'importe qui — d'autant plus efficace qu'il porterait notre nom de domaine.
 *
 * Le schéma est tout de même contrôlé avant la redirection : une valeur en base
 * peut avoir été saisie de travers, et `javascript:` dans un `Location` n'a
 * aucune raison de partir d'ici.
 *
 * ## Le compteur n'empêche jamais le départ
 *
 * L'incrément est atomique côté base et sans effet sur la réponse. Si l'écriture
 * échoue, la personne part quand même : un compteur de tableau de bord ne fait
 * pas obstacle à la lecture d'une source.
 *
 * ## Référencement
 *
 * `X-Robots-Tag: noindex, nofollow` : le lien d'origine porte déjà `nofollow`
 * — on ne transmet pas notre autorité à ce qu'on est en train de démentir — et
 * passer par une route interne ne doit pas contourner cette décision.
 */
final class DepartVersLaSourceController
{
    public function __invoke(HubArticle $article): Response
    {
        $destination = trim((string) $article->source_url);

        // Sans source, il n'y a rien à compter ni où aller : on renvoie à
        // l'article plutôt que de rendre une erreur pour un lien mal formé.
        if ($destination === '' || ! $this->estUneUrlSure($destination)) {
            return redirect()->route('public.fact', $article);
        }

        // `increment()` compile un `UPDATE … SET source_clicks = source_clicks + 1` :
        // deux départs simultanés en comptent bien deux.
        $article->increment('source_clicks');

        /** @var RedirectResponse $reponse */
        $reponse = redirect()->away($destination);

        return $reponse->withHeaders(['X-Robots-Tag' => 'noindex, nofollow']);
    }

    private function estUneUrlSure(string $url): bool
    {
        $schema = parse_url($url, PHP_URL_SCHEME);

        return in_array($schema, ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
