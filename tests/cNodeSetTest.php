<?php

require_once('src/cNodeSet.php');
require_once('src/cQuery.php');

class cNodeSetTest extends PHPUnit_Framework_TestCase
{
	protected $object;

	protected function setUp()
	{
		$this->document = '
			<html>
			<head>
			<title>this is title.</title>
			</head>
			<body>
			<div id="header">header</div>
			<div id="wrapper">content</div>
			<div id="footer">footer</div>
			</body>
			</html>
		';
		$this->object = new cQuery($this->document);
	}

	protected function tearDown()
	{
	}

	function test_attr() {
		$elements = $this->object->query('div');
		// get
		$this->assertEquals($elements->eq(0)->attr('id'), 'header');
		$this->assertEquals($elements->eq(1)->attr('id'), 'wrapper');
		$this->assertEquals($elements->eq(2)->attr('id'), 'footer');
		// set all, key => value
		$elements->attr('id', 'test_id');
		foreach ($elements as $element) {
			$this->assertEquals($element->attr('id'), 'test_id');
		}
		// set all, array
		$elements->attr(array('id'=> 'array_test', 'title'=>'test_title'));
		foreach ($elements as $element) {
			$this->assertEquals($element->attr('id'), 'array_test');
			$this->assertEquals($element->attr('title'), 'test_title');
		}
	}

	function test_css() {
		$elements = $this->object->query('div');
		// set
		$elements->css(array('color'=>'#000', 'border'=>'1px solid #000'));
		for ($i=0; $i<$elements->length; ++$i) {
			$this->assertEquals($elements->get($i)->getAttribute('style'), 'color:#000; border:1px solid #000;');
		}
		$elements->css('margin', 5); // add style
		$elements->css('color', '#fff'); // override style
		for ($i=0; $i<$elements->length; ++$i) {
			$this->assertEquals($elements->get($i)->getAttribute('style'), 'color:#fff; border:1px solid #000; margin:5px;');
		}

		// get
		$this->assertEquals($elements->css('color'), '#fff');
		$this->assertEquals($elements->css('border'), '1px solid #000');
		$this->assertEquals($elements->css('margin'), '5px');
	}

	function test_class() {
		$elements = $this->object->query('div');
		// add
		$elements->addClass(array('test','class'));
		for ($i=0; $i<$elements->length; ++$i) {
			$elements->get($i)->getAttribute('test class');
		}
		// has
		$this->assertEquals($elements->hasClass('test'), true);
		$this->assertEquals($elements->hasClass('class'), true);
		$this->assertEquals($elements->hasClass('content'), false);

		// remove
		$elements->removeClass('class');
		for ($i=0; $i<$elements->length; ++$i) {
			$elements->get($i)->getAttribute('test');
		}
		$this->assertEquals($elements->hasClass('class'), false);
	}

	function test_append() {
		$elements = $this->object->query('div');
		$elements->append('<b>hello</b>');
		$source = $this->object->saveHTML();
		$source = preg_replace('/[\n\t]+/', '', $source);
		$source = preg_replace('/^.*?<body>(.*?)<\/body>.*$/', '$1', $source);
		$ret = array(
			'<div id="header">header<b>hello</b></div>',
			'<div id="wrapper">content<b>hello</b></div>',
			'<div id="footer">footer<b>hello</b></div>',
		);
		$this->assertEquals($source, join('', $ret));
	}

	function test_before() {
		$elements = $this->object->query('div');
		$elements->before('<b>hello</b>');
		$source = $this->object->saveHTML();
		$source = preg_replace('/[\n\t]+/', '', $source);
		$source = preg_replace('/^.*?<body>(.*?)<\/body>.*$/', '$1', $source);
		$ret = array(
			'<b>hello</b><div id="header">header</div>',
			'<b>hello</b><div id="wrapper">content</div>',
			'<b>hello</b><div id="footer">footer</div>',
		);
		$this->assertEquals($source, join('', $ret));
	}

	function test_after() {
		$elements = $this->object->query('div');
		$elements->after('<b>hello</b>');
		$source = $this->object->saveHTML();
		// var_dump($source); die;
		$source = preg_replace('/[\n\t]+/', '', $source);
		$source = preg_replace('/^.*?<body>(.*?)<\/body>.*$/', '$1', $source);
		$ret = array(
			'<div id="header">header</div><b>hello</b>',
			'<div id="wrapper">content</div><b>hello</b>',
			'<div id="footer">footer</div><b>hello</b>',
		);
		$this->assertEquals($source, join('', $ret));
	}

