<?php

namespace App\Auth\Request;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => [
                'required',
                'string',
                'min:8',
                Password::default(),
                function ($attribute, $value, $fail) {
                    // Check for at least one uppercase letter
                    if (!preg_match('/[A-Z]/', $value)) {
                        $fail('The :attribute must contain at least one uppercase letter.');
                    }
                    
                    // Check for at least one lowercase letter
                    if (!preg_match('/[a-z]/', $value)) {
                        $fail('The :attribute must contain at least one lowercase letter.');
                    }
                    
                    // Check for at least one number or special character
                    if (!preg_match('/[0-9]/', $value) && !preg_match('/[^A-Za-z0-9]/', $value)) {
                        $fail('The :attribute must contain at least one number or special character.');
                    }
                },
            ],
            'privacy_policy_accepted' => ['required', 'accepted'],
            'terms_conditions_accepted' => ['required', 'accepted'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'The email field is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'This email is already registered.',
            'password.required' => 'The password field is required.',
            'password.min' => 'The password must be at least 8 characters.',
            'privacy_policy_accepted.required' => 'You must accept the privacy policy.',
            'privacy_policy_accepted.accepted' => 'You must accept the privacy policy.',
            'terms_conditions_accepted.required' => 'You must accept the terms and conditions.',
            'terms_conditions_accepted.accepted' => 'You must accept the terms and conditions.',
        ];
    }
}

