<?php

namespace Sifoni;

use Silex\Provider\MonologServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\HttpFragmentServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;
use Silex\Provider\LocaleServiceProvider;
use Silex\Provider\AssetServiceProvider;
use Silex\Provider\CsrfServiceProvider;
use Silex\Provider\VarDumperServiceProvider;
use Sifoni\Provider\CapsuleServiceProvider;
use Sifoni\Provider\SessionServiceProvider;
use Sifoni\Provider\HttpCacheServiceProvider;
use Monolog\Logger;
use Twig_SimpleFunction;
use Twig_Environment;

class Engine
{
    private static $instance = null;
    private $app = null;

    protected function __construct()
    {
        // Constructor of Engine
    }

    private function __clone()
    {
        // Prevent clone instance
    }

    private function __wakeup()
    {
        // Prevent unserialize instance
    }

    /**
     * @return Sifoni\Engine
     */
    public static function getInstance()
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * @return Sifoni\Application
     */
    public function getApp()
    {
        return $this->app;
    }

    /**
     * @param array $values Values of DI Application
     *
     * @return Sifoni\Engine
     */
    public function init(array $values = [])
    {
        if ($this->app) {
            return false;
        }

        $this->app = new Application($values);

        return $this;
    }

    /**
     * @param array $new_options
     *
     * @return Sifoni\Engine
     *
     * @throws Exception
     */
    public function bootstrap(array $new_options = [])
    {
        if (!isset($new_options['path.root'])) {
            throw new Exception('Missing path to root dir.');
        }

        $default_options = [
            'debug' => false,
            'logging' => true,
            'timezone' => 'Asia/Ho_Chi_Minh', // I <3 Vietnam
            'web_profiler' => true,
            'enabled_http_fragment' => true,
            'enabled_http_cache' => false,
            'enabled_twig' => true,
            'enabled_session' => true,
            'enabled_capsule' => true,
            'enabled_csrf' => true,
            'enabled_asset' => true,
            'dir.app' => 'app',
            'dir.storage' => 'storage',
            'app.vendor_name' => 'App',
        ];

        $options = array_replace($default_options, $new_options);
        foreach ($options as $key => $value) {
            $this->app[$key] = $value;
        }

        return $this;
    }

    /**
     * @param $name
     *
     * @return string
     */
    public function getAppPath($name)
    {
        return $this->app['path.root'].DIRECTORY_SEPARATOR.$this->app['dir.app'].DIRECTORY_SEPARATOR.$name;
    }

    /**
     * @param $name
     *
     * @return string
     */
    public function getStoragePath($name)
    {
        return $this->app['path.root'].DIRECTORY_SEPARATOR.$this->app['dir.storage'].DIRECTORY_SEPARATOR.$name;
    }

    /**
     * @param $file_name
     *
     * @throws Exception
     */
    private function loadConfig($file_name)
    {
        $file_path = $this->getAppPath('config').DIRECTORY_SEPARATOR.$file_name.'.php';

        if (is_readable($file_path)) {
            $configs = require $file_path;
            foreach ($configs as $key => $value) {
                $this->app['config.'.$file_name.'.'.$key] = $value;
            }
        } else {
            throw new Exception("Can't read from config file.");
        }
    }

    private function registerServices()
    {
        $app = $this->app;

        $app->register(new ServiceControllerServiceProvider());

        $app['request'] = $app->factory(function ($c) {
            return $c['request_stack']->getCurrentRequest();
        });

        if ($app['logging']) {
            $app->register(new MonologServiceProvider(), [
                'monolog.logfile' => $this->getStoragePath('log').DIRECTORY_SEPARATOR.($app['debug'] ? 'debug.log' : 'production.log'),
            ]);

            if (!$app['debug']) {
                $app['monolog.level'] = function () {
                    return Logger::ERROR;
                };
            }
        }

        if ($app['enabled_http_fragment']) {
            $app->register(new HttpFragmentServiceProvider());
        }

        if ($app['enabled_twig']) {
            $app->register(new TwigServiceProvider(), [
                'twig.path' => $this->getAppPath('view'),
            ]);
        }

        if ($app['enabled_session']) {
            $app->register(new SessionServiceProvider());
        }

        if ($app['enabled_csrf']) {
            $app->register(new CsrfServiceProvider());

            if ($app['enabled_twig']) {
                $app['twig'] = $app->extend('twig', function (Twig_Environment $twig, $app) {
                    $csrf_token = new Twig_SimpleFunction('csrf_token', function ($token_id) use ($app) {
                        return $app['csrf.token_manager']->getToken($token_id);
                    });
                    $twig->addFunction($csrf_token);

                    return $twig;
                });
            }
        }

        if ($app['enabled_capsule']) {
            $app->register(new CapsuleServiceProvider(), $app['config.database.parameters']);
        }

        if ($app['enabled_asset']) {
            $app->register(new AssetServiceProvider(), [
                'assets.version' => time(),
                'assets.version_format' => '%s?version=%s',
            ]);
        }

        if ($app['enabled_http_cache']) {
            $app->register(new HttpCacheServiceProvider(), [
                'http_cache.cache_dir' => $this->getStoragePath('cache').DIRECTORY_SEPARATOR,
            ]);
        }

        if ($app['debug']) {
            $app->register(new VarDumperServiceProvider());

            if ($app['web_profiler']) {
                $app->register(new WebProfilerServiceProvider(), [
                    'profiler.cache_dir' => $this->getStoragePath('cache').DIRECTORY_SEPARATOR.'profiler'.DIRECTORY_SEPARATOR
                ]);
            }
        }
    }

