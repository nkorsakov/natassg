<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaskType extends Model
{
    protected $fillable = ['slug', 'label', 'color', 'icon', 'sort', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'type_id');
    }
}
