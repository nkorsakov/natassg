<?php

namespace Database\Seeders;

use App\Models\DisbursementMethod;
use App\Models\EventType;
use App\Models\ExpenseArticle;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use Illuminate\Database\Seeder;

class DictionarySeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['slug' => 'draft', 'label' => 'Черновик', 'color' => '#9A9BA3', 'sort' => 10, 'is_system' => true],
            ['slug' => 'new', 'label' => 'Новое', 'color' => '#37A878', 'sort' => 20, 'is_system' => true],
            ['slug' => 'in_progress', 'label' => 'В работе', 'color' => '#6957EE', 'sort' => 30, 'is_system' => true],
            ['slug' => 'waiting_money', 'label' => 'Ждёт денег', 'color' => '#FFAD4D', 'sort' => 40, 'is_system' => true],
            ['slug' => 'waiting', 'label' => 'Ждёт кого-то', 'color' => '#5B8DEF', 'sort' => 50, 'is_system' => true],
            ['slug' => 'done', 'label' => 'Готово', 'color' => '#626571', 'sort' => 60, 'is_system' => true],
            ['slug' => 'cancelled', 'label' => 'Отменено', 'color' => '#E96667', 'sort' => 70, 'is_system' => true],
        ];
        foreach ($statuses as $row) {
            TaskStatus::updateOrCreate(['slug' => $row['slug']], $row);
        }
        if (! TaskStatus::query()->where('is_default', true)->exists()) {
            $fallback = TaskStatus::query()->where('slug', 'new')->first()
                ?? TaskStatus::query()->orderBy('sort')->orderBy('id')->first();
            if ($fallback) {
                $fallback->update(['is_default' => true]);
            }
        }

        $priorities = [
            ['slug' => 'normal', 'label' => 'Обычный', 'color' => '#9A9BA3', 'sort' => 10, 'is_system' => true],
            ['slug' => 'high', 'label' => 'Высокий', 'color' => '#FFAD4D', 'sort' => 20, 'is_system' => true],
            ['slug' => 'urgent', 'label' => 'Срочный', 'color' => '#E96667', 'sort' => 30, 'is_system' => true],
        ];
        foreach ($priorities as $row) {
            TaskPriority::updateOrCreate(['slug' => $row['slug']], $row);
        }

        $types = [
            ['slug' => 'purchase', 'label' => 'Покупка', 'color' => '#6957EE', 'icon' => 'mdi-shopping-outline', 'sort' => 10, 'is_system' => true],
            ['slug' => 'search', 'label' => 'Поиск', 'color' => '#0D9488', 'icon' => 'mdi-magnify', 'sort' => 20, 'is_system' => true],
            ['slug' => 'organize', 'label' => 'Организация', 'color' => '#FFAD4D', 'icon' => 'mdi-calendar-check', 'sort' => 30, 'is_system' => true],
            ['slug' => 'call', 'label' => 'Звонок', 'color' => '#5B8DEF', 'icon' => 'mdi-phone-outline', 'sort' => 40, 'is_system' => true],
        ];
        foreach ($types as $row) {
            TaskType::updateOrCreate(['slug' => $row['slug']], $row);
        }

        $eventTypes = [
            ['slug' => 'meeting', 'label' => 'Встреча', 'color' => '#6957EE', 'sort' => 10, 'is_system' => true],
            ['slug' => 'trip', 'label' => 'Поездка', 'color' => '#0D9488', 'sort' => 20, 'is_system' => true],
            ['slug' => 'personal', 'label' => 'Личное', 'color' => '#FFAD4D', 'sort' => 30, 'is_system' => true],
            ['slug' => 'other', 'label' => 'Другое', 'color' => '#9A9BA3', 'sort' => 40, 'is_system' => true],
        ];
        foreach ($eventTypes as $row) {
            EventType::updateOrCreate(['slug' => $row['slug']], $row);
        }

        $articles = [
            ['slug' => 'transport', 'label' => 'Транспорт', 'color' => '#5B8DEF', 'sort' => 10, 'is_system' => true],
            ['slug' => 'food', 'label' => 'Еда', 'color' => '#FFAD4D', 'sort' => 20, 'is_system' => true],
            ['slug' => 'supplies', 'label' => 'Материалы', 'color' => '#6957EE', 'sort' => 30, 'is_system' => true],
            ['slug' => 'services', 'label' => 'Услуги', 'color' => '#0D9488', 'sort' => 40, 'is_system' => true],
            ['slug' => 'other', 'label' => 'Прочее', 'color' => '#9A9BA3', 'sort' => 50, 'is_system' => true],
        ];
        foreach ($articles as $row) {
            ExpenseArticle::updateOrCreate(['slug' => $row['slug']], $row);
        }

        $methods = [
            ['slug' => 'transfer', 'label' => 'Перевод', 'color' => '#6957EE', 'sort' => 10, 'is_system' => true],
            ['slug' => 'cash_office', 'label' => 'Наличка в офисе', 'color' => '#0D9488', 'sort' => 20, 'is_system' => true],
        ];
        foreach ($methods as $row) {
            DisbursementMethod::updateOrCreate(['slug' => $row['slug']], $row);
        }
    }
}
