<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    use ApiResponse;

    public function show(): JsonResponse
    {
        $database = 'connected';

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $database = 'unavailable';
        }

        if ($database === 'unavailable') {
            return self::error('Service temporarily unavailable.', 503);
        }

        return self::success([
            'status' => 'ok',
            'service' => config('app.name'),
            'environment' => config('app.env'),
            'database' => $database,
            'timestamp' => now()->toIso8601String(),
        ], 'Service is healthy.');
    }
}