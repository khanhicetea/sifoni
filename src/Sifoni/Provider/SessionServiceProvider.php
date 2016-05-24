<?php

namespace Sifoni\Provider;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeSessionHandler;
use Symfony\Component\HttpFoundation\Session\Storage\MockFileSessionStorage;
use Silex\Application;
use Silex\Provider\SessionServiceProvider as SilexSessionServiceProvider;
use Sifoni\Adapter\SifoniSessionStorage;


class SessionServiceProvider extends SilexSessionServiceProvider
{

    public function register(Application $app)
    {
        $this->app = $app;

        $app['session.test'] = false;

        $app['session'] = $app->share(function ($app) {
            if (!isset($app['session.storage'])) {
                if ($app['session.test']) {
                    $app['session.storage'] = $app['session.storage.test'];
                } else {
                    $app['session.storage'] = $app['session.storage.native'];
                }
            }

            return new Session($app['session.storage']);
        });

        $app['session.storage.handler'] = $app->share(function ($app) {
            if ($app['session.storage.save_path']) {
                return new NativeFileSessionHandler($app['session.storage.save_path']);
            }

            return new NativeSessionHandler();
        });

        $app['session.storage.native'] = $app->share(function ($app) {
            return new SifoniSessionStorage(
                $app['session.storage.options'],
                $app['session.storage.handler']
            );
        });

        $app['session.storage.test'] = $app->share(function () {
            return new MockFileSessionStorage();
        });

        $app['session.storage.options'] = array();
        $app['session.default_locale'] = 'en';
        $app['session.storage.save_path'] = null;
    }

}
