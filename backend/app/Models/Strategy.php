<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** A strategy tag such as PD, HMO, Potential HMO or Social housing. A deal can have several. */
#[Fillable(['slug', 'label'])]
class Strategy extends Model
{
    public function deals(): BelongsToMany
    {
        return $this->belongsToMany(Deal::class);
    }

    /**
     * Resolve tags as scouts or the form send them: slug or label, any case ("HMO", "potential hmo", "social-housing").
     *
     * @return array{0: Collection<int, Strategy>, 1: list<string>} the strategies found and the inputs not recognised
     */
    public static function resolve(array $tags): array
    {
        $all = self::all();
        $found = collect();
        $unknown = [];

        foreach ($tags as $tag) {
            $key = Str::slug((string) $tag);
            $match = $all->first(fn (self $s) => $s->slug === $key || Str::slug($s->label) === $key);
            $match ? $found->put($match->id, $match) : $unknown[] = (string) $tag;
        }

        return [$found->values(), $unknown];
    }

    public function toOption(): array
    {
        return ['slug' => $this->slug, 'label' => $this->label];
    }
}
