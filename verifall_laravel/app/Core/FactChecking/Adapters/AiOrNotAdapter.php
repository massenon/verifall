<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Adapters;

use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\TypeContenu;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * AI or Not — détecte qu'un contenu ressemble à une production automatique.
 *
 * ## Ce moteur ne rend JAMAIS de verdict, et c'est le point essentiel
 *
 * Il rend un SIGNAL. `MoteurAnalyse::porteUnVerdict()` vaut déjà `false` pour
 * `DetecteurIA`, avec la raison écrite à côté : « un détecteur d'IA dit qu'un
 * texte ressemble à une production automatique — pas qu'il est faux. Confondre
 * les deux ferait condamner une information vraie sur la seule foi de son
 * style. »
 *
 * Cet adaptateur rend donc `verdict: null`. Le score sert à pondérer, jamais à
 * trancher.
 *
 * **La raison est plus lourde ici qu'ailleurs.** Ces détecteurs se trompent
 * surtout contre les personnes qui n'écrivent pas dans leur langue maternelle.
 * Sur une plateforme béninoise, un faux positif accuserait à tort un rédacteur
 * francophone non natif. Et la documentation d'AI or Not — dépouillée
 * intégralement le 10 septembre 2026 — **ne publie AUCUN taux de faux
 * positifs, aucun chiffre de précision, et ne dit rien des langues prises en
 * charge**. Pas un mot sur le français.
 *
 * On ne peut donc pas calibrer ce moteur : on ne peut que refuser de lui faire
 * dire plus qu'il ne sait. C'est ce que fait ce fichier.
 *
 * ## Ce que la documentation établit
 *
 * Trois endpoints synchrones, sous `Authorization: Bearer` :
 *
 *   · `POST /v2/text/sync`  — corps `application/x-www-form-urlencoded`,
 *     champ `text`, **250 caractères minimum**, 500 000 maximum ;
 *   · `POST /v2/image/sync` — multipart ; rend `ai_generated` ET `deepfake` ;
 *   · `POST /v2/video/sync` — multipart ; **n'analyse qu'une PORTION** des
 *     vidéos longues, et demande un délai de 120 s.
 *
 * Il n'existe **aucun endpoint audio** : la piste sonore n'est examinée qu'au
 * travers d'une vidéo (`ai_voice`, `ai_music`).
 *
 * `GET /v1/system/live` existe mais n'exige pas la clé, et la documentation
 * déconseille de l'appeler souvent. Il ne peut donc pas servir de sonde : il
 * répond « le service est allumé », pas « votre clé est acceptée ». D'où
 * l'absence de `sonde()` ici — mieux vaut un état inconnu qu'un état faux.
 */
final class AiOrNotAdapter extends AdaptateurHttp
{
    private const BASE = 'https://api.aiornot.com/v2/';

    /** Plancher imposé par le fournisseur : en deçà, il refuse d'analyser. */
    private const TEXTE_MINIMUM = 250;

    private const TEXTE_MAXIMUM = 500000;

    /**
     * Délai pour la vidéo.
     *
     * La fiche le demande explicitement — « Please set a 120 second timeout ».
     * Le délai commun du socle est de 20 s : le garder ferait échouer toute
     * analyse vidéo sur un dépassement, et le journal accuserait le réseau.
     */
    private const DELAI_VIDEO = 120;

    public function nom(): MoteurAnalyse
    {
        return MoteurAnalyse::DetecteurIA;
    }

    /**
     * Ni le lien, ni l'audio.
     *
     * Le LIEN n'a pas de contenu propre à soumettre — c'est la page qu'il
     * faudrait chercher, et ce n'est pas le métier de ce moteur. L'AUDIO n'a
     * aucun endpoint chez ce fournisseur.
     */
    public function supporte(TypeContenu $type): bool
    {
        return in_array($type, [TypeContenu::Texte, TypeContenu::Image, TypeContenu::Video], true);
    }

    protected function configure(): bool
    {
        return $this->cle() !== '';
    }

    protected function executer(SacDePreuves $entree): ?VerdictMoteur
    {
        $debut = microtime(true);

        $reponse = match ($entree->type) {
            TypeContenu::Texte => $this->analyserLeTexte($entree),
            TypeContenu::Image, TypeContenu::Video => $this->analyserLeFichier($entree),
            default => null,
        };

        if ($reponse === null) {
            return null;
        }

        $this->refuserSiQuota($reponse);

        if ($reponse->failed()) {
            return null;
        }

        /** @var array<string, mixed> $donnees */
        $donnees = $reponse->json();

        $signaux = $this->signaux($donnees, $entree->type);

        if ($signaux === []) {
            return null;
        }

        // Le signal le plus fort décide de la confiance rendue ; tous sont
        // rapportés en clair dans le raisonnement.
        $plusFort = max(array_column($signaux, 'confiance'));

        return VerdictMoteur::rendu(
            moteur: $this->nom(),
            // JAMAIS de verdict. Voir l'en-tête de cette classe.
            verdict: null,
            confiance: $plusFort,
            raisonnement: $this->redaction($signaux),
            latenceMs: $this->latence($debut),
        );
    }

