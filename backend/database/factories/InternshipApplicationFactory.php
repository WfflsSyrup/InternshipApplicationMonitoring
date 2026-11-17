<?php

namespace Database\Factories;

use App\Models\InternshipApplication;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InternshipApplication>
 */
class InternshipApplicationFactory extends Factory
{
    protected $model = InternshipApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_code' => 'INT-'.now()->year.'-'.fake()->unique()->numerify('####'),
            'student_id' => User::factory()->student(),
            'adviser_id' => null,
            'partner_reviewer_id' => null,
            'host_company_name' => fake()->company(),
            'target_role' => 'Intern',
            'internship_hours_required' => 486,
            'target_start_date' => now()->addMonths(2)->toDateString(),
            'requirements_checklist' => array_fill_keys(
                array_keys(InternshipApplication::DEFAULT_REQUIREMENTS_CHECKLIST),
                true,
            ),
            'status' => InternshipApplication::STATUS_DRAFT,
            'interview_schedule' => null,
            'interview_notes' => null,
            'placement_confirmed_at' => null,
            'placement_acknowledged_at' => null,
        ];
    }

    public function withStatus(string $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
        ]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'partner_reviewer_id' => $user->getKey(),
        ]);
    }
}