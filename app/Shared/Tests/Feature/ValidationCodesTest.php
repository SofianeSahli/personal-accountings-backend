<?php

declare(strict_types=1);

use App\Shared\Errors\ValidationCodes;
use App\Shared\Tests\Support\RejectsEverything;
use Illuminate\Support\Facades\Validator;

it('REQ-6.3 gives one <field>.<rule> code per failed rule', function (): void {
    $validator = Validator::make(
        ['amount' => 'ab', 'note' => str_repeat('x', 5)],
        ['amount' => ['required', 'integer', 'min:3'], 'note' => ['max:3'], 'date' => ['required']],
    );
    $validator->passes();

    expect(ValidationCodes::from($validator))->toBe([
        'amount' => ['amount.integer', 'amount.min'],
        'note' => ['note.max'],
        'date' => ['date.required'],
    ]);
});

it('REQ-6.3 keys nested fields by their path and drops indexes from the code', function (): void {
    $validator = Validator::make(
        ['items' => [['amount' => 1], ['amount' => null]]],
        ['items.*.amount' => ['required']],
    );
    $validator->passes();

    expect(ValidationCodes::from($validator))->toBe([
        'items.1.amount' => ['items.amount.required'],
    ]);
});

it('REQ-6.3 names the code of a rule object after its class in snake case', function (): void {
    $validator = Validator::make(['phone' => '123'], ['phone' => [new RejectsEverything]]);
    $validator->passes();

    expect(ValidationCodes::from($validator))->toBe([
        'phone' => ['phone.rejects_everything'],
    ]);
});

it('REQ-6.3 snake-cases multi-word rule names', function (): void {
    $validator = Validator::make([], ['email' => ['required_without:phone']]);
    $validator->passes();

    expect(ValidationCodes::from($validator))->toBe([
        'email' => ['email.required_without'],
    ]);
});

it('REQ-6.3 returns no fields when validation passes', function (): void {
    $validator = Validator::make(['amount' => 5], ['amount' => ['required', 'integer']]);
    $validator->passes();

    expect(ValidationCodes::from($validator))->toBe([]);
});
