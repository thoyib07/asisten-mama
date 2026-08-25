<?php

use App\Modules\Bills\Providers\BillsServiceProvider;
use App\Modules\Calendar\Providers\CalendarServiceProvider;
use App\Modules\Cooking\Providers\CookingServiceProvider;
use App\Modules\Finance\Providers\FinanceServiceProvider;
use App\Modules\Household\Providers\HouseholdServiceProvider;
use App\Modules\ShoppingList\Providers\ShoppingListServiceProvider;
use App\Modules\Tasks\Providers\TasksServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AppPanelProvider;

return [
    AppServiceProvider::class,
    AppPanelProvider::class,
    AdminPanelProvider::class,
    CookingServiceProvider::class,
    ShoppingListServiceProvider::class,
    FinanceServiceProvider::class,
    HouseholdServiceProvider::class,
    CalendarServiceProvider::class,
    TasksServiceProvider::class,
    BillsServiceProvider::class,
];
