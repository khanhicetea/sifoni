<?php

namespace Sifoni\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Csrf\CsrfToken;
use Sifoni\Engine;

abstract class Base
{
    protected $app = null;
    protected $request = null;
    protected $response = null;

    public function __construct()
    {
        $this->app = $app = Engine::getInstance()->getApp();
        $this->request = $app['request'];
        $this->response = new Response(null, Response::HTTP_OK, []);
    }

    public function setTtl($time)
    {
        $this->response->setTtl($time);
    }

    public function setCache($time)
    {
        $this->response->setMaxAge($time);
    }

    public function setSharedCache($time)
    {
        $this->response->setSharedMaxAge($time);
    }

    public function render($view, array $parameters = [])
    {
        return $this->app->render($view, $parameters, $this->response);
    }

    public function json($data = [], $status = Response::HTTP_OK, array $headers = [])
    {
        return $this->app->json($data, $status, $headers);
    }

    public function redirect($named_route, $params = [])
    {
        return $this->app->redirect($this->app->url($named_route, $params));
    }

    public function addFlashMessage($flash_type, $flash_content)
    {
        $this->app['session']->getFlashBag()->add('message', [
            'type' => $flash_type,
            'content' => $flash_content,
        ]);
    }

    public function redirectWithFlash($named_route, $params = [], $flash_type, $flash_content)
    {
        $this->addFlashMessage($flash_type, $flash_content);

        return $this->app->redirect($this->app->url($named_route, $params));
    }

    public function getPostData($field = null, $default = null)
    {
        return empty($field) ? $this->request->request->all() : $this->request->request->get($field, $default);
    }

    public function getQueryParam($field = null, $default = null)
    {
        return empty($field) ? $this->request->query->all() : $this->request->query->get($field, $default);
    }

    public function isFormValid($action = '', $token_name = '_token')
    {
        $token = new CsrfToken($action, $this->request->get($token_name));

        return $this->app['csrf.token_manager']->isTokenValid($token);
    }
}
