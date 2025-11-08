<?php

namespace App\Http\Requests;

class UserRequest extends BaseRequest
{
    public function authorize()
    {
        $user = $this->route('user');

        return empty($user) || $user->auth_id === $this->input('auth_id');
    }

    /**
     * Child rules specific to UserRequest
     */
    protected function childRules(): array
    {
        $user = $this->route('user');

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email,' . ($user?->id ?? ''),
            ],

            'phone_number' => [
                'required',
                'unique:users,phone_number,' . ($user?->id ?? ''),
            ],
            'auth_id' => ['required', 'string'],
        ];
    }
}
