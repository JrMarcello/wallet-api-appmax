<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

trait ApiResponse
{
    protected function success(
        mixed $data = null,
        string $message = 'Success',
        int $code = Response::HTTP_OK
    ): JsonResponse {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    protected function error(
        string $message,
        int $code = Response::HTTP_BAD_REQUEST,
        array $errors = []
    ): JsonResponse {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
            'data' => null,
        ], $code);
    }
}
