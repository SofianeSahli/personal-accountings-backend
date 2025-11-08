<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class BaseRequest extends FormRequest
{
    protected ?string $userId = null;

    /**
     * Merge base rules with child rules
     */
    public function rules(): array
    {
        $childRules = $this->childRules();

        return array_merge($this->baseRules(), $childRules);
    }

    /**
     * Generate validation messages dynamically
     */
    public function messages(): array
    {
        $allRules = $this->rules();
        $messages = [];

        foreach ($allRules as $field => $rules) {
            foreach ($rules as $rule) {
                // extract rule name (e.g., "required", "max:255", "unique:users,email")
                $ruleName = is_string($rule)
                    ? explode(':', $rule)[0]
                    : (is_object($rule) ? class_basename($rule) : 'rule');

                $messages["{$field}.{$ruleName}"] = "{$field}.{$ruleName}";
            }
        }

        return $messages;
    }

    /**
     * Common rules shared across all requests.
     * You can add auth_id or other global fields here.
     */
    protected function baseRules(): array
    {
        return [
            'auth_id' => ['required', 'string'],
        ];
    }

    abstract protected function childRules(): array;
}
