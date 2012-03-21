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

  function processHtml($html) { $dom = new \DOMDocument(); @$dom->loadHTML($html); return $this($dom); }
  function processXml ($xml)  { $dom = new \DOMDocument(); $dom->loadXML($xml);    return $this($dom); }
  function processDom ($dom)  { return $this($dom); }


  function fromHtml() { $self = $this; return function($html) use($self) { return $self->processHtml($html); }; }
  function fromXml()  { $self = $this; return function($html) use($self) { return $self->processXml($html); }; }


  /** @internal */
  static function defaultExtractor($n) { return $n instanceof \DOMNode ? $n->nodeValue : $n; }
  static function normalizeWhitespaces($n) { return trim(preg_replace('~\s+~', ' ', $n->nodeValue)); }
  //static function defaultExtractor($ns) { $res = ''; foreach ($ns as $n) { $res .= $n->nodeValue; } return $res; }


  /** Applies function $f to result of matcher (*after* extractor) */
  function andThen($f) { // todo
    $self = $this;
    return new Matcher(function ($dom, $contextNode = null, $extractor = null) use($self, $f) {
      return $f($self($dom, $contextNode, $extractor));
    });
  }



  /** defaultExtractor == null => use outer extractor
   *  @param string $basePath
   *  @param array|object $paths
   *  @param callable|null $defaultExtractor
   */
  static function multi($basePath, $paths = null, $defaultExtractor = null) {
    return new Matcher(function($dom, $contextNode = null, $extractor = null) use($basePath, $paths, $defaultExtractor) {
      $xpath = new \DOMXpath($dom);
      $extractor = Matcher::_getExtractor($defaultExtractor, $extractor);
      $matches = $xpath->query($basePath, $contextNode);

      $return = array();

      if (!$paths) {
        foreach ($matches as $m) $return[] = call_user_func($extractor, $m);

      } else {
        foreach ($matches as $m) $return[] = Matcher::_extractPaths($dom, $m, $paths, $extractor);
      }

      return $return;
    });
  }


  static function single($path, $defaultExtractor = null) {
    return new Matcher(function ($dom, $contextNode = null, $extractor = null) use($path, $defaultExtractor) {
      $xpath = new \DOMXpath($dom);
      $extractor = Matcher::_getExtractor($defaultExtractor, $extractor);

      if (is_array($path)) {
        return Matcher::_extractPaths($dom, $contextNode, $path, $extractor);

      } else {
        return Matcher::_extractValue($extractor, $xpath->query($path, $contextNode));
      }
    });
  }


  /** @internal */
  static function _getExtractor($defaultExtractor, $extractor) {
    if ($defaultExtractor !== null) return $defaultExtractor;
    else if ($extractor === null)   return 'Atrox\Matcher::defaultExtractor'; // use default extractor
    else                            return $extractor; // use outer extractor passed as explicit argument
  }


  /** @internal */
  static function _extractPaths($dom, $contextNode, $paths, $extractor) {
    $xpath = new \DOMXpath($dom);
    $return = array();

    foreach ($paths as $key => $val) {
      if (is_array($val)) { // path => array()
        $n = $xpath->query($key, $contextNode)->item(0);
        $r = ($n === null) ? array_fill_keys(array_keys($val), null) : self::_extractPaths($dom, $n, $val, $extractor);
        $return = array_merge($return, $r); // todo: object result

      } elseif ($val instanceof Matcher || $val instanceof \Closure) { // key => multipath
        $return[$key] = $val($dom, $contextNode, $extractor);
      
      } elseif (is_string($val)) { // key => path
        $return[$key] = self::_extractValue($extractor, $xpath->query($val, $contextNode));

      } else {
        throw new \Exception("Invalid path. Expected string, array or marcher, ".gettype($val)." given");
      }
    }

    return $return;
  }

  /** @internal */
  static function _extractValue($extractor, $matches) {
    return $matches->length === 0 ? null : call_user_func($extractor, $matches->item(0));
  }
}
