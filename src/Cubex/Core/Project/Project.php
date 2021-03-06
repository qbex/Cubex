<?php
/**
 * @author  brooke.bryan
 */
namespace Cubex\Core\Project;

use Cubex\Bundle\BundlerTrait;
use Cubex\Core\Application\Application;
use Cubex\Events\EventManager;
use Cubex\Foundation\Config\ConfigTrait;
use Cubex\Core\Http\IDispatchable;
use Cubex\Core\Http\IDispatchableAccess;
use Cubex\Core\Http\Request;
use Cubex\Core\Http\Response;
use Cubex\ServiceManager\IServiceManagerAware;
use Cubex\ServiceManager\ServiceManagerAwareTrait;

/**
 * Project Dispatchable
 *
 * Handles dispatching a request to the relevant application
 *
 */
abstract class Project
  implements IDispatchable, IDispatchableAccess, IServiceManagerAware
{
  use ServiceManagerAwareTrait;
  use ConfigTrait;
  use BundlerTrait;

  /**
   * Project Name
   *
   * @return string
   */
  abstract public function name();

  /**
   * @return \Cubex\Core\Application\Application
   */
  abstract public function defaultApplication();

  /**
   * @var \Cubex\Core\Http\Request
   */
  protected $_request;

  /**
   * @var \Cubex\Core\Http\Response
   */
  protected $_response;

  /**
   * @param \Cubex\Core\Http\Request $req
   *
   * @return \Cubex\Core\Application\Application
   * @throws \Exception
   */
  public function getApplication(Request $req)
  {
    $app = $this->getBySubDomainAndPath($req->subDomain(), $req->path());

    if($app === null)
    {
      $app = $this->getBySubDomain($req->subDomain());
    }

    if($app === null)
    {
      $app = $this->getByPath($req->path());
    }

    if($app === null)
    {
      $app = $this->defaultApplication();
    }

    if($app !== null && $app instanceof Application)
    {
      return $app;
    }
    else
    {
      throw new \Exception("No application could be located");
    }
  }

  /**
   * Get Application based on sub domain and path
   *
   * @param $subdomain
   * @param $path
   *
   * @return \Cubex\Core\Application\Application|null
   */
  public function getBySubDomainAndPath($subdomain, $path)
  {
    return null;
  }

  /**
   * Get Applcation based on sub domain only
   *
   * @param $subdomain
   *
   * @return \Cubex\Core\Application\Application|null
   */
  public function getBySubDomain($subdomain)
  {
    return null;
  }

  /**
   * Get Application based on path only
   *
   * @param $path
   *
   * @return \Cubex\Core\Application\Application|null
   */
  public function getByPath($path)
  {
    return null;
  }

  /**
   * @param \Cubex\Core\Http\Request  $request
   * @param \Cubex\Core\Http\Response $response
   *
   * @return \Cubex\Core\Http\Response
   * @throws \RuntimeException
   */
  public function dispatch(Request $request, Response $response)
  {
    $this->_request  = $request;
    $this->_response = $response;

    EventManager::trigger(
      EventManager::CUBEX_TIMETRACK_START,
      [
      'name'  => 'project.dispatch',
      'label' => "Dispatch Project"
      ]
    );

    $this->prepareProject();

    $app = $this->getApplication($request);
    $app->setServiceManager($this->getServiceManager());
    $app->setProject($this);
    $app->init();

    if($this->_configuration === null)
    {
      throw new \RuntimeException("No configuration has been set");
    }

    $app->configure($this->_configuration);

    $return = $app->dispatch($request, $response);

    $this->shutdownBundles();

    EventManager::trigger(
      EventManager::CUBEX_TIMETRACK_END,
      [
      'name' => 'project.dispatch'
      ]
    );

    return $return;
  }

  public function prepareProject($isCli = false)
  {
    $this->init();
    if($isCli)
    {
      $this->initCli();
    }
    else
    {
      $this->initWeb();
    }
    $this->_configure();
    $this->addDefaultBundles();
    $this->initialiseBundles();
    EventManager::trigger(EventManager::CUBEX_PROJECT_PREPARE);
    return $this;
  }

  /**
   * @return \Cubex\Core\Http\Request
   */
  public function request()
  {
    return $this->_request;
  }

  /**
   * @return \Cubex\Core\Http\Response
   */
  public function response()
  {
    return $this->_response;
  }

  /**
   * Initialise Project
   */
  public function init()
  {
    return $this;
  }

  /**
   * Initialise For Web Only
   */
  public function initWeb()
  {
    return $this;
  }

  /**
   * Initialise For CLI Only
   */
  public function initCli()
  {
    return $this;
  }

  protected function _configure()
  {
    return $this;
  }
}
