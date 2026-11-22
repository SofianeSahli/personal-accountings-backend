<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `categories` ADD CONSTRAINT `categories_type_check` CHECK (`type` IN ('expense', 'income', 'saving'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `categories` DROP CHECK `categories_type_check`');
    }
};
