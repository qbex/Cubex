<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\Routing;

use Cubex\Foundation\DataHandler\HandlerInterface;
use Cubex\Foundation\DataHandler\HandlerTrait;

class StdRoute implements Route, HandlerInterface
{
  use HandlerTrait;

  protected $_pattern;
  protected $_result;
  protected $_verbs;
  /**
   * @var Route[]
   */
  protected $_subRoutes;

  /**
   * @param $pattern
   * @param $result
   * @param $verbs
   */
  public function __construct($pattern, $result, array $verbs = ['ANY'])
  {
    $this->_pattern   = $pattern;
    $this->_result    = $result;
    $this->_subRoutes = array();
    $this->setVerbs($verbs);
  }

  /**
   * Get Pattern
   *
   * @return string
   */
  public function pattern()
  {
    return $this->_pattern;
  }

  /**
   * @param string $pattern
   *
   * @return static
   */
  public function setPattern($pattern)
  {
    $this->_pattern = $pattern;
  }

  /**
   * Route Match Result
   *
   * @return mixed
   */
  public function result()
  {
    return $this->_result;
  }

  /**
   * @param $result
   *
   * @return $this
   */
  public function setResult($result)
  {
    $this->_result = $result;
    return $this;
  }

  /**
   * Route Match HTTP Verb
   *
   * @return mixed
   */
  public function verbs()
  {
    return strtoupper($this->_verbs);
  }

  /**
   * Set matching HTTP Verb
   *
   * @param $verbs
   *
   * @return $this
   */
  public function setVerbs(array $verbs = ['ANY'])
  {
    $this->_verbs = array();
    foreach($verbs as $verb)
    {
      $this->_verbs[strtoupper($verb)] = true;
    }
    return $this;
  }

  public function addVerb($verb)
  {
    $this->_verbs[strtoupper($verb)] = true;
    return $this;
  }

  public function excludeVerb($verb)
  {
    $this->_verbs[strtoupper($verb)] = false;
    return $this;
  }

  /**
   * @param $verb
   *
   * @return bool|mixed
   */
  public function matchesVerb($verb)
  {
    if(isset($this->_verbs['ANY']) && $this->_verbs['ANY'])
    {
      return true;
    }

    $verb = strtoupper($verb);
    if(isset($this->_verbs[$verb]) && $this->_verbs[$verb])
    {
      return true;
    }

    return false;
  }

  /**
   * @return Route[]
   */
  public function subRoutes()
  {
    return $this->_subRoutes;
  }

  /**
   * @param Route $route
   *
   * @return static
   */
  public function addSubRoute(Route $route)
  {
    $this->_subRoutes[] = $route;
  }

  /**
   * @return bool
   */
  public function hasSubRoutes()
  {
    return !empty($this->_subRoutes);
  }

  /**
   * @param array $routes
   *
   * @return Route[]
   */
  public static function fromArray(array $routes)
  {
    $finalRoutes = array();

    foreach($routes as $pattern => $result)
    {
      if(is_array($result))
      {
        $route = new StdRoute($pattern, null);
        foreach($result as $subPattern => $subResult)
        {
          if($subPattern == '')
          {
            $route->setResult($subResult);
          }
          else if(is_array($subResult))
          {
            $subRoutes = static::fromArray($subResult);
            foreach($subRoutes as $subRoute)
            {
              $route->addSubRoute($subRoute);
            }
          }
          else
          {
            $subRoute = new StdRoute($subPattern, $subResult);
            $route->addSubRoute($subRoute);
          }
        }
        $finalRoutes[] = $route;
      }
      else
      {
        $finalRoutes[] = new StdRoute($pattern, $result);
      }
    }
    return $finalRoutes;
  }

  /**
   * Array of data generated by route matching
   *
   * @return array
   */
  public function routeData()
  {
    return $this->getData();
  }

  /**
   * @param $key
   * @param $value
   *
   * @return $this
   */
  public function addRouteData($key, $value)
  {
    $this->setData($key, $value);
    return $this;
  }
}
