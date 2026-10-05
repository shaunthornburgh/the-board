<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['slug', 'label'])]
class DevelopmentType extends Model
{
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}
