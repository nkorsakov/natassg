<?php

namespace App\Http\Controllers;

use App\Models\AdvanceStatus;
use App\Models\EventType;
use App\Models\TaskPriority;
use App\Models\TaskStatus;
use App\Models\TaskType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
    ];

    public function index(): Response
    {
        return Inertia::render('Settings/Index');
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

    /** @return class-string */
    protected function modelFor(string $key): string
    {
        abort_unless(isset(self::DICT_MAP[$key]), 404);

        return self::DICT_MAP[$key];
    }
}
