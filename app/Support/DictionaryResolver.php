<?php

namespace App\Support;

use App\Models\AdvanceStatus;
use App\Models\DisbursementMethod;
use App\Models\EventType;
use App\Models\ExpenseArticle;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use InvalidArgumentException;

class DictionaryResolver
{
    public static function statusId(string|int|null $value): ?int
    {
        return self::resolve(TaskStatus::class, $value);
    }

    /** Slug статуса поручения по умолчанию (для новых записей). */
    public static function defaultTaskStatusSlug(): string
    {
        return self::defaultSlug(TaskStatus::class, 'new');
    }

    public static function defaultTaskStatusId(): int
    {
        return self::resolveRequired(TaskStatus::class, self::defaultTaskStatusSlug());
    }

    public static function priorityId(string|int|null $value): ?int
    {
        return self::resolve(TaskPriority::class, $value);
    }

    public static function taskTypeId(string|int|null $value): ?int
    {
        return self::resolve(TaskType::class, $value);
    }

    public static function eventTypeId(string|int|null $value): ?int
    {
        return self::resolve(EventType::class, $value);
    }

    public static function advanceStatusId(string|int|null $value): ?int
    {
        return self::resolve(AdvanceStatus::class, $value);
    }

    /** Slug статуса аванса по умолчанию (для новых записей). */
    public static function defaultAdvanceStatusSlug(): string
    {
        return self::defaultSlug(AdvanceStatus::class, 'pending');
    }

    public static function defaultAdvanceStatusId(): int
    {
        return self::resolveRequired(AdvanceStatus::class, self::defaultAdvanceStatusSlug());
    }

    public static function expenseArticleId(string|int|null $value): ?int
    {
        return self::resolve(ExpenseArticle::class, $value);
    }

    public static function disbursementMethodId(string|int|null $value): ?int
    {
        return self::resolve(DisbursementMethod::class, $value);
    }

    public static function statusSlugById(?int $id): ?string
    {
        if (! $id) {
            return null;
        }

        return TaskStatus::query()->whereKey($id)->value('slug');
    }

    public static function rublesToMinor(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    public static function minorToRubles(int $minor): float
    {
        return round($minor / 100, 2);
    }

    /**
     * @param  class-string  $model
     */
    protected static function defaultSlug(string $model, string $legacyFallback): string
    {
        $slug = $model::query()->where('is_default', true)->orderBy('sort')->value('slug');
        if ($slug) {
            return (string) $slug;
        }

        $slug = $model::query()->where('slug', $legacyFallback)->value('slug');
        if ($slug) {
            return (string) $slug;
        }

        $slug = $model::query()->orderBy('sort')->orderBy('id')->value('slug');
        if (! $slug) {
            throw new InvalidArgumentException('В справочнике статусов нет ни одного пункта.');
        }

        return (string) $slug;
    }

    /**
     * @param  class-string  $model
     */
    protected static function resolve(string $model, string|int|null $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            $id = (int) $value;
            if ($model::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        $id = $model::query()->where('slug', (string) $value)->value('id');
        if (! $id) {
            throw new InvalidArgumentException("Неизвестное значение словаря: {$value}");
        }

        return (int) $id;
    }

    /**
     * @param  class-string  $model
     */
    protected static function resolveRequired(string $model, string|int $value): int
    {
        $id = self::resolve($model, $value);
        if ($id === null) {
            throw new InvalidArgumentException("Неизвестное значение словаря: {$value}");
        }

        return $id;
    }
}
