<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Request completed successfully.', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }

    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }

    public static function collection(iterable $data, string $message = 'Request completed successfully.'): JsonResponse
    {
        return self::success($data, $message);
    }
}