<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_user', function (Blueprint $table): void {
            $table->uuid('id')->primary()->charset('ascii')->collation('ascii_bin');
            $table->uuid('household_id')->charset('ascii')->collation('ascii_bin');
            $table->uuid('user_id')->charset('ascii')->collation('ascii_bin');
            $table->string('role', 10);
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('active_user_id')->charset('ascii')->collation('ascii_bin')->nullable()
                ->storedAs('IF(`deleted_at` IS NULL, `user_id`, NULL)');
            $table->unique('active_user_id');
            $table->index('user_id');
            $table->index(['household_id', 'role', 'created_at']);
            $table->foreign('household_id')->references('id')->on('households')->restrictOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_user');
    }
};
