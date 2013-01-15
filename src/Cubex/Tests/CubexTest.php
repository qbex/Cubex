<?php
/**
 * @author: gareth.evans
 */
namespace Cubex\Tests;

use Cubex\Loader;

class CubexTest extends TestCase
{
  public function testCubexHasLoaded()
  {
    $this->assertTrue(
      class_exists('Cubex\Loader'),
      "Cubex has not been loaded"
    );
  }

  public function testCubexHasInstantiated()
  {
    $cubex = Transport::$data[Transport::CUBEX];
    $this->assertInstanceOf('Cubex\Loader', $cubex);

    return $cubex;
  }

  /**
   * @depends testCubexHasInstantiated
   * @param $cubex \Cubex\Loader
   */
  public function testCubexConfigurationExists($cubex)
  {
    $configuration = $cubex->getConfig();
    $this->assertInstanceOf(
      'Cubex\Foundation\Config\ConfigGroup',
      $configuration
    );
  }
}
