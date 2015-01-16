<?php

namespace Sifoni;

use Silex\Provider\MonologServiceProvider;
use Silex\Provider\HttpCacheServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\WebProfilerServiceProvider;
use Silex\Provider\ServiceControllerServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Sifoni\Provider\CapsuleServiceProvider;

class Engine {
    const ENV_DEV = 'DEV';
    const ENV_TEST = 'TEST';
    const ENV_PROD = 'PROD';

    private static $_instance = null;
    private $app = null;

    protected function __construct() {
        // Constructor of Engine
    }

    private function __clone() {
        // Prevent clone instance
    }

    private function __wakeup() {
        // Prevent unserialize instance
    }

    /**
     * @return static::$_instance
     */
    public static function getInstance() {
        if (static::$_instance === null) {
            static::$_instance = new static();
        }

        return static::$_instance;
    }

    /**
     * @return $this->app
     */
    public function getApp() {
        return $this->app;
    }

    /**
     * @param array $values Values of DI Application
     * @return $this|bool
     */
    public function init(array $values = array()) {
        if ($this->app) {
            return false;
        }

        $this->app = new Application($values);
        return $this;
    }

    /**
     * @param array $new_options
     * @return $this
     * @throws Exception
     */
    public function bootstrap(array $new_options = array()) {
        if (!isset($new_options['path.root'])) {
            throw new Exception('Missing path to root dir.');
        }

        if (!defined('DS')) {
            define('DS', DIRECTORY_SEPARATOR);
            define('EXT', '.php');
        }

        $default_options = array(
            'env' => static::ENV_DEV,
            'http_cache' => false,
            'dir.app' => 'app',
            'dir.config' => 'config',
            'dir.cache' => 'cache',
            'dir.log' => 'log',
            'dir.controller' => 'controller',
            'dir.model' => 'model',
            'dir.view' => 'view',
            'app.vendor_name' => 'App'
        );

        $options = array_replace($default_options, $new_options);
        foreach ($options as $key => $value) {
            $this->app[$key] = $value;
        }

        $this->app['debug'] = ($this->app['env'] != static::ENV_PROD);

        return $this;
    }

    /**
     * @param $name
     * @return string
     */
    public function getDirPath($name) {
        $dir_name = isset($this->app['dir.' . $name]) ? $this->app['dir.' . $name] : $name;
        return $this->app['path.root'] . DS . $dir_name;
    }

    /**
     * @param $file_name
     * @throws Exception
     */
    private function loadConfig($file_name) {
        $file_path = $this->getDirPath('config') . DS . $file_name . EXT;

        if (is_readable($file_name)) {
            $configs = require ($file_path);
            foreach ($configs as $key => $value) {
                $this->app['config.' . $file_name . '.' . $key] = $value;
            }
        } else {
            throw new Exception('Can\'t read from config file.');
        }
    }

    private function registerServices() {
        $app = $this->app;

        if ($app['debug']) {
            $app->register(new MonologServiceProvider(), array(
                'monolog.logfile' => $this->getDirPath('log') . DS . 'debug.log',
            ));

            $app->register(new WebProfilerServiceProvider(), array(
                'profiler.cache_dir' => $this->getDirPath('cache') . DS . 'profiler' . DS,
                'profiler.mount_prefix' => '/_profiler',
            ));
        }

        if ($app['enabled_http_cache']) {
            $app->register(new HttpCacheServiceProvider(), array(
                'http_cache.cache_dir' => $this->getDirPath('cache') . DS,
            ));
        }

        $app->register(new ServiceControllerServiceProvider());
        $app->register(new UrlGeneratorServiceProvider());
        $app->register(new ValidatorServiceProvider());
        $app->register(new FormServiceProvider());
        $app->register(new TwigServiceProvider(), array(
            'twig.path' => $this->getDirPath('view'),
        ));
        $app->register(new SessionServiceProvider(), array(
            'session.storage.options' => array(
                'name' => 'Sifoni_Session',
                'cookie_lifetime' => 0,
                'cookie_path' => '/',
                'cookie_httponly' => true
            )
        ));

        $app->register(new CapsuleServiceProvider(), $app['config.database.parameters']);
    }

    private function loadHooks() {
        $file_path = $this->getDirPath('config') . DS . 'hook' . EXT;
        if (is_readable($file_path)) {
            include_once $file_path;
        }
    }

    /**
     * Load languages
     */
    private function loadLanguages() {
        $app = $this->app;
        $engine = $this;
        $languages = $app['config.app.languages'];

        $app->register(new TranslationServiceProvider(), array(
            'locale_fallbacks' => $languages,
        ));

        $app['translator.domains'] = $app->share(function () use ($app, $engine) {
            $translator_domains = array(
                'messages' => array(),
                'validators' => array()
            );
            $languages = $app['config.app.languages'];

            foreach ($languages as $language) {
                if (is_readable($engine->getDirPath('language') . DS . strtolower($language) . EXT)) {
                    $trans = include ($engine->getDirPath('language') . DS . strtolower($language) . EXT);
                    $translator_domains['messages'][$language] = $trans['messages'];
                    $translator_domains['validators'][$language] = $trans['validators'];
                }
            }

            return $translator_domains;
        });;
    }

    /**
     * Load routing
     */
    private function loadRouting() {
        $app = $this->app;
        $maps = array();

        $routing_file_path = $this->getDirPath('config') . DS . 'routing' . EXT;
        if (is_readable($routing_file_path)) {
            $maps = require_once($routing_file_path);
        }

        $prefix_locale = (count($app['config.app.languages']) > 1) ? '/{_locale}' : '';
        $app_controller_prefix = $app['app.vendor_name'] . '\\Controller\\';

        foreach ($maps as $prefix => $routes) {
            $map = $this->app['controllers_factory'];

            foreach ($routes as $pattern => $target) {
                $targets = explode(':', $target);
                $controller_name = $app_controller_prefix . str_replace('_', '\\', $targets[0]);
                $action = $targets[1] . 'Action';
                $bind_name = isset($targets[2]) ? $targets[2] : strtolower($targets[0] . '_' . $targets[1]);

                $tmp = $map->match($pattern, $controller_name . '::' . $action)->bind($bind_name);

                if (isset($targets[3])) {
                    $defaults = explode(',', $targets[3]);
                    foreach ($defaults as $default) {
                        $values = explode('=', $default);
                        $tmp->value($values[0], $values[1]);
                    }
                }

                if ($prefix_locale != '' && $prefix == '/' && $pattern == '/') {
                    $match = $app->match('/', $controller_name . '::' . $action);
                    if (isset($targets[4])) {
                        $match->method(strtoupper($targets[4]));
                    }
                }
            }

            $app->mount($prefix_locale . $prefix, $map);
        }
    }

    public function start() {
        $this->loadConfig('app');
        $this->loadConfig('database');
        $this->registerServices();
        $this->loadHooks();
        $this->loadLanguages();
        $this->loadRouting();

        return $this;
    }

    public function run() {
        $app = $this->app;

        if ($app['enabled_http_cache']) {
            $app['http_cache']->run();
        } else {
            $app->run();
        }

        return $this;
    }
}
