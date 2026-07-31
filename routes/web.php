<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::redirect('/', '/login');

Route::get('/login', function () {
    return Inertia::render('Auth/Login');
})->name('login');

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard/Index', [
        'stats' => [
            ['value' => '8', 'label' => 'активных поручений', 'icon' => 'mdi-check-circle-outline', 'bg' => '#eceaff', 'color' => 'primary'],
            ['value' => '3', 'label' => 'ожидают финансирования', 'icon' => 'mdi-currency-rub', 'bg' => '#fff1dd', 'color' => 'warning'],
            ['value' => '2', 'label' => 'события на сегодня', 'icon' => 'mdi-calendar-month-outline', 'bg' => '#e2f7ee', 'color' => 'success'],
            ['value' => '5', 'label' => 'чеков нужно внести', 'icon' => 'mdi-receipt-text-outline', 'bg' => '#ffe9e9', 'color' => 'error'],
        ],
        'priorityTasks' => [
            ['title' => 'Купить цветы к ужину', 'meta' => 'Сегодня, до 16:00', 'pills' => [['label' => 'Срочно', 'kind' => 'urgent']]],
            ['title' => 'Забронировать ресторан на субботу', 'meta' => 'Сегодня', 'pills' => [['label' => 'Нужны деньги', 'kind' => 'money']]],
            ['title' => 'Забрать платье из химчистки', 'meta' => 'Сегодня, до 18:00', 'pills' => [['label' => 'В работе', 'kind' => 'wait']]],
            ['title' => 'Подтвердить доставку мебели', 'meta' => 'Завтра, 11:00', 'pills' => []],
        ],
        'agenda' => [
            ['time' => '11:30', 'title' => 'Встреча с дизайнером', 'desc' => 'Шоурум «Дом» · 1 поручение', 'dot' => '#6957EE'],
            ['time' => '17:00', 'title' => 'Ужин с гостями', 'desc' => 'Дом · 3 поручения', 'dot' => '#FFAD4D'],
        ],
        'financePreview' => [
            'label' => 'Ожидает одобрения',
            'count' => '1 заявка',
            'amount' => '45 000 ₽',
            'hint' => 'Подарок к юбилею · сегодня',
        ],
    ]);
})->name('dashboard');

Route::get('/tasks', function () {
    return Inertia::render('Tasks/Index', [
        'filters' => [
            ['value' => 'all', 'label' => 'Все · 8'],
            ['value' => 'today', 'label' => 'Сегодня · 4'],
            ['value' => 'in_progress', 'label' => 'В работе · 3'],
            ['value' => 'money', 'label' => 'Ожидают денег · 2'],
            ['value' => 'done', 'label' => 'Выполненные'],
        ],
        'tasks' => [
            ['title' => 'Купить цветы к ужину', 'meta' => 'Покупка · По поручению Елены', 'date' => 'Сегодня, 16:00', 'assignee' => 'АМ', 'pills' => [['label' => 'Срочно', 'kind' => 'urgent']]],
            ['title' => 'Забронировать ресторан на субботу', 'meta' => 'Организация · Нужен аванс', 'date' => 'Сегодня', 'assignee' => 'АМ', 'pills' => [['label' => 'Деньги', 'kind' => 'money']]],
            ['title' => 'Забрать платье из химчистки', 'meta' => 'Покупка · Тверская, 18', 'date' => 'Сегодня, 18:00', 'assignee' => 'АМ', 'pills' => [['label' => 'В работе', 'kind' => 'wait']]],
            ['title' => 'Подтвердить доставку мебели', 'meta' => 'Звонок · «Artefacto»', 'date' => 'Завтра, 11:00', 'assignee' => 'АМ', 'pills' => [['label' => 'В работе', 'kind' => 'wait']]],
            ['title' => 'Найти мастера по шторам', 'meta' => 'Поиск · 3 варианта', 'date' => '2 августа', 'assignee' => 'АМ', 'pills' => [['label' => 'Новое', 'kind' => 'green']]],
        ],
        'weekProgress' => ['done' => 17, 'total' => 25, 'percent' => 68],
        'insights' => [
            ['title' => 'Небольшая подсказка', 'text' => 'У двух поручений сегодня нет назначенного времени. Добавьте его, чтобы ничего не упустить.'],
            ['title' => 'Внимание к авансам', 'text' => 'По заявке «Поездка в Москву» пока не приложены чеки.'],
        ],
    ]);
})->name('tasks.index');

