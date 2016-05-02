<?php

require_once __DIR__ . "/../Matcher.php";
require_once __DIR__ . "/../vendor/autoload.php";

use Tester\Assert;
use Atrox\Matcher;
use Atrox\MatcherContext;
use Masterminds\HTML5;

Tester\Environment::setup();

date_default_timezone_set('America/Los_Angeles');

$html = file_get_contents(__DIR__ . "/test-doc.html");
$html5 = new HTML5(array('disable_html_ns' => true));
$dom = $html5->loadHTML($html);

$m = Matcher::single('//h1');
Assert::same("title", $m($dom));

$m = Matcher::multi('//h2');
Assert::same(array('article1', 'article2', 'article3', 'article4'), $m($dom));
