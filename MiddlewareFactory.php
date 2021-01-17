<?php
/**
 * Arikaim
 *
 * @link        http://www.arikaim.com
 * @copyright   Copyright (c)  Konstantin Atanasov <info@arikaim.com>
 * @license     http://www.arikaim.com/license
 * 
 */
namespace Arikaim\Core\Routes;

use Arikaim\Core\Utils\Factory;

/**
 * Middleware factory class.
 */
class MiddlewareFactory
{
    /**
     * Object pool
     *
     * @var array
     */
    private static $instances;

    /**
     * Create middleware instance
     *
     * @param string $class
     * @return object
     */
    public static function create(string $class)
    {
        $instance = Self::$instances[$class] ?? null;  
        if (empty($instance) == true) {
            Self::$instances[$class] = Factory::createInstance($class);
        }
       
        return Self::$instances[$class];
    }
}