    private function analyserLeTexte(SacDePreuves $entree): ?Response
    {
        $texte = trim($entree->affirmation.' '.($entree->contexte ?? ''));

        /*
         | Sous le plancher du fournisseur, on n'appelle PAS.
         |
         | Il rendrait 422, la clé serait comptée en échec, et le journal
         | parlerait d'une panne là où il n'y a qu'un texte court. La plupart
         | des affirmations soumises à une plateforme de fact-checking tiennent
         | en une phrase : ce cas est la règle, pas l'exception.
         */
        if (mb_strlen($texte) < self::TEXTE_MINIMUM) {
            return null;
        }

        return Http::timeout($this->delai())
            ->withToken($this->cle())
            ->asForm()
            ->post(self::BASE.'text/sync', [
                'text' => mb_substr($texte, 0, self::TEXTE_MAXIMUM),
            ]);
    }

    private function analyserLeFichier(SacDePreuves $entree): ?Response
    {
        if ($entree->cheminFichier === null || ! Storage::disk('local')->exists($entree->cheminFichier)) {
            return null;
        }

        $chemin = Storage::disk('local')->path($entree->cheminFichier);
        $flux = fopen($chemin, 'r');

        if ($flux === false) {
            return null;
        }

        $video = $entree->type === TypeContenu::Video;

        return Http::timeout($video ? self::DELAI_VIDEO : $this->delai())
            ->withToken($this->cle())
            ->attach('file', $flux, basename($chemin))
            ->post(self::BASE.($video ? 'video' : 'image').'/sync');
    }

    /**
     * Les signaux lisibles de la réponse, quelle que soit la modalité.
     *
     * Le chemin du verdict CHANGE selon la modalité — `report.ai_text`,
     * `report.ai_generated.ai`, `report.ai_video`… — et la documentation
     * prévient que certains sous-objets valent `null` dans une réponse 200,
     * « for sub-model errors without failing completely ». Un `data_get` nu
     * rendrait alors une confiance de 0 qu'on lirait comme « aucun signal »,
     * là où il faut lire « ce sous-modèle n'a rien dit ».
     *
     * @param  array<string, mixed>  $donnees
     * @return list<array{libelle: string, confiance: int}>
     */
    private function signaux(array $donnees, TypeContenu $type): array
    {
        $champs = match ($type) {
            TypeContenu::Texte => ['ai_text' => 'texte engendré par une IA'],
            TypeContenu::Image => [
                'ai_generated.ai' => 'image engendrée par une IA',
                'deepfake' => 'visage synthétique (deepfake)',
            ],
            TypeContenu::Video => [
                'ai_video' => 'images engendrées par une IA',
                'ai_voice' => 'voix synthétique',
                'ai_music' => 'musique engendrée par une IA',
                'deepfake_video' => 'visage synthétique (deepfake)',
            ],
            default => [],
        };

        $signaux = [];

        foreach ($champs as $chemin => $libelle) {
            $bloc = data_get($donnees, 'report.'.$chemin);

            // Sous-modèle absent ou en erreur : on l'ignore, on ne l'invente pas.
            if (! is_array($bloc) || ! array_key_exists('is_detected', $bloc)) {
                continue;
            }

            if ($bloc['is_detected'] !== true) {
                continue;
            }

            $signaux[] = [
                'libelle' => $libelle,
                // Le fournisseur rend un flottant 0–1 ; le socle attend 0–100.
                'confiance' => $this->confiance((float) ($bloc['confidence'] ?? 0) * 100, 0),
            ];
        }

        return $signaux;
    }

    /**
     * Ce que l'analyste lira, et ce qu'il ne doit pas y lire.
     *
     * La dernière phrase n'est pas une précaution de style : elle est la seule
     * chose qui empêche un signal de style d'être relu comme un verdict de
     * véracité, six mois plus tard, par quelqu'un qui n'a pas lu ce fichier.
     *
     * @param  list<array{libelle: string, confiance: int}>  $signaux
     */
    private function redaction(array $signaux): string
    {
        $lignes = array_map(
            static fn (array $s): string => sprintf('%s (%d %%)', $s['libelle'], $s['confiance']),
            $signaux,
        );

        return 'Signaux relevés : '.implode(', ', $lignes)
            .'. Ce moteur mesure une RESSEMBLANCE avec une production automatique, '
            .'jamais la véracité du contenu. Le fournisseur ne publie aucun taux '
            .'de faux positifs, et ces détecteurs se trompent davantage sur les '
            .'textes écrits dans une langue seconde.';
    }

    private function cle(): string
    {
        return (string) config('services.aiornot.key', '');
    }
}