	function test_replace() {
		$elements = $this->object->query('div');
		$elements->replace('<b>hello</b>');
		$source = $this->object->saveHTML();
		$source = preg_replace('/[\n\t]+/', '', $source);
		$source = preg_replace('/^.*?<body>(.*?)<\/body>.*$/', '$1', $source);
		$ret = array(
			'<b>hello</b>',
			'<b>hello</b>',
			'<b>hello</b>',
		);
		$this->assertEquals($source, join('', $ret));
	}

	function test_remove() {
		$elements = $this->object->query('div');
		$elements->remove();
		$source = $this->object->saveHTML();
		$source = preg_replace('/[\n\t]+/', '', $source);
		$source = preg_replace('/^.*?<body>(.*?)<\/body>.*$/', '$1', $source);
		$this->assertEquals($source, '');
	}

	function test_empty() {
		$elements = $this->object->query('div');
		$elements->_empty();
		$source = $this->object->saveHTML();
		$source = preg_replace('/[\n\t]+/', '', $source);
		$source = preg_replace('/^.*?<body>(.*?)<\/body>.*$/', '$1', $source);
		$ret = array(
			'<div id="header"></div>',
			'<div id="wrapper"></div>',
			'<div id="footer"></div>',
		);
		$this->assertEquals($source, join('', $ret));
	}

	function test_next() {
		$elements = $this->object->query('div#header');
		$next = $elements->_next();
		$this->assertEquals($next->length, 1);
		$this->assertEquals($next->attr('id'), 'wrapper');
	}

	function test_nextAll() {
		$elements = $this->object->query('div#header');
		$next = $elements->nextAll();
		$this->assertEquals($next->length, 2);
		$this->assertEquals($next->eq(0)->attr('id'), 'wrapper');
		$this->assertEquals($next->eq(1)->attr('id'), 'footer');
	}

	function test_parent() {
		$elements = $this->object->query('div');
		$parent = $elements->parent();
		$this->assertEquals($parent->length, 1);
		$this->assertEquals($parent->get(0)->tagName, 'body');
	}

	function test_parents() {
		$elements = $this->object->query('div');
		$parent = $elements->parents();
		$this->assertEquals($parent->length, 2);
		$this->assertEquals($parent->get(0)->tagName, 'html');
		$this->assertEquals($parent->get(1)->tagName, 'body');
	}

	function test_siblings() {
		$this->object = new cQuery('
			<html><body>
			<div class="wrapper">
				<span class="selected">menu1</span>
				<span>menu2</span>
				<span>menu3</span>
			</div>
			<div class="wrapper">
				<span>menu4</span>
				<span class="selected">menu5</span>
				<span class="selected">menu6</span>
				<span>menu7</span>
			</div>
			</body></html>
		');

		$elements = $this->object->query('*[@class="selected"]');
		$this->assertEquals($elements->get(0)->nodeValue, 'menu1');
		$this->assertEquals($elements->get(1)->nodeValue, 'menu5');
		$this->assertEquals($elements->get(2)->nodeValue, 'menu6');

		$siblings = $elements->siblings();
		$this->assertEquals($siblings->length, 6);
		$this->assertEquals($siblings->get(0)->nodeValue, 'menu2');
		$this->assertEquals($siblings->get(1)->nodeValue, 'menu3');
		$this->assertEquals($siblings->get(2)->nodeValue, 'menu4');
		$this->assertEquals($siblings->get(3)->nodeValue, 'menu6');
		$this->assertEquals($siblings->get(4)->nodeValue, 'menu7');
		$this->assertEquals($siblings->get(5)->nodeValue, 'menu5');
	}

	function test_children() {
		$html = $this->object->query('html');
		$children = $html->children();
		$this->assertEquals($children->length, 2);
		$this->assertEquals($children->get(0)->tagName, 'head');
		$this->assertEquals($children->get(1)->tagName, 'body');
	}

	function test_contents() {
		$this->object = new cQuery('
			<html>
			<body>
			abcde<span>12345</span>@@@@
			</body>
			</html>
		');
		$contents = $this->object->query('body')->contents();
		$this->assertEquals($contents->length, 3);
		$this->assertEquals($contents->get(0)->nodeType, XML_TEXT_NODE);
		$this->assertEquals($contents->get(0)->nodeValue, 'abcde');
		$this->assertEquals($contents->get(1)->nodeType, XML_ELEMENT_NODE);
		$this->assertEquals($contents->get(1)->tagName, 'span');
		$this->assertEquals($contents->get(1)->nodeValue, '12345');
		$this->assertEquals($contents->get(2)->nodeType, XML_TEXT_NODE);
		$this->assertEquals($contents->get(2)->nodeValue, '@@@@');
	}

	function test_html() {
		$this->markTestIncomplete();
	}

	function test_text() {
		$this->markTestIncomplete();
	}

	function test_add() {
		$this->markTestIncomplete();
	}
}
?>
