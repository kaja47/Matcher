<?php

require_once __DIR__ . "/../Matcher.php";
require_once __DIR__ . "/../vendor/autoload.php";

use Tester\Assert;
use Atrox\Matcher;
use Atrox\MatcherContext;

Tester\Environment::setup();

date_default_timezone_set('America/Los_Angeles');


$html = file_get_contents(__DIR__ . "/test-doc.html");

$m = Matcher::single('//h1')->fromHtml();
Assert::same("title", $m($html));


$m = Matcher::multi('//h2')->fromHtml();
Assert::same(array('article1', 'article2', 'article3', 'article4'), $m($html));


$m = Matcher::multi('//div[@class="article"]', array(
	'title' => 'h2',
	'url'   => 'h2/a/@href',
	'text'  => './/div[@class="text"]'
))->fromHtml();

Assert::same(array(
	array('title' => 'article1', 'url' => 'url1', 'text' => 'text1'),
	array('title' => 'article2', 'url' => 'url2', 'text' => 'text2'),
	array('title' => 'article3', 'url' => 'url3', 'text' => 'text3'),
	array('title' => 'article4', 'url' => null, 'text' => 'text4'),
), $m($html));


$m = Matcher::multi('//div[@class="article"]', (object) array(
	'title' => 'h2',
	'url'   => 'h2/a/@href',
	'text'  => './/div[@class="text"]'
))->fromHtml();

Assert::equal(array(
	(object) array('title' => 'article1', 'url' => 'url1', 'text' => 'text1'),
	(object) array('title' => 'article2', 'url' => 'url2', 'text' => 'text2'),
	(object) array('title' => 'article3', 'url' => 'url3', 'text' => 'text3'),
	(object) array('title' => 'article4', 'url' => null, 'text' => 'text4'),
), $m($html));


$m = Matcher::multi('//div[@class="article"]', array(
	'title' => 'h2',
	'tags'  => Matcher::multi('.//span[@class="tag"]'),
))->fromHtml();

Assert::same(array(
	array('title' => 'article1', 'tags' => array('tag1', 'tag2', 'tag3')),
	array('title' => 'article2', 'tags' => array('tag4')),
	array('title' => 'article3', 'tags' => array('tag5', 'tag6')),
	array('title' => 'article4', 'tags' => array()),
), $m($html));


$m = Matcher::multi('//div[@class="article"]', array(
	'title' => 'h2',
	'url'   => Matcher::single('h2/a/@href')->map(function ($url) {
		return $url ? 'http://example.com/'.$url : null;
	}),
	'date' => Matcher::single('.//span[@class="date"]')->map(function ($date) {
		if (preg_match('~published: (.*)~', $date, $m)) {
			return strtotime($m[1]);
		}	else {
			return null;
		}
	})
))->fromHtml();

Assert::same(array(
	array('title' => 'article1', 'url' => 'http://example.com/url1', 'date' => strtotime('2000-01-01')),
	array('title' => 'article2', 'url' => 'http://example.com/url2', 'date' => strtotime('2001-01-01')),
	array('title' => 'article3', 'url' => 'http://example.com/url3', 'date' => strtotime('2002-01-01')),
	array('title' => 'article4', 'url' => null, 'date' => null),
), $m($html));


$m = Matcher::multi('//div[@class="article"]', array(
	'title' => 'h2',
	'date' => Matcher::single('.//span[@class="date"]')
		->regex('~published: (.*)~')
		->first()
		->map('strtotime'),
))->fromHtml();

Assert::same(array(
	array('title' => 'article1', 'date' => strtotime('2000-01-01')),
	array('title' => 'article2', 'date' => strtotime('2001-01-01')),
	array('title' => 'article3', 'date' => strtotime('2002-01-01')),
	array('title' => 'article4', 'date' => false),
), $m($html));


// method first() can handle nulls

$m = Matcher::single('//non-existent-node')->regex('~published: (?P<date>.*)~')->first()->fromHtml();

Assert::same(null, $m($html));


$m = Matcher::single('//span[@class="date"]')->regex('~wrong pattern: (?P<date>.*)~')->first()->fromHtml();

Assert::same(null, $m($html));


$m = Matcher::multi('//div[@class="article"]', array(
	'title' => 'h2',
	'id' => Matcher::single('@data-id')->asInt(),
))->fromHtml();

Assert::same(array(
	array('title' => 'article1', 'id' => 1),
	array('title' => 'article2', 'id' => 2),
	array('title' => 'article3', 'id' => 3),
	array('title' => 'article4', 'id' => 4),
), $m($html));


