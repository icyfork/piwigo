<?php
class PiwigoIcy {
  static $instance;

  // true if the user accesses as an administrsator (Linux sudo inspired)
  public $access_as_administrastor = false;

  // Create a Singleton class. Thanks to pcdinh.
  private function __contruct()
  {
    // do nothing
  }

  // Get the singleton object
  public function getInstance()
  {
    if (self::$instance === null)
    {
      self::$instance = new PiwigoIcy();
    }
    return self::$instance;
  }
}
?>
