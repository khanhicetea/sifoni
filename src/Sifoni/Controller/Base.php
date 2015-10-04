<?php

namespace Sifoni\Controller;

use Symfony\Component\HttpFoundation\Response;
use Sifoni\Engine;
use Symfony\Component\Security\Csrf\CsrfToken;

class Base {
    protected $app = null;
    protected $request = null;
    protected $response = null;

    public function __construct() {
        $this->app = $app = Engine::getInstance()->getApp();
        $this->request = $app['request'];
        $this->response = new Response(null, Response::HTTP_OK, array());
    }

    public function setTtl($time) {
        $this->response->setTtl($time);
    }

    public function setCache($time) {
        $this->response->setMaxAge($time);
    }

    public function setSharedCache($time) {
        $this->response->setSharedMaxAge($time);
    }

    public function render($view, array $parameters = array()) {
        return $this->app->render($view, $parameters, $this->response);
    }

    public function json($data = array(), $status = Response::HTTP_OK, array $headers = array()) {
        return $this->app->json($data, $status, $headers);
    }

    public function redirect($named_route, $params = array()) {
        return $this->app->redirect($this->app->url($named_route, $params));
    }
    
    public function addFlashMessage($flash_type, $flash_content) {
        $this->app['session']->getFlashBag()->add('message', array(
            'type' => $flash_type,
            'content' => $flash_content
        ));
    }

    public function redirectWithFlash($named_route, $params = array(), $flash_type, $flash_content) {
        $this->addFlashMessage($flash_type, $flash_content);

        return $this->app->redirect($this->app->url($named_route, $params));
    }

    public function getPostData($field = null, $default = null) {
        return empty($field) ? $this->request->request->all() : $this->request->request->get($field, $default);
    }

    public function getQueryParam($field = null, $default = null) {
        return empty($field) ? $this->request->query->all() : $this->request->query->get($field, $default);
    }

    public function isFormValid($action = '', $token_name = '_token') {
        $token = new CsrfToken($action, $this->request->get($token_name));
        return $this->app['form.csrf_provider']->isTokenValid($token);
    }
}
