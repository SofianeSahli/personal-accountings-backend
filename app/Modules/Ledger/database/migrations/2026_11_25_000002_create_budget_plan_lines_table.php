<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_plan_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary()->charset('ascii')->collation('ascii_bin');
            $table->uuid('budget_plan_id')->charset('ascii')->collation('ascii_bin');
            $table->uuid('category_id')->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('planned');
            $table->unique(['budget_plan_id', 'category_id']);
            $table->foreign('budget_plan_id')->references('id')->on('budget_plans')->restrictOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_plan_lines');
    }
};
