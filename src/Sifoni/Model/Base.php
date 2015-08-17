<?php

namespace Sifoni\Model;

use Illuminate\Database\Eloquent\Model;
use Sifoni\Engine;

// Enable Eloquent
$app = Engine::getInstance()->getApp();
$app['capsule.eloquent'] = true;
$app['capsule']->bootEloquent();
$app['capsule']->setAsGlobal();
$app['db'] = DB::connection();

/*
 * Base Model extends Eloquent ORM
 */
class Base extends Model {

}
