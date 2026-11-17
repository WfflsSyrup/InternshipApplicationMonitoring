<?php

namespace Database\Seeders;

use App\Models\ApplicationStatusLog;
use App\Models\InternshipApplication;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DemoSeeder extends Seeder
{
    private const PASSWORD = 'Password123!';

    public function run(): void
    {
        $coordinator = $this->user([
            'student_or_employee_id' => 'EMP-COORD-001',
            'name' => 'Program Coordinator',
            'email' => 'coord.demo@uls-test.edu.ph',
            'role' => User::ROLE_COORDINATOR,
            'department_or_company' => 'College of Information Technology',
        ]);

        $adviser = $this->user([
            'student_or_employee_id' => 'EMP-ADV-001',
            'name' => 'Engr. Rowena Villanueva',
            'email' => 'adviser.demo@uls-test.edu.ph',
            'role' => User::ROLE_ADVISER,
            'department_or_company' => 'BSIT Department',
        ]);

        $partnerReviewer = $this->user([
            'student_or_employee_id' => 'EMP-PARTNER-001',
            'name' => 'TechCorp Talent Partner',
            'email' => 'partner.demo@techcorp-test.com',
            'role' => User::ROLE_PARTNER_REVIEWER,
            'department_or_company' => 'TechCorp Test Solutions',
        ]);

        $studentOne = $this->user([
            'student_or_employee_id' => '2026-00001',
            'name' => 'Ana Dela Cruz',
            'email' => 'student1.demo@uls-test.edu.ph',
            'role' => User::ROLE_STUDENT,
            'department_or_company' => 'BSIT Department',
        ]);

        $studentTwo = $this->user([
            'student_or_employee_id' => '2026-00002',
            'name' => 'Miguel Santos',
            'email' => 'student2.demo@uls-test.edu.ph',
            'role' => User::ROLE_STUDENT,
            'department_or_company' => 'BSIT Department',
        ]);

        $allRequirements = [
            'resume_ready' => true,
            'endorsement_letter' => true,
            'parent_consent' => true,
            'medical_clearance' => true,
        ];

        $submitted = $this->application($studentOne, [
            'reference_code' => 'INT-2026-0001',
            'host_company_name' => 'TechCorp Test Solutions',
            'target_role' => 'Junior Backend Developer',
            'internship_hours_required' => 486,
            'target_start_date' => '2026-02-02',
            'requirements_checklist' => array_merge($allRequirements, ['endorsement_letter' => false]),
            'status' => InternshipApplication::STATUS_SUBMITTED,
        ], Carbon::now()->subDays(7));

        $this->log($submitted, $studentOne, null, InternshipApplication::STATUS_DRAFT, 'Application created.', Carbon::now()->subDays(7));
        $this->log($submitted, $studentOne, InternshipApplication::STATUS_DRAFT, InternshipApplication::STATUS_SUBMITTED, 'Submitted for adviser review.', Carbon::now()->subDays(6));

        $verified = $this->application($studentTwo, [
            'reference_code' => 'INT-2026-0002',
            'host_company_name' => 'DataNest Analytics',
            'target_role' => 'Data Analyst Intern',
            'internship_hours_required' => 400,
            'target_start_date' => '2026-02-09',
            'requirements_checklist' => $allRequirements,
            'status' => InternshipApplication::STATUS_REQUIREMENTS_VERIFIED,
            'adviser_id' => $adviser->getKey(),
        ], Carbon::now()->subDays(9));

        $this->log($verified, $studentTwo, null, InternshipApplication::STATUS_DRAFT, 'Application created.', Carbon::now()->subDays(9));
        $this->log($verified, $studentTwo, InternshipApplication::STATUS_DRAFT, InternshipApplication::STATUS_SUBMITTED, 'Submitted for adviser review.', Carbon::now()->subDays(8));
        $this->log($verified, $adviser, InternshipApplication::STATUS_SUBMITTED, InternshipApplication::STATUS_REQUIREMENTS_VERIFIED, 'All academic requirements validated against the curriculum checklist.', Carbon::now()->subDays(5));

        $partnerSubmitted = $this->application($studentOne, [
            'reference_code' => 'INT-2026-0003',
            'host_company_name' => 'TechCorp Test Solutions',
            'target_role' => 'QA Test Intern',
            'internship_hours_required' => 500,
            'target_start_date' => '2026-03-02',
            'requirements_checklist' => $allRequirements,
            'status' => InternshipApplication::STATUS_PARTNER_SUBMITTED,
            'adviser_id' => $adviser->getKey(),
            'partner_reviewer_id' => $partnerReviewer->getKey(),
        ], Carbon::now()->subDays(13));

        $this->log($partnerSubmitted, $studentOne, null, InternshipApplication::STATUS_DRAFT, 'Application created.', Carbon::now()->subDays(13));
        $this->log($partnerSubmitted, $studentOne, InternshipApplication::STATUS_DRAFT, InternshipApplication::STATUS_SUBMITTED, 'Submitted for adviser review.', Carbon::now()->subDays(12));
        $this->log($partnerSubmitted, $adviser, InternshipApplication::STATUS_SUBMITTED, InternshipApplication::STATUS_REQUIREMENTS_VERIFIED, 'Academic requirements verified.', Carbon::now()->subDays(10));
        $this->log($partnerSubmitted, $adviser, InternshipApplication::STATUS_REQUIREMENTS_VERIFIED, InternshipApplication::STATUS_ENDORSED, 'Endorsed for partner deployment.', Carbon::now()->subDays(9));
        $this->log($partnerSubmitted, $coordinator, InternshipApplication::STATUS_ENDORSED, InternshipApplication::STATUS_PARTNER_SUBMITTED, 'Endorsed package forwarded to the assigned partner reviewer.', Carbon::now()->subDays(4));

        $interviewScheduled = $this->application($studentTwo, [
            'reference_code' => 'INT-2026-0004',
            'host_company_name' => 'Nexa Systems Inc.',
            'target_role' => 'Mobile Development Intern',
            'internship_hours_required' => 420,
            'target_start_date' => '2026-02-16',
            'requirements_checklist' => $allRequirements,
            'status' => InternshipApplication::STATUS_INTERVIEW_SCHEDULED,
            'adviser_id' => $adviser->getKey(),
            'partner_reviewer_id' => $partnerReviewer->getKey(),
            'interview_schedule' => Carbon::now()->addDays(14)->setTime(10, 0),
            'interview_notes' => 'Panel interview with the mobile engineering team.',
        ], Carbon::now()->subDays(16));

        $this->log($interviewScheduled, $studentTwo, null, InternshipApplication::STATUS_DRAFT, 'Application created.', Carbon::now()->subDays(16));
        $this->log($interviewScheduled, $studentTwo, InternshipApplication::STATUS_DRAFT, InternshipApplication::STATUS_SUBMITTED, 'Submitted for adviser review.', Carbon::now()->subDays(15));
        $this->log($interviewScheduled, $adviser, InternshipApplication::STATUS_SUBMITTED, InternshipApplication::STATUS_REQUIREMENTS_VERIFIED, 'Academic requirements verified.', Carbon::now()->subDays(13));
        $this->log($interviewScheduled, $adviser, InternshipApplication::STATUS_REQUIREMENTS_VERIFIED, InternshipApplication::STATUS_ENDORSED, 'Endorsed for partner deployment.', Carbon::now()->subDays(12));
        $this->log($interviewScheduled, $coordinator, InternshipApplication::STATUS_ENDORSED, InternshipApplication::STATUS_PARTNER_SUBMITTED, 'Endorsed package forwarded to the assigned partner reviewer.', Carbon::now()->subDays(8));
        $this->log($interviewScheduled, $partnerReviewer, InternshipApplication::STATUS_PARTNER_SUBMITTED, InternshipApplication::STATUS_INTERVIEW_SCHEDULED, 'Panel interview scheduled with the mobile engineering team.', Carbon::now()->subDays(6));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(array $attributes): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $attributes['email']],
            array_merge($attributes, ['password' => self::PASSWORD]),
        );

        return $user->fresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function application(User $student, array $attributes, Carbon $createdAt): InternshipApplication
    {
        $application = InternshipApplication::query()->updateOrCreate(
            ['reference_code' => $attributes['reference_code']],
            array_merge($attributes, ['student_id' => $student->getKey()]),
        );

        $application->created_at = $createdAt;
        $application->save();

        return $application;
    }

    private function log(InternshipApplication $application, User $actor, ?string $from, string $to, string $remarks, Carbon $at): void
    {
        $log = new ApplicationStatusLog([
            'internship_application_id' => $application->getKey(),
            'actor_id' => $actor->getKey(),
            'actor_role' => $actor->role,
            'from_status' => $from,
            'to_status' => $to,
            'remarks' => $remarks,
        ]);

        $log->created_at = $at;
        $log->save();
    }
}
