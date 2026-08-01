<?php

namespace App\Console\Commands;

use Database\Seeders\DemoSeeder;
use Illuminate\Console\Command;

class DemoSeedCommand extends Command
{
    protected $signature = 'demo:seed';

    protected $description = 'Загрузить полный набор демо-данных (is_demo). НЕ делает migrate:fresh.';

    public function handle(): int
    {
        $this->warn('Демо-сидер: сначала очистит старые is_demo, затем создаст новые.');
        $this->warn('migrate:fresh / db:wipe НЕ используются.');

        $this->callSilent('db:seed', ['--class' => DemoSeeder::class]);

        $this->info('Готово. Убрать демо: php artisan demo:clear');

        return self::SUCCESS;
    }
}
