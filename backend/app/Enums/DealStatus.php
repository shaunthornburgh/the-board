<?php

namespace App\Enums;

/**
 * Where a deal is in the scout → analyst → review → published pipeline.
 *
 * new → rejected | awaiting_analyst → analyst_done → rejected | awaiting_review → rejected | published
 * A published deal can be unpublished back to awaiting_review. Rejected is final for now.
 */
enum DealStatus: string
{
    case New = 'new';
    case AwaitingAnalyst = 'awaiting_analyst';
    case AnalystDone = 'analyst_done';
    case AwaitingReview = 'awaiting_review';
    case Rejected = 'rejected';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::AwaitingAnalyst => 'Awaiting analyst',
            self::AnalystDone => 'Analyst done',
            self::AwaitingReview => 'Awaiting review',
            self::Rejected => 'Rejected',
            self::Published => 'Published',
        };
    }

    /** @return list<self> */
    public function next(): array
    {
        return match ($this) {
            self::New => [self::Rejected, self::AwaitingAnalyst],
            self::AwaitingAnalyst => [self::AnalystDone],
            self::AnalystDone => [self::Rejected, self::AwaitingReview],
            self::AwaitingReview => [self::Rejected, self::Published],
            self::Published => [self::AwaitingReview],
            self::Rejected => [],
        };
    }

    public function canMoveTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }

    public function canReject(): bool
    {
        return $this->canMoveTo(self::Rejected);
    }

    /** Pipeline order, for tabs and counts. */
    public static function ordered(): array
    {
        return [self::New, self::AwaitingAnalyst, self::AnalystDone, self::AwaitingReview, self::Published, self::Rejected];
    }

    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::ordered());
    }
}
