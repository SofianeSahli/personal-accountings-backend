<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_plans', function (Blueprint $table): void {
            $table->uuid('id')->primary()->charset('ascii')->collation('ascii_bin');
            $table->uuid('book_id')->charset('ascii')->collation('ascii_bin');
            $table->date('month');
            $table->unsignedBigInteger('expected_income')->nullable();
            $table->unsignedBigInteger('planned_saving')->nullable();
            $table->uuid('created_by')->charset('ascii')->collation('ascii_bin');
            $table->uuid('updated_by')->charset('ascii')->collation('ascii_bin');
            $table->timestamps();
            $table->unique(['book_id', 'month']);
            $table->foreign('book_id')->references('id')->on('books')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plans');
    }
};
