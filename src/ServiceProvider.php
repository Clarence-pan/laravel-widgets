<?php

namespace Arrilot\Widgets;

use Arrilot\Widgets\Console\WidgetMakeCommand;
use Arrilot\Widgets\Factories\AsyncWidgetFactory;
use Arrilot\Widgets\Factories\WidgetFactory;
use Arrilot\Widgets\Misc\LaravelApplicationWrapper;
use Illuminate\Console\AppNamespaceDetectorTrait;
use Illuminate\Support\Facades\Blade;

class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    use AppNamespaceDetectorTrait;

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/config/config.php', 'laravel-widgets'
        );

        $this->app->bind('arrilot.widget', function () {
            return new WidgetFactory(new LaravelApplicationWrapper());
        });

        $this->app->bind('arrilot.async-widget', function () {
            return new AsyncWidgetFactory(new LaravelApplicationWrapper());
        });

        $this->app->singleton('arrilot.widget-group-collection', function () {
            return new WidgetGroupCollection(new LaravelApplicationWrapper());
        });

        $this->app->singleton('command.widget.make', function ($app) {
            return new WidgetMakeCommand($app['files']);
        });

        $this->commands('command.widget.make');

        $this->app->alias('arrilot.widget', 'Arrilot\Widgets\Factories\WidgetFactory');
        $this->app->alias('arrilot.async-widget', 'Arrilot\Widgets\Factories\AsyncWidgetFactory');
        $this->app->alias('arrilot.widget-group-collection', 'Arrilot\Widgets\WidgetGroupCollection');
    }

    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/config/config.php' => config_path('laravel-widgets.php'),
        ]);

        $routeConfig = [
            'namespace'  => 'Arrilot\Widgets\Controllers',
            'prefix'     => 'arrilot',
            'middleware' => $this->app['config']->get('laravel-widgets.route_middleware', []),
        ];

        if (!$this->app->routesAreCached()) {
            $this->app['router']->group($routeConfig, function ($router) {
                $router->get('load-widget', 'WidgetController@showWidget');
            });
        }

        Blade::directive('widget', function ($expression) {
            return "<?php echo app('arrilot.widget')->run{$expression}; ?>";
        });

        // Blade::directive cannot recognize @async-widget, so @async-widget still use the custom matcher.
        $this->registerBladeDirective('async-widget', '$1<?php echo app("arrilot.async-widget")->run$2; ?>');

        Blade::directive('asyncWidget', function ($expression) {
            return "<?php echo app('arrilot.async-widget')->run{$expression}; ?>";
        });

        Blade::directive('widgetGroup', function ($expression) {
            return "<?php echo app('arrilot.widget-group-collection')->group{$expression}->display(); ?>";
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['arrilot.widget', 'arrilot.async-widget'];
    }

    /**
     * Register a blade directive.
     *
     * @param $name
     * @param $expression
     */
    protected function registerBladeDirective($name, $expression)
    {
        Blade::extend(function ($view) use ($name, $expression) {
            $pattern = $this->createMatcher($name);

            return preg_replace($pattern, $expression, $view);
        });
    }

    /**
     * Substitution for $compiler->createMatcher().
     *
     * Get the regular expression for a generic Blade function.
     *
     * @param string $function
     *
     * @return string
     */
    protected function createMatcher($function)
    {
        return '/(?<!\w)(\s*)@'.$function.'(\s*\(.*\))/';
    }
}
