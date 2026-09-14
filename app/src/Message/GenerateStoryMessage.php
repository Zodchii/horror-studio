<?php

declare(strict_types=1);

namespace App\Message;

final readonly class GenerateStoryMessage
{
    public function __construct(public int $storyId)
    {
    }
}
