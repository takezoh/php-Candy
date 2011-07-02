<?php

class SimplePhpParser {

	private $_operators = array();
	private $_functions = array();

	function __construct() {
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
				$args = null;
				if (preg_match('/^\!/', $token)) {
					$ex = '!';
					$token = substr($token, 1);
				}
				$args = substr($this->parse($matched[3]), 1, -1);
				return '(isset($'.Candy::USER_FUNC_PREFIX.$token.')?'.
					$ex.'call_user_func($'.Candy::USER_FUNC_PREFIX.$token.($args?','.$args:'').'):'.
					'(is_callable(\''.$token.'\')?'.$ex.$token.'('.$args.'):null))';
				// if (array_key_exists(self::USER_FUNC_PREFIX.$token, $this->_functions)) {
					// return '(isset($'. self::USER_FUNC_PREFIX .$token.')&&method_exists($'. self::USER_FUNC_PREFIX .$token.',\'__invoke\')?'. $ex.'$'.self::USER_FUNC_PREFIX.$token.'->__invoke'.$this->parse($matched[3]).':null)';
				// }
				// return '(!is_callable(\''.$token.'\')?null:'.$ex.$token.$this->parse($matched[3]).')';
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
