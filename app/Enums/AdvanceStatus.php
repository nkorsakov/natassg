<?php

namespace App\Enums;

enum AdvanceStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Reporting = 'reporting';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Заявка',
            self::Approved => 'Утвердили',
            self::Reporting => 'На отчёте',
            self::Closed => 'Закрыта',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => '#FFAD4D',
            self::Approved => '#6957EE',
            self::Reporting => '#0D9488',
            self::Closed => '#37A878',
        };
    }

    public function sort(): int
    {
        return match ($this) {
            self::Pending => 10,
            self::Approved => 20,
            self::Reporting => 30,
            self::Closed => 40,
        };
    }

    public function isFunded(): bool
    {
        return match ($this) {
            self::Reporting, self::Closed => true,
            default => false,
        };
    }

    public function allowsExpenses(): bool
    {
        return $this === self::Reporting;
    }

    public function allowsClose(): bool
    {
        return $this === self::Reporting;
    }

    /** @return list<array{id: string, label: string, color: string, sort: int, is_default: bool}> */
    public static function dictionary(): array
    {
        return array_map(
            fn (self $s) => [
                'id' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
                'sort' => $s->sort(),
                'is_default' => $s === self::Pending,
            ],
            self::cases()
        );
    }

    public static function tryFromLegacy(?string $slug): ?self
    {
        $slug = match ($slug) {
            'received', 'issued' => 'reporting',
            default => $slug,
        };

        return self::tryFrom((string) $slug);
    }

    public static function fromSlug(?string $slug): self
    {
        return self::tryFromLegacy($slug) ?? self::Pending;
    }
}
