<?php

require_once('src/SimplePhpParser.php');
require_once('src/TemplateFunction.php');

class SimplePhpParserTest extends PHPUnit_Framework_TestCase
{
	protected $object;

	protected function setUp()
	{
		$this->object = new SimplePhpParser(array(SimplePhpParser::USER_FUNC_PREFIX.'document' => array($this, 'publicDummy')));
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
		$this->assertEquals($php, '(!@method_exists($'.SimplePhpParser::USER_FUNC_PREFIX.'document,\'__invoke\')?null:!$'.SimplePhpParser::USER_FUNC_PREFIX.'document->__invoke())');
		$php = $this->object->parse('document()');
		$this->assertEquals($php, '(!@method_exists($'.SimplePhpParser::USER_FUNC_PREFIX.'document,\'__invoke\')?null:$'.SimplePhpParser::USER_FUNC_PREFIX.'document->__invoke())');
		$this->assertEquals(eval('return '. $php .';'), null);
		${SimplePhpParser::USER_FUNC_PREFIX.'document'} = new TemplateFunction('document', array($this, 'publicDummy'));
		$this->assertEquals(eval('return '. $php .';'), 'Dummy');
	}

	/**
	 * @depends test_textObject
	 * @depends test_operators
	 */
	function test_global_func() {
		$php = $this->object->parse('!each($array)');
		$this->assertEquals($php, '(!is_callable(\'each\')?null:!each($array))');
		$php = $this->object->parse('each($array)');
		$this->assertEquals($php, '(!is_callable(\'each\')?null:each($array))');
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
			'(!@method_exists($'.SimplePhpParser::USER_FUNC_PREFIX.'document,\'__invoke\')?null:$'.SimplePhpParser::USER_FUNC_PREFIX.'document->__invoke(',
				'\'aaa\', ',
				'(!@method_exists($'.SimplePhpParser::USER_FUNC_PREFIX.'document,\'__invoke\')?null:$'.SimplePhpParser::USER_FUNC_PREFIX.'document->__invoke(',
					'(!is_callable(\'each\')?null:each(',
						'$test[\'b\'][$c->test($d[\'c\'], $c->test())][\'e\']',
					')), ',
					'\'ccc\', $o->b()',
				'))',
			'))',
		);
		$this->assertEquals($php, join('', $expected));
	}

	function publicDummy() {
		return 'Dummy';
	}
}
?>