Route::get('/calendar', function () {
    return Inertia::render('Calendar/Index', [
        'initialDate' => '2026-07-31',
        'events' => [
            [
                'id' => '1',
                'title' => 'Встреча с дизайнером',
                'start' => '2026-07-31T11:30:00',
                'end' => '2026-07-31T13:00:00',
                'backgroundColor' => '#6957EE',
                'borderColor' => '#6957EE',
            ],
            [
                'id' => '2',
                'title' => 'Ужин с гостями',
                'start' => '2026-07-31T17:00:00',
                'end' => '2026-07-31T20:00:00',
                'backgroundColor' => '#FFAD4D',
                'borderColor' => '#FFAD4D',
                'textColor' => '#191827',
            ],
            [
                'id' => '3',
                'title' => 'Ресторан',
                'start' => '2026-08-01T13:00:00',
                'end' => '2026-08-01T15:00:00',
                'backgroundColor' => '#37A878',
                'borderColor' => '#37A878',
            ],
            [
                'id' => '4',
                'title' => 'Поездка в Москву',
                'start' => '2026-08-04',
                'end' => '2026-08-06',
                'backgroundColor' => '#6957EE',
                'borderColor' => '#6957EE',
                'allDay' => true,
            ],
            [
                'id' => '5',
                'title' => 'День рождения',
                'start' => '2026-08-08',
                'backgroundColor' => '#FFAD4D',
                'borderColor' => '#FFAD4D',
                'textColor' => '#191827',
                'allDay' => true,
            ],
            [
                'id' => '6',
                'title' => 'Забрать платье из химчистки',
                'start' => '2026-07-31T18:00:00',
                'end' => '2026-07-31T18:30:00',
                'backgroundColor' => '#E96667',
                'borderColor' => '#E96667',
            ],
        ],
    ]);
})->name('calendar.index');

Route::get('/finance', function () {
    return Inertia::render('Finance/Index', [
        'summary' => [
            ['label' => 'Ожидает одобрения', 'amount' => '45 000 ₽', 'tone' => 'orange'],
            ['label' => 'Деньги выданы', 'amount' => '210 000 ₽', 'tone' => null],
            ['label' => 'Закрыто в июле', 'amount' => '384 500 ₽', 'tone' => 'green'],
        ],
        'advances' => [
            ['title' => 'Подарок к юбилею', 'desc' => 'Поручение: подобрать и купить подарок', 'amount' => '45 000 ₽', 'status' => 'pending', 'statusLabel' => 'На согласовании', 'date' => 'Сегодня, 10:15'],
            ['title' => 'Поездка в Москву', 'desc' => 'Нераспределенные расходы под отчет', 'amount' => '150 000 ₽', 'status' => 'ready', 'statusLabel' => 'Деньги выданы', 'date' => '29 июля'],
            ['title' => 'Ужин с гостями', 'desc' => 'Ресторан, цветы, доставка', 'amount' => '60 000 ₽', 'status' => 'approved', 'statusLabel' => 'Одобрена', 'date' => '30 июля'],
            ['title' => 'Уход за домом', 'desc' => 'Покупки для загородного дома', 'amount' => '32 000 ₽', 'status' => 'closed', 'statusLabel' => 'Закрыта', 'date' => '28 июля'],
        ],
    ]);
})->name('finance.index');
