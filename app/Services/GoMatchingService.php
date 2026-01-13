<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GoMatchingService
{
    public function matchTechnicians(array $payload): array
    {
        $response = Http::timeout(3)
            ->post(config('go.matching_service_url') . '/match', $payload);

        if (! $response->successful()) {
            throw new \RuntimeException('Go matching service failed');
        }

        return $response->json();
    }
}
