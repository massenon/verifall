<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Models\ConsultationHub;
use App\Models\DataExport;
use App\Models\HistoriqueConnexion;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Droit d'accès et de portabilité — « Exporter mes données ».
 *
 * L'archive est construite par SECTIONS, chacune décrite à un seul endroit. Les
 * modules à venir — vérifications, progression, forum — ajouteront la leur ici,
 * et l'export les suivra sans que le reste bouge. C'est la seule façon qu'un
 * export « complet » le soit encore après huit sprints.
 *
 * Deux formats dans une même archive : JSON pour la portabilité — structuré,
 * réimportable — et CSV pour la lisibilité, le règlement demandant un format
 * « couramment utilisé », ce qu'un tableur est et qu'un JSON n'est pas pour
 * tout le monde.
 */
final class ExportDonneesService
{
    /** Durée de vie du lien de téléchargement. */
    public const HEURES_VALIDITE = 24;

    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Ouvre une demande. Le travail lourd part en file d'attente.
     */
    public function demander(User $utilisateur): DataExport
    {
        // Les archives antérieures sont détruites : en garder plusieurs du même
        // compte multiplierait sans raison les copies de ses données.
        foreach (DataExport::where('user_id', $utilisateur->id)->get() as $ancienne) {
            $ancienne->detruire();
        }

        // Extraire l'intégralité de ses données est une action sensible, même
        // quand c'est la personne concernée qui la demande : elle produit un
        // fichier qui peut ensuite lui être dérobé.
        $this->audit->record(
            AuditLogService::EXPORT_DONNEES, 'profile', $utilisateur,
            cibleLibelle: 'Export de mes données personnelles',
        );

        return DataExport::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $utilisateur->id,
            'status' => DataExport::EN_ATTENTE,
        ]);
    }

    /**
     * Construit l'archive et la range sur le disque privé.
     */
    public function construire(DataExport $export): DataExport
    {
        $utilisateur = $export->user;

        if ($utilisateur === null) {
            $export->update(['status' => DataExport::ECHEC]);

            return $export;
        }

        $sections = $this->sections($utilisateur);

        // Nom aléatoire : un nom déduit de l'identité rendrait le contenu du
        // dossier lisible sans même ouvrir un fichier.
        $chemin = 'exports/'.Str::uuid()->toString().'.zip';

        Storage::disk('local')->makeDirectory('exports');
        $absolu = Storage::disk('local')->path($chemin);

        $zip = new ZipArchive;
        $zip->open($absolu, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $zip->addFromString('donnees.json', (string) json_encode(
            $sections,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        $csv = [];

        foreach ($sections as $nom => $contenu) {
            if ($this->estTabulaire($contenu)) {
                $zip->addFromString($nom.'.csv', $this->versCsv($contenu));
                $csv[] = $nom.'.csv';
            }
        }

        // Le sommaire ne liste que ce qui se trouve RÉELLEMENT dans l'archive :
        // annoncer des fichiers absents ferait chercher en vain, et douter du
        // reste.
        $zip->addFromString('LISEZ-MOI.txt', $this->lisezMoi($utilisateur, $csv));
        $zip->close();

        $export->update([
            'path' => $chemin,
            'status' => DataExport::PRET,
            'size_bytes' => Storage::disk('local')->size($chemin),
            'expires_at' => now()->addHours(self::HEURES_VALIDITE),
        ]);

        return $export->refresh();
    }

    /**
     * Contenu de l'archive, section par section.
     *
     * @return array<string, mixed>
     */
    private function sections(User $utilisateur): array
    {
        return [
            'profil' => [
                'identifiant_public' => $utilisateur->uuid,
                'prenom' => $utilisateur->first_name,
                'nom' => $utilisateur->last_name,
                'email' => $utilisateur->email,
                'telephone' => $utilisateur->phone,
                'profession' => $utilisateur->profession,
                'ville' => $utilisateur->city,
                'bio' => $utilisateur->bio,
                'inscrit_le' => $utilisateur->created_at?->toIso8601String(),
                'identite_verifiee_le' => $utilisateur->kyc_verified_at?->toIso8601String(),
            ],

            'economie_et_progression' => [
                'unites' => $utilisateur->units,
                'experience' => $utilisateur->xp,
                'jours_consecutifs' => $utilisateur->streak_days,
            ],

            'preferences_de_notification' => $utilisateur->notification_preferences ?? [],

            // Le journal des consentements est la preuve que la personne peut
            // exiger de nous : il a plus sa place ici que n'importe quoi d'autre.

            // Arbitrage n° 178 : le droit d'accès couvre les deux historiques.
            'connexions' => HistoriqueConnexion::query()->where('user_id', $utilisateur->id)
                ->latest('created_at')->get()
                ->map(fn (HistoriqueConnexion $c): array => [
                    'date' => $c->created_at->toIso8601String(),
                    'espace' => $c->espace,
                    'resultat' => $c->resultat,
                    'methode' => $c->methode,
                    'navigateur' => $c->navigateur,
                    'systeme' => $c->systeme,
                    'appareil' => $c->appareil,
                    'reseau' => $c->ip_tronquee,
                    'pays' => $c->pays,
                ])->all(),

            'lectures_du_hub' => ConsultationHub::query()->where('user_id', $utilisateur->id)
                ->latest('created_at')->get()
                ->map(fn (ConsultationHub $c): array => [
                    'date' => $c->created_at->toIso8601String(),
                    'rubrique' => $c->rubrique,
                    'titre' => $c->titre,
                    'action' => $c->action,
                ])->all(),
            'consentements' => $utilisateur->consents()
                ->orderBy('recorded_at')
                ->get()
                ->map(fn ($consentement): array => [
                    'type' => $consentement->type instanceof BackedEnum
                        ? $consentement->type->value
                        : (string) $consentement->type,
                    'accorde' => (bool) $consentement->granted,
                    'version_du_document' => (string) $consentement->document_version,
                    'enregistre_le' => $consentement->recorded_at?->toIso8601String(),
                ])->all(),

            /*
             | Les sections « vérifications », « progression » et « forum »
             | s'ajouteront avec leurs modules. Les déclarer vides d'avance
             | laisserait croire que la personne n'a rien fait, alors que la
             | fonctionnalité n'existe simplement pas encore.
             */
        ];
    }

    private function estTabulaire(mixed $contenu): bool
    {
        return is_array($contenu)
            && $contenu !== []
            && array_is_list($contenu)
            && is_array($contenu[0]);
    }

    /**
     * @param  list<array<string, mixed>>  $lignes
     */
    private function versCsv(array $lignes): string
    {
        $flux = fopen('php://temp', 'r+');

        // BOM UTF-8 : sans lui, Excel affiche des caractères accentués cassés.
        fwrite($flux, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($flux, array_keys($lignes[0]), ';');

        foreach ($lignes as $ligne) {
            fputcsv($flux, array_map(
                static fn (mixed $valeur): string => is_scalar($valeur) || $valeur === null
                    ? (string) $valeur
                    : (string) json_encode($valeur, JSON_UNESCAPED_UNICODE),
                $ligne,
            ), ';');
        }

        rewind($flux);
        $csv = (string) stream_get_contents($flux);
        fclose($flux);

        return $csv;
    }

    /**
     * @param  list<string>  $csv  fichiers tabulaires réellement ajoutés
     */
    private function lisezMoi(User $utilisateur, array $csv): string
    {
        $date = now()->translatedFormat('d F Y à H:i');
        $heures = self::HEURES_VALIDITE;

        $listeCsv = $csv === []
            ? '(aucun — vos données ne comportent pour l\'instant aucune liste)'
            : implode(', ', $csv);

        return implode("\n", [
            "Vérif'All — export de vos données personnelles",
            '=============================================',
            '',
            "Établi le {$date} pour {$utilisateur->email}.",
            '',
            'Contenu',
            '-------',
            'donnees.json ....... l\'ensemble de vos données, structurées.',
            "csv ................ {$listeCsv}",
            '',
            'Ce que cette archive ne contient PAS',
            '------------------------------------',
            'Votre mot de passe et vos secrets d\'authentification : nous n\'en',
            'conservons que des empreintes, dont l\'original ne peut pas être',
            'retrouvé. C\'est délibéré, et c\'est ce qui les protège.',
            '',
            'Ce fichier contient des données personnelles. Le lien de',
            "téléchargement expire après {$heures} heures ; conservez l'archive en",
            'lieu sûr.',
        ]);
    }
}
