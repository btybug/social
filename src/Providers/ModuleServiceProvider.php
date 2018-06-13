<?php

namespace Btybug\Social\Providers;

use Illuminate\Support\ServiceProvider;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the module services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadTranslationsFrom(__DIR__ . '/../Resources/Lang', 'social');
        $this->loadViewsFrom(__DIR__ . '/../Resources/Views', 'social');



        \Eventy::action('admin.menus', [
            "title" => "Social",
            "custom-link" => "#",
            "icon" => "fa fa-ils",
            "is_core" => "yes",
            "children" => [
                [
                    "title" => "index",
                    "custom-link" => "/admin/social",
                    "icon" => "fa fa-angle-right",
                    "is_core" => "yes",
                ],
            ]]);
        //TODO; remove when finish all
        \Btybug\btybug\Models\Routes::registerPages('btybug/social');
    }

    /**
     * Register the module services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
    }
}
