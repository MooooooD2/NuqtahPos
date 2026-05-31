<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('create_journal_entry');
    }

    public function rules(): array
    {
        return [
            'entry_date' => 'required|date|before_or_equal:today',
            'description' => 'nullable|string|max:500',
            'reference_type' => 'nullable|string|max:50',
            'reference_id' => 'nullable|integer|min:1',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|integer|exists:accounts,id',
            'lines.*.debit' => 'nullable|numeric|min:0',
            'lines.*.credit' => 'nullable|numeric|min:0',
            'lines.*.description' => 'nullable|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('lines')) {
                return;
            }

            $lines = $this->input('lines', []);
            $totalDebit = collect($lines)->sum(fn ($l) => (float) ($l['debit'] ?? 0));
            $totalCredit = collect($lines)->sum(fn ($l) => (float) ($l['credit'] ?? 0));

            if (round(abs($totalDebit - $totalCredit), 2) > 0.01) {
                $validator->errors()->add('lines', __('pos.journal_entry_unbalanced', [
                    'debit' => number_format($totalDebit, 2),
                    'credit' => number_format($totalCredit, 2),
                ]));
            }
        });
    }
}
