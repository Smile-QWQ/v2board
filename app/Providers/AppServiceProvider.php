<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app['view']->addNamespace('theme', public_path() . '/theme');
        $this->loadRuntimeThemeConfig();
    }

    private function loadRuntimeThemeConfig(): void
    {
        $themeConfigDir = base_path('config/theme');
        if (!File::isDirectory($themeConfigDir)) {
            return;
        }

        foreach (File::files($themeConfigDir) as $themeConfigFile) {
            if ($themeConfigFile->getExtension() !== 'php') {
                continue;
            }

            $themeName = $themeConfigFile->getFilenameWithoutExtension();
            $themeConfig = include $themeConfigFile->getPathname();
            if (!is_array($themeConfig)) {
                continue;
            }

            config(["theme.{$themeName}" => $themeConfig]);
        }
    }
}
