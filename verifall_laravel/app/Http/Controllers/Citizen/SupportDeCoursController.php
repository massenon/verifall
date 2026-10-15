<?php

declare(strict_types=1);

namespace App\Http\Controllers\Citizen;

use App\Core\Lms\Services\PiecesJointesDeLecon;
use App\Models\LessonAttachment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sert un support de cours à qui a le droit de l'ouvrir.
 *
 * ## Pourquoi ce contrôleur existe
 *
 * Un cours PAYANT dont les PDF sont servis par une URL devinable est un cours
 * vendu une fois et diffusé cent. Les fichiers vivent donc sur le disque privé,
 * sous un nom aléatoire, et ne passent que par ici.
 *
 * ## Deux gardes, et aucune n'est redondante
 *
 *   · la **signature** prouve que le lien vient de nous et n'a pas été
 *     fabriqué — sans elle, il suffirait de connaître un uuid ;
 *   · le **droit d'ouvrir** — inscription au cours, ou formateur du cours, ou
 *     habilitation pédagogique — parce qu'un lien signé se transmet, et qu'un
 *     lien transmis ne doit pas ouvrir le cours de quelqu'un d'autre.
 *
 * La signature n'expire pas : un support de cours se consulte tout au long de
 * la formation, et un lien qui périme au milieu d'un module ferait recharger la
 * page pour rien. C'est le DROIT qui protège ici, pas la durée — à la
 * différence d'une pièce d'identité, où l'inverse est vrai.
 *
 * ## `attachment` et non `inline`
 *
 * Un support de cours se garde : on le télécharge pour le relire hors ligne,
 * ce qui compte au Bénin où la connexion n'est pas acquise. C'est l'inverse du
 * choix fait pour les pièces KYC, et pour la raison inverse.
 */
final class SupportDeCoursController
{
    public function __invoke(LessonAttachment $piece, PiecesJointesDeLecon $pieces): StreamedResponse
    {
        abort_unless($pieces->peutOuvrir($piece, Auth::guard('web')->user()), 403);

        abort_unless(Storage::disk($pieces->disque())->exists($pieces->chemin($piece)), 404);

        return Storage::disk($pieces->disque())->download(
            $pieces->chemin($piece),
            // Le nom d'ORIGINE au téléchargement : personne ne veut retrouver
            // « 9ab2…f3c.pdf » dans son dossier.
            $piece->original_name,
            ['X-Robots-Tag' => 'noindex, nofollow'],
        );
    }
}
