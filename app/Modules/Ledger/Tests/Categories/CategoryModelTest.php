<?php

declare(strict_types=1);

use App\Modules\Ledger\Models\Category;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

it('REQ-2.9 stores the translation key next to the code', function (): void {
    $top = Category::factory()->code('food')->create();
    $leaf = Category::factory()->under(Category::factory()->under($top, 'groceries')->create(), 'other')->create();

    expect($top->fresh()?->translation_key)->toBe('categories.food.label')
        ->and($leaf->fresh()?->translation_key)->toBe('categories.food.groceries.other.label')
        ->and($leaf->fresh()?->is_other)->toBeTrue();
});

it('REQ-2.4 refuses a type other than expense, income or saving in the database', function (): void {
    $category = Category::factory()->create();

    DB::table('categories')->where('id', $category->id)->update(['type' => 'gift']);
})->throws(QueryException::class);

it('REQ-2.4 refuses the same code twice in the database', function (): void {
    Category::factory()->code('food')->create();
    Category::factory()->code('food')->create();
})->throws(UniqueConstraintViolationException::class);

it('REQ-2.6 refuses to delete a category that has children', function (): void {
    $top = Category::factory()->create();
    Category::factory()->under($top, 'groceries')->create();

    DB::table('categories')->where('id', $top->id)->delete();
})->throws(QueryException::class);
