<?php

namespace Sifoni;

use Silex\Application as SilexApplication;

class Application extends SilexApplication {
    public function __construct(array $values = array())
    {
        parent::__construct();
        
        $this['request'] = $this['request_stack']->getCurrentRequest();
    }
}
