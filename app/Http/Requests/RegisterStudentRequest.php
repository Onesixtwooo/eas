<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class RegisterStudentRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'student_number' => ['required', 'string', 'max:50', 'unique:students,student_number'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:1000'],
            'assessment_form' => [
                Rule::requiredIf($this->routeIs('register.store')),
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png',
                'max:5120',
                'dimensions:min_width=100,min_height=100,max_width=6000,max_height=6000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value?->isValid()) {
                        return;
                    }

                    $contents = file_get_contents($value->getRealPath());
                    $imageType = @exif_imagetype($value->getRealPath());

                    if (! in_array($imageType, [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
                        $fail('The assessment form must be a genuine JPG or PNG image.');
                    }

                    $dangerousPhp = '/<\?(?:php|=)|\b(?:eval|assert|system|exec|shell_exec|passthru|proc_open|popen|pcntl_exec)\s*\(/i';

                    if ($contents === false || preg_match($dangerousPhp, $contents)) {
                        $fail('The assessment form contains prohibited PHP code.');
                    }
                },
            ],
            'year_level' => ['required', 'integer', 'between:1,5'],
            'block' => ['required', 'string', 'in:A,B,C,D,E,F,G'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'otp' => [
                'required',
                'digits:6',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $email = strtolower(trim((string) $this->input('email')));
                    $expiresAt = (int) $this->session()->get('registration_email_otp_expires_at', 0);

                    if ($this->session()->get('registration_email_otp_email') !== $email) {
                        $fail('Send an OTP to this email address before registering.');
                    } elseif ($expiresAt < now()->timestamp) {
                        $fail('The OTP has expired. Send a new code.');
                    } elseif (! hash_equals((string) $this->session()->get('registration_email_otp_hash'), hash('sha256', (string) $value))) {
                        $fail('The OTP is incorrect.');
                    }
                },
            ],
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }

    public function attributes(): array
    {
        return ['student_number' => 'student ID', 'year_level' => 'year level', 'assessment_form' => 'assessment form'];
    }
}
