<?php namespace Tourane\ProxyKit;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Processor\WebProcessor;
use ProxyManager\Configuration;
use ProxyManager\Factory\AccessInterceptorValueHolderFactory;
use ProxyManager\FileLocator\FileLocator;
use ProxyManager\GeneratorStrategy\FileWriterGeneratorStrategy;

const OPTS_FIELD_LOGGING = "loggingMethods";

class Adapter {
  private $log;
  private $factory;

  public function __construct($opts = array()) {
    // initialize the instance
    $logOpts = array();
    $logChannel = null;
    $logLevel = Logger::INFO;
    $logDir = "/var/log";
    $logFilename = "access.log";
    $logPath = null;
    if (array_key_exists("logging", $opts) && is_array($opts["logging"])) {
      $logOpts = $opts["logging"];

      if (array_key_exists("level", $logOpts) && is_string($logOpts["level"])) {
        $tmpLevel = $this->transformLogLevel($logOpts["level"]);
        if ($tmpLevel != null) {
          $logLevel = $tmpLevel;
        }
      }

      if (array_key_exists("file", $logOpts) && is_array($logOpts["file"])) {
        $fopts = $logOpts["file"];
        if (array_key_exists("dir", $fopts) && is_string($fopts["dir"])) {
          $logDir = $fopts["dir"];
        }
        if (array_key_exists("filename", $fopts) && is_string($fopts["filename"])) {
          $logFilename = $fopts["filename"];
        }
        $logPath = Underscore::PathCombine($logDir, $logFilename, true);
      }

      if (array_key_exists("channel", $logOpts) && is_string($logOpts["channel"])) {
        $logChannel = $logOpts["channel"];
      }
    }

    $msgParts = array("%datetime% - %level_name%");
    if (strlen($logChannel) > 0) {
      array_push($msgParts, "[%channel%]");
    }
    array_push($msgParts, "%message% %context% %extra%\n");
    $messagePattern = join(" ", $msgParts);

    $dateFormat = "Y-m-d\TH:i:s.uP";
    $formatter = new LineFormatter($messagePattern, $dateFormat);

    $this->log = new Logger($logChannel);

    if ($logPath != null && strlen($logPath) > 0) {
      $stream = new StreamHandler($logPath, $logLevel);
      $stream->setFormatter($formatter);
      $this->log->pushHandler($stream);
    }

    if (array_key_exists("extra", $logOpts) && is_array($logOpts["extra"])) {
      $extra = $logOpts["extra"];
      if (!(array_key_exists("Uid", $extra) && $extra["Uid"] == false)) {
        $this->log->pushProcessor(new UidProcessor(24));
      }
      if (array_key_exists("ProcessId", $extra) && $extra["ProcessId"] == true) {
        $this->log->pushProcessor(new ProcessIdProcessor());
      }
      if (array_key_exists("Introspection", $extra) && $extra["Introspection"] == true) {
        $this->log->pushProcessor(new IntrospectionProcessor());
      }
      if (array_key_exists("MemoryUsage", $extra) && $extra["MemoryUsage"] == true) {
        $this->log->pushProcessor(new MemoryUsageProcessor());
      }
      if (!(array_key_exists("Web", $extra) && $extra["Web"] == false)) {
        $this->log->pushProcessor(new WebProcessor());
      }
    }

    $proxyCacheEnabled = false;
    $proxyCacheDir = null;

    if (array_key_exists("caching", $opts) && is_array($opts["caching"])) {
      $cacheOpts = $opts["caching"];
      if (array_key_exists("enabled", $cacheOpts) && is_bool($cacheOpts["enabled"])) {
        $proxyCacheEnabled = $cacheOpts["enabled"];
      }
      if (array_key_exists("dir", $cacheOpts) && is_string($cacheOpts["dir"])) {
        $proxyCacheDir = $cacheOpts["dir"];
      }
    }

    if (strlen($proxyCacheDir) > 0 && is_dir($proxyCacheDir) === false) {
      mkdir($proxyCacheDir, 0755, true);
    }

    if ($proxyCacheEnabled && is_dir($proxyCacheDir)) {
      // create a Configuration
      $config = new Configuration();

      // register a GeneratorStrategy
      $fileLocator = new FileLocator($proxyCacheDir);
      $config->setGeneratorStrategy(new FileWriterGeneratorStrategy($fileLocator));

      // set the directory to read the generated proxies from
      $config->setProxiesTargetDir($proxyCacheDir);

      // then register the autoloader
      spl_autoload_register($config->getProxyAutoloader());
      $this->factory = new AccessInterceptorValueHolderFactory($config);
    } else {
      $this->factory = new AccessInterceptorValueHolderFactory();
    }
  }

  public function getLogger() {
    return $this->log;
  }

  private function transformLogLevel($level) {
    $level = strtoupper($level);
    switch ($level) {
      case "DEBUG":
        return Logger::DEBUG;
      case "INFO":
        return Logger::INFO;
      case "NOTICE":
        return Logger::NOTICE;
      case "WARNING":
        return Logger::WARNING;
      case "ERROR":
        return Logger::ERROR;
      case "CRITICAL":
        return Logger::CRITICAL;
      case "ALERT":
        return Logger::ALERT;
      case "EMERGENCY":
        return Logger::EMERGENCY;
    }
    return null;
  }

  private function getPrefixLogging($opts = array()) {
    $that = $this;
    return function ($proxy, $instance, $method, $params, & $returnEarly) use ($that, $opts) {
      $msg = sprintf("%s.%s +", get_class($instance), $method);
      if (!(array_key_exists("logArguments", $opts) && $opts["logArguments"] == false)) {
        $that->log->info($msg, array("arguments" => $params));
      } else {
        $that->log->info($msg);
      }
    };
  }

  private function getSuffixLogging($opts = array()) {
    $that = $this;
    return function ($proxy, $instance, $method, $params, $returnValue, & $returnEarly) use ($that, $opts) {
      $msg = sprintf("%s.%s -", get_class($instance), $method);
      if (array_key_exists("logReturnValue", $opts) && $opts["logReturnValue"] == true) {
        $that->log->info($msg, array("returnValue" => $returnValue));
      } else {
        $that->log->info($msg);
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
