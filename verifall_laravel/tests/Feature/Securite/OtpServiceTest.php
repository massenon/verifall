<?php

declare(strict_types=1);

use App\Core\Identity\Services\OtpService;
use App\Core\Identity\Services\OtpVerification;
use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use App\Support\Hashing\IdentityHasher;

/*
|--------------------------------------------------------------------------
| Codes à usage unique — tests obligatoires de SECURITE_LOGIN
|--------------------------------------------------------------------------
| « OTP expiré refusé », « OTP rejoué refusé », « brute force bloqué » y sont
| explicitement exigés. Chacun est ici.
*/

beforeEach(function (): void {
    $this->otp = app(OtpService::class);
    $this->identite = 'adderly@verifall.com';
});

it('émet un code de six chiffres', function (): void {
    $code = $this->otp->issue($this->identite, OtpPurpose::Registration);

    expect($code)->toHaveLength(6)
        ->and(ctype_digit($code))->toBeTrue();
});

it('ne stocke jamais le code en clair', function (): void {
    $code = $this->otp->issue($this->identite, OtpPurpose::Registration);

    // Une fuite de la table ne doit pas livrer les codes en cours.
    expect(OtpCode::where('code_hash', $code)->exists())->toBeFalse()
        ->and(OtpCode::first()->code_hash)->not->toBe($code)
        ->and(OtpCode::first()->code_hash)->toHaveLength(64);
});

it('ne stocke pas non plus l\'identité en clair', function (): void {
    $this->otp->issue($this->identite, OtpPurpose::Registration);

    // La table sert l'anti-énumération : y lire les e-mails serait contradictoire.
    expect(OtpCode::first()->identity_hash)->not->toContain('adderly')
        ->and(OtpCode::first()->identity_hash)->toHaveLength(64);
});

it('accepte le bon code', function (): void {
    $code = $this->otp->issue($this->identite, OtpPurpose::Registration);

    expect($this->otp->verify($this->identite, OtpPurpose::Registration, $code))
        ->toBe(OtpVerification::Valide);
});

it('refuse un code rejoué', function (): void {
    $code = $this->otp->issue($this->identite, OtpPurpose::Registration);
    $this->otp->verify($this->identite, OtpPurpose::Registration, $code);

    // Usage unique : le même code ne doit jamais servir deux fois.
    expect($this->otp->verify($this->identite, OtpPurpose::Registration, $code)->estValide())
        ->toBeFalse();
});

it('refuse un code expiré', function (): void {
    $code = $this->otp->issue($this->identite, OtpPurpose::Registration);

    $this->travel(OtpService::TTL_MINUTES + 1)->minutes();

    expect($this->otp->verify($this->identite, OtpPurpose::Registration, $code))
        ->toBe(OtpVerification::Expire);
});

it('bloque le forçage après cinq tentatives', function (): void {
    $code = $this->otp->issue($this->identite, OtpPurpose::Registration);

    for ($i = 0; $i < OtpService::MAX_ATTEMPTS; $i++) {
        $this->otp->verify($this->identite, OtpPurpose::Registration, '000000');
    }

    // Le code est brûlé : même la BONNE valeur ne passe plus. Sans cela, un
    // code à six chiffres se force en quelques minutes.
    expect($this->otp->verify($this->identite, OtpPurpose::Registration, $code)->estValide())
        ->toBeFalse();
});

it('invalide le code précédent quand un nouveau est demandé', function (): void {
    $ancien = $this->otp->issue($this->identite, OtpPurpose::Registration);
    $nouveau = $this->otp->issue($this->identite, OtpPurpose::Registration);

    // Deux codes valides en parallèle doubleraient la surface d'attaque.
    expect($this->otp->verify($this->identite, OtpPurpose::Registration, $ancien)->estValide())
        ->toBeFalse()
        ->and($this->otp->verify($this->identite, OtpPurpose::Registration, $nouveau)->estValide())
        ->toBeTrue();
});

it('cloisonne les usages entre eux', function (): void {
    $code = $this->otp->issue($this->identite, OtpPurpose::Registration);

    // Un code d'inscription ne doit pas réinitialiser un mot de passe.
    expect($this->otp->verify($this->identite, OtpPurpose::PasswordReset, $code)->estValide())
        ->toBeFalse();
});

it('cloisonne les identités entre elles', function (): void {
    $code = $this->otp->issue($this->identite, OtpPurpose::Registration);

    expect($this->otp->verify('intrus@verifall.com', OtpPurpose::Registration, $code)->estValide())
        ->toBeFalse();
});

it('ignore la casse et les espaces autour de l\'identité', function (): void {
    $code = $this->otp->issue('Adderly@Verifall.com ', OtpPurpose::Registration);

    // Sinon le verrouillage se contournerait par une simple majuscule.
    expect($this->otp->verify('adderly@verifall.com', OtpPurpose::Registration, $code)->estValide())
        ->toBeTrue();
});

it('ne révèle rien lorsqu\'aucun code n\'a été demandé', function (): void {
    $resultat = $this->otp->verify('inconnu@verifall.com', OtpPurpose::Registration, '123456');

    // Message identique à celui d'un code faux : dire « aucun code en attente »
    // révélerait qu'aucune demande n'a été faite pour cette adresse.
    expect($resultat->message())
        ->toBe(OtpVerification::Invalide->message());
});

it('purge les codes expirés', function (): void {
    $this->otp->issue($this->identite, OtpPurpose::Registration);
    $this->travel(2)->days();

    expect($this->otp->purgeExpired())->toBe(1)
        ->and(OtpCode::count())->toBe(0);
});

describe('pseudonymisation', function (): void {
    it('sale les empreintes avec la clé applicative', function (): void {
        $hasher = app(IdentityHasher::class);

        // Un SHA-256 nu se renverserait par dictionnaire d'adresses courantes.
        expect($hasher->hash('adderly@verifall.com'))
            ->not->toBe(hash('sha256', 'adderly@verifall.com'));
    });

    it('compare en temps constant', function (): void {
        $hasher = app(IdentityHasher::class);
        $empreinte = $hasher->hash('41.85.12.34');

        expect($hasher->equals($empreinte, '41.85.12.34'))->toBeTrue()
            ->and($hasher->equals($empreinte, '41.85.12.35'))->toBeFalse();
    });
});
