<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table): void {
            $table->uuid('id')->primary()->charset('ascii')->collation('ascii_bin');
            $table->uuid('book_id')->charset('ascii')->collation('ascii_bin');
            $table->uuid('category_id')->charset('ascii')->collation('ascii_bin');
            $table->uuid('recurring_rule_id')->charset('ascii')->collation('ascii_bin')->nullable();
            $table->date('rule_due_on')->nullable();
            $table->unsignedBigInteger('amount');
            $table->date('date');
            $table->string('other_name', 60)->nullable();
            $table->string('comment', 200)->nullable();
            $table->uuid('created_by')->charset('ascii')->collation('ascii_bin');
            $table->uuid('updated_by')->charset('ascii')->collation('ascii_bin');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['recurring_rule_id', 'rule_due_on']);
            $table->index(['book_id', 'deleted_at', 'date', 'created_at', 'id']);
            $table->index(['book_id', 'category_id']);
            $table->index(['book_id', 'created_by']);
            $table->foreign('book_id')->references('id')->on('books')->restrictOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('recurring_rule_id')->references('id')->on('recurring_rules')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
