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
        $rules = [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:255'],
            'categorie_id' => ['required', 'string', 'exists:categories,id'],
            'user_id' => ['required', 'exists:users,id'],
            'date' => ['required'],
            'occurence' => ['required', 'in:one_time,weekly,monthly,yearly'],
            'end_date' => ['nullable'],
            'start_date' => ['nullable']
        ];

        // Add conditional rules for recurring transactions
        if ($this->input('occurence') !== 'one_time') {
            $rules['end_date'][] = 'required';
            $rules['start_date'][] = 'required';
        }

        return $rules;
    }

}
