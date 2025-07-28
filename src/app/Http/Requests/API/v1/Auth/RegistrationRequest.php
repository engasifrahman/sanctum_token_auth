<?php

namespace App\Http\Requests\API\v1\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class RegistrationRequest extends FormRequest
{

    /**
     * Prepare the data for validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $data = [];

        if ($this->filled('email')) {
            $data['email'] = strtolower($this->input('email'));
        }

        if ($this->filled('roles') && is_array($this->input('roles'))) {
            $data['roles'] = array_map('ucwords', $this->input('roles'));
        }

        if (!empty($data)) {
            $this->merge($data);
        }
    }

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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'roles'   => 'required|array',
            'roles.*' => 'exists:roles,name',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * Adds custom validation logic to ensure:
     * - Only existing administrators can assign 'admin' or 'super admin' roles.
     * - If 'admin' or 'super admin' is selected, no other roles may be selected.
     *
     * @param Validator $validator
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            // Skip if basic validation failed
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $restrictedRoles = ['Admin', 'Super Admin'];
            $selectedRoles = $this->input('roles', []);

            $restrictedSelected = array_intersect($selectedRoles, $restrictedRoles);

            // Only apply check if restricted roles are selected
            if (empty($restrictedSelected)) {
                return;
            }

            $user = Auth::guard('sanctum')->user();

            // Require a valid Sanctum token
            if (!$this->bearerToken() || !$user) {
                $validator->errors()->add(
                    'roles',
                    'Authentication is required to assign admin roles.'
                );
                return;
            }

            // Ensure current user is an administrator
            if (!$user?->isAdministrator()) {
                $validator->errors()->add(
                    'roles',
                    'Only existing administrators can assign Admin or Super Admin roles.'
                );
                return;
            }

            // Disallow mixing restricted roles with other roles
            if (array_diff($selectedRoles, $restrictedSelected)) {
                $validator->errors()->add(
                    'roles',
                    'If Admin or Super Admin is selected, no other roles are allowed.'
                );
            }
        });
    }
}
