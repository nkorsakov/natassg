<?php

namespace App\Http\Controllers;

use App\Models\AdvanceStatus;
use App\Models\Contact;
use App\Models\DisbursementMethod;
use App\Models\EventType;
use App\Models\ExpenseArticle;
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
        'advanceStatuses' => AdvanceStatus::class,
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

        $item->save();

        return back();
    }

    public function destroyDict(Request $request, string $key, string $slug): RedirectResponse
    {
        $model = $this->modelFor($key);
        $item = $model::where('slug', $slug)->firstOrFail();

        if ($item->is_system) {
            return back()->withErrors(['dict' => 'Системный элемент нельзя удалить.']);
        }

        if ($model::count() <= 1) {
            return back()->withErrors(['dict' => 'Нужен хотя бы один элемент.']);
        }

        $item->delete();

        return back();
    }

    public function setSupplier(Request $request, Contact $contact): RedirectResponse
    {
        abort_unless($contact->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'is_supplier' => ['required', 'boolean'],
        ]);

        $contact->is_supplier = (bool) $data['is_supplier'];
        $contact->save();

        return back();
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
