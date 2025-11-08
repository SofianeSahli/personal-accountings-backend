<?php

namespace App\Http\Requests;

class MoneyTransitionRequest extends BaseRequest
{
    public function authorize()
    {
        $moneyTransition = $this->route('moneyTransition');

        return empty($moneyTransition) || $moneyTransition->user_id === $this->input('user_id');
    }

    public function childRules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'categorie_id' => ['required', 'string', 'exists:categories,id'],
            'user_id' => ['required', 'exists:users,id'],
        ];
    }
}
