<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\StoreApplicationRequest;
use App\Http\Requests\Applications\TransitionApplicationRequest;
use App\Http\Requests\Applications\UpdateApplicationRequest;
use App\Http\Resources\InternshipApplicationResource;
use App\Models\InternshipApplication;
use App\Models\User;
use App\Exceptions\InvalidStateTransitionException;
use App\Services\ApplicationWorkflowService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApplicationController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ApplicationWorkflowService $workflow)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InternshipApplication::class);

        $perPage = max(1, min(50, (int) $request->query('per_page', 15)));
        $page = max(1, (int) $request->query('page', 1));

        $query = InternshipApplication::query()
            ->visibleTo($request->user())
            ->with([
                'student:id,student_or_employee_id,name,role',
                'adviser:id,name,role',
                'partnerReviewer:id,name,role',
            ])
            ->withStatus($request->query('status'))
            ->select([
                'id',
                'reference_code',
                'status',
                'host_company_name',
                'target_role',
                'student_id',
                'adviser_id',
                'partner_reviewer_id',
                'updated_at',
                'version',
            ])
            ->latest('id');

        $total = $query->count();
        $applications = $query->forPage($page, $perPage)->get();

        $maxUpdatedAt = $applications->max('updated_at');
        $etag = $this->computeEtag($request->user(), $request->query('status'), $page, $perPage, $total, $maxUpdatedAt);

        $ifNoneMatch = $request->header('If-None-Match');

        if ($ifNoneMatch !== null && $ifNoneMatch === $etag) {
            return response()->json([], 304)
                ->header('ETag', $etag)
                ->header('Cache-Control', 'private, max-age=0, must-revalidate');
        }

        return self::success(
            InternshipApplicationResource::collection($applications),
            'Applications retrieved successfully.',
        )->header('ETag', $etag)
         ->header('X-Total-Count', (string) $total)
         ->header('X-Per-Page', (string) $perPage)
         ->header('X-Current-Page', (string) $page)
         ->header('X-Last-Page', (string) ceil($total / $perPage));
    }

    private function computeEtag(User $user, ?string $status, int $page, int $perPage, int $total, ?string $maxUpdatedAt): string
    {
        $parts = [
            $user->getKey(),
            $user->role,
            $status ?? 'all',
            $page,
            $perPage,
            $total,
            $maxUpdatedAt ?? 'none',
        ];

        return '"'.hash('sha256', implode('|', $parts)).'"';
    }

    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $this->authorize('create', InternshipApplication::class);

        $application = $this->workflow->createForStudent($request->user(), $request->validated());

        return self::success(
            new InternshipApplicationResource($application),
            'Application created successfully.',
            201,
        );
    }

    public function show(InternshipApplication $application): JsonResponse
    {
        $this->authorize('view', $application);

        $application->load(['student', 'adviser', 'partnerReviewer', 'statusLogs.actor']);

        return self::success(
            new InternshipApplicationResource($application),
            'Application retrieved successfully.',
        );
    }

    public function update(UpdateApplicationRequest $request, InternshipApplication $application): JsonResponse
    {
        $this->authorize('update', $application);

        $data = $request->validated();

        $version = $data['expected_version'] ?? null;

        try {
            $application->fill([
                'host_company_name' => $data['host_company_name'],
                'target_role' => $data['target_role'],
                'internship_hours_required' => $data['internship_hours_required'],
                'target_start_date' => $data['target_start_date'],
                'requirements_checklist' => $data['requirements_checklist'],
            ]);

            if ($version !== null) {
                $updatedRows = InternshipApplication::where('id', $application->id)
                    ->where('version', $version)
                    ->update(array_merge(
                        $application->getAttributes(),
                        ['version' => $version + 1],
                    ));

                if ($updatedRows === 0) {
                    $latest = InternshipApplication::find($application->id);
                    throw new InvalidStateTransitionException(
                        'Conflict detected: this application was modified by another session. Reload the latest version and retry.',
                        409,
                        ['version' => ['version_conflict'], 'latest_version' => $latest?->version ?? 0],
                    );
                }

                $application->refresh();
            } else {
                $application->save();
            }

            $application->load(['student', 'adviser', 'partnerReviewer', 'statusLogs.actor']);

            return self::success(
                new InternshipApplicationResource($application),
                'Application updated successfully.',
            );
        } catch (InvalidStateTransitionException $e) {
            $errors = $e->getErrors();
            $status = $e->getCode() ?: 422;
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                self::error($e->getMessage(), $status, $errors)
            );
        }
    }

    public function transition(TransitionApplicationRequest $request, InternshipApplication $application): JsonResponse
    {
        $this->authorize('transition', $application);

        try {
            $application = $this->workflow->transition($application, $request->user(), $request->validated());

            $application->load(['student', 'adviser', 'partnerReviewer']);

            return self::success(
                new InternshipApplicationResource($application),
                'Application status updated successfully.',
            );
        } catch (InvalidStateTransitionException $e) {
            $errors = $e->getErrors();
            $status = $e->getCode() ?: 422;

            // Only handle version conflict (409) here; let global handler format other 422s
            if ($status === 409 && isset($errors['latest_version'])) {
                $data = ['latest_version' => $errors['latest_version']];
                unset($errors['latest_version']);
                throw new \Illuminate\Http\Exceptions\HttpResponseException(
                    response()->json([
                        'success' => false,
                        'message' => $e->getMessage(),
                        'data' => $data,
                        'errors' => $errors,
                    ], $status)
                );
            }

            throw $e;
        }
    }
}