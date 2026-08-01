<?php

namespace App\Console\Commands;

use App\Services\DigestService;
use Illuminate\Console\Command;

class DigestMorningCommand extends Command
{
    protected $signature = 'digest:morning';

    protected $description = 'Утренний дайджест: события и задачи на сегодня';

    public function handle(DigestService $digests): int
    {
        $sent = $digests->sendMorning();
        $this->info("Отправлено пользователям: {$sent}");

        return self::SUCCESS;
    }
}
