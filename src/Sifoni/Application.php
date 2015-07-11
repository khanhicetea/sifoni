<?php

namespace Sifoni;

use Silex\Application as SilexApplication;

class Application extends SilexApplication {
    use SilexApplication\TwigTrait;
    use SilexApplication\SecurityTrait;
    use SilexApplication\FormTrait;
    use SilexApplication\UrlGeneratorTrait;
    use SilexApplication\SwiftmailerTrait;
    use SilexApplication\MonologTrait;
    use SilexApplication\TranslationTrait;
}
