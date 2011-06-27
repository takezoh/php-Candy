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

}
?>
