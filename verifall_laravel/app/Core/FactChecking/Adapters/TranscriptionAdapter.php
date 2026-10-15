<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Adapters;

use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\TypeContenu;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Transcription — OpenAI Whisper / gpt-4o-transcribe.
 *
 * ## Ce moteur ne rend JAMAIS de verdict, et c'est le point essentiel
 *
 * `MoteurAnalyse::porteUnVerdict()` vaut déjà `false` pour `Transcription` :
 * ce moteur ne juge rien, il MET EN TEXTE ce qui a été dit ou montré, pour que
 * les moteurs qui raisonnent sur du texte — DeepSeek, OpenAI lui-même, et la
 * décomposition en claims — aient quelque chose à lire. Le verdict reste
 * entièrement du ressort du pipeline preuves → adjudication de Vérif'All.
 *
 * ## Pourquoi cet adaptateur ouvre vidéo ET audio
 *
 * `TypeContenu::moteurIndispensable()` ferme les deux types tant qu'aucun
 * adaptateur réel n'existe ici ({@see RegistreDesMoteurs::adaptateurReel()}) :
 * sans texte, il n'y a rien à analyser. L'endpoint `/v1/audio/transcriptions`
 * d'OpenAI accepte directement les conteneurs vidéo courants (mp4, webm,
 * mov...) — il en extrait la piste sonore lui-même. Aucun outil d'extraction
 * de trame n'est donc nécessaire pour ce moteur-ci ; seule l'analyse VISUELLE
 * image par image d'une vidéo resterait à construire (NEWINSTRUCTION32),
 * et dépendrait d'un binaire `ffmpeg` absent de ce poste.
 */
final class TranscriptionAdapter extends AdaptateurHttp
{
    private const ENDPOINT = 'https://api.openai.com/v1/audio/transcriptions';

    public function nom(): MoteurAnalyse
    {
        return MoteurAnalyse::Transcription;
    }

    public function supporte(TypeContenu $type): bool
    {
        return in_array($type, [TypeContenu::Audio, TypeContenu::Video], true);
    }

    protected function configure(): bool
    {
        return is_string(config('services.openai.key')) && config('services.openai.key') !== '';
    }

    /** Même sonde que les deux autres moteurs OpenAI : même clé, même fournisseur. */
    protected function sonde(): array
    {
        return [
            'https://api.openai.com/v1/models',
            ['Authorization' => 'Bearer '.(string) config('services.openai.key')],
        ];
    }

    protected function executer(SacDePreuves $entree): ?VerdictMoteur
    {
        $debut = microtime(true);

        if ($entree->cheminFichier === null || ! Storage::disk('local')->exists($entree->cheminFichier)) {
            return null;
        }

        $chemin = Storage::disk('local')->path($entree->cheminFichier);
        $flux = fopen($chemin, 'r');

        if ($flux === false) {
            return null;
        }

        $reponse = Http::timeout($this->delai())
            ->withToken((string) config('services.openai.key'))
            ->attach('file', $flux, basename($chemin))
            ->post(self::ENDPOINT, [
                'model' => (string) config('services.openai.transcription_model', 'gpt-4o-transcribe'),
                'response_format' => 'json',
            ]);

        $this->refuserSiQuota($reponse);

        if ($reponse->failed()) {
            return null;
        }

        $transcription = trim((string) data_get($reponse->json(), 'text', ''));

        if ($transcription === '') {
            return null;
        }

        return VerdictMoteur::rendu(
            moteur: $this->nom(),
            // JAMAIS de verdict : ce moteur transcrit, il ne juge pas.
            verdict: null,
            // Pas de score de confiance fourni par ce fournisseur pour la
            // transcription elle-même : une transcription rendue est un fait,
            // pas une estimation à pondérer.
            confiance: 100,
            raisonnement: mb_substr($transcription, 0, 4000),
            latenceMs: $this->latence($debut),
        );
    }

    /** Délai plus large : un fichier audio/vidéo prend plus longtemps qu'un prompt texte. */
    protected function delai(): int
    {
        return (int) config('factcheck.runtime.timeout_transcription', 60);
    }
}
