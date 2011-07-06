<?php

require_once('src/DOMCompiler.php');
require_once('src/simplePhpParser.php');
require_once('src/varsHelper.php');

class DOMCompilerText extends PHPUnit_Framework_TestCase
{
	protected $object;

	protected function setUp()
	{
		$vars = new varsHelper();
		$smarty = null;
		$this->object = new DOMCompiler($vars, null, $smarty);
	}

	protected function tearDown()
	{
	}

	function test_preload() {
		$preload = new ReflectionMethod('DOMCompiler', '_preload');
		$save = new ReflectionMethod('DOMCompiler', '_save');
		$preload->setAccessible(true);
		$save->setAccessible(true);

		// attr: native_php, simple_php
		// native_php, simple_php
		// simple_php_escaped
		$subject = '<html><body id="${$attr_simple_php}" class="<?php echo $attr_native_php; ?>">\${hello!}${$simple_php}<?php echo $native_php; ?></body></html>';
		$source = $preload->invoke($this->object, $subject);
		$source = preg_replace('/.*?(<body[^>]*>.*?<\/body>).*$/', '$1', $source);
		$this->assertEquals($source, '<body id="%@CANDY:phpset=0%" class="%@CANDY:phpset=1%">${hello!}<php>%@CANDY:phpcode=2%</php><php>%@CANDY:phpcode=3%</php></body>');

		$source = $save->invoke($this->object, $source);
		$source = preg_replace('/.*?(<body[^>]*>.*?<\/body>).*$/', '$1', $source);
		$this->assertEquals($source, '<body id="<?php echo $attr_simple_php; ?>" class="<?php echo $attr_native_php; ?>">${hello!}<?php echo $simple_php; echo $native_php; ?></body>');
	}

	function test_smarty_interchangeable() {
		$this->markTestIncomplete();
	}
}

?>
