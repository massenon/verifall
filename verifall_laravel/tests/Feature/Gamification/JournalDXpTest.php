<?php

declare(strict_types=1);

use App\Core\Application\Contracts\XpLogRepositoryInterface;
use App\Core\Gamification\Services\JournalDXp;
use App\Enums\MotifGainXp;
use App\Models\Badge;
use App\Models\User;
use App\Models\XpLog;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| Le journal des points d'expérience
|--------------------------------------------------------------------------
|
| Il n'existait pas. Quatre services appelaient `increment('xp', …)`
| directement — quiz de cours, quiz d'accueil, badges, prime de fin de cours —
| et le commentaire de `User` affirmait pourtant que l'XP passait par un service
| transactionnel, comme les unités.
|
| La propriété que ces tests tiennent est UNE : **le compteur et le journal
| disent la même chose.** Tout le reste en découle — c'est l'écart qui rendrait
| un litige intranchable.
|
*/

beforeEach(function (): void {
    $this->citoyen = User::factory()->actif()->create();
    $this->citoyen->forceFill(['xp' => 0])->save();

    $this->journal = app(JournalDXp::class);
    $this->depot = app(XpLogRepositoryInterface::class);
});

it('crédite le compteur ET écrit la ligne, indissociablement', function () {
    $ligne = $this->journal->crediter($this->citoyen, 50, MotifGainXp::Quiz);

    expect($this->citoyen->fresh()->xp)->toBe(50)
        ->and($ligne->delta)->toBe(50)
        ->and($ligne->balance_after)->toBe(50)
        ->and($ligne->reason)->toBe(MotifGainXp::Quiz);
});

it('garde le compteur et le journal d’accord sur une suite de gains', function () {
    // LA propriété. Un écart ici est le signal qu'une écriture est passée à côté
    // du service — exactement ce qui était vrai partout avant qu'il existe.
    $this->journal->crediter($this->citoyen, 50, MotifGainXp::Quiz);
    $this->journal->crediter($this->citoyen, 250, MotifGainXp::Cours);
    $this->journal->crediter($this->citoyen, 30, MotifGainXp::Badge);

    expect($this->depot->somme($this->citoyen))->toBe(330)
        ->and($this->citoyen->fresh()->xp)->toBe(330);
});

it('inscrit le solde APRÈS chaque mouvement, pas le montant', function () {
    // `balance_after` est ce qui rend une ligne vérifiable sans rejouer tout
    // l'historique.
    $this->journal->crediter($this->citoyen, 50, MotifGainXp::Quiz);
    $deuxieme = $this->journal->crediter($this->citoyen, 250, MotifGainXp::Cours);

    expect($deuxieme->balance_after)->toBe(300)
        ->and($deuxieme->delta)->toBe(250);
});

it('aligne l’instance de l’appelant, pour ne pas afficher l’ancien total', function () {
    $this->journal->crediter($this->citoyen, 120, MotifGainXp::Cours);

    // Sans cet alignement, l'écran affiche le total d'avant le gain qu'il vient
    // d'annoncer.
    expect($this->citoyen->xp)->toBe(120);
});

it('refuse un crédit nul ou négatif', function () {
    // Un « gain » de zéro n'a rien à faire dans un journal, et un gain négatif
    // est un retrait : il a sa propre méthode, et son propre motif.
    expect(fn () => $this->journal->crediter($this->citoyen, 0, MotifGainXp::Quiz))
        ->toThrow(RuntimeException::class);

    expect(fn () => $this->journal->crediter($this->citoyen, -10, MotifGainXp::Quiz))
        ->toThrow(RuntimeException::class);

    expect(XpLog::count())->toBe(0);
});

it('écrit le libellé dans la langue de REPLI, pas celle de l’écran', function () {
    // Un journal panaché ne se cherche plus : les mêmes opérations y porteraient
    // deux libellés selon la langue du navigateur du jour.
    app()->setLocale('en');

    $ligne = $this->journal->crediter($this->citoyen, 40, MotifGainXp::Cours);

    expect($ligne->label)->toBe('Cours terminé');
});

it('rattache la source quand on la lui donne', function () {
    $badge = Badge::create([
        'uuid' => (string) Str::uuid(),
        'slug' => 'verificateur-aguerri',
        'icon' => 'shield',
        'name' => 'Vérificateur aguerri',
        'description' => 'Dix vérifications menées à leur terme.',
        'requirement_label' => '10 vérifications',
        'xp_reward' => 30,
        'units_reward' => 0,
        'position' => 1,
        'is_active' => true,
    ]);

    $ligne = $this->journal->crediter($this->citoyen, 30, MotifGainXp::Badge, $badge);

    expect($ligne->source_type)->toBe(Badge::class)
        ->and($ligne->source_id)->toBe($badge->id)
        ->and($ligne->source->is($badge))->toBeTrue();
});

it('groupe la répartition par motif, en base', function () {
    $this->journal->crediter($this->citoyen, 50, MotifGainXp::Quiz);
    $this->journal->crediter($this->citoyen, 20, MotifGainXp::Quiz);
    $this->journal->crediter($this->citoyen, 250, MotifGainXp::Cours);

    expect($this->depot->repartition($this->citoyen))
        ->toBe(['course' => 250, 'quiz' => 70]);
});

it('ne laisse PAS voir le journal d’un autre compte', function () {
    // Le bornage est dans le dépôt, pas dans l'appelant : c'est là que la fuite
    // se produirait.
    $autre = User::factory()->actif()->create();
    $this->journal->crediter($autre, 999, MotifGainXp::Cours);

    expect($this->depot->somme($this->citoyen))->toBe(0)
        ->and($this->depot->repartition($this->citoyen))->toBe([]);
});

it('ramène un retrait excessif à zéro, et inscrit le solde RÉEL', function () {
    /*
     | Contrairement aux unités, l'XP n'achète rien : un total négatif n'y
     | voudrait rien dire. Refuser la correction laisserait le compte faux, donc
     | on la borne — et c'est le mouvement RÉELLEMENT appliqué qui est journalisé,
     | sans quoi la somme du journal cesserait d'égaler le compteur.
     */
    $this->journal->crediter($this->citoyen, 100, MotifGainXp::Cours);

    $retrait = $this->journal->retirer($this->citoyen, 500);

    expect($this->citoyen->fresh()->xp)->toBe(0)
        ->and($retrait->delta)->toBe(-100)
        ->and($retrait->balance_after)->toBe(0)
        ->and($this->depot->somme($this->citoyen))->toBe(0);
});
