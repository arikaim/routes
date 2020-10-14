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

use Exception;

/**
 * Parses route strings:
 * 
 * "/user/{name}[/{id:[0-9]+}]"
 */
class RouteParser 
{

    const VARIABLE_REGEX = <<<'REGEX'
\{
    \s* ([a-zA-Z_][a-zA-Z0-9_-]*) \s*
    (?:
        : \s* ([^{}]*(?:\{(?-1)\}[^{}]*)*)
    )?
\}
REGEX;

    const DEFAULT_DISPATCH_REGEX = '[^/]+';

    /**
     * Parse route
     *
     * @param string $route
     * @return array
     */
    public static function parse($route)
    {
        $routeWithoutClosingOptionals = \rtrim($route, ']');
        $numOptionals = \strlen($route) - \strlen($routeWithoutClosingOptionals);

        // Split on [ while skipping placeholders
        $segments = \preg_split('~' . Self::VARIABLE_REGEX . '(*SKIP)(*F) | \[~x', $routeWithoutClosingOptionals);
        if ($numOptionals !== count($segments) - 1) {
            // If there are any ] in the middle of the route, throw a more specific error message
            if (\preg_match('~' . Self::VARIABLE_REGEX . '(*SKIP)(*F) | \]~x', $routeWithoutClosingOptionals)) {
                throw new Exception('Optional segments can only occur at the end of a route');
            }
            throw new Exception("Number of opening '[' and closing ']' does not match");
        }

        $currentRoute = '';
        $routeDatas = [];
        foreach ($segments as $n => $segment) {
            if ($segment === '' && $n !== 0) {
                throw new Exception('Empty optional part');
            }

            $currentRoute .= $segment;
            $routeDatas[] = Self::parsePlaceholders($currentRoute);
        }
        
        return $routeDatas;
    }

    /**
     * Parses a route string that does not contain optional segments.
     *
     * @param string
     * @return array
     */
    public static function parsePlaceholders($route)
    {
        if (!preg_match_all(
            '~' . self::VARIABLE_REGEX . '~x', $route, $matches,
            PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        )) {
            return [$route];
        }

        $offset = 0;
        $routeData = [];
        foreach ($matches as $set) {
            if ($set[0][1] > $offset) {
                $routeData[] = \substr($route, $offset, $set[0][1] - $offset);
            }
            $routeData[] = [
                $set[1][0],
                isset($set[2]) ? \trim($set[2][0]) : Self::DEFAULT_DISPATCH_REGEX
            ];
            $offset = $set[0][1] + \strlen($set[0][0]);
        }

        if ($offset !== \strlen($route)) {
            $routeData[] = \substr($route, $offset);
        }

        return $routeData;
    }

    /**
     * Create route regex
     *
     * @param array $routeData
     * @return string
     */
    public static function createRegex($routeData)
    {
        if (Self::isStaticRoute($routeData) == true) {
            return $routeData[0];
        }

        list($regex,$vars) = Self::buildRegexForRoute($routeData);

        return $regex;
    }

    /**
     * Create route regex
     * 
     * @param array
     * @return array
     */
    public static function buildRegexForRoute($routeData)
    {
        $regex = '';
        $variables = [];
        foreach ($routeData as $part) {
            if (\is_string($part)) {
                $regex .= \preg_quote($part, '~');
                continue;
            }

            list($varName, $regexPart) = $part;

            if (isset($variables[$varName])) {
                continue;
            }

            if (Self::regexHasCapturingGroups($regexPart)) {
                continue;
            }

            $variables[$varName] = $varName;
            $regex .= '(' . $regexPart . ')';
        }

        return [$regex, $variables];
    }

    /**
     * Test for capturing groups
     * @param string
     * @return bool
     */
    public static function regexHasCapturingGroups($regex)
    {
        if (false === \strpos($regex,'(')) {
            return false;
        }

        return (bool)preg_match(
            '~
                (?:
                    \(\?\(
                  | \[ [^\]\\\\]* (?: \\\\ . [^\]\\\\]* )* \]
                  | \\\\ .
                ) (*SKIP)(*FAIL) |
                \(
                (?!
                    \? (?! <(?![!=]) | P< | \' )
                  | \*
                )
            ~x',
            $regex
        );
    }

    /**
     * 
     * Return true if route is static
     * @param array
     * @return bool
     */
    public static function isStaticRoute($routeData)
    {
        return (count($routeData) === 1 && \is_string($routeData[0]));
    }
}
