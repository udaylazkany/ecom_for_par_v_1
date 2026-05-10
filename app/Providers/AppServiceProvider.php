<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
{
    $this->app->bind(
        \App\Interfaces\UserRepositoryInterface::class,
        \App\Repositories\UserRepository::class
    );

    $this->app->bind(
        \App\Interfaces\CartRepositoryInterface::class,
        \App\Repositories\CartRepository::class
    );
     $this->app->bind(
        \App\Interfaces\CheckoutRepositoryInterface::class,
        \App\Repositories\CheckoutRepository::class
    );
}


    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
