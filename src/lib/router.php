<?php

namespace alexandria\lib;

class router
{
    protected $autoroute_path;
    protected $default_route;
    protected $fail_route;

    protected $pre_routes;
    protected $post_routes;
    protected $rewrites;

    protected $search_controllers;
    protected $continue;
    protected $tail;

    public function __construct(array $args)
    {
        $this->search_controllers = $args['search_controllers'] ?? ['{$route}\\controller', '{$route}'];

        $this->autoroute_path = $args['autoroute_path'] ?? $_SERVER['PATH_INFO'] ?? null;
        if (is_null($this->autoroute_path) && isset($_SERVER['argv']))
        {
            $this->autoroute_path = implode('/', $_SERVER['argv']);
        }
        $this->autoroute_path = urldecode($this->autoroute_path);

        $this->default_route = $args['default_route'] ?? 'index';
        $this->fail_route    = $args['fail_route'] ?? 'notfound';

        $this->pre_routes  = $args['pre_routes'] ?? [];
        $this->post_routes = $args['post_routes'] ?? [];
        $this->rewrites    = $args['rewrites'] ?? [];
    }

    /**
     * @param string $route
     *
     * @return bool
     */
    protected function _route(string $route): bool
    {
        $_route = $route;
        foreach ($this->rewrites as $from => $to)
        {
            $_route = str_replace($from, $to, $_route);
        }

        foreach ($this->search_controllers as $class)
        {
            $controller = str_replace('{$route}', $_route, $class);
            $controller = str_replace('/', '\\', $controller);
            $controller = preg_replace('~\\\+~', '\\', $controller);

            if (class_exists($controller))
            {
                $this->tail = str_replace($_route, '', $this->autoroute_path);
                $this->tail = trim(rtrim($this->tail, '/'), '/');

                new $controller();
                return true;
            }
        }

        return false;
    }

    /**
     * @param bool $use_fallback
     */
    public function autoroute(bool $redirect = false)
    {
        if (!$redirect) // do prerouting only on independent calls
        {
            $this->continue = true;
            $this->preroute();

            if (!$this->continue)
            {
                return; // some preroute controller told us to halt
            }
        }

        $routed         = false;
        $this->continue = false; // prerouting finished, not disabling autocontinuation
        if (!empty($this->autoroute_path))
        {
            $path = explode('/', $this->autoroute_path);
            do
            {
                $sub = implode('/', $path);
                if ($this->_route($sub))
                {
                    $routed = true;
                    if ($this->continue) // called controller had set us to continue autoroute cycle
                    {
                        $this->continue = false; // reset it for the next controller in cycle
                    }
                    else // controller doesn't stated anything, halt cycle after first matched controller call
                    {
                        break;
                    }
                }

                array_pop($path); // walk up to the root
            }
            while (!empty($path));
        }

        if ($redirect) // that was redirect cycle, return result
        {
            return;
        }

        // no route was found and route string became empty, trying default route
        if (!$routed && empty($this->autoroute_path))
        {
            $routed = $this->_route($this->default_route);
        }

        // no default route succeeded, trying fail route
        if (!$routed)
        {
            $routed = $this->_route($this->fail_route);
        }

        // do postrouting after all
        $this->continue = true;
        $this->postroute();

        // no routes called, trigger error
        if (!$routed)
        {
            if (stripos('CLI', PHP_SAPI) === false)
            {
                http_response_code(404);
            }

            trigger_error("Route class for {$this->autoroute_path} not found. Default and fail routes are not found too. Check your configuration.",
                E_USER_WARNING);
        }
    }

    protected function preroute()
    {
        foreach ($this->pre_routes as $route)
        {
            if ($this->continue)
            {
                $this->_route($route);
            }
        }
    }

    protected function postroute()
    {
        foreach ($this->post_routes as $route)
        {
            if ($this->continue)
            {
                $this->_route($route);
            }
        }
    }

    /**
     * @param string   $to
     * @param uri|null $uri
     *
     * @return string|null
     */
    public function redirect(string $to, uri $uri = null): ?string
    {
        $this->autoroute_path = $to;

        if ($uri)
        {
            $rewrite = rtrim($to, '/').'/'.$this->tail;
            $uri->build($rewrite);
        }

        $buff = $this->autoroute($use_fallbacks = false);
        return $buff;
    }

    public function continue()
    {
        $this->continue = true;
    }

    public function stop()
    {
        $this->continue = false;
    }

    public function tail()
    {
        return $this->tail;
    }
}
