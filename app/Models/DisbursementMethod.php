<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisbursementMethod extends Model
{
    protected $fillable = ['slug', 'label', 'color', 'sort', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function advances(): HasMany
    {
        return $this->hasMany(Advance::class, 'disbursement_method_id');
    }
}