$m = Matcher::multi('//div[@class="article"]', array(
	'title'   => 'h2',
	'tags'    => Matcher::count('.//span[@class="tag"]'),
	'hasTags' => Matcher::has('.//span[@class="tag"]'),
	'hasTags2' => Matcher::multi('.//span[@class="tag"]')->map(function ($tags) { return count($tags) > 0; }),
))->fromHtml();

Assert::same(array(
	array('title' => 'article1', 'tags' => 3, 'hasTags' => true,  'hasTags2' => true,),
	array('title' => 'article2', 'tags' => 1, 'hasTags' => true,  'hasTags2' => true,),
	array('title' => 'article3', 'tags' => 2, 'hasTags' => true,  'hasTags2' => true,),
	array('title' => 'article4', 'tags' => 0, 'hasTags' => false, 'hasTags2' => false),
), $m($html));


$m = Matcher::single(array(
	'title' => '//h1',
	'date'  => Matcher::constant('2014-01-01'),
))->fromHtml();

Assert::same(array(
	'title' => 'title',
	'date'  => '2014-01-01',
), $m($html));


// flatten nesting
$m = Matcher::multi('//div[@class="article"]', array(
	Matcher::single('h2', array(
		'title' => '.',
		'url'   => './a/@href',
	)),
	'text'  => './/div[@class="text"]'
))->fromHtml();

Assert::same(array(
	array('title' => 'article1', 'url' => 'url1', 'text' => 'text1'),
	array('title' => 'article2', 'url' => 'url2', 'text' => 'text2'),
	array('title' => 'article3', 'url' => 'url3', 'text' => 'text3'),
	array('title' => 'article4', 'url' => null, 'text' => 'text4'),
), $m($html));


$m = Matcher::multi('//div[@class="article"]', array(
	array(
		'title' => './h2',
		'url'   => './h2/a/@href',
	),
	'text'  => './/div[@class="text"]'
))->fromHtml();

Assert::same(array(
	array('title' => 'article1', 'url' => 'url1', 'text' => 'text1'),
	array('title' => 'article2', 'url' => 'url2', 'text' => 'text2'),
	array('title' => 'article3', 'url' => 'url3', 'text' => 'text3'),
	array('title' => 'article4', 'url' => null, 'text' => 'text4'),
), $m($html));


$m = Matcher::multi('//div[@class="article"]', array(
	1 => 'h2'
))->fromHtml();

Assert::exception(function () use ($m, $html) {
	return $m($html);
}, '\Exception', '~^Cannot merge scalar value~');


// mixed array/object flattening

$m = Matcher::multi('//div[@class="article"]', (object) array(
	array(
		'title' => './h2',
	),
	'text'  => './/div[@class="text"]'
))->fromHtml();

Assert::equal(array(
	(object) array('title' => 'article1', 'text' => 'text1'),
	(object) array('title' => 'article2', 'text' => 'text2'),
	(object) array('title' => 'article3', 'text' => 'text3'),
	(object) array('title' => 'article4', 'text' => 'text4'),
), $m($html));


$m = Matcher::multi('//div[@class="article"]', array(
	(object) array(
		'title' => './h2',
	),
	'text'  => './/div[@class="text"]'
))->fromHtml();

Assert::same(array(
	array('title' => 'article1', 'text' => 'text1'),
	array('title' => 'article2', 'text' => 'text2'),
	array('title' => 'article3', 'text' => 'text3'),
	array('title' => 'article4', 'text' => 'text4'),
), $m($html));


$m = Matcher::multi('//div[@class="article"]', (object) array(
	(object) array(
		'title' => './h2',
	),
	'text'  => './/div[@class="text"]'
))->fromHtml();

Assert::equal(array(
	(object) array('title' => 'article1', 'text' => 'text1'),
	(object) array('title' => 'article2', 'text' => 'text2'),
	(object) array('title' => 'article3', 'text' => 'text3'),
	(object) array('title' => 'article4', 'text' => 'text4'),
), $m($html));



$m = Matcher::multi('//div[@class="article"]', array(
	'titleData' => Matcher::single('h2', array(
		'title' => '.',
		'url'   => './a/@href',
	)),
	'text'  => './/div[@class="text"]'
))->fromHtml();

Assert::same(array(
	array('titleData' => array('title' => 'article1', 'url' => 'url1'), 'text' => 'text1'),
	array('titleData' => array('title' => 'article2', 'url' => 'url2'), 'text' => 'text2'),
	array('titleData' => array('title' => 'article3', 'url' => 'url3'), 'text' => 'text3'),
	array('titleData' => array('title' => 'article4', 'url' => null),   'text' => 'text4'),
), $m($html));


