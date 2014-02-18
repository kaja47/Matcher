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

  const toString  = '\Atrox\Matcher::toString';
  const oneline   = '\Atrox\Matcher::oneline';
  const normalize = '\Atrox\Matcher::normalize';
  const identity  = '\Atrox\Matcher::identity';

  private $f;
  private $m; // path

  function __construct($f, $m = null) { $this->f = $f; $this->m = $m; }
  function __invoke()      { return call_user_func_array($this->f, func_get_args()); }


  /**
   *  @param string|array|int|Closure|Matcher $path
   */
  static function multi($path) {
    $args = func_get_args();
    $path = array_shift($args);
    $m = new Matcher(function($node, $extractor = null) use($path) {
      $extractor = Matcher::_getExtractor($extractor);
      if (is_string($path)) return array_map($extractor, Matcher::_xpathAll($node, $path));
      else                  return Matcher::_evalPath($node, $path, $extractor);
    }, $path);
    return empty($args) ? $m : $m->raw()->_deepMapNode(call_user_func_array('\Atrox\Matcher::multi', $args));
  }


  /**
   *  @param string|array|int|Closure|Matcher $path
   */
  static function single($path) {
    $args = func_get_args();
    $path = array_shift($args);
    $m = new Matcher(function ($node, $extractor = null) use($path) {
      $extractor = Matcher::_getExtractor($extractor);
      return Matcher::_evalPath($node, $path, $extractor);
    }, $path);
    return empty($args) ? $m : $m->raw()->_deepMapNode(call_user_func_array('\Atrox\Matcher::single', $args));
  }

  static function has($path) {
    return Matcher::multi($path)->map(function ($xs) { return count($xs) > 0; });
  }

  static function count($path) {
    return Matcher::multi($path)->map('count');
  }

  function _filter($f) { return $this->raw()->map(function($ns) use ($f) { return array_filter($ns, $f); }); }
  function _single($path) { return $this->raw()->_deepMapNode(Matcher::single($path)); }



  static function loadHTML($html, $asDom) {
    $dom = @ \DOMDocument::loadHTML($html);
    if ($dom === false) {
      throw new \Exception("Invalid HTML document");
    }
    if ($asDom) {
      return $dom;
    }
    $simpleXML = @ simplexml_import_dom($dom);
    if ($simpleXML === false) {
      throw new \Exception("Can't import DOM document into SimpleXML");
    }
    return $simpleXML;
  }

  static function loadXML($xml, $asDom) {
    if ($asDom) {
      $dom = @ \DOMDocument::loadXML($xml);
      if ($dom === false) {
        throw new \Exception("Invalid XML document");
      }
      return $dom;

    } else {
      $simpleXML = @ simplexml_load_string($xml);
      if ($simpleXML === false) {
        throw new \Exception("Invalid XML document");
      }
      return $simpleXML;
    }
  }


  function fromHtml($ex = null, $asDom = true) {
    $m = new Matcher(function ($html) use ($asDom) { return Matcher::loadHTML($html, $asDom); });
    return $m->map($this->withExtractor($ex));
  }
  function fromXml($ex = null, $asDom = true) {
    $m = new Matcher(function ($xml) use ($asDom) { return Matcher::loadXML($xml, $asDom); });
    return $m->map($this->withExtractor($ex));
  }


  // extractors:

  static function toString($n)  { return trim(preg_replace('~ +~', ' ', self::_nodeToString($n))); }
  static function oneline($n)   { return trim(preg_replace('~\s+~', ' ', self::_nodeToString($n))); }
  static function normalize($n) { return trim(preg_replace('~[ \t]+~', " ", preg_replace('~\n{3,}~', "\n\n", preg_replace('~([ \t]+$|^[ \t]+)~m', '', self::_nodeToString($n))))); }
  static function identity($n)  { return $n; }
  static function distr($f)     { return function ($x) use ($f) { return array_map($f, $x); }; }


  /** @param callback $f  SimpleXMLElement => ? */
  function withExtractor($f) {
    $self = $this->f;
    return new Matcher(function ($node, $extractor = null) use($self, $f) { // outer extractor passed as argument is thrown away
      return $self($node, $f);
    }, $this);
  }

  function raw() { return $this->withExtractor(Matcher::identity); }


  /** maps only SimpleXMLElement in arbitrary depth */
  private function _deepMapNode(Matcher $f) {
    $mapf = function ($node, $extractor) use($f, &$mapf) {
      if ($node instanceof \SimpleXMLElement) return call_user_func($f, $node, $extractor);
      if ($node instanceof \DOMNode)          return call_user_func($f, $node, $extractor);
      if (is_object($node))                   return (object) $mapf(get_object_vars($node), $extractor);
      if (is_array($node) && empty($node))    return array();
      if (is_array($node))                    return array_combine(array_keys($node), array_map($mapf, $node, array_fill(0, count($node), $extractor)));
      else                                    return $node;
    };

    return $this->mapEx($mapf);
  }


  /** Applies function $f to result of matcher (*after* extractor) */
  function map($f) { return $this->mapEx(function ($node, $extractor) use ($f) { return call_user_func($f, $node); }); }
  function andThen($f) { return $this->map($f); }


  private function mapEx($f) {
    $self = $this->f;
    return new Matcher(function ($node, $extractor = null) use($self, $f) {
      return $f($self($node, $extractor), $extractor);
    }, $this);
  }


  /**
   * monadic bind function (may not work)
   * @param callback $f: A => Matcher[B]
   */
  function flatMap($f) {
    $self = $this->f;
    return new Matcher(function ($node, $extractor = null) use($self, $f) {
      $m = $f($self($node, Matcher::identity));
      return $m($node, $extractor);
    }, $this);
  }



  function asInt()   { return $this->map('intval'); }
  function asFloat() { return $this->map('floatval'); }
  function first()   { return $this->map(function ($xs) { return reset($xs); }); } 


  /**
   * regexes without named patterns will return numeric array without key 0
   * if result of previous matcher is array, it recursively applies regex on every element of that array
   */
  function regex($regex) {
    $f = function ($res) use($regex, & $f) { // &$f for anonymous recursion
      if ($res === null) {
        return null;

      } else if (is_string($res)) {
        preg_match($regex, $res, $m);
        if (count(array_filter(array_keys($m), 'is_string')) === 0) { // regex has no named subpatterns
          unset($m[0]);
        } else {
          foreach ($m as $k => $v) if (is_int($k)) unset($m[$k]);
        }
        return $m;

      } else if (is_array($res)) {
        $return = array();
        foreach ($res as $k => $v) $return[$k] = $f($v);
        return $return;

      } else {
        throw new \Exception("Method `regex' should be applied only to Matcher::single which returns string or array of strings");
      }
    };
    return $this->map($f);
  }


  /** experimental and uncomprehensible */
  function seqOr(Matcher $that) {
    $self = $this;

    $thisPath = $this;
    while ($thisPath instanceof Matcher) {
      $thisRaw  = $thisPath->raw();
      $thisPath = $thisPath->m;
    }
    $thatPath = $that;
    while ($thatPath instanceof Matcher) {
      $thatRaw  = $thatPath->raw();
      $thatPath = $thatPath->m;
    }

    if (!is_string($thisPath) || !is_string($thatPath)) throw new \Exception('Method seqOr can be used only with matchers matching against string.');

    $mm = Matcher::multi("$thisPath | $thatPath")->raw();
    return new Matcher(function ($node, $extractor = null) use ($mm, $self, $that, $thisRaw, $thatRaw) {
      $rawByBoth = $mm($node, $extractor);

      $run = function ($m) use ($node, $extractor) { $arr = $m($node, $extractor); return is_array($arr) ? $arr : array($arr); };

      $raw     = array_merge($run($thisRaw), $run($thatRaw));
      $matched = array_merge($run($self), $run($that));

      $return = array();
      foreach($rawByBoth as $n) {
        $strict = ($n instanceof \DOMNode); // strict must be false for SimpleXML and true for DOM
        if (($i = array_search($n, $raw, $strict)) !== false) {
          $return[] = $matched[$i];
        }
      }
      return $return;
    }, $mm);
  }


  // actual runtime methods

  /** @internal */
  static function _getExtractor($extractor) {
    return $extractor ?: Matcher::toString; // Use outer extractor passed as argument, if it's null, use default extractor
  }


  static function _xpathAll($node, $path) {
    if ($node instanceof \DOMNode) {
      $dom = ($node instanceof \DOMDocument) ? $node : $node->ownerDocument;
      $xpath = new \DOMXPath($dom);
      $res = $xpath->evaluate($path, $node);
      return (is_scalar($res)) ? $res : iterator_to_array($res);
    } else {
      return $node->xpath($path);
    }
  }


  static function _children($node) {
    if ($node instanceof \DOMNode) {
      return $node->childNodes;
    } else {
      return $node->children();
    }
  }


  static function _nodeToString($node) {
    if ($node instanceof \DOMNode) {
      return $node->nodeValue;
    } else {
      return dom_import_simplexml($node)->nodeValue;
    }
  }


  /** @internal */
  static function _evalPath($node, $path, $extractor) { // todo
    if ($path instanceof Matcher || $path instanceof \Closure) { // key => multipath
      return $path($node, $extractor);

    } elseif (is_array($path) || is_object($path)) { // key => array(paths)
      return Matcher::_extractPaths($node, $path, $extractor);

    } elseif (is_string($path)) { // key => path
      $matches = self::_xpathAll($node, $path);
      if (is_scalar($matches)) {
        return $matches;
      } else if (count($matches) === 0) {
        return null;
      } else {
        return call_user_func($extractor, $matches[0]);
      }

    } elseif (is_int($path)) { // key => position of child element
      $ns = self::_children($node);
      return call_user_func($extractor, $ns[$path]);

    } else {
      throw new \Exception("Invalid path. Expected string, int, array, stdClass object, Matcher object of function, ".gettype($val)." given");
    }
  }


  /** @internal **/
  static function _extractPaths($node, $paths, $extractor) {
    $return = array();

    foreach ($paths as $key => $val) {
      if (is_int($key)) { // merge into current level
        $return = array_merge($return, Matcher::_evalPath($node, $val, $extractor)); // extracted value shoud be array

      } else {
        $return[$key] = Matcher::_evalPath($node, $val, $extractor);

      }
    }

    return is_object($paths) ? (object) $return : $return;
  }
}
