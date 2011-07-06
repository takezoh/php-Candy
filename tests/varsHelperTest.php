<?php

require_once('src/varsHelper.php');

class varsHelperTest extends PHPUnit_Framework_TestCase {

	protected $object;

	function setUp() {
		$this->object = new varsHelper();
	}

	function tearDown() {
	}

	function test() {
		$this->object->test = 'abc';
		$this->assertEquals($this->object->get_var('test'), 'abc');
		$export = $this->object->export();
		foreach ($export as $key => &$var) {
			$$key =& $var;
		}
		$this->object->assign('test', '123');
		$this->assertEquals($test, '123');
		$test = 'bbb';
		$this->assertEquals($this->object->test, 'bbb');
	}
}
?>
