<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE `recurring_rules` ADD CONSTRAINT `recurring_rules_frequency_check` CHECK (`frequency` IN ('weekly', 'monthly', 'yearly'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `recurring_rules` DROP CHECK `recurring_rules_frequency_check`');
    }
};
