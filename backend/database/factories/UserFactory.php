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
        return [
            'student_or_employee_id' => fake()->unique()->numerify('ID-####'),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => User::ROLE_STUDENT,
            'department_or_company' => 'BSIT Department',
            'remember_token' => Str::random(10),
        ];
    }

    public function student(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => User::ROLE_STUDENT,
            'department_or_company' => 'BSIT Department',
        ]);
    }

    public function adviser(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => User::ROLE_ADVISER,
            'department_or_company' => 'BSIT Department',
        ]);
    }

    public function coordinator(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => User::ROLE_COORDINATOR,
            'department_or_company' => 'College of Information Technology',
        ]);
    }

    public function partnerReviewer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => User::ROLE_PARTNER_REVIEWER,
            'department_or_company' => 'Partner Test Company',
        ]);
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
