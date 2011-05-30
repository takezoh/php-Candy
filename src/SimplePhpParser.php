<?php

class SimplePhpParser {

	const USER_FUNC_PREFIX = '__candy_func_';
	private $_operators = array();
	private $_functions = array();

	function __construct($functions=array()) {
		$this->_functions = &$functions;
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
		foreach ($operators as $operator) {
			$this->_operators[strlen($operator)][] = $operator;
		}
	}
	function parse($string) {
		return preg_replace_callback('/((?:([\'"]).*?(?<!\\\\)\2)|[^\(\)\[\],\s]+)(\((?:([\'"]).*?(?<!\\\\)\4|(?3)|[^)])*\))?/s', array($this, '_parser_text_object'), $string);
	}
	function _parser_text_object($matched) {
		if ($len = strlen($token = trim($matched[1]))) {
			if (
				preg_match('/^[\'"]/', $token) ||
				preg_match('/^(\+\+|\-\-|\!)?\$/', $token) ||
				preg_match('/^->/', $token) ||
				is_numeric($token)
			){
				return $token.(isset($matched[3]) ? $this->parse($matched[3]) : '');
			}
			if (isset($matched[3])) {
				$ex = null;
				if (preg_match('/^\!/', $token)) {
					$ex = '!';
					$token = substr($token, 1);
				}
				if (array_key_exists(self::USER_FUNC_PREFIX.$token, $this->_functions)) {
					return '(isset($'. self::USER_FUNC_PREFIX .$token.')&&method_exists($'. self::USER_FUNC_PREFIX .$token.',\'__invoke\')?'. $ex.'$'.self::USER_FUNC_PREFIX.$token.'->__invoke'.$this->parse($matched[3]).':null)';
				}
				return '(!is_callable(\''.$token.'\')?null:'.$ex.$token.$this->parse($matched[3]).')';
			}
			if (array_key_exists($len, $this->_operators) && in_array($token, $this->_operators[$len])) {
				return " ". $token ." ";
			}
			return "'". $token ."'";
		}
		return null;
	}
}
?>
