<?php

namespace Sifoni;

use Silex\Application as SilexApplication;

class Application extends SilexApplication
{
    use SilexApplication\TwigTrait;
    use SilexApplication\UrlGeneratorTrait;
    use SilexApplication\MonologTrait;
    use SilexApplication\TranslationTrait;
}
