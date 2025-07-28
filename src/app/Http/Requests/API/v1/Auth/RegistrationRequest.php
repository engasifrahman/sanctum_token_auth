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
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $selectedRoles = $this->input('roles', []);
            $restrictedRoles = ['Admin', 'Super Admin'];
            $restrictedSelected = array_intersect($selectedRoles, $restrictedRoles);

            // Check if restricted roles are selected
            if (!empty($restrictedSelected)) {
                $user = Auth::guard('sanctum')->user();

                if (!$this->bearerToken() || !$user) {
                    $validator->errors()->add('roles', 'Authentication is required to assign admin roles.');
                    return;
                }

                if (!$user->isAdministrator()) {
                    $validator->errors()->add('roles', 'Only existing administrators can assign Admin or Super Admin roles.');
                    return;
                }

                if (count($restrictedSelected) !== count($selectedRoles)) {
                    $validator->errors()->add('roles', 'If Admin or Super Admin is selected, no other roles are allowed.');
                    return;
                }
            }

            // Ensure Subscriber always includes User role
            if (in_array('Subscriber', $selectedRoles) && !in_array('User', $selectedRoles)) {
                $validator->errors()->add('roles', 'Subscriber role cannot be registered without the User role.');
            }
        });
    }
}
