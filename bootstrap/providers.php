<?php

use App\Modules\Cooking\Providers\CookingServiceProvider;
use App\Modules\Finance\Providers\FinanceServiceProvider;
use App\Modules\Household\Providers\HouseholdServiceProvider;
use App\Modules\ShoppingList\Providers\ShoppingListServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    CookingServiceProvider::class,
    ShoppingListServiceProvider::class,
    FinanceServiceProvider::class,
    HouseholdServiceProvider::class,
];
