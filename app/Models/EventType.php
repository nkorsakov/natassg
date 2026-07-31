<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventType extends Model
{
    protected $fillable = ['slug', 'label', 'color', 'sort', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(CalendarEvent::class, 'event_type_id');
    }
}
