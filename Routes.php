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

use Arikaim\Core\Routes\RoutesStorageInterface;
use Arikaim\Core\Interfaces\RoutesInterface;
use Arikaim\Core\Interfaces\CacheInterface;
use Arikaim\Core\Routes\Route;
use Arikaim\Core\Routes\RouteType;
use Exception;

/**
 * Routes storage
*/
class Routes implements RoutesInterface
{
    /**
     *  Route type constant
     */
    const PAGE      = 1;
    const API       = 2;
    const HOME_PAGE = 3;

    /**
     * Cache save time
     *
     * @var integer
     */
    public static $cacheSaveTime = 4;

    /**
     * Routes storage adapter
     *
     * @var RoutesStorageInterface
     */
    protected $adapter;

    /**
     * Cache
     *
     * @var CacheInterface
     */
    protected $cache;

    /**
     * Constructor
     * 
     * @param RoutesStorageInterface $adapter 
     * @param CacheInterface $cache
     */
    public function __construct(RoutesStorageInterface $adapter, CacheInterface $cache)
    {
        $this->adapter = $adapter;    
        $this->cache = $cache;

        Self::$cacheSaveTime = \defined('CACHE_SAVE_TIME') ? \constant('CACHE_SAVE_TIME') : Self::$cacheSaveTime;
    }

    /**
     * Resolve middlewares
     *
     * @param string $handlerClass
     * @return array|null
     */
    protected function resolveMiddlewares(string $handlerClass): ?array
    {
        if (\class_exists($handlerClass) == false) {
            return null;
        }

        $controller = new $handlerClass();
        $middlewares = $controller->getMiddlewares();

        return (count($middlewares) > 0) ? $middlewares : null;
    }

    /**
     * Set routes status
     *
     * @param array     $filterfilter
     * @param integer   $status
     * @return boolean
     */
    public function setRoutesStatus(array $filter = [], int $status): bool
    {
        return $this->adapter->setRoutesStatus($filter,$status);
    }

    /**
     * Save route redirect url
     *
     * @param string $method
     * @param string $pattern
     * @param string $url
     * @return boolean
     */
    public function setRedirectUrl(string $method, string $pattern, string $url): bool
    {
        return $this->adapter->saveRedirectUrl($method,$pattern,$url);
    }

    /**
     * Add template route
     *
     * @param string $pattern
     * @param string $handlerClass
     * @param string|null $handlerMethod
     * @param string $templateName
     * @param string|null $pageName
     * @param integer|null $auth
     * @param boolean $replace 
     * @param string|null $redirectUrl 
     * @param integer $type 
     * @param boolean $withLanguage
     * @return bool
     */
    public function saveTemplateRoute(
        string $pattern, 
        string $handlerClass, 
        ?string $handlerMethod, 
        string $templateName, 
        ?string $pageName, 
        ?string $auth = null, 
        bool $replace = false, 
        ?string $redirectUrl = null,
        int $type = Self::PAGE, 
        bool $withLanguage = true
    ): bool
    {
        $handlerMethod = ($handlerMethod == null) ? 'pageLoad' : $handlerMethod;
        $languagePattern = Route::getLanguagePattern($pattern);
        
        if ($replace == true) {           
            $this->delete('GET',$pattern);
            $this->delete('GET',$pattern . $languagePattern);
            if ($type == Self::HOME_PAGE) {
                $this->deleteHomePage();
            }           
        }

        if ($this->has('GET',$pattern) == true) {
            return false;
        }

        if (Route::isValidPattern($pattern) == false) {           
            return false;
        }

        $pattern = ($withLanguage == true) ? $pattern . $languagePattern : $pattern;

        // check if exist with language pattern
        if ($this->has('GET',$pattern) == true) {
            return false;
        }
       
        $middlewares = $this->resolveMiddlewares($handlerClass);

        $route = [
            'method'         => 'GET',
            'pattern'        => $pattern,
            'handler_class'  => $handlerClass,
            'handler_method' => $handlerMethod,
            'auth'           => $auth,
            'type'           => $type,
            'page_name'      => $pageName,
            'template_name'  => $templateName,
            'redirect_url'   => $redirectUrl,
            'middlewares'    => (empty($middlewares) == false) ? \json_encode($middlewares) : null
        ];
        
        $this->cache->delete('routes.list');

        if (Route::validate('GET',$pattern,$this->getAllRoutes()) == false) {
            return false;
        }
     
        return $this->adapter->addRoute($route);
    }

    /**
     * Add home page route
     *
     * @param string $pattern
     * @param string $handlerClass
     * @param string $handlerMethod
     * @param string $extension
     * @param string $pageName
     * @param integer $auth  
     * @param string|null $name
     * @param boolean $withLanguage
     * @return bool
     */
    public function addHomePageRoute($pattern, $handlerClass, $handlerMethod, $extension, $pageName, $auth = null, $name = null, $withLanguage = true)
    {
        return $this->addPageRoute($pattern,$handlerClass,$handlerMethod,$extension,$pageName,$auth,$name,$withLanguage,Self::HOME_PAGE);
    }

