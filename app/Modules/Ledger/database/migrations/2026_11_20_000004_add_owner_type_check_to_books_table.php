<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `books` ADD CONSTRAINT `books_owner_type_check` CHECK (`owner_type` IN ('account', 'household'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `books` DROP CHECK `books_owner_type_check`');
    }
};
