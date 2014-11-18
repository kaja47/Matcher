<?php

require_once __DIR__ . "/../Matcher.php";
require_once __DIR__ . "/../vendor/autoload.php";

use Tester\Assert;
use Atrox\Matcher as M;
use Atrox\MatcherContext;

Tester\Environment::setup();


runTests(true);
runTests(false);


function test($xml, $matcher, $expected) {
	Assert::same($matcher($xml), $expected);
}

function runTests($asDom) {

	$xml1 = '
	<texts>
		<a>test</a>
		<b>  test  </b>
		<c>  test  test  </c>
		<d>1 <x>2</x> 3 <x>4</x> 5</d>
		<e>
		1
	<x>2</x>
		3    <x>4</x>


		5

	</e>
		<f txt="  text  " />
		<g txt="  1
	2
		3

		4 5    " />
		<h txt="xxx&lt;&quot;&gt;xxx" />
		<i>xxx&lt;&quot;&gt;xxx</i>
	</texts>

	';

	$xml2 = '
	<root>
		<a id="1">
			<b id="1" n="1">a1.b1</b>
			<b id="2" n="2">a1.b2</b>
			<x><b n="3">a1.x.b</b></x>
		</a>

		<a id="2">
			<b id="1" n="4">a2.b1</b>
			<b id="2" n="5">a2.b2
				<b id="1" n="6">a2.b2.b1</b>
			</b>
		</a>

		<a id="3">
			<b id="1" n="7">a3.b1</b>
			<b id="2" n="8"><x n="9">a3.b2.x</x></b>
		</a>
	</root>
	';

	test($xml1, M::single('//a')->fromXml(null, $asDom), 'test');
	test($xml1, M::single('//a')->map('strtoupper')->fromXml(null, $asDom), 'TEST');
	test(M::loadXML($xml1), M::single('//a'), 'test');
	test(M::loadXML($xml1), M::single('//a')->map('strtoupper'), 'TEST');
	test($xml1, M::single('//b')->fromXml(null, $asDom), 'test');
	test($xml1, M::single('//b')->map('strtoupper')->fromXml(null, $asDom), 'TEST');
	test($xml1, M::single('//c')->fromXml(null, $asDom), 'test test');
	test($xml1, M::single('//d')->fromXml(null, $asDom), '1 2 3 4 5');
	test($xml1, M::single('//e')->fromXml(M::oneline, $asDom), '1 2 3 4 5');
	test($xml1, M::single('//e')->withExtractor(M::oneline)->fromXml(null, $asDom), '1 2 3 4 5');
	test($xml1, M::single('//e')->fromXml(M::normalize, $asDom), "1\n2\n3 4\n\n5");
	test($xml1, M::single('//f/@txt')->fromXml(null, $asDom), "text");
	test($xml1, M::single('//g/@txt')->fromXml(M::oneline, $asDom), "1 2 3 4 5");
	test($xml1, M::single('//h/@txt')->fromXml(null, $asDom), "xxx<\">xxx");
	test($xml1, M::single('//i')->fromXml(null, $asDom), "xxx<\">xxx");
	test($xml1, M::single('//d', 1)->fromXml(null, $asDom), '2');
	test($xml1, M::single('//d', 3)->fromXml(null, $asDom), null);
	test($xml1, M::single('//d/*[1]')->fromXml(null, $asDom), '2');
	test($xml1, M::single(array(
		'k1' => M::single('//b')->withExtractor('Atrox\\Matcher::_nodeToString'),
		'k2' => M::single('//b')->withExtractor(M::normalize),
	))->withExtractor(M::identity)->fromXml(null, $asDom), array(
		'k1' => '  test  ',
		'k2' => 'test',
	));

	test($xml2, M::single('//a')->fromXml(M::oneline, $asDom), "a1.b1 a1.b2 a1.x.b");
	test($xml2, M::single(array('x' => '//a[@id="1"]/@id', 'y' => '//a[@id="2"]/@id'))->fromXml(M::oneline, $asDom),array('x' => '1', 'y' => '2'));
	test($xml2, M::single('//a', 'b')->fromXml(M::oneline, $asDom), "a1.b1");
	test($xml2, M::single('//a', 'b')->fromXml(M::oneline, $asDom), "a1.b1");
	test($xml2, M::single('//a', './b')->fromXml(M::oneline, $asDom), "a1.b1");
	test($xml2, M::single('//a[@id="2"]', 'b', 'b')->fromXml(M::oneline, $asDom), null);
	test($xml2, M::single('//a[@id="2"]', 'b[2]', 'b')->fromXml(M::oneline, $asDom), 'a2.b2.b1');
	test($xml2, M::single('//a[@id="2"]/b/b')->fromXml(M::oneline, $asDom), 'a2.b2.b1');
	test($xml2, M::single('//a[@id="2"]', './/b[@id="2"]')->fromXml(M::oneline, $asDom), 'a2.b2 a2.b2.b1');
	test($xml2, M::single('//a[@id="2"]', array('x' => './/b[@id="1"]', 'y' => './/b[@id="2"]'))->fromXml(M::oneline, $asDom), array('x' => 'a2.b1', 'y' => 'a2.b2 a2.b2.b1'));
	test($xml2, M::single(array(
		'x' => M::single('//a[1]', './*/b'),
		'y' => M::single('//a[2]', './*/b')
	))->fromXml(M::oneline, $asDom), array('x' => 'a1.x.b', 'y' => 'a2.b2.b1'));
	test($xml2, M::single(array(
		'x' => M::single('//a[1]', $mmm = array('x' => './b[@id="1"]', 'y' => './b[@id="2"]')),
		'y' => M::single('//a[3]', $mmm),
	))->fromXml(M::oneline, $asDom), array('x' => array('x' => 'a1.b1', 'y' => 'a1.b2' ), 'y' => array('x' => 'a3.b1', 'y' => 'a3.b2.x')));
	test($xml2, M::single(array('x' => '//a[@id="1"]/@id', 'y' => function () { return 47; }))->fromXml(M::oneline, $asDom), array('x' => '1', 'y' => 47));
	test($xml2, M::single(array('x' => '//a[@id="1"]/@id', 'y' => M::constant(47)))->fromXml(M::oneline, $asDom), array('x' => '1', 'y' => 47));

	test($xml2, M::multi('//a')->map('count')->fromXml(M::oneline, $asDom), 3);
	test($xml2, M::multi('//a')->fromXml(M::oneline, $asDom)->map('count'), 3);
	test($xml2, M::multi('//a', 'b/@n')->fromXml(null, $asDom), array( array('1', '2'), array('4', '5'), array('7', '8') ));
	test($xml2, M::multi('//a', M::multi('*', 'b/@n'))->fromXml(null, $asDom), array( array( array(), array(), array('3') ), array( array(), array('6') ), array( array(), array() ) ));
	test($xml1, M::multi('/texts', 'e')->first()->first()->fromXml(M::oneline, $asDom), '1 2 3 4 5');
	test($xml1, M::multi('/texts', M::single('e'))->first()->fromXml(M::oneline, $asDom), '1 2 3 4 5');

	test($xml2, M::has('/root/a')->fromXml(null, $asDom), true);
	test($xml2, M::has('/root/a[@id="3"]')->fromXml(null, $asDom), true);
	test($xml2, M::has('/root/a[@id="4"]')->fromXml(null, $asDom), false);
	test($xml2, M::count('/root/a')->fromXml(null, $asDom), 3);




	$thread = '
	<thread>
		<op id="1" />
		<replies>
			<reply id="1.1" />
			<reply id="1.2" />
		</replies>
	</thread>
	';

	test($thread, M::single(array(
		'op'      => M::single('//op',    $mmm = M::single('./@id')),
		'replies' => M::multi('.//reply', $mmm),
	))->fromXml(null, $asDom), array('op' => '1', 'replies' => array('1.1', '1.2')));
	test($thread, M::single(array(
		'op'      => M::single('//op',    $mmm = M::single('./@id')),
		'replies' => M::multi('.//reply', $mmm)
	))->fromXml(null, $asDom), array('op' => '1', 'replies' => array('1.1', '1.2')));
	test($thread, M::single(array(
		'op'      => M::single('//op',    $mmm = array('id' => './@id')),
		'replies' => M::multi('.//reply', $mmm)
	))->fromXml(null, $asDom), array('op' => array('id' => '1'), 'replies' => array(array('id' => '1.1'), array('id' => '1.2'))));


	Assert::exception(function () use ($asDom) {
		$m = M::single('/test')->fromXml(null, $asDom);
		$m('<');
	}, '\RuntimeException', '~^Invalid XML document~');


	Assert::exception(function () use ($asDom) {
		$m = M::single('/test')->fromXml(null, $asDom);
		$m('');
	}, '\RuntimeException', '~^Invalid XML document~');



	// namespaces

	$atomXml = trim('
	<?xml version="1.0" encoding="utf-8"?>
	<feed xmlns="http://www.w3.org/2005/Atom">
		<title>Example Feed</title>
		<link href="http://example.org/" />
		<id>urn:uuid:60a76c80-d399-11d9-b91C-0003939e0af6</id>

		<entry>
			<title>Atom-Powered Robots Run Amok</title>
			<link href="http://example.org/2003/12/13/atom03" />
			<id>urn:uuid:1225c695-cfb8-4ebb-aaaa-80da344efa6a</id>
		</entry>
	</feed>
	');

	$atomMatcher = M::multi('/atom:feed/atom:entry', array(
		'title' => './atom:title',
		'id'    => './atom:id',
	))->fromXml(new MatcherContext(null, array('atom' => 'http://www.w3.org/2005/Atom')), $asDom);

	$expected = array(array(
		'title' => 'Atom-Powered Robots Run Amok',
		'id'    => 'urn:uuid:1225c695-cfb8-4ebb-aaaa-80da344efa6a',
	));

	test($atomXml, $atomMatcher, $expected);

}
