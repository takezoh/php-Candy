<?php

require_once('src/TemplateFunction.php');

class TemplateFunctionTest extends PHPUnit_Framework_TestCase
{
	protected $object;

	function test_clouser() {
		new TemplateFunction('test', create_function('','return "test1";'));
		$t = new TemplateFunction('test', create_function('$res, $base','return array($res, "test2", $base->__invoke());'));
		$ret = $t->__invoke('do');
		$this->assertEquals($ret, array('do', 'test2', 'test1'));
	}
}
?>
