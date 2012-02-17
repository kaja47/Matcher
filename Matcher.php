<?php

/**
 * This file is part of the Atrox toolbox
 *
 * Copyright (c) 2012, Karel Čížek (kaja47@k47.cz)
 *
 * @license New BSD License
 */

namespace Atrox;


class Matcher {

  private $f;

  function __construct($f) { $this->f = $f; }
  function __invoke()      { return call_user_func_array($this->f, func_get_args()); }

  function processHtml($html) { $dom = new \DOMDocument(); $dom->loadHTML($html); return $this($dom); }
  function processXml ($xml)  { $dom = new \DOMDocument(); $dom->loadXML($xml);   return $this($dom); }
  function processDom ($dom)  { return $this($dom); }


  static function nodeValue($n) { return $n instanceof \DOMNode ? $n->nodeValue : $n; }


  static function multi($basePath, array $paths = null, $extractFunc = 'Atrox\Matcher::nodeValue') {
    return new Matcher(function($dom, $contextNode = null) use($basePath, $paths, $extractFunc) {
      $xpath = new \DOMXpath($dom);
      $matches = $xpath->query($basePath, $contextNode);

      $return = array();

      if (!$paths) {
        foreach ($matches as $m) $return[] = call_user_func_array($extractFunc, array($m, null));

      } else {
        foreach ($matches as $m) $return[] = Matcher::extractPaths($dom, $m, $paths, $extractFunc);
      }

      return $return;
    });
  }


  static function single($path, $extractFunc = 'Atrox\Matcher::nodeValue') {
    return new Matcher(function ($dom, $contextNode = null) use($path, $extractFunc) {
      $xpath = new \DOMXpath($dom);
      return Matcher::extractValue($extractFunc, $xpath->query($path, $contextNode));
    });
  }


  static function extractPaths($dom, $contextNode, $paths, $extractFunc) {
    $xpath = new \DOMXpath($dom);
    $return = array();

    foreach ($paths as $key => $val) {
      if (is_array($val)) { // path => array()
        $n = $xpath->query($key, $contextNode)->item(0);
        $r = ($n === null) ? array_fill_keys(array_keys($val), null) : self::extractPaths($dom, $n, $val, $extractFunc);
        $return = array_merge($return, $r);

      } elseif ($val instanceof Matcher || $val instanceof \Closure) { // key => multipath
        $return[$key] = $val($dom, $contextNode);
      
      } elseif (is_string($val)) { // key => path
        $return[$key] = self::extractValue($extractFunc, $xpath->query($val, $contextNode));

      } else {
        throw new \Exception("Invalid path. Expected string, array or marcher, ".gettype($val)." given");
      }
    }

    return $return;
  }

  static function extractValue($extractFunc, $matches) {
    return $matches->length === 0 ? null : call_user_func_array($extractFunc, array($matches->item(0)));
  }
}
