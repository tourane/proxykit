<?php namespace Tourane\ProxyKit;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\WebProcessor;
use ProxyManager\Factory\AccessInterceptorValueHolderFactory as Factory;
use ProxyManager\Factory\LazyLoadingValueHolderFactory;
use ProxyManager\Proxy\LazyLoadingInterface;

const OPTS_FIELD_LOGGING = "loggingMethods";

class Adapter {
  private $log;
  private $logDir = "/var/log";
  private $logFilename = "access.log";
  private $logLabel = "";
  private $factory;

  public function __construct($opts = array()) {
    // initialize the instance
    if (array_key_exists("logDir", $opts) && is_string($opts["logDir"])) {
      $this->logDir = $opts["logDir"];
    }
    if (array_key_exists("logFilename", $opts) && is_string($opts["logFilename"])) {
      $this->logFilename = $opts["logFilename"];
    }
    if (array_key_exists("logLabel", $opts) && is_string($opts["logLabel"])) {
      $this->logLabel = $opts["logLabel"];
    }
    $logPath = Underscore::PathCombine($this->logDir, $this->logFilename, true);

    $msgParts = array("%datetime% - %level_name%");
    if (strlen($this->logLabel) > 0) {
      array_push($msgParts, "[%channel%]");
    }
    array_push($msgParts, "%message% %context% %extra%\n");
    $messagePattern = join(" ", $msgParts);

    $dateFormat = "Y-m-d\TH:i:s.uP";
    $formatter = new LineFormatter($messagePattern, $dateFormat);
    $stream = new StreamHandler($logPath, Logger::DEBUG);
    $stream->setFormatter($formatter);

    $this->log = new Logger($this->logLabel);
    $this->log->pushHandler($stream);
    if (!(array_key_exists("extraUID", $opts) && $opts["extraUID"] == false)) {
      $this->log->pushProcessor(new UidProcessor(24));
    }
    if (array_key_exists("extraProcessId", $opts) && $opts["extraProcessId"] == true) {
      $this->log->pushProcessor(new ProcessIdProcessor());
    }
    if (array_key_exists("extraIntrospection", $opts) && $opts["extraIntrospection"] == true) {
      $this->log->pushProcessor(new IntrospectionProcessor());
    }
    if (array_key_exists("extraMemoryUsage", $opts) && $opts["extraMemoryUsage"] == true) {
      $this->log->pushProcessor(new MemoryUsageProcessor());
    }
    if (!(array_key_exists("extraWeb", $opts) && $opts["extraWeb"] == false)) {
      $this->log->pushProcessor(new WebProcessor());
    }
    $this->factory = new Factory();
  }

  public function getLogger() {
    return $this->log;
  }

  private function getPrefixLogging($opts = array()) {
    $that = $this;
    return function ($proxy, $instance, $method, $params, & $returnEarly) use ($that, $opts) {
      $msg = sprintf("%s.%s +", get_class($instance), $method);
      if (!(array_key_exists("logArguments", $opts) && $opts["logArguments"] == false)) {
        $that->log->debug($msg, array("arguments" => $params));
      } else {
        $that->log->debug($msg);
      }
    };
  }

  private function getSuffixLogging($opts = array()) {
    $that = $this;
    return function ($proxy, $instance, $method, $params, $returnValue, & $returnEarly) use ($that, $opts) {
      $msg = sprintf("%s.%s -", get_class($instance), $method);
      if (array_key_exists("logReturnValue", $opts) && $opts["logReturnValue"] == true) {
        $that->log->debug($msg, array("returnValue" => $returnValue));
      } else {
        $that->log->debug($msg);
      }
    };
  }

  public function wrap($target, $opts = array()) {
    $wrapper = $this->factory->createProxy($target);
    if (array_key_exists(OPTS_FIELD_LOGGING, $opts)) {
      $methods = $opts[OPTS_FIELD_LOGGING];
      if (is_array($methods)) {
        if (Underscore::is_sequential_array($methods)) {
          foreach ($methods as $methodName) {
            $wrapper->setMethodPrefixInterceptor($methodName, $this->getPrefixLogging());
            $wrapper->setMethodSuffixInterceptor($methodName, $this->getSuffixLogging());
          }
        } else {
          foreach ($methods as $methodName => $methodOpts) {
            $privOpts = array();
            if (is_array($methodOpts)) {
              $privOpts = $methodOpts;
            }
            $wrapper->setMethodPrefixInterceptor($methodName, $this->getPrefixLogging($privOpts));
            $wrapper->setMethodSuffixInterceptor($methodName, $this->getSuffixLogging($privOpts));
          }
        }
      }
    }
    return $wrapper;
  }
}
?>
