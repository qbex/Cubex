<?php
/**
 * @author  brooke.bryan
 */

namespace Cubex\Log;

use Cubex\Events\EventManager;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;

class Logger implements LoggerInterface
{
  protected $_eventType;
  protected $_logName;

  public function __construct($eventType = EventManager::CUBEX_LOG, $log = null)
  {
    $this->_eventType = $eventType;
    if($log === null)
    {
      $log = CUBEX_TRANSACTION;
    }
    $this->_logName = $log;
  }

  /**
   * System is unusable.
   *
   * @param string $message
   * @param array  $context
   *
   * @return null
   */
  public function emergency($message, array $context = array())
  {
    $this->_log(LogLevel::EMERGENCY, $message, $context);
  }

  /**
   * Action must be taken immediately.
   *
   * Example: Entire website down, database unavailable, etc. This should
   * trigger the SMS alerts and wake you up.
   *
   * @param string $message
   * @param array  $context
   *
   * @return null
   */
  public function alert($message, array $context = array())
  {
    $this->_log(LogLevel::ALERT, $message, $context);
  }

  /**
   * Critical conditions.
   *
   * Example: Application component unavailable, unexpected exception.
   *
   * @param string $message
   * @param array  $context
   *
   * @return null
   */
  public function critical($message, array $context = array())
  {
    $this->_log(LogLevel::CRITICAL, $message, $context);
  }

  /**
   * Runtime errors that do not require immediate action but should typically
   * be logged and monitored.
   *
   * @param string $message
   * @param array  $context
   *
   * @return null
   */
  public function error($message, array $context = array())
  {
    $this->_log(LogLevel::ERROR, $message, $context);
  }

  /**
   * Exceptional occurrences that are not errors.
   *
   * Example: Use of deprecated APIs, poor use of an API, undesirable things
   * that are not necessarily wrong.
   *
   * @param string $message
   * @param array  $context
   *
   * @return null
   */
  public function warning($message, array $context = array())
  {
    $this->_log(LogLevel::WARNING, $message, $context);
  }

  /**
   * Normal but significant events.
   *
   * @param string $message
   * @param array  $context
   *
   * @return null
   */
  public function notice($message, array $context = array())
  {
    $this->_log(LogLevel::NOTICE, $message, $context);
  }

  /**
   * Interesting events.
   *
   * Example: User logs in, SQL logs.
   *
   * @param string $message
   * @param array  $context
   *
   * @return null
   */
  public function info($message, array $context = array())
  {
    $this->_log(LogLevel::INFO, $message, $context);
  }

  /**
   * Detailed debug information.
   *
   * @param string $message
   * @param array  $context
   *
   * @return null
   */
  public function debug($message, array $context = array())
  {
    $this->_log(LogLevel::DEBUG, $message, $context);
  }

  /**
   * Logs with an arbitrary level.
   *
   * @param mixed  $level
   * @param string $message
   * @param array  $context
   *
   * @return null
   */
  public function log($level, $message, array $context = array())
  {
    $this->_log($level, $message, $context);
  }

  public function _log(
    $level, $message, array $context = array(), $file = '',
    $line = 0
  )
  {
    EventManager::trigger(
      $this->_eventType,
      array(
           'level'          => $level,
           'message'        => $message,
           'context'        => $context,
           'file'           => $file,
           'line'           => $line,
           'transaction_id' => CUBEX_TRANSACTION,
           'log_name'       => $this->_logName
      )
    );
  }
}
