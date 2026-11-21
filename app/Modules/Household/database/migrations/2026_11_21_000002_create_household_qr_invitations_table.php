<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('household_qr_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary()->charset('ascii')->collation('ascii_bin');
            $table->uuid('household_id')->charset('ascii')->collation('ascii_bin');
            $table->uuid('invited_by')->charset('ascii')->collation('ascii_bin');
            $table->char('token_hash', 64)->charset('ascii')->collation('ascii_bin')->unique();
            $table->string('status', 10)->charset('ascii')->collation('ascii_bin');
            $table->timestamp('expires_at');
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['household_id', 'status']);
            $table->foreign('household_id')->references('id')->on('households')->restrictOnDelete();
            $table->foreign('invited_by')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('household_qr_invitations');
    }
};
