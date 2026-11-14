<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_sign_ins', function (Blueprint $table): void {
            $table->uuid('id')->primary()->charset('ascii')->collation('ascii_bin');
            $table->uuid('user_id')->charset('ascii')->collation('ascii_bin');
            $table->uuid('device_id')->nullable()->charset('ascii')->collation('ascii_bin');
            $table->char('token_hash', 64)->unique();
            $table->string('code_hash');
            $table->unsignedTinyInteger('failed_attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_sign_ins');
    }
};
