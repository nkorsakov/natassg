<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\DisbursementMethod;
use App\Models\EventType;
use App\Models\ExpenseArticle;
use App\Models\Supplier;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use App\Models\User;
use App\Support\SkyDeskPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    private const DICT_MAP = [
        'statuses' => TaskStatus::class,
        'priorities' => TaskPriority::class,
        'taskTypes' => TaskType::class,
        'eventTypes' => EventType::class,
        'expenseArticles' => ExpenseArticle::class,
        'disbursementMethods' => DisbursementMethod::class,
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Settings/Index', [
            'users' => $user->is_admin
                ? User::query()
                    ->orderByDesc('is_admin')
                    ->orderBy('name')
                    ->get()
                    ->map(fn (User $u) => SkyDeskPresenter::user($u))
                    ->values()
                : [],
        ]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'initials' => ['nullable', 'string', 'max:8'],
            'role' => ['nullable', 'string', 'max:255'],
        ]);

        $request->user()->update([
            'name' => $data['name'],
            'initials' => $data['initials'] ?? null,
            'role_title' => $data['role'] ?? null,
        ]);

        return back()->with('status', 'Профиль сохранён.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'initials' => ['nullable', 'string', 'max:8'],
            'role' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', Password::defaults()],
            'is_admin' => ['sometimes', 'boolean'],
            'telegram_id' => ['nullable', 'integer', 'unique:users,telegram_id'],
        ]);

        $email = $this->resolveEmail($data['login']);

        if (User::where('email', $email)->exists()) {
            return back()->withErrors(['login' => 'Такой логин уже занят.']);
        }

        User::create([
            'email' => $email,
            'name' => $data['name'],
            'initials' => $data['initials'] ?? null,
            'role_title' => $data['role'] ?? 'Личный помощник',
            'password' => $data['password'],
            'is_admin' => (bool) ($data['is_admin'] ?? false),
            'telegram_id' => $data['telegram_id'] ?? null,
            'email_verified_at' => now(),
        ]);

        return back()->with('status', 'Пользователь создан.');
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'initials' => ['nullable', 'string', 'max:8'],
            'role' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', Password::defaults()],
            'is_admin' => ['sometimes', 'boolean'],
            'telegram_id' => [
                'nullable',
                'integer',
                Rule::unique('users', 'telegram_id')->ignore($user->id),
            ],
        ]);

        if (isset($data['name'])) {
            $user->name = $data['name'];
        }
        if (array_key_exists('initials', $data)) {
            $user->initials = $data['initials'];
        }
        if (array_key_exists('role', $data)) {
            $user->role_title = $data['role'];
        }
        if (array_key_exists('telegram_id', $data)) {
            $user->telegram_id = $data['telegram_id'];
        }
        if (array_key_exists('is_admin', $data)) {
            // Нельзя снять админку с самого себя, если это единственный админ
            if ($user->id === $request->user()->id && ! $data['is_admin']) {
                $otherAdmins = User::where('is_admin', true)->where('id', '!=', $user->id)->exists();
                if (! $otherAdmins) {
                    return back()->withErrors(['is_admin' => 'Нужен хотя бы один администратор.']);
                }
            }
            $user->is_admin = (bool) $data['is_admin'];
        }
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return back()->with('status', 'Пользователь обновлён.');
    }

    public function storeDict(Request $request, string $key): RedirectResponse
    {
        $model = $this->modelFor($key);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:32'],
            'icon' => ['nullable', 'string', 'max:64'],
        ]);

        $slug = Str::slug($data['label'], '_');
        if ($slug === '') {
            $slug = $key.'_'.Str::random(6);
        }
        if ($model::where('slug', $slug)->exists()) {
            $slug .= '_'.Str::lower(Str::random(4));
        }

        $sort = ((int) $model::max('sort')) + 10;

        $payload = [
            'slug' => $slug,
            'label' => $data['label'],
            'color' => $data['color'],
            'sort' => $sort,
            'is_system' => false,
        ];
        if ($key === 'taskTypes') {
            $payload['icon'] = $data['icon'] ?? 'mdi-checkbox-blank-circle-outline';
        }

        $model::create($payload);

        return back();
    }

    public function updateDict(Request $request, string $key, string $slug): RedirectResponse
    {
        $model = $this->modelFor($key);
        $item = $model::where('slug', $slug)->firstOrFail();

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'color' => ['sometimes', 'string', 'max:32'],
            'icon' => ['nullable', 'string', 'max:64'],
            'is_default' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['label'])) {
            $item->label = $data['label'];
        }
        if (isset($data['color'])) {
            $item->color = $data['color'];
        }
        if ($key === 'taskTypes' && array_key_exists('icon', $data)) {
            $item->icon = $data['icon'];
        }

        if (array_key_exists('is_default', $data) && $this->dictSupportsDefault($key)) {
            if ($data['is_default']) {
                $model::query()->where('is_default', true)->update(['is_default' => false]);
                $item->is_default = true;
            } elseif ($item->is_default) {
                return back()->withErrors(['dict' => 'Сначала назначьте другой статус по умолчанию.']);
            }
        }

        $item->save();

        return back();
    }

    public function destroyDict(Request $request, string $key, string $slug): RedirectResponse
    {
        $model = $this->modelFor($key);
        $item = $model::where('slug', $slug)->firstOrFail();

        if ($model::count() <= 1) {
            return back()->withErrors(['dict' => 'Нужен хотя бы один элемент в справочнике.']);
        }

        $usage = $this->dictUsageCount($key, $item);
        if ($usage > 0) {
            return back()->withErrors([
                'dict' => "«{$item->label}» используется в данных ({$usage}). Сначала перенесите записи на другой пункт.",
            ]);
        }

        $wasDefault = $this->dictSupportsDefault($key) && (bool) $item->is_default;

        try {
            $item->delete();
        } catch (\Throwable $e) {
            return back()->withErrors(['dict' => 'Не удалось удалить: элемент ещё связан с данными.']);
        }

        if ($wasDefault) {
            $fallback = $model::query()->orderBy('sort')->orderBy('id')->first();
            if ($fallback) {
                $fallback->is_default = true;
                $fallback->save();
            }
        }

        return back();
    }

    protected function dictSupportsDefault(string $key): bool
    {
        return in_array($key, ['statuses'], true);
    }

    protected function dictUsageCount(string $key, mixed $item): int
    {
        $id = (int) $item->id;

        return match ($key) {
            'statuses' => \App\Models\Task::query()->where('status_id', $id)->count(),
            'priorities' => \App\Models\Task::query()->where('priority_id', $id)->count(),
            'taskTypes' => \App\Models\Task::query()->where('type_id', $id)->count(),
            'eventTypes' => \App\Models\CalendarEvent::query()->where('event_type_id', $id)->count(),
            'expenseArticles' => \App\Models\Expense::query()->where('article_id', $id)->count(),
            'disbursementMethods' => \App\Models\Advance::query()->where('disbursement_method_id', $id)->count(),
            default => 0,
        };
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where(fn ($q) => $q->where('user_id', $user->id)),
                Rule::unique('suppliers', 'contact_id')->where(fn ($q) => $q->where('user_id', $user->id)),
            ],
            'note' => ['nullable', 'string'],
        ]);

        $contact = null;
        if (! empty($data['contact_id'])) {
            $contact = Contact::query()
                ->where('user_id', $user->id)
                ->whereKey($data['contact_id'])
                ->firstOrFail();
        }

        $supplier = Supplier::create([
            'user_id' => $user->id,
            'name' => trim($data['name']),
            'contact_id' => $contact?->id,
            'note' => $data['note'] ?? null,
        ]);

        if ($contact) {
            $contact->is_supplier = true;
            $contact->save();
        }

        return back()->with('created_supplier_id', $supplier->id);
    }

    public function updateSupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($supplier->user_id === $request->user()->id, 403);

        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'contact_id' => [
                'nullable',
                'integer',
                Rule::exists('contacts', 'id')->where(fn ($q) => $q->where('user_id', $user->id)),
                Rule::unique('suppliers', 'contact_id')
                    ->where(fn ($q) => $q->where('user_id', $user->id))
                    ->ignore($supplier->id),
            ],
            'note' => ['nullable', 'string'],
        ]);

        $oldContactId = $supplier->contact_id;

        if (isset($data['name'])) {
            $supplier->name = trim($data['name']);
        }
        if (array_key_exists('note', $data)) {
            $supplier->note = $data['note'];
        }
        if (array_key_exists('contact_id', $data)) {
            $supplier->contact_id = $data['contact_id'] ?: null;
        }

        $supplier->save();

        $this->syncContactSupplierFlags($user->id, array_filter([$oldContactId, $supplier->contact_id]));

        return back();
    }

    public function destroySupplier(Request $request, Supplier $supplier): RedirectResponse
    {
        abort_unless($supplier->user_id === $request->user()->id, 403);

        if ($supplier->expenses()->exists()) {
            return back()->withErrors(['supplier' => 'Нельзя удалить поставщика с тратами.']);
        }

        $contactId = $supplier->contact_id;
        $supplier->delete();

        if ($contactId) {
            $this->syncContactSupplierFlags($request->user()->id, [$contactId]);
        }

        return back();
    }

    /** @param  array<int|null>  $contactIds */
    protected function syncContactSupplierFlags(int $userId, array $contactIds): void
    {
        foreach (array_unique(array_filter($contactIds)) as $contactId) {
            $contact = Contact::query()
                ->where('user_id', $userId)
                ->whereKey($contactId)
                ->first();
            if (! $contact) {
                continue;
            }
            $linked = Supplier::query()
                ->where('user_id', $userId)
                ->where('contact_id', $contactId)
                ->exists();
            $contact->is_supplier = $linked;
            $contact->save();
        }
    }

    protected function ensureAdmin(Request $request): void
    {
        abort_unless((bool) $request->user()?->is_admin, 403);
    }

    protected function resolveEmail(string $login): string
    {
        $login = trim($login);

        if (str_contains($login, '@')) {
            return mb_strtolower($login);
        }

        return mb_strtolower($login).'@skydesk.local';
    }

    /** @return class-string */
    protected function modelFor(string $key): string
    {
        abort_unless(isset(self::DICT_MAP[$key]), 404);

        return self::DICT_MAP[$key];
    }
}
