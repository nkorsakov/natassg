<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdvanceStatus extends Model
{
    protected $fillable = ['slug', 'label', 'color', 'sort', 'is_system', 'is_default'];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function advances(): HasMany
    {
        return $this->hasMany(Advance::class, 'status_id');
    }
}
