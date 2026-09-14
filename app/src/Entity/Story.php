<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'stories')]
class Story
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 200)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $prompt;

//    #[ORM\Column(length: 20)]
//    private string $status = 'pending';   // день 8: переедет в enum — оставляю тебе заметку

    #[ORM\Column(enumType: StoryStatus::class)]
    private StoryStatus $status = StoryStatus::Pending;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;
    public function __construct(string $title, string $prompt)
    {
        $this->title = $title;
        $this->prompt = $prompt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getPrompt(): string { return $this->prompt; }
   // public function getStatus(): string { return $this->status; }
    public function getStatus(): StoryStatus
    {
        return $this->status;
    }

    public function transitionTo(StoryStatus $next): void
    {
        $this->status->canTransitionTo($next)?:
            throw new \DomainException(
                sprintf('Illegal transition %s -> %s', $this->status->value, $next->value)
            );


        $this->status = $next;
    }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getContent(): ?string
    {
        return $this->content;
    }

    public function markCompleted(string $content): void
    {
        $this->transitionTo(StoryStatus::Completed);
        $this->content = $content;
    }
}
