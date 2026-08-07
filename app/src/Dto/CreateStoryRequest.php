<?php

declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateStoryRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 200)]
        public readonly string $title,

        #[Assert\NotBlank]
        #[Assert\Length(min: 10)]
        public readonly string $prompt,
    ) {
    }
}
