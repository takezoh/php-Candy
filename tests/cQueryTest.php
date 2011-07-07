<?php

require_once('src/cQuery.php');
require_once('src/cNodeSet.php');

class cQueryTest extends PHPUnit_Framework_TestCase {

	protected $object = null;

	function setUp() {
		$this->object = new cQuery('
			<html>
			<head>
			<title>this is title.</title>
			</head>
			<body>
			<div class="wrapper">
				<div id="header1" class="header">this is header.</div>
				<div id="content1" class="content">this is content.</div>
				<div id="footer1" class="footer">this is footer.</div>
			</div>
			<div class="ads">
				<span id"ads1">ads</span>
			</div>
			<div class="wrapper">
				<div id="header2" class="header">this is header2.</div>
				<div id="content2" class="content">this is content2.</div>
				<div id="footer2" class="footer">this is footer2.</div>
			</div>
			</body>
			</html>
		');
	}

	function tearDown() {
	}

	function test_preload() {
		$reflector = (object) null;
		$reflector->_preload = new ReflectionMethod('cQuery', '_preload');
		$reflector->_preload->setAccessible(true);
		foreach (array('_root', '_doctype', '_content_type', '_html_exists', '_head_exists', '_body_exists') as $param) {
			$reflector->{$param} = new ReflectionProperty('cQuery', $param);
			$reflector->{$param}->setAccessible(true);
		}

		// HTML>HEAD,BODY
		$subject = '<!DOCTYPE html><html><head><meta http-equiv="content-type" content="text/html; charset=Shift_JIS"><title>test</title></head><body><div>test</div></body></html>';
		$source = $reflector->_preload->invoke($this->object, $subject);
		$this->assertEquals($source, '<!DOCTYPE html><html><head><meta http-equiv="content-type" content="text/html; charset=utf8" /><title>test</title></head><body><div>test</div></body></html>');
		$this->assertEquals($reflector->_root->getValue($this->object), 'html');
		$this->assertEquals($reflector->_doctype->getValue($this->object), '<!DOCTYPE html>');
		$this->assertEquals($reflector->_content_type->getValue($this->object), '<meta http-equiv="content-type" content="text/html; charset=Shift_JIS" />');
		$this->assertEquals($reflector->_html_exists->getValue($this->object), 1);
		$this->assertEquals($reflector->_head_exists->getValue($this->object), 1);
		$this->assertEquals($reflector->_body_exists->getValue($this->object), 1);

		// HTML>BODY
		$subject = '<!DOCTYPE html><html><body><div>test</div></body></html>';
		$source = $reflector->_preload->invoke($this->object, $subject);
		$this->assertEquals($source, '<!DOCTYPE html><html><head><meta http-equiv="content-type" content="text/html; charset=utf8" /></head><body><div>test</div></body></html>');
		$this->assertEquals($reflector->_root->getValue($this->object), 'html');
		$this->assertEquals($reflector->_doctype->getValue($this->object), '<!DOCTYPE html>');
		$this->assertEquals($reflector->_content_type->getValue($this->object), null);
		$this->assertEquals($reflector->_html_exists->getValue($this->object), 1);
		$this->assertEquals($reflector->_head_exists->getValue($this->object), 0);
		$this->assertEquals($reflector->_body_exists->getValue($this->object), 1);

		// HEAD only
		$subject = '<head><meta http-equiv="content-type" content="text/html; charset=Shift_JIS"><title>test</title></head>';
		$source = $reflector->_preload->invoke($this->object, $subject);
		$this->assertEquals($source, '<html><head><meta http-equiv="content-type" content="text/html; charset=utf8" /><title>test</title></head></html>');
		$this->assertEquals($reflector->_root->getValue($this->object), 'head');
		$this->assertEquals($reflector->_doctype->getValue($this->object), null);
		$this->assertEquals($reflector->_content_type->getValue($this->object), '<meta http-equiv="content-type" content="text/html; charset=Shift_JIS" />');
		$this->assertEquals($reflector->_html_exists->getValue($this->object), 0);
		$this->assertEquals($reflector->_head_exists->getValue($this->object), 1);
		$this->assertEquals($reflector->_body_exists->getValue($this->object), 0);

		// BODY only
		$subject = '<body><div>test</div></body>';
		$source = $reflector->_preload->invoke($this->object, $subject);
		$this->assertEquals($source, '<html><head><meta http-equiv="content-type" content="text/html; charset=utf8" /></head><body><div>test</div></body></html>');
		$this->assertEquals($reflector->_root->getValue($this->object), 'body');
		$this->assertEquals($reflector->_doctype->getValue($this->object), null);
		$this->assertEquals($reflector->_content_type->getValue($this->object), null);
		$this->assertEquals($reflector->_html_exists->getValue($this->object), 0);
		$this->assertEquals($reflector->_head_exists->getValue($this->object), 0);
		$this->assertEquals($reflector->_body_exists->getValue($this->object), 1);

		// none BODY
		$subject = '<div>test</div>';
		$source = $reflector->_preload->invoke($this->object, $subject);
		$this->assertEquals($source, '<html><head><meta http-equiv="content-type" content="text/html; charset=utf8" /></head><body><div>test</div></body></html>');
		$this->assertEquals($reflector->_root->getValue($this->object), 'body');
		$this->assertEquals($reflector->_doctype->getValue($this->object), null);
		$this->assertEquals($reflector->_content_type->getValue($this->object), null);
		$this->assertEquals($reflector->_html_exists->getValue($this->object), 0);
		$this->assertEquals($reflector->_head_exists->getValue($this->object), 0);
		$this->assertEquals($reflector->_body_exists->getValue($this->object), 0);
	}


	function test_query() {
		// $this->object->query(':even:contains("ads")');
		// $this->object->query('div:first, div:last');
		$this->markTestIncomplete();
	}

	function test_create() {
		$nodes = $this->object->create('<b>hello</b>');
		$this->assertEquals($nodes->tagName, 'b');
		$this->assertEquals($nodes->nodeValue, 'hello');
	}

}
?>
