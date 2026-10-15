<?php

declare(strict_types=1);

namespace App\Core\Community\Services;

use App\Core\Application\Contracts\DepotNotes;
use App\Enums\StatutNoteCommunaute;
use App\Models\CommunityNote;
use App\Models\CommunityNoteVote;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mews\Purifier\Facades\Purifier;

/**
 * Proposer une note, voter sur une note, savoir ce qu'on a le droit de voir.
 *
 * La règle de visibilité tient en une phrase : une note n'apparaît
 * publiquement que si elle a FRANCHI LE PONT ; sinon elle n'est visible que de
 * son auteur. C'est ce qui empêche le mécanisme de servir de seconde tribune —
 * écrire une note ne donne pas la parole, la faire approuver par des gens qui
 * ne s'accordent pas la donne.
 */
final class NotesDeCommunaute
{
    /**
     * Une seule note par personne et par sujet.
     *
     * POURQUOI : sans cette limite, quelqu'un publierait cinq variantes de la
     * même note en espérant qu'une passe. Le mécanisme deviendrait un jeu de
     * hasard, et l'algorithme de pontage n'aurait plus assez de votes par note
     * pour dire quoi que ce soit.
     */
    public const NOTES_PAR_PERSONNE_ET_SUJET = 1;

    /** Longueur maximale du texte — une note ajoute du contexte, pas une tribune. */
    public const LONGUEUR_MAXIMALE = 1000;

    /** Nombre maximal de sources retenues, pour éviter la liste-fleuve. */
    public const SOURCES_MAXIMUM = 5;

    public function __construct(private readonly DepotNotes $depot) {}

    /**
     * Propose une note sur un sujet.
     *
     * @param  list<string>  $sources
     *
     * @throws ValidationException
     */
    public function proposer(User $auteur, Thread $sujet, string $texte, array $sources): CommunityNote
    {
        $texte = trim($texte);

        if (mb_strlen($texte) < 20) {
            throw ValidationException::withMessages([
                'texte' => 'Une note doit apporter un contexte : écrivez au moins une phrase.',
            ]);
        }

        $liens = $this->liensValides($sources);

        /*
         | UNE NOTE SANS SOURCE N'EST QU'UN AVIS DE PLUS — et c'est exactement
         | ce dont un fil polarisé n'a pas besoin. Le refus est ici, dans le
         | service, et non seulement dans le formulaire : une API, un import ou
         | un seeder empruntent le même chemin.
         */
        if ($liens === []) {
            throw ValidationException::withMessages([
                'sources' => 'Une note doit citer au moins une source vérifiable (une adresse web complète).',
            ]);
        }

        if ($this->depot->compterNotesDeLAuteurSurSujet($sujet, $auteur) >= self::NOTES_PAR_PERSONNE_ET_SUJET) {
            throw ValidationException::withMessages([
                'texte' => 'Vous avez déjà proposé une note sur ce sujet.',
            ]);
        }

        return $this->depot->creer([
            'uuid' => (string) Str::uuid(),
            'thread_id' => $sujet->id,
            'user_id' => $auteur->id,
            // Purifié AVANT persistance, puis affiché en texte simple : une
            // note est du texte, jamais du balisage.
            'body' => trim(strip_tags((string) Purifier::clean($texte, 'default'))),
            'sources' => array_slice($liens, 0, self::SOURCES_MAXIMUM),
            // Toute note naît EN ATTENTE. Aucune n'est publiée par le seul
            // fait d'avoir été écrite, pas même celle d'un modérateur.
            'status' => StatutNoteCommunaute::EnAttente->value,
        ]);
    }

