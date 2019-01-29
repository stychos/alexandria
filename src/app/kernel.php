<?php

namespace alexandria\app;

use alexandria\lib;

/**
 * Application kernel
 */
class kernel
{
    /** @var config Kernel configuration object */
    protected $config;

    /** @var lib\router */
    protected $router;

    /** @var lib\uri */
    protected $uri;

    /** @var lib\request */
    protected $request;

    /** @var lib\response */
    protected $response;

    /** @var theme */
    protected $theme;

    /** @var array Kernel search path prefixes */
    protected static $_search = [
        '',                  // local aplication module
        'alexandria\\app\\', // framework application
        'alexandria\\lib\\', // framework library
    ];


    public function __construct(array $config = [])
    {
        if (!empty($config['kernel']['search']))
        {
            self::$_search = $config['kernel']['search'];
        }

        $this->config   = $this->load('config', $config);
        $this->router   = $this->load('router');
        $this->uri      = $this->load('uri');
        $this->request  = $this->load('request');
        $this->response = $this->load('response');
        $this->theme    = $this->load('theme');
    }

    public function run(): lib\response
    {
        $buffer = $this->router->autoroute();
        if ($this->request->is_http())
        {
            $buffer = $this->theme->render($buffer);
        }

        $this->response->append($buffer);
        return $this->response;
    }

    public static function load(string $class, array $config = [])
    {
        static $cache;

        foreach (self::$_search as $prefix)
        {
            $classname = $prefix . $class;
            if (!empty($cache[$classname]))
            {
                return $cache[$classname];
            }

            if (class_exists($classname))
            {
                if (empty($config) && !empty($cache['config']))
                {
                    $config = $cache['config']->{$class} ?? null;
                }

                $instance = new $classname($config);

                $cache[$classname] = $instance;
                return $cache[$classname];
            }
        }

        return null;
    }
}
