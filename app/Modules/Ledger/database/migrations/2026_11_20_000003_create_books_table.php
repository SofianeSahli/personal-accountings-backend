<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table): void {
            $table->uuid('id')->primary()->charset('ascii')->collation('ascii_bin');
            $table->string('name', 60);
            $table->char('currency', 3)->charset('ascii')->collation('ascii_bin');
            $table->string('timezone', 64);
            $table->string('owner_type', 20)->charset('ascii')->collation('ascii_bin');
            $table->uuid('owner_id')->charset('ascii')->collation('ascii_bin');
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('household_owner_id')->charset('ascii')->collation('ascii_bin')->nullable()
                ->storedAs("IF(`owner_type` = 'household' AND `deleted_at` IS NULL, `owner_id`, NULL)");
            $table->unique('household_owner_id');
            $table->index(['owner_type', 'owner_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
