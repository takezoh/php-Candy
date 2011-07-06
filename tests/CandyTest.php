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

	function test() {
	}

}
?>