$m = Matcher::multi('//div[@class="article"]', array(
	'titleData' => array(
		'title' => 'h2',
		'url'   => 'h2/a/@href',
	),
	'text'  => './/div[@class="text"]'
))->fromHtml();

Assert::same(array(
	array('titleData' => array('title' => 'article1', 'url' => 'url1'), 'text' => 'text1'),
	array('titleData' => array('title' => 'article2', 'url' => 'url2'), 'text' => 'text2'),
	array('titleData' => array('title' => 'article3', 'url' => 'url3'), 'text' => 'text3'),
	array('titleData' => array('title' => 'article4', 'url' => null),   'text' => 'text4'),
), $m($html));


// orElse

$m = Matcher::multi('//div[@class="article"]', array(
	'url' => Matcher::single('h2/a/@href')->orElse('@data-id')
))->fromHtml();

Assert::same(array(
	array('url' => 'url1'),
	array('url' => 'url2'),
	array('url' => 'url3'),
	array('url' => '4'),
), $m($html));


$m = Matcher::multi('//div[@class="article"]', array(
	'url' => Matcher::single('h2/a/@href')->orElse('@data-id')->map(function ($x) {
		return 'http://example.com/'.$x;
	})
))->fromHtml();

Assert::same(array(
	array('url' => 'http://example.com/url1'),
	array('url' => 'http://example.com/url2'),
	array('url' => 'http://example.com/url3'),
	array('url' => 'http://example.com/4'),
), $m($html));


$m = Matcher::multi("//table//tr[position() > 1]", array(
	'name'  => 1,
	'score' => Matcher::single(2)->asInt(),
))->fromHtml();

Assert::same(array(
	array('name' => 'A. A.', 'score' =>  2),
	array('name' => 'B. B.', 'score' => 10),
), $m(file_get_contents(__DIR__ . '/test-table.html')));


// everything is a function
$m = Matcher::multi('//div[@class="article"]', array(
	'title' => 'h2',
	'id'    => function (\DOMElement $node) {
		return (int) $node->getAttribute('data-id');
	},
))->fromHtml();

Assert::same(array(
	array('title' => 'article1', 'id' => 1),
	array('title' => 'article2', 'id' => 2),
	array('title' => 'article3', 'id' => 3),
	array('title' => 'article4', 'id' => 4),
), $m($html));


$matcher = Matcher::single('//h1');

$m = $matcher->fromHtml();
Assert::same("title", $m($html));

$m = $matcher->fromXml();
Assert::same("title", $m($html));



// XPath functions

$m = Matcher::single('count(//h1)')->fromHtml();
Assert::same(1.0, $m($html));

$m = Matcher::single('count(//h2)')->fromHtml();
Assert::same(4.0, $m($html));


// extractors

$xml = trim("
<root>
	<el>
		multiple
		lines
	</el>
</root>
");

$matcher = Matcher::single('/root/el');

$m = $matcher->fromXml(Matcher::toString); // default
Assert::same("multiple\n\t\tlines", $m($xml));

$m = $matcher->fromXml(Matcher::normalize);
Assert::same("multiple\nlines", $m($xml));

$m = $matcher->fromXml(Matcher::oneline);
Assert::same('multiple lines', $m($xml));

$m = $matcher->fromXml(Matcher::identity);
Assert::type('DOMElement', $m($xml));

// DOM/SimpleXML

$m = $matcher->fromXml(Matcher::identity, false);
Assert::type('SimpleXMLElement', $m($xml));



// xml namespaces
$atomXml = trim('
<?xml version="1.0" encoding="utf-8"?>

<feed xmlns="http://www.w3.org/2005/Atom">
	<title>Example Feed</title>

	<entry>
		<title>Atom-Powered Robots Run Amok</title>
	</entry>

</feed>
');

$m = Matcher::multi('//atom:entry/atom:title')->fromXml(new MatcherContext(
	null, array(
		'atom' => 'http://www.w3.org/2005/Atom',
	)
));

Assert::same(array('Atom-Powered Robots Run Amok'), $m($atomXml));



// error messages when matching with `fromHtml` on DOM document 

$dom = new \DOMDocument;
$dom->loadHTML($html);

$m = Matcher::single('//h1')->fromHtml();

Assert::exception(function () use ($m, $dom) {
	return $m($dom);
}, '\RuntimeException', '~^Can\'t create DOM document~');

$m = Matcher::single('//h1')->fromXml();

Assert::exception(function () use ($m, $dom) {
	return $m($dom);
}, '\RuntimeException', '~^Can\'t create DOM document~');
