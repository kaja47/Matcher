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


  public $f; // public only for support PHP 5.3


  function __construct($f) { $this->f = $f; }
  function __invoke() { return call_user_func_array($this->f, func_get_args()); }



  /** @param string|array|int|Closure|Matcher $path */
  static function multi($path, $next = null) {
    if (is_string($path)) {
      $m = new Matcher(function($node, $context = null) use ($path) {
        $context = Matcher::inventContext($context);
        return array_map($context->getExtractor(), $context->xpathAll($node, $path));
      }, $path);
    } else {
      $m = self::single($path);
    }

    return ($next === null) ? $m : $m->mapRaw(function ($nodes, $context) use ($next) {
      $m = Matcher::multi($next);
      $count = count($nodes);
      return ($count === 0) ? array() : array_combine(array_keys($nodes), array_map($m->f, $nodes, array_fill(0, $count, $context)));
    });
  }

  /** @param string|array|int|Closure|Matcher $path */
  static function single($path) {
    $args = func_get_args();
    $path = array_shift($args);
    $m = new Matcher(function ($node, $context = null) use ($path) {
      $context = Matcher::inventContext($context);
      return Matcher::_evalPath($node, $path, $context);
    }, $path);

    return empty($args) ? $m : $m->mapRaw(call_user_func_array('\Atrox\Matcher::single', $args)->f);
  }

  static function has($path) {
    return Matcher::multi($path)->map(function ($xs) { return count($xs) > 0; });
  }

  static function count($path) {
    return Matcher::multi($path)->map('count');
  }

  static function constant($value) {
    return function () use ($value) { return $value; };
  }


  private static function checkInput($str, $type) {
    if (!is_string($str)) {
      $type = is_object($str) ? get_class($str) : gettype($str);
      $hint = '';
      if ($str instanceof \DOMDocument || $str instanceof \SimpleXMLElement) {
        $hint = ' When matching on DOM objects you don\'t need to call `fromHtml` or `fromXml`';
      }
      throw new \RuntimeException("Can't create DOM document: string expected, $type given.$hint");
    }

    if ($str === '') {
      throw new \RuntimeException("Invalid $type document: empty string");
    }
  }

  private static function checkXml($status, $errmsg) {
    if ($status === false) {
      $error = libxml_get_last_error();
      if ($error && $error->message) {
        throw new \RuntimeException($errmsg.": ".trim($error->message));
      } else {
        throw new \RuntimeException($errmsg);
      }
    }
  }

  static function loadHTML($html, $asDom = true) {
    self::checkInput($html, 'HTML');

    $useIntErr = libxml_use_internal_errors(true);
    $dom = new \DOMDocument;
    $ok = $dom->loadHTML($html);
    libxml_use_internal_errors($useIntErr);

    self::checkXml($ok, "Invalid HTML document");

    if ($asDom) {
      return $dom;
    }

    $useIntErr = libxml_use_internal_errors(true);
    $simpleXML = simplexml_import_dom($dom);
    libxml_use_internal_errors($useIntErr);

    self::checkXml($simpleXML, "Can't import DOM document into SimpleXML");
    return $simpleXML;
  }

  static function loadXML($xml, $asDom = true) {
    self::checkInput($xml, 'XML');

    if ($asDom) {
      $useIntErr = libxml_use_internal_errors(true);
      $dom = new \DOMDocument();
      $ok = $dom->loadXML($xml);
      libxml_use_internal_errors($useIntErr);

      self::checkXml($ok, "Invalid XML document");
      return $dom;

    } else {
      $useIntErr = libxml_use_internal_errors(true);
      $simpleXML = simplexml_load_string($xml);
      libxml_use_internal_errors($useIntErr);

      self::checkXml($simpleXML, "Invalid XML document");
      return $simpleXML;
    }
  }


  function fromHtml($ex = null, $asDom = true) {
    $f = $this->f;
    $context = ($ex instanceof MatcherContext) ? $ex : new MatcherContext($ex);
    return new Matcher(function ($html) use ($asDom, $context, $f) { return $f(Matcher::loadHTML($html, $asDom), $context); });
  }
  function fromXml($ex = null, $asDom = true) {
    $f = $this->f;
    $context = ($ex instanceof MatcherContext) ? $ex : new MatcherContext($ex);
    return new Matcher(function ($html) use ($asDom, $context, $f) { return $f(Matcher::loadXML($html, $asDom), $context); });
  }


  // extractors:

  static function toString($n)  { return trim(preg_replace('~ +~', ' ', self::_nodeToString($n))); }
  static function oneline($n)   { return trim(preg_replace('~\s+~', ' ', self::_nodeToString($n))); }
  static function normalize($n) { return trim(preg_replace('~[ \t]+~', " ", preg_replace('~\n{3,}~', "\n\n", preg_replace('~([ \t]+$|^[ \t]+)~m', '', self::_nodeToString($n))))); }
  static function identity($n)  { return $n; }


  function withExtractor($extractor) {
    $self = $this->f;
    return new Matcher(function ($node, $context = null) use ($self, $extractor) { // outer extractor passed as argument is thrown away
      $context = Matcher::inventContext($context);
      return $self($node, $context->withExtractor($extractor));
    });
  }

  function withContext(MatcherContext $context) {
    $self = $this->f;
    return new Matcher(function ($node, $_) use ($self, $context) { // outer extractor passed as argument is thrown away
      return $self($node, $context);
    });
  }

  /** Creates new Macther that applies function $f to the result of $this
    * matcher *after* extraction. */
  function map($f) {
    $self = $this->f;
    return new Matcher(function ($node, $context = null) use ($self, $f) {
      $context = Matcher::inventContext($context);
      return call_user_func($f, $self($node, $context));
    });
  }

  /** Alias for map method. */
  function andThen($f) { return $this->map($f); }

  /** Creates new Matcher that executes $this matcher without extraction and
    * then applies function $f to the result */
  function mapRaw($f) {
    $self = $this->f;
    return new Matcher(function ($node, $context = null) use ($self, $f) {
      $context = Matcher::inventContext($context);
      return $f($self($node, $context->withExtractor(Matcher::identity)), $context);
    });
  }


  /** Creates new Matcher that executes $this matcher and if nothing is found
    * (results is null or empty array), executes second matcher. */
  function orElse($m) {
    $self = $this->f;
    return new Matcher(function ($node, $context = null) use ($self, $m) {
      $context = Matcher::inventContext($context);
      return $self($node, $context) ?: Matcher::_evalPath($node, $m, $context);
    });
  }


  /** Creates new matcher that converts result of $this matcher to integer */
  function asInt()   { return $this->map('intval'); }

  /** Creates new matcher that converts result of $this matcher to float */
  function asFloat() { return $this->map('floatval'); }

  /** Creates new matcher that converts result of $this matcher to integer */
  function first()   {
    return $this->map(function ($xs) {
      return ($xs === null || (is_array($xs) && empty($xs))) ? null : reset($xs);
    });
  }


  /** Regexes without named patterns will return numeric array without key 0.
    * If result of previous matcher is array, it recursively applies regex on
    * every element of that array.  */
  function regex($regex) {
    $f = function ($res) use ($regex, & $f) { // &$f for anonymous recursion
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


  /** Creates the default MatcherContext in case nothing is passed as an
    * argument. This can happen when current matcher is the top level matcher.
    * @internal */
  static function inventContext(MatcherContext $context = null) {
    return ($context === null) ? new MatcherContext(null) : $context;
  }


  // actual runtime methods


  /** @internal */
  static function _nodeToString($node) {
    if ($node instanceof \DOMNode) {
      return $node->nodeValue;
    } else {
      return dom_import_simplexml($node)->nodeValue;
    }
  }


  /** @internal */
  static function _evalPath($node, $path, $context) {
    if ($node === null) { // nothing is passed in current matcher, this happens when p₁ in single(p₁, p₂) matches nothing
      return null;

    } else if ($path instanceof Matcher || $path instanceof \Closure) { // key => multipath
      return $path($node, $context);

    } elseif (is_array($path) || is_object($path)) { // key => array(paths)
      return Matcher::_extractPaths($node, $path, $context);

    } elseif (is_string($path)) { // key => path
      $matches = $context->xpathAll($node, $path);
      if (is_scalar($matches)) {
        return $matches;
      } else if (count($matches) === 0) {
        return null;
      } else {
        return call_user_func($context->getExtractor(), $matches[0]);
      }

    } elseif (is_int($path)) { // key => position of child element
      $ns = $context->position($node, $path);
      return empty($ns) ? null : call_user_func($context->getExtractor(), reset($ns));

    } else {
      throw new \Exception("Invalid path. Expected string, int, array, stdClass object, Matcher object of function, ".gettype($path)." given");
    }
  }


  /** Performs matching on paths specified by arrays or objects.
    * @internal **/
  private static function _extractPaths($node, $paths, $context) {
    $return = array();

    foreach ($paths as $key => $val) {
      if (is_int($key)) { // merge into current level
        $x = Matcher::_evalPath($node, $val, $context);
        if (is_scalar($x) || $x === null) {
          throw new \Exception("Cannot merge scalar value" . (is_string($val) ? " produced by path `$val`" : "") . " Expected array or object.");
        }
        if (is_object($x)) $x = (array) $x;
        $return = array_merge($return, $x);

      } else {
        $return[$key] = Matcher::_evalPath($node, $val, $context);

      }
    }

    return is_object($paths) ? (object) $return : $return;
  }
}


class MatcherContext {
  private $extractor, $namespaces, $pathTranslator;

  function __construct($extractor = null, $namespaces = array(), $pathTranslator = null) {
    $this->extractor = $extractor;
    $this->namespaces = $namespaces;
    $this->pathTranslator = $pathTranslator;
  }

  function withExtractor($extractor) {
    return new self($extractor, $this->namespaces, $this->pathTranslator);
  }

  function position($node, $pos) {
    return $this->xpathAll($node, "*[$pos]");
  }

  function xpathAll($node, $path) {
    if ($this->pathTranslator !== null) {
      $path = call_user_func($this->pathTranslator, $path);
    }

    if ($node instanceof \DOMNode) {
      $dom = ($node instanceof \DOMDocument) ? $node : $node->ownerDocument;
      $xpath = new \DOMXPath($dom);
      foreach ($this->namespaces as $prefix => $url) {
        $xpath->registerNamespace($prefix, $url);
      }

      $res = $xpath->evaluate($path, $node);

      return (is_scalar($res)) ? $res : iterator_to_array($res);
    } else if ($node instanceof \SimpleXMLElement) {
      foreach ($this->namespaces as $prefix => $url) {
        $node->registerXPathNamespace($prefix, $url);
      }
      return $node->xpath($path);
    } else {
      $hint = (gettype($node) === 'string') ? ' Maybe you forgot to call `fromXml` or `fromHtml` on your matcher.' : '';
      $type = is_object($node) ? get_class($node) : gettype($node);
      throw new \Exception("Cannot execute query. DOMNode or SimpleXMLElement expected, $type given.$hint");
    }
  }

  function getExtractor() {
    return $this->extractor ?: Matcher::toString; // Use outer extractor passed as argument, if it's null, use default extractor
  }
}


class CssMatcherContext extends MatcherContext {
  function __construct($extractor = null) {
    parent::__construct($extractor, array(), 'Symfony\Component\CssSelector\CssSelector::toXpath');
  }
}
