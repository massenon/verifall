<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Sauvegardes BD » — et ce que cet écran NE FAIT PAS.
 *
 * ## Le constat, avant le code
 *
 * **Il n'existe aucun mécanisme de sauvegarde dans ce projet.** Pas de paquet
 * (`composer.json` ne mentionne ni `spatie/laravel-backup` ni aucun autre), pas
 * de commande, pas de tâche planifiée, et `docs/DEPLOIEMENT_CPANEL.md` n'en
 * dit pas un mot.
 *
 * ## Pourquoi cet écran ne fabrique PAS de sauvegarde
 *
 * Le réflexe serait un bouton « sauvegarder maintenant » appelant `mysqldump`.
 * Ce serait irresponsable, pour trois raisons qui tiennent toutes :
 *
 *  · **`mysqldump` n'est pas garanti disponible.** La cible de déploiement est
 *    un hébergement cPanel mutualisé, où `exec()` est souvent désactivé et le
 *    binaire absent du `PATH`. Un bouton qui échoue silencieusement sur un
 *    écran de sauvegarde est pire que pas de bouton : on croit être sauvegardé.
 *
 *  · **Une sauvegarde qu'on ne peut pas RESTAURER ne sert à rien.** Livrer
 *    l'aller sans le retour donnerait une sécurité imaginaire — et personne ne
 *    s'en aperçoit avant le jour où il faut restaurer.
 *
 *  · **Le dump contient TOUT** : adresses, mots de passe hachés, journaux
 *    d'audit, dossiers de vérification. Le déposer sur un disque accessible au
 *    web, ou le proposer au téléchargement depuis un navigateur, créerait une
 *    fuite bien pire que l'absence de sauvegarde.
 *
 * ## Ce qu'il fait, et qui est utile
 *
 * Il LIT le disque de sauvegarde configuré et dit ce qu'il y trouve : combien
 * de fichiers, quand date le plus récent, quelle taille. C'est ce qui répond à
 * la seule question qu'on se pose vraiment — **« sommes-nous sauvegardés ? »**
 * — sans prétendre y répondre à la place de l'hébergeur.
 *
 * Et quand il n'y a rien, il le dit sans détour : aucune sauvegarde n'est
 * produite par la plateforme, et c'est à mettre en place hors d'elle.
 *
 * **Le module complet reste à faire, et le journal du chantier le porte.**
 */
#[Layout('components.layouts.staff', ['title' => "Sauvegardes · Vérif'All"])]
final class SauvegardesBaseDeDonnees extends Component
{
    /**
     * Le dossier scruté, sous le disque `local`.
     *
     * Volontairement HORS du disque public : un dump de base de données
     * accessible par une URL serait une fuite complète.
     */
    private const DOSSIER = 'sauvegardes';

    public function mount(): void
    {
        abort_unless(Auth::guard('staff')->user()?->can('maintenance.backup') ?? false, 403);
    }

    public function render(): View
    {
        $fichiers = $this->fichiers();

        return view('livewire.staff.sauvegardes', [
            'fichiers' => $fichiers,
            'indicateurs' => $this->indicateurs($fichiers),
            'dossier' => self::DOSSIER,
        ]);
    }

    /**
     * Ce que le disque contient, du plus récent au plus ancien.
     *
     * @return list<array{nom: string, taille: int, date: ?Carbon}>
     */
    private function fichiers(): array
    {
        $disque = Storage::disk('local');

        if (! $disque->directoryExists(self::DOSSIER)) {
            return [];
        }

        $lignes = [];

        foreach ($disque->files(self::DOSSIER) as $chemin) {
            $lignes[] = [
                'nom' => basename($chemin),
                'taille' => $disque->size($chemin),
                'date' => Carbon::createFromTimestamp($disque->lastModified($chemin)),
            ];
        }

        usort($lignes, static fn (array $a, array $b): int => ($b['date']?->timestamp ?? 0) <=> ($a['date']?->timestamp ?? 0));

        return $lignes;
    }

    /**
     * @param  list<array{nom: string, taille: int, date: ?Carbon}>  $fichiers
     * @return array{nombre: int, poids: int, plus_recente: ?Carbon, age_jours: ?int}
     */
    private function indicateurs(array $fichiers): array
    {
        $plusRecente = $fichiers === [] ? null : $fichiers[0]['date'];

        return [
            'nombre' => count($fichiers),
            'poids' => array_sum(array_column($fichiers, 'taille')),
            'plus_recente' => $plusRecente,
            /*
             | L'ÂGE de la dernière sauvegarde, en jours. C'est le seul chiffre
             | qui compte : « 12 sauvegardes » ne dit rien si la plus récente
             | date de six mois. `null` quand il n'y en a aucune — et non zéro,
             | qui se lirait « sauvegardé aujourd'hui ».
             */
            'age_jours' => $plusRecente?->diffInDays(now()) !== null
                ? (int) $plusRecente->diffInDays(now())
                : null,
        ];
    }
}
