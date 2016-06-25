<?php

namespace Sifoni\Provider\HttpCache;

use Symfony\Component\HttpKernel\HttpCache\HttpCache as BaseHttpCache;
use Symfony\Component\HttpFoundation\Request;

class HttpCache extends BaseHttpCache
{
    /**
     * Handles the Request and delivers the Response.
     *
     * @param Request $request The Request object
     */
    public function run(Request $request = null, $return_response = false)
    {
        if (null === $request) {
            $request = Request::createFromGlobals();
        }

        $response = $this->handle($request);

        if ($return_response) {
            return $response;
        }

        $response->send();
        $this->terminate($request, $response);
    }
}
