<?php

namespace Sifoni\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Sifoni\Provider\HttpCache\HttpCache;
use Silex\Api\EventListenerProviderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\HttpCache\Esi;
use Symfony\Component\HttpKernel\HttpCache\Store;
use Symfony\Component\HttpKernel\EventListener\SurrogateListener;

class HttpCacheServiceProvider implements ServiceProviderInterface, EventListenerProviderInterface
{
    public function register(Container $app)
    {
        $app['http_cache'] = function ($app) {
            $app['http_cache.options'] = array_replace(
                array(
                    'debug' => $app['debug'],
                ), $app['http_cache.options']
            );

            return new HttpCache($app, $app['http_cache.store'], $app['http_cache.esi'], $app['http_cache.options']);
        };

        $app['http_cache.esi'] = function ($app) {
            return new Esi();
        };

        $app['http_cache.store'] = function ($app) {
            return new Store($app['http_cache.cache_dir']);
        };

        $app['http_cache.esi_listener'] = function ($app) {
            return new SurrogateListener($app['http_cache.esi']);
        };

        $app['http_cache.options'] = array();
    }

    public function subscribe(Container $app, EventDispatcherInterface $dispatcher)
    {
        $dispatcher->addSubscriber($app['http_cache.esi_listener']);
    }
}
