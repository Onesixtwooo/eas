<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExcuseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === 'student';
    }

    protected function prepareForValidation(): void
    {
        // The start of a new request is authoritative server time, not a value
        // supplied by (or calculated on) the user's device.
        $serverTime = now()->format('H:i');
        $this->merge([
            'start_time' => $serverTime,
            'end_time' => $serverTime,
        ]);

        if (! $this->has('subject_ids') && $this->filled('subject_id')) {
            $this->merge(['subject_ids' => [$this->input('subject_id')]]);
        }
    }

    public function rules(): array
    {
        return ['absence_date' => 'required|date|before_or_equal:today', 'subject_ids' => 'required|array|min:1', 'subject_ids.*' => 'integer|distinct|exists:subjects,id', 'reason_category_id' => 'required|exists:reason_categories,id', 'explanation' => 'required|string|min:20|max:3000', 'start_time' => 'required|date_format:H:i', 'end_time' => 'required|date_format:H:i', 'guardian_name' => 'nullable|string|max:255', 'guardian_contact' => 'nullable|string|max:30', 'document' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', 'declaration' => 'required_if:intent,submit|accepted'];
    }
}
