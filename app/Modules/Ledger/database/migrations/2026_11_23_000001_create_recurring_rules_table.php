<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recurring_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary()->charset('ascii')->collation('ascii_bin');
            $table->uuid('book_id')->charset('ascii')->collation('ascii_bin');
            $table->uuid('category_id')->charset('ascii')->collation('ascii_bin');
            $table->unsignedBigInteger('amount');
            $table->string('other_name', 60)->nullable();
            $table->string('comment', 200)->nullable();
            $table->string('frequency', 10)->charset('ascii')->collation('ascii_bin');
            $table->unsignedTinyInteger('interval');
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->date('next_due_on')->nullable();
            $table->date('due_order')->storedAs("COALESCE(`next_due_on`, '9999-12-31')");
            $table->uuid('created_by')->charset('ascii')->collation('ascii_bin');
            $table->uuid('updated_by')->charset('ascii')->collation('ascii_bin');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['book_id', 'deleted_at', 'due_order', 'id']);
            $table->index('next_due_on');
            $table->foreign('book_id')->references('id')->on('books')->restrictOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recurring_rules');
    }
};
