<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success($data = null, string $message = '', int $code = 200): JsonResponse
    {
        $response = ['success' => true];
        if ($message) {
            $response['message'] = $message;
        }
        if (! is_null($data)) {
            $response = array_merge($response, is_array($data) ? $data : ['data' => $data]);
        }

        return response()->json($response, $code);
    }

    protected function error(string $message, int $code = 422, array $errors = []): JsonResponse
    {
        $response = ['success' => false, 'message' => $message];
        if ($errors) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $code);
    }
}
