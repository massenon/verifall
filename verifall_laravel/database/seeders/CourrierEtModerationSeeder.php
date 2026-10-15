<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ContactMessage;
use App\Models\ContentReport;
use App\Models\NewsletterSubscriber;
use App\Models\Reply;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * De quoi remplir trois écrans qui, en démonstration, paraissaient en panne.
 *
 * ## Pourquoi un seeder pour ça
 *
 * Un écran vide et un écran cassé se ressemblent exactement. Quelqu'un qui
 * ouvre « Messages reçus » ou « Contenus signalés » sur une base de
 * démonstration et n'y voit rien conclut que la fonctionnalité ne marche pas —
 * et il a de bonnes raisons, puisque c'était vrai jusqu'à hier.
 *
 * ## Rejouable sans doublonner
 *
 * Chaque enregistrement est reconnu par une clé stable — l'adresse pour un
 * abonné, la paire contenu/motif pour un signalement. Rejouer le seeder ne
 * duplique donc rien, ce que la suite de tests vérifie sur l'ensemble du jeu
 * de démonstration.
 *
 * ## Aucune donnée réelle
 *
 * Les adresses sont sur `example.com`, réservé à cet usage par la RFC 2606 :
 * aucun courriel de démonstration ne peut partir vers une vraie boîte, même si
 * quelqu'un déclenche un envoi par mégarde.
 */
final class CourrierEtModerationSeeder extends Seeder
{
    public function run(): void
    {
        $this->abonnes();
        $this->messages();
        $this->signalements();
    }

    /**
     * Des abonnés à l'infolettre, dans les trois états qui existent.
     *
     * Les trois, et pas seulement des confirmés : l'écran distingue « en
     * attente » de « désabonné », et une liste où tout le monde est confirmé ne
     * montrerait jamais ces colonnes à l'œuvre.
     */
    private function abonnes(): void
    {
        $jeu = [
            ['sena.hounkpe@example.com', 'Séna Hounkpè', 'confirme'],
            ['marius.dossou@example.com', 'Marius Dossou', 'confirme'],
            ['adjoa.kponou@example.com', 'Adjoa Kponou', 'confirme'],
            ['ines.tchibozo@example.com', 'Inès Tchibozo', 'confirme'],
            ['gildas.agbo@example.com', 'Gildas Agbo', 'confirme'],
            ['fabrice.zinsou@example.com', 'Fabrice Zinsou', 'attente'],
            ['clarisse.bio@example.com', 'Clarisse Bio', 'attente'],
            ['romuald.kakpo@example.com', 'Romuald Kakpo', 'desabonne'],
        ];

        foreach ($jeu as $index => [$email, $nom, $etat]) {
            if (NewsletterSubscriber::query()->where('email', $email)->exists()) {
                continue;
            }

            NewsletterSubscriber::query()->create([
                'uuid' => (string) Str::uuid(),
                'email' => $email,
                'name' => $nom,
                /*
                 | Les jetons sont HACHÉS, comme en service. Les semer en clair
                 | ferait diverger la démonstration du fonctionnement réel, et
                 | c'est précisément le genre d'écart qui fait conclure à un
                 | défaut le jour où l'on teste le lien de désabonnement.
                 */
                'confirmation_token' => Hash::make((string) Str::uuid()),
                // Obligatoire en base : c'est lui qui signe le lien « se
                // désabonner » de chaque envoi. L'omettre ferait échouer
                // l'insertion — et, en service, priverait l'abonné de sortie.
                'unsubscribe_token' => Hash::make((string) Str::uuid()),
                'confirmed_at' => $etat === 'confirme' ? now()->subDays(30 - $index) : null,
                'unsubscribed_at' => $etat === 'desabonne' ? now()->subDays(3) : null,
                'created_at' => now()->subDays(40 - $index),
            ]);
        }
    }

