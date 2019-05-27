<?php
/*
  composer install
  php examples/logging.php
*/
require_once __DIR__ . '/../vendor/autoload.php';

use Tourane\ProxyKit\Adapter as Adapter;

class MongoClient {
  public function find($query, $opts) {
    $items = array( 1, 3, 5, 7, 11);
    return array(
      "items" => $items,
      "total" => count($items)
    );
  }
  public function close($opts) {
    return true;
  }
}

$db = new MongoClient();

$adapter = new Adapter(array(
  "logDir" => dirname(__FILE__ ) . '/log',
  "logFilename" => "access.log",
  "logLabel" => "example-01",
  "extraProcessId" => true
));

$db = $adapter->wrap($db, array(
  "loggingMethods" => array(
    "find" => array(),
    "close" => array(
      "logArguments" => true,
      "logReturnValue" => true
    )
  )
));

$result = $db->find(array("type" => "prime", "max" => 15), null);
printf("find: %s\n", json_encode($result));

$db->close(array());
?>
