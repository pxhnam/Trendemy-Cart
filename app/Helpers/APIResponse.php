<?php

namespace App\Helpers;

use Illuminate\Http\JsonResponse;

class APIResponse
{
    public static function make(int $status, string $message = '', $data = []): JsonResponse
    {
        if (empty($message)) {
            $message = HttpStatus::message($status);
        }

        $response = [
            'status' => $status,
            'message' => $message,
        ];

        if (!empty($data)) {
            $response['data'] = $data;
        }
        return response()->json($response);
    }
}