    /**
     * Add page route
     *
     * @param string $pattern
     * @param string $handlerClass
     * @param string $handlerMethod
     * @param string $extension
     * @param string $pageName
     * @param integer $auth  
     * @param string|null $name
     * @param boolean $withLanguage
     * @param integer $type
     * @return bool
     */
    public function addPageRoute($pattern, $handlerClass, $handlerMethod, $extension, $pageName, $auth = null, $name = null, $withLanguage = true, $type = Self::PAGE)
    {
        if (Route::isValidPattern($pattern) == false) {           
            return false;
        }

        $languagePattern = Route::getLanguagePattern($pattern);
        // check if exist with language pattern
        if ($this->has('GET',$pattern . $languagePattern) == true) {
            return false;
        }
        if ($this->has('GET',$pattern) == true) {
            return false;
        }

        $pattern = ($withLanguage == true) ? $pattern . $languagePattern : $pattern;

        $middlewares = $this->resolveMiddlewares($handlerClass);
        $route = [
            'method'         => 'GET',
            'pattern'        => $pattern,
            'handler_class'  => $handlerClass,
            'handler_method' => $handlerMethod,
            'auth'           => $auth,
            'type'           => $type,
            'extension_name' => $extension,
            'page_name'      => $pageName,
            'name'           => $name,
            'regex'          => null,
            'middlewares'    => (empty($middlewares) == false) ? \json_encode($middlewares) : null
        ];

        $this->cache->delete('routes.list');
        
        if (Route::validate('GET',$pattern,$this->getAllRoutes()) == false) {
            return false;
        }

        return $this->adapter->addRoute($route);    
    }

    /**
     * Get language pattern
     *
     * @param string $pattern
     * @return string
     */
    public function getLanguagePattern(string $pattern): string
    {
        return Route::getLanguagePattern($pattern);
    }

    /**
     * Add api route
     *
     * @param string $method
     * @param string $pattern
     * @param string $handlerClass
     * @param string|null $handlerMethod
     * @param string|null $extension
     * @param integer|null $auth
     * @return bool
     * @throws Exception
     */
    public function addApiRoute(
        string $method,
        string $pattern, 
        string $handlerClass, 
        ?string $handlerMethod, 
        ?string $extension, 
        ?string $auth = null
    ): bool
    {
        if (Route::isValidPattern($pattern) == false) {           
            return false;
        }      
        if (RouteType::isValidApiRoutePattern($pattern) == false) {
            throw new Exception('Not valid api route pattern.',1);
            return false;
        }

        $middlewares = $this->resolveMiddlewares($handlerClass);

        $route = [
            'method'         => $method,
            'pattern'        => $pattern,
            'handler_class'  => $handlerClass,
            'handler_method' => $handlerMethod,
            'auth'           => $auth,
            'type'           => Self::API,
            'regex'          => null,
            'extension_name' => $extension,
            'middlewares'    => (empty($middlewares) == false) ? \json_encode($middlewares) : null
        ];
        
        $this->cache->delete('routes.list');

        if (Route::validate($method,$pattern,$this->getAllRoutes()) == false) {
            return false;
        }

        return $this->adapter->addRoute($route);    
    }

    /**
     * Return true if reoute exists
     *
     * @param string $method
     * @param string $pattern
     * @return boolean
     */
    public function has(string $method, string $pattern): bool
    {
        return $this->adapter->hasRoute($method,$pattern);
    }

    /**
     * Delete route
     *
     * @param string $method
     * @param string $pattern
     * @return bool
     */
    public function delete(string $method, string $pattern): bool
    {
        return $this->adapter->deleteRoute($method,$pattern);
    }

    /**
     * Save route options
     *
     * @param string $method
     * @param string $pattern
     * @param array $options
     * @return boolean
     */
    public function saveRouteOptions(string $method, string $pattern, $options): bool
    {
        return $this->adapter->saveRouteOptions($method,$pattern,$options);
    }

    /**
     * Delete home page route
     *
     * @return boolean
     */
    public function deleteHomePage(): bool
    {
        return $this->adapter->deleteRoutes(['type' => Self::HOME_PAGE]);
    }

    /**
     * Delete routes
     *
     * @param array $filterfilter
     * @return boolean
     */
    public function deleteRoutes($filter = []): bool
    {
        return $this->adapter->deleteRoutes($filter);
    }

    /**
     * Get route
     *
     * @param string $method
     * @param string $pattern
     * @return array|false
    */
    public function getRoute(string $method, string $pattern)
    {
        return $this->adapter->getRoute($method,$pattern);
    }

    /**
     * Get routes
     *
     * @param array $filter  
     * @return array
     */
    public function getRoutes(array $filter = [])
    {
        return $this->adapter->getRoutes($filter);
    }

    /**
     * Get all actve routes from storage
     *
     * @return array
     */
    public function getAllRoutes()
    {
        $routes = $this->cache->fetch('routes.list');  
        if (\is_array($routes) == false) {
            $routes = $this->getRoutes(['status' => 1]);  
            $this->cache->save('routes.list',$routes,Self::$cacheSaveTime);         
        }

        return $routes;
    }

    /**
     * Get routes list for request method
     *
     * @param string $method
     * @param int|null $type
     * @return array
     */
    public function searchRoutes(string $method, $type = null)
    {
        $cacheItemkey = 'routes.list.' . $method . '.' . $type ?? 'all';
        $routes = $this->cache->fetch($cacheItemkey);  
        if (\is_array($routes) == false) {
            $routes = $this->adapter->searchRoutes($method,$type);
            $this->cache->save($cacheItemkey,$routes,Self::$cacheSaveTime);   
        }
        
        return $routes;
    }

    /**
     * Get home page route
     *
     * @return array
     */
    public function getHomePageRoute(): array
    {
        return $this->adapter->getHomePageRoute();
    }
}