    private function loadHooks()
    {
        $file_path = $this->getAppPath('config').DIRECTORY_SEPARATOR.'hook.php';
        if (is_readable($file_path)) {
            include_once $file_path;
        }
    }

    /**
     * Load languages.
     */
    private function loadLanguages()
    {
        $app = $this->app;
        $engine = $this;
        $languages = $app['config.app.languages'];
        $app['multi_languages'] = count($languages) > 1;

        $app->register(new LocaleServiceProvider());
        $app->register(new TranslationServiceProvider(), [
            'locale_fallbacks' => $languages,
        ]);

        $app['translator.domains'] = function () use ($app, $engine) {
            $translator_domains = [
                'messages' => [],
                'validators' => [],
            ];
            $languages = $app['config.app.languages'];

            foreach ($languages as $language) {
                if (is_readable($engine->getAppPath('language').DIRECTORY_SEPARATOR.strtolower($language).'.php')) {
                    $trans = include $engine->getAppPath('language').DIRECTORY_SEPARATOR.strtolower($language).'.php';
                    $translator_domains['messages'][$language] = isset($trans['messages']) ? $trans['messages'] : [];
                    $translator_domains['validators'][$language] = isset($trans['validators']) ? $trans['validators'] : [];
                }
            }

            return $translator_domains;
        };
    }

    /**
     * Load routing.
     */
    private function loadRouting()
    {
        $app = $this->app;
        $maps = [];

        $routing_file_path = $this->getAppPath('config').DIRECTORY_SEPARATOR.'routing.php';
        if (is_readable($routing_file_path)) {
            $maps = require_once $routing_file_path;
        }

        if ($maps) {
            $prefix_locale = $app['multi_languages'] ? '/{_locale}' : '';
            $app_controller_prefix = $app['app.vendor_name'].'\\Controller\\';

            foreach ($maps as $prefix => $routes) {
                $map = $this->app['controllers_factory'];

                foreach ($routes as $pattern => $target) {
                    if ($pattern == '.' && is_callable($target)) {
                        call_user_func($target, $map);
                    } else {
                        $params = is_array($target) ? $target : explode(':', $target);
                        $controller_name = $app_controller_prefix.$params[0];
                        $action = $params[1].'Action';
                        $bind_name = isset($params[2]) ? $params[2] : false;
                        $method = isset($params[4]) ? strtolower($params[4]) : 'get|post';

                        $tmp = $map->match($pattern, $controller_name.'::'.$action)->method($method);
                        if ($bind_name) {
                            $tmp->bind($bind_name);
                        }

                        if (!empty($params[3])) {
                            if (is_array($params[3])) {
                                foreach ($params[3] as $key => $value) {
                                    $tmp->value($key, $value);
                                }
                            } else {
                                $defaults = explode(',', $params[3]);
                                foreach ($defaults as $default) {
                                    $values = explode('=', $default);
                                    $tmp->value($values[0], $values[1]);
                                }
                            }
                        }

                        if ($prefix_locale != '' && $prefix == '/' && $pattern == '/') {
                            $app->$method('/', $controller_name.'::'.$action);
                        }
                    }
                }

                $app->mount($prefix_locale.$prefix, $map);
            }
        }
    }

    public function start()
    {
        date_default_timezone_set($this->app['timezone']);

        $this->loadConfig('app');
        $this->loadConfig('database');
        $this->registerServices();
        $this->loadHooks();
        $this->loadLanguages();
        $this->loadRouting();

        return $this;
    }

    public function run($request = null, $send_response = true)
    {
        $app = $this->app;
        $request_handler = $app['enabled_http_cache'] ? $app['http_cache'] : $app;

        return $request_handler->run($request, $send_response);
    }
}
