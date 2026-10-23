<?php

namespace App\Enums;

/** What the Analyst bot says about a candidate. The bot's KILL is stored as reject. */
enum AnalystVerdict: string
{
    case Send = 'send';
    case Mention = 'mention';
    case Reject = 'reject';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Accepts the bot's wording in any case: SEND, Mention, KILL, reject… */
    public static function fromBot(?string $value): ?self
    {
        $value = strtolower(trim((string) $value));

        return self::tryFrom($value === 'kill' ? 'reject' : $value);
    }

    /** Every input fromBot() accepts, for validation messages. */
    public static function accepted(): array
    {
        return [...array_column(self::cases(), 'value'), 'kill'];
    }

    public static function options(): array
    {
        return array_combine(array_column(self::cases(), 'value'), array_map(fn (self $v) => $v->label(), self::cases()));
    }
}
