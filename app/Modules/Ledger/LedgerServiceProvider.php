<?php

declare(strict_types=1);

namespace App\Modules\Ledger;

use App\Modules\Household\Events\HouseholdDeleted;
use App\Modules\Identity\Models\User;
use App\Modules\Ledger\Commands\SyncCategoriesCommand;
use App\Modules\Ledger\Listeners\SoftDeleteHouseholdBook;
use App\Modules\Ledger\Models\Book;
use App\Modules\Ledger\Policies\BookPolicy;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class LedgerServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');

        Relation::enforceMorphMap([Book::ACCOUNT_OWNER => User::class]);
        Gate::policy(Book::class, BookPolicy::class);
        Event::listen(HouseholdDeleted::class, SoftDeleteHouseholdBook::class);

        if ($this->app->runningInConsole()) {
            $this->commands([SyncCategoriesCommand::class]);
        }

        Route::middleware('api')->prefix('api/v1')->group(__DIR__.'/routes.php');
    }
}
