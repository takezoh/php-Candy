<?php

include('src/DOMCompiler.php');
include('src/SimplePhpParser.php');

class DOMCompilerText extends PHPUnit_Framework_TestCase
{
	protected $object;

	protected function setUp()
	{
		$this->object = new DOMCompiler(array(), array(
			'smarty' => null,
		));
	}

	protected function tearDown()
	{
	}

	function test_preloadString() {
		$reflector = (object) null;
		$reflector->_preloadString = new ReflectionMethod('DOMCompiler', '_preloadString');
		$reflector->_preloadString->setAccessible(true);
		foreach (array('_root', '_doctype', '_content_type', '_html_exists', '_head_exists', '_body_exists') as $param) {
			$reflector->{$param} = new ReflectionProperty('DOMCompiler', $param);
			$reflector->{$param}->setAccessible(true);
		}

		// HTML>HEAD,BODY
		$subject = '<!DOCTYPE html><html><head><meta http-equiv="content-type" content="text/html; charset=Shift_JIS"><title>test</title></head><body><div>test</div></body></html>';
		$source = $reflector->_preloadString->invoke($this->object, $subject);
		$this->assertEquals($source, '<!DOCTYPE html><html><head><meta http-equiv="content-type" content="text/html; charset=utf8" /><title>test</title></head><body><div>test</div></body></html>');
		$this->assertEquals($reflector->_root->getValue($this->object), 'html');
		$this->assertEquals($reflector->_doctype->getValue($this->object), '<!DOCTYPE html>');
		$this->assertEquals($reflector->_content_type->getValue($this->object), '<meta http-equiv="content-type" content="text/html; charset=Shift_JIS" />');
		$this->assertEquals($reflector->_html_exists->getValue($this->object), 1);
		$this->assertEquals($reflector->_head_exists->getValue($this->object), 1);
		$this->assertEquals($reflector->_body_exists->getValue($this->object), 1);

		// HTML>BODY
		$subject = '<!DOCTYPE html><html><body><div>test</div></body></html>';
		$source = $reflector->_preloadString->invoke($this->object, $subject);
		$this->assertEquals($source, '<!DOCTYPE html><html><head><meta http-equiv="content-type" content="text/html; charset=utf8" /></head><body><div>test</div></body></html>');
		$this->assertEquals($reflector->_root->getValue($this->object), 'html');
		$this->assertEquals($reflector->_doctype->getValue($this->object), '<!DOCTYPE html>');
		$this->assertEquals($reflector->_content_type->getValue($this->object), null);
		$this->assertEquals($reflector->_html_exists->getValue($this->object), 1);
		$this->assertEquals($reflector->_head_exists->getValue($this->object), 0);
		$this->assertEquals($reflector->_body_exists->getValue($this->object), 1);

		// HEAD only
		$subject = '<head><meta http-equiv="content-type" content="text/html; charset=Shift_JIS"><title>test</title></head>';
		$source = $reflector->_preloadString->invoke($this->object, $subject);
		$this->assertEquals($source, '<html><head><meta http-equiv="content-type" content="text/html; charset=utf8" /><title>test</title></head></html>');
		$this->assertEquals($reflector->_root->getValue($this->object), 'head');
		$this->assertEquals($reflector->_doctype->getValue($this->object), null);
		$this->assertEquals($reflector->_content_type->getValue($this->object), '<meta http-equiv="content-type" content="text/html; charset=Shift_JIS" />');
		$this->assertEquals($reflector->_html_exists->getValue($this->object), 0);
		$this->assertEquals($reflector->_head_exists->getValue($this->object), 1);
		$this->assertEquals($reflector->_body_exists->getValue($this->object), 0);

		// BODY only
		$subject = '<body><div>test</div></body>';
		$source = $reflector->_preloadString->invoke($this->object, $subject);
		$this->assertEquals($source, '<html><head><meta http-equiv="content-type" content="text/html; charset=utf8" /></head><body><div>test</div></body></html>');
		$this->assertEquals($reflector->_root->getValue($this->object), 'body');
		$this->assertEquals($reflector->_doctype->getValue($this->object), null);
		$this->assertEquals($reflector->_content_type->getValue($this->object), null);
		$this->assertEquals($reflector->_html_exists->getValue($this->object), 0);
		$this->assertEquals($reflector->_head_exists->getValue($this->object), 0);
		$this->assertEquals($reflector->_body_exists->getValue($this->object), 1);

		// none BODY
		$subject = '<div>test</div>';
		$source = $reflector->_preloadString->invoke($this->object, $subject);
		$this->assertEquals($source, '<html><head><meta http-equiv="content-type" content="text/html; charset=utf8" /></head><body><div>test</div></body></html>');
		$this->assertEquals($reflector->_root->getValue($this->object), null);
		$this->assertEquals($reflector->_doctype->getValue($this->object), null);
		$this->assertEquals($reflector->_content_type->getValue($this->object), null);
		$this->assertEquals($reflector->_html_exists->getValue($this->object), 0);
		$this->assertEquals($reflector->_head_exists->getValue($this->object), 0);
		$this->assertEquals($reflector->_body_exists->getValue($this->object), 0);
	}

	function test_preloadstring_escape() {
		$preload = new ReflectionMethod('DOMCompiler', '_preloadString');
		$save = new ReflectionMethod('DOMCompiler', '_save');
		$preload->setAccessible(true);
		$save->setAccessible(true);

		// attr: native_php, simple_php
		// native_php, simple_php
		// simple_php_escaped
		$subject = '<html><body id="${$attr_simple_php}" class="<?php echo $attr_native_php; ?>">\${hello!}${$simple_php}<?php echo $native_php; ?></body></html>';
		$source = $preload->invoke($this->object, $subject);
		$source = preg_replace('/.*?(<body[^>]*>.*?<\/body>).*$/', '$1', $source);
		$this->assertEquals($source, '<body id="%@CANDY:phpblock=0%" class="%@CANDY:phpblock=1%">${hello!}<php><![CDATA[echo $simple_php;]]></php><php><![CDATA[echo $native_php;]]></php></body>');

		$source = $save->invoke($this->object, $source);
		$source = preg_replace('/.*?(<body[^>]*>.*?<\/body>).*$/', '$1', $source);
		$this->assertEquals($source, '<body id="<?php echo $attr_simple_php; ?>" class="<?php echo $attr_native_php; ?>">${hello!}<?php echo $simple_php; ?><?php echo $native_php; ?></body>');
	}

	function test_smarty_interchangeable() {
		$this->markTestIncomplete();
	}
}

?>
