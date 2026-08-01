<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Supplier;
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

        if ($contact->is_supplier) {
            Supplier::create([
                'user_id' => $request->user()->id,
                'name' => $contact->name ?: 'Поставщик',
                'contact_id' => $contact->id,
            ]);
        }

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

        $wasSupplier = (bool) $contact->is_supplier;
        $contact->fill($data)->save();

        if (array_key_exists('is_supplier', $data)) {
            $wantSupplier = (bool) $data['is_supplier'];
            $existing = Supplier::query()
                ->where('user_id', $request->user()->id)
                ->where('contact_id', $contact->id)
                ->first();

            if ($wantSupplier && ! $existing) {
                Supplier::create([
                    'user_id' => $request->user()->id,
                    'name' => $contact->name ?: 'Поставщик',
                    'contact_id' => $contact->id,
                ]);
            } elseif (! $wantSupplier && $existing && ! $existing->expenses()->exists()) {
                $existing->delete();
                $contact->is_supplier = false;
                $contact->save();
            } elseif (! $wantSupplier && $existing && $existing->expenses()->exists()) {
                // Флаг оставляем — поставщик используется в тратах
                $contact->is_supplier = true;
                $contact->save();
            } elseif ($wantSupplier && $wasSupplier === false) {
                $contact->is_supplier = true;
                $contact->save();
            }
        }

        return back();
    }

    public function destroy(Request $request, Contact $contact): RedirectResponse
    {
        abort_unless($contact->user_id === $request->user()->id, 403);

        // Отвязываем контакт от поставщиков; самих поставщиков не трогаем
        Supplier::query()
            ->where('user_id', $request->user()->id)
            ->where('contact_id', $contact->id)
            ->update(['contact_id' => null]);

        $contact->delete();

        return back();
    }
}
