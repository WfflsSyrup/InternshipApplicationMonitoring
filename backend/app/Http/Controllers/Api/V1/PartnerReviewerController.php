<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class PartnerReviewerController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        abort_unless(request()->user()?->isCoordinator() === true, 403, 'This action is unauthorized.');

        $reviewers = User::query()
            ->where('role', User::ROLE_PARTNER_REVIEWER)
            ->orderBy('name')
            ->get();

        return self::success(
            UserResource::collection($reviewers),
            'Partner reviewers retrieved successfully.',
        );
    }
}