    /** Des messages de contact, dans les quatre statuts. */
    private function messages(): void
    {
        $jeu = [
            [
                'Awa Sossou', 'awa.sossou@example.com', 'RTB Cotonou',
                "Bonjour, une vidéo circule depuis hier sur WhatsApp : on y voit une file d'attente devant une station-service, avec un message annonçant une pénurie de carburant à partir de lundi. Pouvez-vous vérifier ? Beaucoup de gens y croient dans mon quartier.",
                'new', 0,
            ],
            [
                'Kossi Adjovi', 'kossi.adjovi@example.com', null,
                "Je suis enseignant à Parakou et j'aimerais utiliser vos tutoriels en classe de terminale. Est-ce possible d'obtenir des comptes pour mes élèves ?",
                'in_progress', 2,
            ],
            [
                'Rachida Baba', 'rachida.baba@example.com', 'Radio Tokpa',
                "Nous préparons une émission sur la désinformation en période électorale et souhaiterions inviter quelqu'un de votre équipe. Quelle est la marche à suivre ?",
                'handled', 9,
            ],
            [
                'Blaise Amoussou', 'blaise.amoussou@example.com', null,
                "Le message que j'ai vu circuler annonce la gratuité de l'électricité en septembre. Mon voisin l'a partagé à tout son carnet d'adresses. J'aimerais un démenti clair à lui envoyer.",
                'new', 1,
            ],
            [
                'Offre exceptionnelle', 'promo@example.com', null,
                'Augmentez votre trafic de 500 % dès cette semaine. Réponse immédiate garantie, cliquez sans tarder pour en profiter.',
                'spam', 4,
            ],
        ];

        foreach ($jeu as [$nom, $email, $organisation, $texte, $statut, $joursPasses]) {
            if (ContactMessage::query()->where('email', $email)->exists()) {
                continue;
            }

            $traitant = in_array($statut, ['handled', 'in_progress', 'spam'], true)
                ? User::query()->where('email', 'rene@verifall.com')->value('id')
                : null;

            ContactMessage::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $nom,
                'email' => $email,
                'organisation' => $organisation,
                'message' => $texte,
                'status' => $statut,
                'handled_by' => $traitant,
                'handled_at' => $traitant !== null ? now()->subDays(max(0, $joursPasses - 1)) : null,
                'created_at' => now()->subDays($joursPasses),
                'updated_at' => now()->subDays($joursPasses),
            ]);
        }
    }

    /**
     * Des signalements de contenu, sur DEUX types différents.
     *
     * Deux, et pas un seul : c'est ce qui montre que la file est bien commune
     * aux sujets et aux réponses, et fait apparaître le filtre par type.
     */
    private function signalements(): void
    {
        $signaleur = User::query()->where('email', 'adderly@verifall.com')->value('id')
            ?? User::query()->value('id');

        if ($signaleur === null) {
            return;
        }

        $cibles = array_values(array_filter([
            [Thread::class, Thread::query()->value('id'), 'Hors sujet', "Ce fil n'a rien à voir avec la catégorie où il est publié."],
            [Reply::class, Reply::query()->value('id'), 'Propos injurieux', 'La réponse prend à partie un autre membre nommément.'],
            [Thread::class, Thread::query()->skip(1)->value('id'), 'Fausse information', 'Le message reprend une rumeur déjà démentie par la plateforme, sans le mentionner.'],
        ], static fn (array $c): bool => $c[1] !== null));

        $statuts = ['pending', 'pending', 'upheld'];

        foreach ($cibles as $index => [$type, $id, $motif, $detail]) {
            $existe = ContentReport::query()
                ->where('reportable_type', $type)
                ->where('reportable_id', $id)
                ->where('reason', $motif)
                ->exists();

            if ($existe) {
                continue;
            }

            $statut = $statuts[$index] ?? 'pending';
            $traitant = $statut === 'pending'
                ? null
                : User::query()->where('email', 'sandrine@verifall.com')->value('id');

            ContentReport::query()->create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $signaleur,
                'reportable_type' => $type,
                'reportable_id' => $id,
                'reason' => $motif,
                'detail' => $detail,
                'status' => $statut,
                'handled_by' => $traitant,
                'handled_at' => $traitant !== null ? now()->subDay() : null,
                'handling_note' => $traitant !== null
                    ? 'Contenu retiré et auteur averti.'
                    : null,
                'created_at' => now()->subDays(6 - $index),
                'updated_at' => now()->subDays(6 - $index),
            ]);
        }
    }
}
