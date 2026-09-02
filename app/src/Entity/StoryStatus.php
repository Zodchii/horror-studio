<?php

declare(strict_types=1);

namespace App\Entity;

enum StoryStatus: string
{
    case Pending = 'pending';
    case Generating = 'generating';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending    => in_array($next, [self::Generating, self::Cancelled], true),
            self::Generating => in_array($next, [self::Completed, self::Failed], true),
            self::Completed, self::Failed, self::Cancelled => false,
        };
    }
}
