<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Applications\StoreApplicationRequest;
use App\Http\Requests\Applications\TransitionApplicationRequest;
use App\Http\Requests\Applications\UpdateApplicationRequest;
use App\Http\Resources\InternshipApplicationResource;
use App\Models\InternshipApplication;
use App\Services\ApplicationWorkflowService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly ApplicationWorkflowService $workflow)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', InternshipApplication::class);

        $applications = InternshipApplication::query()
            ->visibleTo($request->user())
            ->with(['student', 'adviser', 'partnerReviewer'])
            ->withStatus($request->query('status'))
            ->latest('id')
            ->get();

        return self::success(
            InternshipApplicationResource::collection($applications),
            'Applications retrieved successfully.',
        );
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

        $application->fill([
            'host_company_name' => $data['host_company_name'],
            'target_role' => $data['target_role'],
            'internship_hours_required' => $data['internship_hours_required'],
            'target_start_date' => $data['target_start_date'],
            'requirements_checklist' => $data['requirements_checklist'],
        ])->save();

        $application->load(['student', 'adviser', 'partnerReviewer', 'statusLogs.actor']);

        return self::success(
            new InternshipApplicationResource($application),
            'Application updated successfully.',
        );
    }

    public function transition(TransitionApplicationRequest $request, InternshipApplication $application): JsonResponse
    {
        $this->authorize('transition', $application);

        $application = $this->workflow->transition($application, $request->user(), $request->validated());

        $application->load(['student', 'adviser', 'partnerReviewer']);

        return self::success(
            new InternshipApplicationResource($application),
            'Application status updated successfully.',
        );
    }
}