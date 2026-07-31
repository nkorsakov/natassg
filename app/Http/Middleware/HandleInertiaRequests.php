<?php

namespace App\Http\Middleware;

use App\Support\SkyDeskPresenter;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => SkyDeskPresenter::user($user),
            ],
            'skydesk' => fn () => $user ? SkyDeskPresenter::workspace($user) : null,
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'created_task_id' => fn () => $request->session()->get('created_task_id'),
                'created_event_id' => fn () => $request->session()->get('created_event_id'),
                'created_advance_id' => fn () => $request->session()->get('created_advance_id'),
                'created_contact_id' => fn () => $request->session()->get('created_contact_id'),
            ],
        ];
    }
}
