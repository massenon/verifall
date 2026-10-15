<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Langue;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Établit la langue de la requête, dans un ordre qui n'est pas négociable.
 *
 * ## L'ordre, et ce que chaque étage garantit
 *
 * 1. **La session.** Le choix explicite fait par un clic sur le sélecteur. Il
 *    prime sur tout, y compris sur la préférence enregistrée : quelqu'un qui
 *    bascule en anglais depuis un poste partagé veut l'anglais MAINTENANT, pas
 *    la langue de son compte.
 *
 * 2. **La préférence du compte.** Elle suit la personne d'un appareil à
 *    l'autre. Elle n'écrase jamais un choix de session, mais elle est
 *    RECOPIÉE en session à la première requête, sans quoi il faudrait
 *    interroger la base à chaque page.
 *
 * 3. **Le français.**
 *
 * ## Ce qui n'y figure PAS, et c'est le point important
 *
 * `Accept-Language`. Un téléphone vendu au Bénin sort d'usine en anglais dans
 * une proportion notable, et beaucoup de gens n'en changent jamais le réglage.
 * Déduire la langue de cet en-tête servirait donc l'anglais à des personnes
 * qui lisent le français — une dégradation présentée comme une attention.
 *
 * Le français est le défaut, et il ne se déduit de rien.
 */
final class ChoisirLaLangue
{
    public const CLEF_SESSION = 'verifall.langue';

    public function handle(Request $request, Closure $suivant): Response
    {
        $langue = $this->resoudre($request);

        /*
         | Mémorisée en session à chaque requête, y compris quand elle vient du
         | défaut. Sans cela, une personne connectée qui n'a jamais choisi
         | provoquerait une lecture de sa préférence à chaque page.
         |
         | ÉCRITE AVANT le forçage de l'administration, et c'est tout l'intérêt
         | de les séparer. Le personnel de cette plateforme en est aussi le
         | public : quelqu'un qui a choisi l'anglais pour lire une page publique
         | doit le retrouver en y revenant, même après un détour par
         | l'administration. Forcer la langue AFFICHÉE est légitime ; effacer un
         | choix explicite au passage ne l'est pas.
         */
        $request->session()->put(self::CLEF_SESSION, $langue->value);

        App::setLocale($this->langueAffichee($request, $langue)->value);

        return $suivant($request);
    }

    /**
     * La langue effectivement AFFICHÉE — celle du choix, sauf en administration.
     *
     * L'ADMINISTRATION RESTE EN FRANÇAIS, et le code le dit désormais. La règle
     * existait dans la consigne — « multilingue côté public et citoyen » — mais
     * nulle part dans le code, si bien que le contraire s'était installé sans
     * que personne ne le décide.
     *
     * Le mécanisme : ce middleware s'applique au groupe `web`, donc aussi aux
     * routes du personnel, et le sélecteur de langue vit sur le site public. Or
     * le personnel de cette plateforme en est aussi le public. Quelqu'un qui
     * bascule en anglais pour relire une page publique arrivait ensuite en
     * administration avec l'anglais en session — sans qu'aucun geste dans
     * l'administration ne l'ait demandé, et sans sélecteur pour revenir.
     *
     * Ce qu'il y voyait n'était pas une administration en anglais, mais un
     * MÉLANGE : sept des huit énumérations affichées basculaient, la pagination
     * et les messages de validation aussi, tandis que les libellés écrits en
     * dur dans les gabarits restaient français. Sur l'écran des messages de
     * contact, la colonne « statut » passait à l'anglais et la colonne
     * « nature » non — deux langues sur une même ligne de tableau.
     *
     * Traduire l'administration serait un chantier légitime ; le faire à
     * moitié, par ricochet, n'en est pas un.
     *
     * Le contrôle porte sur le NOM DE ROUTE et non sur l'URL : le préfixe
     * d'URL de l'administration est configurable, son espace de noms ne l'est
     * pas.
     */
    private function langueAffichee(Request $request, Langue $choisie): Langue
    {
        return $request->routeIs('staff.*') ? Langue::defaut() : $choisie;
    }

    private function resoudre(Request $request): Langue
    {
        /*
         | L'ADMINISTRATION RESTE EN FRANÇAIS, et le code le dit désormais.
         |
         | La règle existait dans la consigne — « multilingue côté public et
         | citoyen » — mais nulle part dans le code, si bien que le contraire
         | s'était installé sans que personne ne le décide.
         |
         | Le mécanisme : ce middleware s'applique au groupe `web`, donc aussi
         | aux routes du personnel, et le sélecteur de langue vit sur le site
         | public. Or le personnel de cette plateforme en est aussi le public.
         | Quelqu'un qui bascule en anglais pour relire une page publique
         | arrivait ensuite en administration avec l'anglais en session — sans
         | qu'aucun geste dans l'administration ne l'ait demandé, et sans
         | sélecteur pour revenir en arrière.
         |
         | Ce qu'il y voyait n'était pas une administration en anglais : c'était
         | un MÉLANGE. Sept des huit énumérations affichées basculaient, la
         | pagination et les messages de validation aussi, tandis que les
         | libellés écrits en dur dans les gabarits restaient français. Sur
         | l'écran des messages de contact, la colonne « statut » passait à
         | l'anglais et la colonne « nature » non — deux langues sur une même
         | ligne de tableau.
         |
         | Traduire l'administration serait un chantier légitime ; le faire à
         | moitié, par ricochet, n'en est pas un. Tant qu'elle n'est pas
         | traduite pour de bon, elle est servie dans une seule langue.
         |
         | Le contrôle porte sur le NOM DE ROUTE et non sur l'URL : le préfixe
         | d'URL de l'administration est configurable, son espace de noms de
         | routes ne l'est pas.
         */
        $enSession = $request->session()->get(self::CLEF_SESSION);

        if (is_string($enSession) && Langue::tryFrom($enSession) !== null) {
            return Langue::from($enSession);
        }

        $utilisateur = $request->user();

        if ($utilisateur !== null && is_string($utilisateur->locale)) {
            return Langue::depuis($utilisateur->locale);
        }

        return Langue::defaut();
    }
}
