<?php

namespace Sifoni\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Sifoni\Model\DB;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Cache\CacheManager;

class CapsuleServiceProvider implements ServiceProviderInterface
{
    /**
     * Register the Capsule service.
     * Ref: http://stackoverflow.com/questions/17105829/using-eloquent-orm-from-laravel-4-outside-of-laravel.
     *
     * @param $app
     **/
    public function register(Application $app)
    {
        $app['capsule.connection_defaults'] = array(
            'driver' => 'mysql',
            'host' => 'localhost',
            'database' => null,
            'username' => 'root',
            'password' => null,
            'charset' => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix' => null,
            'logging' => false,
        );

        $app['capsule.container'] = $app->share(function () {
            return new Container();
        });

        $app['capsule.dispatcher'] = $app->share(function () use ($app) {
            return new Dispatcher($app['capsule.container']);
        });

        if (class_exists('Illuminate\Cache\CacheManager')) {
            $app['capsule.cache_manager'] = $app->share(function () use ($app) {
                return new CacheManager($app['capsule.container']);
            });
        }

        $app['capsule'] = $app->share(function ($app) {
            $capsule = new DB($app['capsule.container']);
            $capsule->setEventDispatcher($app['capsule.dispatcher']);

            if (isset($app['capsule.cache_manager']) && isset($app['capsule.cache'])) {
                $capsule->setCacheManager($app['capsule.cache_manager']);

                foreach ($app['capsule.cache'] as $key => $value) {
                    $app['capsule.container']->offsetGet('config')->offsetSet('cache.'.$key, $value);
                }
            }

            if (!isset($app['capsule.connections'])) {
                $app['capsule.connections'] = array(
                    'default' => (isset($app['capsule.connection']) ? $app['capsule.connection'] : array()),
                );
            }

            foreach ($app['capsule.connections'] as $connection => $options) {
                $options = array_replace($app['capsule.connection_defaults'], $options);
                $logging = $options['logging'];
                unset($options['logging']);

                $capsule->addConnection($options, $connection);

                if ($logging) {
                    $capsule->getConnection($connection)->enableQueryLog();
                } else {
                    $capsule->getConnection($connection)->disableQueryLog();
                }
            }

            return $capsule;
        });
    }

    /**
     * Boot the Capsule service.
     *
     * @param $app ;
     **/
    public function boot(Application $app)
    {
        // Do nothing here
    }
}
