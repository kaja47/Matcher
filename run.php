<?php

require_once __DIR__ . "/Matcher.php";

use Atrox\Matcher;

$opts = getopt('f:p:s', ['xml', 'html', 'json', 'dump', 'script:']);
if (!$opts || !isset($opts['f']) || (!isset($opts['p']) && !isset($opts['script']))) {
	echo "usage: ".basename(__FILE__)." -f file.html -p xpathPattern --html\n";
	echo "usage: ".basename(__FILE__)." -f file.html --script m.php --html\n";
  die;
}


if (isset($opts['script'])) {
	$m = require_once $opts['script'];
} else {
	$mode = (isset($opts['s'])) ? 'single' : 'multi';
	$patterns = is_array($opts['p']) ? $opts['p'] : [$opts['p']];
	$m = call_user_func_array("Atrox\Matcher::$mode", $patterns);
	$m = (isset($opts['html']) || !isset($opts['xml'])) ? $m->fromHtml() : $m->fromXml();
}

$f = file_get_contents($opts['f']);

$res = $m($f);

if (isset($opts['json'])) {
  echo json_encode($res, JSON_PRETTY_PRINT), "\n";

} else if (isset($opts['dump'])) {
	var_dump($res);
	echo "\n";

} else {
  if (is_scalar($res)) {
    echo $res, "\n";
  } else {
    foreach ($res as $l) {
      echo $l, "\n";
    }
  }
}


