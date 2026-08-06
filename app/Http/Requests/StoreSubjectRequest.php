<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('subjects', 'code')->ignore($this->route('subject'))],
            'name' => ['required', 'string', 'max:255'],
            'course_id' => ['required', 'exists:courses,id'],
            'year_level' => ['required', 'integer', 'between:1,5'],
            'semester' => ['required', 'integer', 'in:1,2'],
        ];
    }

    public function attributes(): array
    {
        return [
            'code' => 'subject code',
            'course_id' => 'course',
            'year_level' => 'year level',
            'semester' => 'semester',
        ];
    }
}
