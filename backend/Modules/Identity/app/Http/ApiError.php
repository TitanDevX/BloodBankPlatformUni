<?php

namespace Modules\Identity\Http;

use Illuminate\Http\JsonResponse;

final class ApiError
{
    /** @param array<int, array<string, mixed>> $extraDetails */
    public static function make(int $code, string $status, string $reason, string $message, array $extraDetails = []): JsonResponse
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'status' => $status,
                'details' => array_merge([[
                    '@type' => 'type.googleapis.com/google.rpc.ErrorInfo',
                    'reason' => $reason,
                    'domain' => parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost',
                ]], $extraDetails),
            ],
        ], $code);
    }
}
