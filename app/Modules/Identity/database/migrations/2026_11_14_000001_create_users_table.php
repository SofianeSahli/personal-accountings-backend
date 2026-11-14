<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary()->charset('ascii')->collation('ascii_bin');
            $table->string('name', 120);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('google_id')->nullable()->unique();
            $table->string('phone', 16)->nullable();
            $table->string('locale', 8)->default('fr');
            $table->string('terms_version', 20);
            $table->timestamp('terms_accepted_at');
            $table->timestamp('two_factor_enabled_at')->nullable();
            $table->unsignedTinyInteger('password_failed_attempts')->default(0);
            $table->timestamp('password_locked_until')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
