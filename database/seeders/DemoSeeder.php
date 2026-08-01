<?php

namespace Database\Seeders;

use App\Models\CalendarEvent;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Supplier;
use App\Models\User;
use App\Services\AdvanceService;
use App\Services\ExpenseService;
use App\Services\ReminderService;
use App\Services\TaskService;
use App\Services\WalletService;
use App\Support\DemoData;
use App\Support\DictionaryResolver;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Полный набор демо-данных со всеми основными вариациями.
 * Помечается is_demo=true → убирается командой `php artisan demo:clear`.
 * НЕ вызывает migrate:fresh.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        DemoData::clear();

        $user = User::query()->where('email', 'nataliya@skydesk.local')->first();
        if (! $user) {
            $this->command?->warn('Пользователь nataliya@skydesk.local не найден. Сначала UserSeeder.');

            return;
        }

        $tasks = app(TaskService::class);
        $advances = app(AdvanceService::class);
        $expenses = app(ExpenseService::class);
        $wallet = app(WalletService::class);
        $reminders = app(ReminderService::class);

        $now = Carbon::now()->startOfHour();

        // --- Контакты и поставщики ---
        $contactLinked = DemoData::mark(Contact::create([
            'user_id' => $user->id,
            'name' => '[DEMO] ООО Цветы',
            'role' => 'Поставщик',
            'phone' => '+7 900 111-22-33',
            'note' => 'Контакт с привязкой к поставщику',
            'is_supplier' => true,
        ]));

        $supplierLinked = DemoData::mark(Supplier::create([
            'user_id' => $user->id,
            'name' => '[DEMO] ООО Цветы',
            'contact_id' => $contactLinked->id,
            'note' => 'Поставщик с контактом',
        ]));

        $supplierSolo = DemoData::mark(Supplier::create([
            'user_id' => $user->id,
            'name' => '[DEMO] Такси без контакта',
            'note' => 'Поставщик без контакта',
        ]));

        DemoData::mark(Contact::create([
            'user_id' => $user->id,
            'name' => '[DEMO] Иван Петров',
            'role' => 'Водитель',
            'phone' => '+7 900 222-33-44',
            'note' => 'Обычный контакт, не поставщик',
            'is_supplier' => false,
        ]));

        // --- Кошелёк: пополнения обоими способами ---
        $topup = $wallet->topUp($user, [
            'amount' => 150000,
            'title' => '[DEMO] Стартовое пополнение',
            'note' => '[DEMO] Перевод на карту',
            'disbursement_method_id' => 'transfer',
        ]);
        DemoData::mark($topup);

        $topupCash = $wallet->topUp($user, [
            'amount' => 10000,
            'title' => '[DEMO] Наличка в офисе',
            'note' => '[DEMO] Получено в кассе',
            'disbursement_method_id' => 'cash_office',
        ]);
        DemoData::mark($topupCash);

        // --- События всех типов ---
        $eventMeeting = $this->event($user, [
            'type' => 'meeting',
            'title' => '[DEMO] Встреча с подрядчиком',
            'start' => $now->copy()->addDay()->setTime(11, 0),
            'end' => $now->copy()->addDay()->setTime(12, 30),
            'place' => 'Офис на Тверской',
            'note' => 'Обсудить смету',
        ]);

        $eventTrip = $this->event($user, [
            'type' => 'trip',
            'title' => '[DEMO] Поездка в аэропорт',
            'start' => $now->copy()->addDays(3)->setTime(6, 0),
            'end' => $now->copy()->addDays(3)->setTime(9, 0),
            'place' => 'Шереметьево',
        ]);

        $eventPersonal = $this->event($user, [
            'type' => 'personal',
            'title' => '[DEMO] Личное — день рождения',
            'start' => $now->copy()->addDays(10)->startOfDay(),
            'end' => $now->copy()->addDays(10)->endOfDay(),
            'all_day' => true,
        ]);

        $eventOther = $this->event($user, [
            'type' => 'other',
            'title' => '[DEMO] Прочее событие без поручений',
            'start' => $now->copy()->subDays(2)->setTime(15, 0),
            'end' => $now->copy()->subDays(2)->setTime(16, 0),
            'note' => 'Прошлое событие',
        ]);

        // --- Поручения: все статусы / приоритеты / типы ---
        $statusMatrix = [
            ['status_id' => 'draft', 'priority_id' => 'normal', 'type_id' => 'purchase', 'title' => '[DEMO] Черновик: купить воду'],
            ['status_id' => 'new', 'priority_id' => 'normal', 'type_id' => 'call', 'title' => '[DEMO] Новое: позвонить флористу'],
            ['status_id' => 'in_progress', 'priority_id' => 'high', 'type_id' => 'organize', 'title' => '[DEMO] В работе: организовать ужин'],
            ['status_id' => 'waiting_money', 'priority_id' => 'urgent', 'type_id' => 'purchase', 'title' => '[DEMO] Ждёт денег: закупка декора'],
            ['status_id' => 'waiting', 'priority_id' => 'normal', 'type_id' => 'search', 'title' => '[DEMO] Ждёт кого-то: ответ от площадки'],
            ['status_id' => 'done', 'priority_id' => 'normal', 'type_id' => 'call', 'title' => '[DEMO] Готово: подтвердить бронь'],
            ['status_id' => 'cancelled', 'priority_id' => 'high', 'type_id' => 'search', 'title' => '[DEMO] Отменено: поиск редкого вина'],
        ];

        $taskByStatus = [];
        foreach ($statusMatrix as $row) {
            $task = $tasks->create($user, [
                ...$row,
                'note' => 'Демо-поручение',
                'deadline' => in_array($row['status_id'], ['done', 'cancelled'], true)
                    ? null
                    : $now->copy()->addDays(5)->setTime(18, 0)->format('Y-m-d\TH:i'),
            ]);
            DemoData::mark($task);
            DemoData::markMany($task->reminders);
            $taskByStatus[$row['status_id']] = $task;
        }

        // Дерево: родитель + 2 подзадачи
        $parent = $tasks->create($user, [
            'status_id' => 'in_progress',
            'priority_id' => 'high',
            'type_id' => 'organize',
            'title' => '[DEMO] Родитель: подготовка мероприятия',
            'note' => 'С подзадачами',
            'deadline' => $now->copy()->addDays(7)->setTime(20, 0)->format('Y-m-d\TH:i'),
            'event_ids' => [$eventMeeting->id],
        ]);
        DemoData::mark($parent);
        DemoData::markMany($parent->reminders);

        $childA = $tasks->create($user, [
            'parent_id' => $parent->id,
            'status_id' => 'new',
            'priority_id' => 'normal',
            'type_id' => 'purchase',
            'title' => '[DEMO] Подзадача: купить цветы',
        ]);
        DemoData::mark($childA);

        $childB = $tasks->create($user, [
            'parent_id' => $parent->id,
            'status_id' => 'waiting_money',
            'priority_id' => 'urgent',
            'type_id' => 'purchase',
            'title' => '[DEMO] Подзадача: заказать кейтеринг',
            'event_ids' => [$eventMeeting->id, $eventTrip->id],
        ]);
        DemoData::mark($childB);

        // Ручное напоминание
        $manualTask = $tasks->create($user, [
            'status_id' => 'new',
            'priority_id' => 'urgent',
            'type_id' => 'call',
            'title' => '[DEMO] С ручным напоминанием',
            'deadline' => $now->copy()->addDays(2)->setTime(12, 0)->format('Y-m-d\TH:i'),
        ]);
        DemoData::mark($manualTask);
        DemoData::markMany($manualTask->reminders);
        $manualReminder = $reminders->createManual(
            $manualTask,
            $now->copy()->addDay()->setTime(9, 0),
            '[DEMO] Не забыть позвонить',
        );
        DemoData::mark($manualReminder);

        // Поручение ↔ событие (поездка)
        $tripTask = $tasks->create($user, [
            'status_id' => 'in_progress',
            'priority_id' => 'high',
            'type_id' => 'organize',
            'title' => '[DEMO] Сопровождение поездки',
            'event_ids' => [$eventTrip->id],
        ]);
        DemoData::mark($tripTask);

        // Личное событие без задач уже есть; свяжем одно поручение с personal
        $giftTask = $tasks->create($user, [
            'status_id' => 'new',
            'priority_id' => 'normal',
            'type_id' => 'purchase',
            'title' => '[DEMO] Купить подарок',
            'event_ids' => [$eventPersonal->id],
        ]);
        DemoData::mark($giftTask);

        // --- Авансы: все статусы и финансовые сценарии ---
        $advPending = $advances->create($user, [
            'title' => '[DEMO] Аванс на согласовании',
            'amount' => 5000,
            'status_id' => 'pending',
            'note' => 'Ждёт решения',
            'task_ids' => [$taskByStatus['waiting_money']->id],
        ]);
        DemoData::mark($advPending);

        $advApproved = $advances->create($user, [
            'title' => '[DEMO] Аванс одобрен, ещё не выдан',
            'amount' => 8000,
            'status_id' => 'approved',
            'disbursement_method_id' => 'transfer',
            'task_ids' => [$childB->id],
        ]);
        DemoData::mark($advApproved);

        // Issued → частичная трата → reporting
        $advReporting = $advances->create($user, [
            'title' => '[DEMO] Аванс на отчёте (частично потрачен)',
            'amount' => 10000,
            'status_id' => 'issued',
            'disbursement_method_id' => 'transfer',
            'task_ids' => [$parent->id],
            'note' => 'Остаток есть',
        ]);
        DemoData::mark($advReporting);
        DemoData::markWalletForAdvance($advReporting);

        $expPartial = $expenses->addExpense($user, [
            'amount' => 3500,
            'article_id' => 'supplies',
            'supplier_id' => $supplierLinked->id,
            'description' => '[DEMO] Частичная трата по авансу',
            'task_id' => $childA->id,
        ], $advReporting);
        DemoData::mark($expPartial);
        DemoData::markWalletForExpense($expPartial);
        DemoData::mark($advReporting->fresh());

        // Issued → полная трата → auto-close
        $advAutoclose = $advances->create($user, [
            'title' => '[DEMO] Аванс закрыт полной тратой',
            'amount' => 2000,
            'status_id' => 'issued',
            'disbursement_method_id' => 'cash_office',
            'task_ids' => [$giftTask->id],
        ]);
        DemoData::mark($advAutoclose);
        DemoData::markWalletForAdvance($advAutoclose);

        $expFull = $expenses->addExpense($user, [
            'amount' => 2000,
            'article_id' => 'other',
            'supplier_id' => $supplierSolo->id,
            'description' => '[DEMO] Полная трата — автозакрытие',
        ], $advAutoclose);
        DemoData::mark($expFull);
        DemoData::markWalletForExpense($expFull);
        DemoData::mark($advAutoclose->fresh());

        // Issued → release
        $advRelease = $advances->create($user, [
            'title' => '[DEMO] Аванс закрыт: остаток → свободно',
            'amount' => 4000,
            'status_id' => 'issued',
            'disbursement_method_id' => 'transfer',
        ]);
        DemoData::mark($advRelease);
        DemoData::markWalletForAdvance($advRelease);

        $expRelease = $expenses->addExpense($user, [
            'amount' => 1500,
            'article_id' => 'transport',
            'supplier_id' => $supplierSolo->id,
            'description' => '[DEMO] Трата перед release',
        ], $advRelease);
        DemoData::mark($expRelease);
        DemoData::markWalletForExpense($expRelease);
        $advances->releaseToFree($advRelease->fresh(['status']));
        DemoData::markWalletForAdvance($advRelease);
        DemoData::mark($advRelease->fresh());

        // Issued → return
        $advReturn = $advances->create($user, [
            'title' => '[DEMO] Аванс закрыт: возврат боссу',
            'amount' => 3000,
            'status_id' => 'issued',
            'disbursement_method_id' => 'cash_office',
        ]);
        DemoData::mark($advReturn);
        DemoData::markWalletForAdvance($advReturn);

        $expReturn = $expenses->addExpense($user, [
            'amount' => 1000,
            'article_id' => 'food',
            'supplier_id' => $supplierLinked->id,
            'description' => '[DEMO] Трата перед возвратом',
        ], $advReturn);
        DemoData::mark($expReturn);
        DemoData::markWalletForExpense($expReturn);
        $advances->returnToBoss($advReturn->fresh(['status']));
        DemoData::markWalletForAdvance($advReturn);
        DemoData::mark($advReturn->fresh());

        // Issued → writeoff
        $advWriteoff = $advances->create($user, [
            'title' => '[DEMO] Аванс закрыт: списание неизвестного',
            'amount' => 2500,
            'status_id' => 'issued',
            'disbursement_method_id' => 'transfer',
        ]);
        DemoData::mark($advWriteoff);
        DemoData::markWalletForAdvance($advWriteoff);

        $expWriteoff = $expenses->addExpense($user, [
            'amount' => 500,
            'article_id' => 'services',
            'supplier_id' => $supplierSolo->id,
            'description' => '[DEMO] Трата перед writeoff',
        ], $advWriteoff);
        DemoData::mark($expWriteoff);
        DemoData::markWalletForExpense($expWriteoff);
        $advances->writeOffUnknown($advWriteoff->fresh(['status']));
        DemoData::markWalletForAdvance($advWriteoff);
        DemoData::mark($advWriteoff->fresh());

        // Чистый issued без трат
        $advIssuedOpen = $advances->create($user, [
            'title' => '[DEMO] Аванс выдан, трат ещё нет',
            'amount' => 6000,
            'status_id' => 'issued',
            'disbursement_method_id' => 'transfer',
            'task_ids' => [$tripTask->id],
            'note' => 'Деньги на руках',
        ]);
        DemoData::mark($advIssuedOpen);
        DemoData::markWalletForAdvance($advIssuedOpen);

        // --- Свободные траты по всем статьям ---
        $freeArticles = ['transport', 'food', 'supplies', 'services', 'other'];
        foreach ($freeArticles as $i => $article) {
            $exp = $expenses->addExpense($user, [
                'amount' => 300 + ($i * 50),
                'article_id' => $article,
                'supplier_id' => $i % 2 === 0 ? $supplierSolo->id : $supplierLinked->id,
                'description' => "[DEMO] Свободная трата: {$article}",
            ]);
            DemoData::mark($exp);
            DemoData::markWalletForExpense($exp);
        }

        // Неиспользуемое событие other уже создано
        unset($eventOther);

        $this->command?->info('Демо-данные загружены для nataliya@skydesk.local (флаг is_demo).');
        $this->command?->info('Убрать: php artisan demo:clear');
    }

    protected function event(User $user, array $data): CalendarEvent
    {
        $event = CalendarEvent::create([
            'user_id' => $user->id,
            'event_type_id' => DictionaryResolver::eventTypeId($data['type'] ?? 'other'),
            'title' => $data['title'],
            'starts_at' => $data['start'],
            'ends_at' => $data['end'] ?? null,
            'all_day' => (bool) ($data['all_day'] ?? false),
            'place' => $data['place'] ?? null,
            'note' => $data['note'] ?? null,
            'is_demo' => true,
        ]);

        return $event;
    }
}
