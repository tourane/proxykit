<?php namespace Tourane\ProxyKit;

class Underscore {
  public static function labelify($label) {
    if (!is_string($label)) return $label;
    return preg_replace('/\W{1,}/i', "_", strtoupper($label));
  }

  public static function is_associative_array($array) {
    return !self::is_sequential_array($array);
  }

  public static function is_sequential_array($array) {
    if (!is_array($array)) return False;
    foreach(array_keys($array) as $index) {
      if (is_string($index)) return False;
      break;
    }
    return True;
  }

  public static function stringToArray($str) {
    if (is_string($str)) {
      $arr = preg_split("/[,]/", $str);
      $arr = array_map(function ($item) {
        return trim($item);
      }, $arr);
      $arr = array_filter($arr, function($item) {
        return strlen($item) > 0;
      });
      return $arr;
    }
    return array();
  }

  public static function PathCombine($base, $subpath, $normalize = true) {
    # normalize
    if($normalize) {
      $base = str_replace('/', DIRECTORY_SEPARATOR, $base);
      $base = str_replace('\\', DIRECTORY_SEPARATOR, $base);
      $subpath = str_replace('/', DIRECTORY_SEPARATOR, $subpath);
      $subpath = str_replace('\\', DIRECTORY_SEPARATOR, $subpath);
    }

    # remove leading/trailing dir separators
    if(!empty($base) && substr($base, -1) == DIRECTORY_SEPARATOR) {
      $base = substr($base, 0, -1);
    }
    if(!empty($subpath) && substr($subpath, 0, 1) == DIRECTORY_SEPARATOR) {
      $subpath = substr($subpath, 1);
    }

    # return combined path
    if(empty($base)) {
      return $subpath;
    } elseif(empty($subpath)) {
      return $base;
    } else {
      return $base.DIRECTORY_SEPARATOR.$subpath;
    }
  }
}

?>
