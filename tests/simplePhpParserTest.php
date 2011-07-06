<?php

require_once('src/simplePhpParser.php');
require_once('src/templateFunction.php');

class simplePhpParserTest extends PHPUnit_Framework_TestCase
{
	protected $object;

	protected function setUp()
	{
		// $functions = array(Candy::USER_FUNC_PREFIX.'document' => array($this, 'publicDummy'));
		// $this->object = new SimplePhpParser(&$functions);
		$this->object = new SimplePhpParser();
	}

	protected function tearDown()
	{
	}

	function test_textObject() {
		// var
		$this->assertEquals($this->object->parse('$text'), '$text');
		$this->assertEquals($this->object->parse('!$text'), '!$text');
		$this->assertEquals($this->object->parse('++$text'), '++$text');
		$this->assertEquals($this->object->parse('--$text'), '--$text');
		$this->assertEquals($this->object->parse('$text++'), '$text++');
		$this->assertEquals($this->object->parse('$text--'), '$text--');

		// object
		$this->assertEquals($this->object->parse('$text->test()'), '$text->test()');
		$this->assertEquals($this->object->parse('!$text->test(test)'), '!$text->test(\'test\')');
		$this->assertEquals($this->object->parse('$text[]'), '$text[]');
		$this->assertEquals($this->object->parse('!$text[test]'), '!$text[\'test\']');

		// numeric
		$this->assertEquals($this->object->parse('1234'), '1234');

		// text
		$this->assertEquals($this->object->parse('"$text"'), '"$text"');
		$this->assertEquals($this->object->parse('"1234"'), '"1234"');
		$this->assertEquals($this->object->parse('abcde'), "'abcde'");
		$this->assertEquals($this->object->parse('#text#'), "'#text#'");
	}

	/**
	 * @dataProvider operatorsProvider
	 */
	function test_operators($text) {
		$this->assertEquals($this->object->parse($text), ' '.$text.' ');
		$this->assertEquals($this->object->parse('"'.$text.'"'), '"'.$text.'"');
	}
	function operatorsProvider() {
		$operators = array(
			// 'as', '=>',
			'?', ':', // '(', ')', ',',
			'+', '-', '*', '/', '%',
			// '++', '--',
			'.', // '.=',
			'=',
			// '+=', '-=', '*=', '/=', '%=',
			// '&=', '|=', '^=', '<<=', '>>=',
			// '&', '|', '^', '~', '<<', '>>',
			'==', '===', '!=', '!==', '<', '>', '<=', '>=',
			// '!',
			'&&', '||',
			// 'and', 'or', 'xor',
			'true', 'false', 'null',
		);
		$ret = array();
		foreach ($operators as $operator) {
			$ret[] = (array)$operator;
		}
		return $ret;
	}

	/**
	 * @depends test_textObject
	 * @depends test_operators
	 */
	function test_user_func() {
		$php = $this->object->parse('!document()');
		$this->assertEquals($php, '(isset($'.Candy::USER_FUNC_PREFIX.'document)?!call_user_func($'.Candy::USER_FUNC_PREFIX.'document,$__candy_vars):(is_callable(\'document\')?!document():null))');
		$php = $this->object->parse('document()');
		$this->assertEquals($php, '(isset($'.Candy::USER_FUNC_PREFIX.'document)?call_user_func($'.Candy::USER_FUNC_PREFIX.'document,$__candy_vars):(is_callable(\'document\')?document():null))');
		$this->assertEquals(eval('return '. $php .';'), null);
		${Candy::USER_FUNC_PREFIX.'document'} = new TemplateFunction('document', array($this, 'publicDummy'));

		$__candy_vars = null;
		$this->assertEquals(eval('return '. $php .';'), 'Dummy');
	}

