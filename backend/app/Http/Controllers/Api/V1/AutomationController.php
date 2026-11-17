<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Automation\RequeueAutomationJobRequest;
use App\Http\Requests\Automation\RunAutomationSweepRequest;
use App\Http\Resources\AutomationJobResource;
use App\Http\Resources\AutomationWeeklySummaryResource;
use App\Models\AutomationJob;
use App\Services\Automation\AutomationDispatcherService;
use App\Services\Automation\AutomationSweepService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomationController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly AutomationSweepService $sweeps,
        private readonly AutomationDispatcherService $dispatcher,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AutomationJob::class);

        $jobs = AutomationJob::query()
            ->visibleTo($request->user())
            ->with('application')
            ->withStatus($request->query('status'))
            ->when(
                $request->filled('application_id'),
                static fn ($query) => $query->where('internship_application_id', (int) $request->query('application_id')),
            )
            ->latest('id')
            ->limit(200)
            ->get();

        return self::success(
            AutomationJobResource::collection($jobs),
            'Automation jobs retrieved successfully.',
        );
    }

    /**
     * Coordinator-only: run a controlled sweep and immediately process the
     * jobs it enqueued.
     */
    public function runSweep(RunAutomationSweepRequest $request): JsonResponse
    {
        $this->authorize('manage', AutomationJob::class);

        $faultMode = $request->faultMode();

        if ($faultMode !== null && ! $this->dispatcher->faultsAllowed()) {
            return self::error(
                'Controlled fault injection is disabled for this environment.',
                422,
                ['fault_mode' => ['Fault injection is disabled for this environment.']],
            );
        }

        $summary = $this->sweeps->run((string) $request->validated('type'), $faultMode);

        return self::success(
            $summary,
            sprintf('The [%s] automation sweep completed.', (string) $request->validated('type')),
        );
    }

    /**
     * Coordinator-only: manual recovery for jobs parked in manual_review_required.
     */
    public function requeue(RequeueAutomationJobRequest $request, AutomationJob $job): JsonResponse
    {
        $this->authorize('manage', AutomationJob::class);

        if ($job->status !== AutomationJob::STATUS_MANUAL_REVIEW_REQUIRED) {
            return self::error(
                'Only jobs that require manual review can be requeued.',
                422,
                ['job' => ['This automation job is not waiting for manual review.']],
            );
        }

        $data = $request->validated();

        $requeued = $this->dispatcher->requeue(
            $job,
            $data['recipient_identifier'] ?? null,
            $data['remarks'] ?? null,
        );

        $dispatch = $this->dispatcher->dispatchPending(
            (int) config('automation.sweeps.dispatch_limit', 50),
            null,
        );

        $requeued->refresh()->load('application');

        return self::success(
            [
                'job' => AutomationJobResource::make($requeued)->resolve($request),
                'dispatch' => $dispatch->toArray(),
            ],
            'The automation job was requeued for recovery.',
        );
    }

    public function weeklySummary(): JsonResponse
    {
        $this->authorize('viewAny', AutomationJob::class);

        $summary = $this->sweeps->latestWeeklySummary();

        return self::success(
            $summary === null ? null : AutomationWeeklySummaryResource::make($summary),
            $summary === null
                ? 'No weekly placement summary has been generated yet.'
                : 'Weekly placement summary retrieved successfully.',
        );
    }
}