<?php


namespace OnlineCity\GatewayAPI;

use OnlineCity\GatewayAPI\Trigger;
use OnlineCity\GatewayAPI\Notification;


class TriggerStore
{


  /**
   * Our single TriggerStore client instance.
   *
   * @var TriggerStore
   */
  private static $instance;

  private static $listening = false;

  /**
   * Disable instantiation.
   */
  private function __construct()
  {
  }

  public static function listen()
  {
    $store = static::getInstance();
    if (self::$listening) return;
    self::initialize();
  }

  /**
   * Create or retrieve the instance of our instance
   *
   * @return TriggerStore
   */
  public static function getInstance()
  {
    if (is_null(static::$instance)) {
      static::$instance = new TriggerStore();
    }

    return static::$instance;
  }

  protected static function initialize()
  {
    self::$listening = true;
    $notifications = gwapi_notification_get_notifications();

    foreach ($notifications as $notification_post) {
      $notification = new Notification($notification_post);
      $notification->registerAction();
    }
  }

  /**
   * Disable the cloning of this class.
   *
   * @return void
   */
  final public function __clone()
  {
    throw new \RuntimeException('Feature disabled.');
  }

  /**
   * Disable the wakeup of this class.
   *
   * @return void
   */
  final public function __wakeup()
  {
    throw new \RuntimeException('Feature disabled.');
  }
}
