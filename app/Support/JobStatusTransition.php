<?php

namespace App\Support;

class JobStatusTransition
{
    public static function allowed(): array
    {
        return [
            'open' => ['assigned'],
            'assigned' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [],
            'cancelled' => [],
        ];
    }

    public static function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::allowed()[$from] ?? []);
    }
}
