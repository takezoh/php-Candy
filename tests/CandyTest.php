<?php

require_once('src/Candy.php');

class candyTest extends PHPUnit_Framework_TestCase
{
	protected $object;

	protected function setUp()
	{
		$this->object = new Candy(array(
			'cache.use' => true,
			'cache.directory' => './sample/cache',
			'template.directory' => './sample/template',
		));
	}

	protected function tearDown()
	{
		@unlink('./sample/cache/*');
	}

	function test_user_func_prefix() {
		$this->assertEquals(SimplePhpParser::USER_FUNC_PREFIX, Candy::USER_FUNC_PREFIX);
	}

	/**
	 * @dataProvider variable_set_get_provider
	 */
	function test_variable_set_get($var) {
		$this->object->assign('var', $var);
		$this->assertEquals($this->object->get_var('var'), $var);
	}

	/**
	 * @depends test_variable_set_get
	 */
	function test_variable_ref() {
		$var = 'variable_ref_test';
		$this->object->assign('ref', &$var);
		$var = 'reftest';
		$this->assertEquals($this->object->get_var('ref'), 'reftest');
	}

	function variable_set_get_provider() {
		return array(
			array('a'),
			array(array(1, '2', 3)),
			array((object)array('abc'=>1234)),
		);
	}

	function test_method_preloadString() {
		$reflector = (object) null;
		$reflector->_preloadString = new ReflectionMethod('Candy', '_preloadString');
		$reflector->_preloadString->setAccessible(true);
		foreach (array('_root', '_doctype', '_content_type', '_html_exists', '_head_exists', '_body_exists') as $param) {
			$reflector->{$param} = new ReflectionProperty('Candy', $param);
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

	function test_smarty_interchangeable() {
		$this->markTestIncomplete();
	}
}
?>
