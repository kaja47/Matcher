<?php

require_once __DIR__ . "/../Matcher.php";
require_once __DIR__ . "/../vendor/autoload.php";

use Tester\Assert;
use Atrox\Matcher;
use Atrox\MatcherContext;

Tester\Environment::setup();



$html = file_get_contents(__DIR__ . "/test-doc.html");

$m = Matcher::single('//h1')->fromHtml();
Assert::same($m($html), "title");


$m = Matcher::multi('//h2')->fromHtml();
Assert::same($m($html), ['article1', 'article2', 'article3', 'article4']);


$m = Matcher::multi('//div[@class="article"]', [
	'title' => 'h2',
	'url'   => 'h2/a/@href',
	'text'  => './/div[@class="text"]'
])->fromHtml();

Assert::same($m($html), [
	['title' => 'article1', 'url' => 'url1', 'text' => 'text1'],
	['title' => 'article2', 'url' => 'url2', 'text' => 'text2'],
	['title' => 'article3', 'url' => 'url3', 'text' => 'text3'],
	['title' => 'article4', 'url' => null, 'text' => 'text4'],
]);


$m = Matcher::multi('//div[@class="article"]', (object) [
	'title' => 'h2',
	'url'   => 'h2/a/@href',
	'text'  => './/div[@class="text"]'
])->fromHtml();

Assert::equal($m($html), [
	(object) ['title' => 'article1', 'url' => 'url1', 'text' => 'text1'],
	(object) ['title' => 'article2', 'url' => 'url2', 'text' => 'text2'],
	(object) ['title' => 'article3', 'url' => 'url3', 'text' => 'text3'],
	(object) ['title' => 'article4', 'url' => null, 'text' => 'text4'],
]);


$m = Matcher::multi('//div[@class="article"]', [
	'title' => 'h2',
	'tags'  => Matcher::multi('.//span[@class="tag"]'),
])->fromHtml();

Assert::same($m($html), [
	['title' => 'article1', 'tags' => ['tag1', 'tag2', 'tag3']],
	['title' => 'article2', 'tags' => ['tag4']],
	['title' => 'article3', 'tags' => ['tag5', 'tag6']],
	['title' => 'article4', 'tags' => []],
]);


$m = Matcher::multi('//div[@class="article"]', [
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
])->fromHtml();

Assert::same($m($html), [
	['title' => 'article1', 'url' => 'http://example.com/url1', 'date' => strtotime('2000-01-01')],
	['title' => 'article2', 'url' => 'http://example.com/url2', 'date' => strtotime('2001-01-01')],
	['title' => 'article3', 'url' => 'http://example.com/url3', 'date' => strtotime('2002-01-01')],
	['title' => 'article4', 'url' => null, 'date' => null],
]);


$m = Matcher::multi('//div[@class="article"]', [
	'title' => 'h2',
	'date' => Matcher::single('.//span[@class="date"]')
		->regex('~published: (.*)~')
		->first()
		->map('strtotime'),
])->fromHtml();

Assert::same($m($html), [
	['title' => 'article1', 'date' => strtotime('2000-01-01')],
	['title' => 'article2', 'date' => strtotime('2001-01-01')],
	['title' => 'article3', 'date' => strtotime('2002-01-01')],
	['title' => 'article4', 'date' => false],
]);


$m = Matcher::multi('//div[@class="article"]', [
	'title' => 'h2',
	'id' => Matcher::single('@data-id')->asInt(),
])->fromHtml();

Assert::same($m($html), [
	['title' => 'article1', 'id' => 1],
	['title' => 'article2', 'id' => 2],
	['title' => 'article3', 'id' => 3],
	['title' => 'article4', 'id' => 4],
]);


$m = Matcher::multi('//div[@class="article"]', [
	'title'   => 'h2',
	'tags'    => Matcher::count('.//span[@class="tag"]'),
	'hasTags' => Matcher::has('.//span[@class="tag"]'),
	'hasTags2' => Matcher::multi('.//span[@class="tag"]')->map(function ($tags) { return count($tags) > 0; }),
])->fromHtml();

Assert::same($m($html), [
	['title' => 'article1', 'tags' => 3, 'hasTags' => true,  'hasTags2' => true,],
	['title' => 'article2', 'tags' => 1, 'hasTags' => true,  'hasTags2' => true,],
	['title' => 'article3', 'tags' => 2, 'hasTags' => true,  'hasTags2' => true,],
	['title' => 'article4', 'tags' => 0, 'hasTags' => false, 'hasTags2' => false],
]);


$m = Matcher::single([
	'title' => '//h1',
	'date'  => Matcher::constant('2014-01-01'),
])->fromHtml();

Assert::same($m($html), [
	'title' => 'title',
	'date'  => '2014-01-01',
]);


// flatten nesting
$m = Matcher::multi('//div[@class="article"]', [
	Matcher::single('h2', [
		'title' => '.',
		'url'   => './a/@href',
	]),
	'text'  => './/div[@class="text"]'
])->fromHtml();

Assert::same($m($html), [
	['title' => 'article1', 'url' => 'url1', 'text' => 'text1'],
	['title' => 'article2', 'url' => 'url2', 'text' => 'text2'],
	['title' => 'article3', 'url' => 'url3', 'text' => 'text3'],
	['title' => 'article4', 'url' => null, 'text' => 'text4'],
]);


$m = Matcher::multi('//div[@class="article"]', [
	'titleData' => Matcher::single('h2', [
		'title' => '.',
		'url'   => './a/@href',
	]),
	'text'  => './/div[@class="text"]'
])->fromHtml();

Assert::same($m($html), [
	['titleData' => ['title' => 'article1', 'url' => 'url1'], 'text' => 'text1'],
	['titleData' => ['title' => 'article2', 'url' => 'url2'], 'text' => 'text2'],
	['titleData' => ['title' => 'article3', 'url' => 'url3'], 'text' => 'text3'],
	['titleData' => ['title' => 'article4', 'url' => null],   'text' => 'text4'],
]);


$m = Matcher::multi("//table//tr[position() > 1]", [
	'name'  => 1,
	'score' => Matcher::single(2)->asInt(),
])->fromHtml();

Assert::same($m(file_get_contents(__DIR__ . '/test-table.html')), [
	['name' => 'A. A.', 'score' =>  2],
	['name' => 'B. B.', 'score' => 10],
]);


// everything is a function
//$m = Matcher::multi('//div[@class="article"]', [
//	'title' => 'h2',
//	'text' => function ($rawDomNode) {
//		return $rawDomNode->getElementsByTagName('');
//	},
//])->fromHtml();
//
//Assert::same($m($html));die;
//Assert::same($m($html), [
//	['title' => 'article1', 'text' => 'text1'],
//	['title' => 'article2', 'text' => 'text2'],
//	['title' => 'article3', 'text' => 'text3'],
//	['title' => 'article4', 'text' => 'text4'],
//]);


$matcher = Matcher::single('//h1');

$m = $matcher->fromHtml();
Assert::same($m($html), "title");

$m = $matcher->fromXml();
Assert::same($m($html), "title");


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
Assert::same($m($xml), "multiple\n\t\tlines");

$m = $matcher->fromXml(Matcher::normalize);
Assert::same($m($xml), "multiple\nlines");

$m = $matcher->fromXml(Matcher::oneline);
Assert::same($m($xml), 'multiple lines');

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
	null, [
		'atom' => 'http://www.w3.org/2005/Atom',
	]
));

Assert::same($m($atomXml), ['Atom-Powered Robots Run Amok']);
