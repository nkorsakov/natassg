<?php

namespace App\Console\Commands;

use App\Support\DemoData;
use Illuminate\Console\Command;

class DemoClearCommand extends Command
{
    protected $signature = 'demo:clear';

    protected $description = 'Удалить только демо-данные (is_demo=true). НЕ делает migrate:fresh.';

    public function handle(): int
    {
        $this->warn('Удаляю только записи с is_demo=true. Словари и пользователи остаются.');

        $stats = DemoData::clear();

        foreach ($stats as $key => $count) {
            $this->line(sprintf('  %-22s %d', $key, $count));
        }

        $this->info('Демо-данные убраны. Балансы кошельков пересчитаны.');

        return self::SUCCESS;
    }
}
