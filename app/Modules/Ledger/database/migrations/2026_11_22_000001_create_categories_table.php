<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->uuid('id')->primary()->charset('ascii')->collation('ascii_bin');
            $table->uuid('parent_id')->charset('ascii')->collation('ascii_bin')->nullable();
            $table->string('code', 120)->charset('ascii')->collation('ascii_bin')->unique();
            $table->string('translation_key', 140)->charset('ascii')->collation('ascii_bin')->unique();
            $table->unsignedTinyInteger('level');
            $table->string('type', 10)->charset('ascii')->collation('ascii_bin');
            $table->string('emoji', 32)->nullable();
            $table->boolean('is_other');
            $table->unsignedSmallInteger('position');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
            $table->index(['parent_id', 'position']);
            $table->foreign('parent_id')->references('id')->on('categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
