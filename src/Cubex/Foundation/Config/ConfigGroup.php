<?php
/**
 * @author Brooke Bryan
 */
namespace Cubex\Foundation\Config;

class ConfigGroup
  implements \Countable, \IteratorAggregate, \ArrayAccess, \JsonSerializable
{
  protected $_count = 0;
  /**
   * @var Config[]
   */
  protected $_configs = array();

  /**
   * @param        $name
   * @param Config $config
   *
   * @return $this
   */
  public function addConfig($name, Config $config)
  {
    $this->_configs[$name] = $config;
    $this->_count++;
    return $this;
  }

  /**
   * Configuration by name exists
   *
   * @param $name
   *
   * @return bool
   */
  public function exists($name)
  {
    return isset($this->_configs[$name]);
  }

  /**
   * @param       $name
   * @param mixed $default
   *
   * @return \Cubex\Foundation\Config\Config|mixed
   */
  public function get($name, $default = null)
  {
    return $this->exists($name) ? $this->_configs[$name] : $default;
  }

  /**
   * @return Config[]
   */
  public function getIterator()
  {
    return new \ArrayIterator($this->_configs);
  }

  public function offsetSet($offset, $value)
  {
    if($offset === null)
    {
      $this->_configs[] = $value;
    }
    else
    {
      $this->_configs[$offset] = $value;
    }
  }

  public function offsetExists($offset)
  {
    return isset($this->_configs[$offset]);
  }

  public function offsetUnset($offset)
  {
    unset($this->_configs[$offset]);
  }

  public function offsetGet($offset)
  {
    return isset($this->_configs[$offset]) ? $this->_configs[$offset] : null;
  }

  /**
   * Count elements of an object
   *
   * @return int
   */
  public function count()
  {
    return $this->_count;
  }

  public static function fromArray(array $array)
  {
    $group      = new ConfigGroup();
    $baseConfig = new Config();
    foreach($array as $configName => $config)
    {
      if($config instanceof \stdClass)
      {
        $config = (array)$config;
      }

      if(is_array($config))
      {
        $cfg = new Config();
        $cfg->hydrate($config);
        $group->addConfig($configName, $cfg);
      }
      else
      {
        $baseConfig->setData($configName, $config);
      }
    }

    if($baseConfig->getData() !== [])
    {
      $group->addConfig('_unassigned_', $baseConfig);
    }

    return $group;
  }

  public function merge(ConfigGroup $replacements, $mergeChildArrays = true)
  {
    foreach($replacements as $name => $config)
    {
      if(!$this->exists($name))
      {
        $this->addConfig($name, $config);
      }
      else
      {
        foreach($config as $item => $value)
        {
          $cfg = $this->get($name);
          if($mergeChildArrays && is_array($value) && $cfg->getExists($item))
          {
            $cfg->setData(
              $item,
              array_merge_recursive($cfg->getArr($item), $value)
            );
          }
          else
          {
            $cfg->setData($item, $value);
          }
        }
      }
    }
    return $this;
  }

  public function jsonSerialize()
  {
    return $this->_configs;
  }
}
