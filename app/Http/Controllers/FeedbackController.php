<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function create(): View
    {
        return view('feedback.create');
    }

    public function store(Request $request): RedirectResponse
    {
        foreach (['vendor_name', 'message'] as $field) {
            $value = $request->input($field);

            if (is_string($value)) {
                $request->merge([$field => trim($value)]);
            }
        }

        $validated = $request->validate([
            'topic' => ['required', 'in:navora,vendor'],
            'vendor_name' => ['required_if:topic,vendor', 'nullable', 'string', 'max:255'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'message' => ['required', 'string', 'min:10', 'max:3000'],
        ], [
            'topic.required' => 'Pilih topik masukan terlebih dahulu.',
            'topic.in' => 'Pilih topik masukan Navora atau vendor.',
            'vendor_name.required_if' => 'Masukkan nama vendor yang ingin kamu beri masukan.',
            'rating.between' => 'Pilih nilai antara 1 sampai 5.',
            'message.required' => 'Tulis masukanmu terlebih dahulu.',
            'message.min' => 'Masukan minimal berisi 10 karakter agar tim kami dapat memahaminya.',
            'message.max' => 'Masukan tidak boleh lebih dari 3.000 karakter.',
        ]);

        Feedback::create([
            'user_id' => $request->user()?->id,
            'topic' => $validated['topic'],
            'vendor_name' => $validated['topic'] === 'vendor' ? $validated['vendor_name'] : null,
            'rating' => $validated['rating'] ?? null,
            'message' => $validated['message'],
        ]);

        return redirect()
            ->route('feedback.create')
            ->with('status', 'Terima kasih, masukanmu sudah tersimpan dan akan membantu Navora berkembang.');
    }
}
