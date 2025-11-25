<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('money_transitions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->double('amount', 15, 2);
            $table->string('description')->nullable();
            $table->uuid('categorie_id')->nullable();
            $table->foreign('categorie_id')
                ->references('id')
                ->on('categories')
                ->onDelete('cascade');
            $table->date('date');
            $table->uuid('user_id');
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->enum('occurence', ['one_time', 'weekly', 'monthly', 'yearly'])->default('one_time');
            $table->uuid('recurrence_batch_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
