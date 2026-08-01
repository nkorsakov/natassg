<?php

namespace App\Console\Commands;

use App\Services\DigestService;
use Illuminate\Console\Command;

class DigestEveningCommand extends Command
{
    protected $signature = 'digest:evening';

    protected $description = 'Вечерний дайджест: события и задачи на завтра';

    public function handle(DigestService $digests): int
    {
        $sent = $digests->sendEvening();
        $this->info("Отправлено пользователям: {$sent}");

        return self::SUCCESS;
    }
}
