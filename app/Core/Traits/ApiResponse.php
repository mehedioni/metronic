<?php

namespace App\Core\Traits;

use Illuminate\Http\JsonResponse;

/**
 * Consistent JSON envelope for API controllers.
 *
 * Success responses: {"success": true, "message": ..., "data": ...}
 * Error responses:   {"success": false, "error": {"code": ..., "message": ..., "details": ...}}
 */
trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        $response = ['success' => true, 'message' => $message];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $status);
    }

    protected function created(mixed $data = null, string $message = 'Created'): JsonResponse
    {
        return $this->success($data, $message, 201);
    }

    protected function error(string $code, string $message, int $status = 400, mixed $details = null): JsonResponse
    {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];

        if ($details !== null) {
            $response['error']['details'] = $details;
        }

        return response()->json($response, $status);
    }

    protected function notFound(string $message = 'Resource not found', string $code = 'NOT_FOUND'): JsonResponse
    {
        return $this->error($code, $message, 404);
    }

    protected function forbidden(string $message = 'Forbidden', string $code = 'FORBIDDEN'): JsonResponse
    {
        return $this->error($code, $message, 403);
    }

    protected function unauthorized(string $message = 'Unauthorized', string $code = 'UNAUTHORIZED'): JsonResponse
    {
        return $this->error($code, $message, 401);
    }

    protected function validationError(mixed $errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->error('VALIDATION_ERROR', $message, 422, $errors);
    }
}
