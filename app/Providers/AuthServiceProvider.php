<?php

namespace App\Providers;

use App\Models\DocumentHeader;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ThirdPartner;
use App\Models\User;
use App\Policies\DocumentHeaderPolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ThirdPartnerPolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        DocumentHeader::class => DocumentHeaderPolicy::class,
        Product::class        => ProductPolicy::class,
        ThirdPartner::class   => ThirdPartnerPolicy::class,
        Payment::class        => PaymentPolicy::class,
        User::class           => UserPolicy::class,
    ];

    public function boot(): void
    {
        // Admin bypass: admin role gets all permissions automatically
        Gate::before(fn (User $user) => $user->role?->name === 'admin' ? true : null);

        Gate::define('manage-users', fn (User $user) => $user->hasPermission('users.create'));

        Gate::define('manage-settings', fn (User $user) => $user->hasPermission('settings.manage'));

        Gate::define('manage-catalogue', fn (User $user) => $user->hasPermission('products.create'));

        Gate::define('manage-documents', fn (User $user) => $user->hasPermission('documents.create'));

        Gate::define('manage-stock', fn (User $user) => $user->hasPermission('stock.manage'));
    }
}
