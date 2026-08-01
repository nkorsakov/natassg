<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Contacts/Index');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string'],
            'is_supplier' => ['sometimes', 'boolean'],
        ]);

        $contact = Contact::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'] ?? '',
            'role' => $data['role'] ?? null,
            'phone' => $data['phone'] ?? null,
            'note' => $data['note'] ?? null,
            'is_supplier' => (bool) ($data['is_supplier'] ?? false),
        ]);

        return back()->with('created_contact_id', $contact->id);
    }

    public function update(Request $request, Contact $contact): RedirectResponse
    {
        abort_unless($contact->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:64'],
            'note' => ['nullable', 'string'],
            'is_supplier' => ['sometimes', 'boolean'],
        ]);

        $contact->fill($data)->save();

        return back();
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        abort_unless($contact->user_id === $request->user()->id, 403);
        $contact->delete();

        return back();
    }
}
