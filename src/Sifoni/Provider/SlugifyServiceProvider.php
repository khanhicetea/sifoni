<?php

namespace Sifoni\Provider;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\Slugify;
use Silex\Application;

class SlugifyServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        $app['slugify.options'] = [];
        $app['slugify.provider'] = null;

        $app['slugify'] = function ($app) {
            return new Slugify($app['slugify.options'], $app['slugify.provider']);
        };

        if (isset($app['twig'])) {
            $app['twig'] = $app->extend('twig', function (\Twig_Environment $twig, $app) {
                $twig->addExtension(new SlugifyExtension($app['slugify']));

                return $twig;
            });
        }
    }

    public function boot(Application $app)
    {
    }
}