    /**
     * Enregistre le vote de quelqu'un sur une note.
     *
     * Un vote par personne et par note, et un second vote REMPLACE le premier.
     * Changer d'avis est légitime ; peser deux fois ne l'est pas — et la
     * mesure de polarisation, qui croise les votants deux à deux, prendrait un
     * acharné pour une communauté.
     *
     * @throws ValidationException
     */
    public function voter(User $votant, CommunityNote $note, bool $utile): CommunityNoteVote
    {
        if ($note->user_id === $votant->id) {
            // Voter pour soi-même n'ajoute aucune information : l'auteur trouve
            // évidemment sa note utile. Pire, cela pousserait artificiellement
            // le compteur d'utilité d'une note qui n'a convaincu personne.
            throw ValidationException::withMessages([
                'vote' => 'On ne vote pas sur sa propre note.',
            ]);
        }

        return $this->depot->enregistrerVote($note, $votant, $utile);
    }

    /**
     * Ce qu'une personne a le droit de voir sur un sujet.
     *
     * Les notes publiées d'abord — elles s'adressent à tout le monde — puis,
     * s'il y en a, les notes de la personne elle-même qui n'ont pas encore
     * franchi le pont.
     *
     * Trois groupes, et ils ne se confondent jamais à l'écran :
     *
     *   · `publiees` — le contexte, visible de tout le monde ;
     *   · `miennes`  — ses propres notes non publiées, avec l'explication de
     *     leur état ; nulle part ailleurs ;
     *   · `a_evaluer` — les notes en attente des AUTRES, proposées au vote.
     *     Sans ce troisième groupe, aucune note ne recevrait jamais de vote et
     *     le mécanisme entier serait décoratif. Voir `docs/ARBITRAGES.md`,
     *     entrée 162.
     *
     * @return array{
     *     publiees: Collection<int, CommunityNote>,
     *     miennes: Collection<int, CommunityNote>,
     *     a_evaluer: Collection<int, CommunityNote>,
     * }
     */
    public function visiblesPour(Thread $sujet, ?User $lecteur): array
    {
        $publiees = $this->depot->publieesPourSujet($sujet);

        if ($lecteur === null) {
            /** @var Collection<int, CommunityNote> $vide */
            $vide = new Collection;

            // Un visiteur non connecté voit le contexte publié, et rien
            // d'autre : il ne peut pas voter, lui montrer la file d'évaluation
            // reviendrait à publier des notes qui n'ont rien franchi.
            return ['publiees' => $publiees, 'miennes' => $vide, 'a_evaluer' => $vide];
        }

        $publieesIds = $publiees->pluck('id')->all();

        return [
            'publiees' => $publiees,
            // Sans celles déjà listées plus haut : une note publiée apparaîtrait
            // deux fois à son auteur, qui croirait l'avoir écrite en double.
            'miennes' => $this->depot->notesDeLAuteur($sujet, $lecteur)
                ->reject(static fn (CommunityNote $note): bool => in_array($note->id, $publieesIds, true))
                ->values(),
            'a_evaluer' => $this->depot->enAttentePourSujet($sujet, $lecteur),
        ];
    }

    /**
     * @return array<int, bool> identifiant de note => utile
     */
    public function mesVotes(Thread $sujet, ?User $lecteur): array
    {
        if ($lecteur === null) {
            return [];
        }

        return $this->depot->mesVotesSurSujet($sujet, $lecteur);
    }

    public function parUuid(string $uuid): ?CommunityNote
    {
        return $this->depot->parUuid($uuid);
    }

    /**
     * Ne garde que ce qui est réellement une adresse web.
     *
     * « je l'ai vu sur WhatsApp » n'est pas une source : la stocker
     * produirait un lien mort affiché comme une preuve, ce qui est pire que
     * pas de source du tout.
     *
     * @param  list<string>  $sources
     * @return list<string>
     */
    private function liensValides(array $sources): array
    {
        $liens = [];

        foreach ($sources as $source) {
            $source = trim($source);

            if ($source === '' || filter_var($source, FILTER_VALIDATE_URL) === false) {
                continue;
            }

            // Seuls http(s) : un `javascript:` ou un `data:` validé par
            // FILTER_VALIDATE_URL deviendrait un lien cliquable dans la page.
            if (! in_array(Str::lower((string) parse_url($source, PHP_URL_SCHEME)), ['http', 'https'], true)) {
                continue;
            }

            if (! in_array($source, $liens, true)) {
                $liens[] = $source;
            }
        }

        return $liens;
    }
}
