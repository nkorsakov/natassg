<?php

return [

    'timezone' => env('NOTIFICATIONS_TIMEZONE', 'Europe/Moscow'),

    'deadline_offset_hours' => (int) env('NOTIFICATIONS_DEADLINE_OFFSET_HOURS', 2),

    'digest' => [
        'morning' => env('NOTIFICATIONS_DIGEST_MORNING', '10:00'),
        'evening' => env('NOTIFICATIONS_DIGEST_EVENING', '22:00'),
    ],

];