	/**
	 * @depends test_textObject
	 * @depends test_operators
	 */
	function test_global_func() {
		$php = $this->object->parse('!each($array)');
		$this->assertEquals($php, '(isset($'.Candy::USER_FUNC_PREFIX.'each)?!call_user_func($'.Candy::USER_FUNC_PREFIX.'each,$array,$__candy_vars):(is_callable(\'each\')?!each($array):null))');
		$php = $this->object->parse('each($array)');
		$this->assertEquals($php, '(isset($'.Candy::USER_FUNC_PREFIX.'each)?call_user_func($'.Candy::USER_FUNC_PREFIX.'each,$array,$__candy_vars):(is_callable(\'each\')?each($array):null))');
		$array = array('key' => 'value');
		$ret = eval('return '.$php.';');
		$this->assertEquals($ret, array(0 => 'key', 1 => 'value', 'key' => 'key', 'value' => 'value'));
	}

	/**
	 * @depends test_user_func
	 * @depends test_global_func
	 */
	function test_recursive_patterns() {
		$subject = 'document(aaa, document(each($test[b][$c->test($d[c], $c->test())][e]), ccc, $o->b()))';
		$php = $this->object->parse($subject);
		$expected = array(
			'(isset($__candy_func_document)?',
				'call_user_func($__candy_func_document,\'aaa\', (isset($__candy_func_document)?',
					'call_user_func($__candy_func_document,(isset($__candy_func_each)?',
						'call_user_func($__candy_func_each,$test[\'b\'][$c->test($d[\'c\'], $c->test())][\'e\'],$__candy_vars):',
					'(is_callable(\'each\')?',
						'each($test[\'b\'][$c->test($d[\'c\'], $c->test())][\'e\']):null)), ',
					'\'ccc\', $o->b(),$__candy_vars):(is_callable(\'document\')?',
						'document((isset($__candy_func_each)?',
							'call_user_func($__candy_func_each,$test[\'b\'][$c->test($d[\'c\'], $c->test())][\'e\'],$__candy_vars):',
						'(is_callable(\'each\')?',
							'each($test[\'b\'][$c->test($d[\'c\'], $c->test())][\'e\']):null)), ',
						'\'ccc\', $o->b()):null)),',
					'$__candy_vars):',
				'(is_callable(\'document\')?',
					'document(\'aaa\', (isset($__candy_func_document)?',
						'call_user_func($__candy_func_document,(isset($__candy_func_each)?',
							'call_user_func($__candy_func_each,$test[\'b\'][$c->test($d[\'c\'], $c->test())][\'e\'],$__candy_vars):',
						'(is_callable(\'each\')?each($test[\'b\'][$c->test($d[\'c\'], $c->test())][\'e\']):null)), ',
					'\'ccc\', $o->b(),$__candy_vars):',
				'(is_callable(\'document\')?',
					'document((isset($__candy_func_each)?',
						'call_user_func($__candy_func_each,$test[\'b\'][$c->test($d[\'c\'], $c->test())][\'e\'],$__candy_vars):',
					'(is_callable(\'each\')?each($test[\'b\'][$c->test($d[\'c\'], $c->test())][\'e\']):null)), ',
				'\'ccc\', $o->b()):null))',
			'):null))'
		);

		/*
			'(isset($'.Candy::USER_FUNC_PREFIX.'document)&&method_exists($'.Candy::USER_FUNC_PREFIX.'document,\'__invoke\')?$'.Candy::USER_FUNC_PREFIX.'document->__invoke(',
				'\'aaa\', ',
				'(isset($'.Candy::USER_FUNC_PREFIX.'document)&&method_exists($'.Candy::USER_FUNC_PREFIX.'document,\'__invoke\')?$'.Candy::USER_FUNC_PREFIX.'document->__invoke(',
					'(!is_callable(\'each\')?null:each(',
						'$test[\'b\'][$c->test($d[\'c\'], $c->test())][\'e\']',
					')), ',
					'\'ccc\', $o->b()',
				'):null)',
			'):null)',
		 */
		$this->assertEquals($php, join('', $expected));
	}

	function publicDummy() {
		return 'Dummy';
	}
}
?>
