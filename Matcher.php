<?php

namespace Atrox;

class Matcher {

  private $f;

  function __construct($f) { $this->f = $f; }
  function __invoke()      { return call_user_func_array($this->f, func_get_args()); }

  function processHtml($html) { $dom = new \DOMDocument(); $dom->loadHTML($html); return $this->processDom($dom); }
  function processXml ($xml)  { $dom = new \DOMDocument(); $dom->loadXML($xml); return $this->processDom($dom); }
  function processDom ($dom)  { return $this($dom); }


  static function extractFunc($n) { return $n->nodeValue; }

  static function multi($basePath, array $paths = null, $extractFunc = 'Atrox\Matcher::extractValue') {
    return new Matcher(function($dom, $contextNode = null) use($basePath, $paths, $extractFunc) {
      $xpath = new \DOMXpath($dom);
      $matches = $xpath->query($basePath, $contextNode);

      $return = array();

      if ($paths === null) {
        foreach ($matches as $m) $return[] = call_user_func_array($extractFunc, array($m, null));

      } else {
        foreach ($matches as $m) $return[] = Matcher::extractPaths($dom, $m, $paths, $extractFunc);
      }

      return $return;
    });
  }


  static function extractPaths($dom, $contextNode, $paths, $extractFunc) {
    $xpath = new \DOMXpath($dom);
    $return = array();

    foreach ($paths as $key => $val) {
      if (is_array($val)) { // path => array()
        $n = $xpath->query($key, $contextNode)->item(0);
        $return = array_merge($return, self::extractPaths($dom, $n, $val, $extractFunc));
      
      } elseif ($val instanceof Matcher) { // key => multipath
        $return[$key] = $val($dom, $contextNode);
      
      } elseif (is_string($val)) { // key => path
        $matches = $xpath->query($val, $contextNode);
        $return[$key] = $matches->length === 0 ? null : call_user_func_array($extractFunc, array($matches->item(0), $key));

      } else {
        throw new \Exception("Invalid path. Expected string, array or marcher, ".gettype($val)." given");
      }
    }

    return $return;
  }

}
