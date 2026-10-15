<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prenom = fake()->firstName();
        $nom = fake()->lastName();

        return [
            /*
             | L'uuid est l'identifiant PUBLIC : getRouteKeyName() le désigne, et
             | aucune URL n'expose l'auto-incrément. Sans lui, un utilisateur de
             | test n'est adressable par aucune route — la colonne étant unique
             | mais nullable, rien ne s'en plaignait.
             */
            'uuid' => (string) Str::uuid(),
            'name' => $prenom.' '.$nom,
            'first_name' => $prenom,
            'last_name' => $nom,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Compte activé — l'OTP a été validé.
     *
     * `otp_verified_at` est hors $fillable : l'activation d'un compte ne peut
     * pas venir d'une requête. La factory passe donc par l'état brut.
     */
    public function actif(): static
    {
        return $this->state(fn (array $attributs) => ['otp_verified_at' => now()]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
