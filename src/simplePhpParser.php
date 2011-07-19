<?php

class simplePhpParser {

	private $lexer = null;
	private $stack = array();
	private $status = array('global');

	function __construct() { }
	function status($status=null) {
		if (is_null($status)) {
			if ($cnt = count($this->status)) {
				return $this->status[$cnt - 1];
			}
			return null;
		}
		if (is_int($status) && $status < 0) {
			array_splice($this->status, -1, $status * -1);
			return null;
		}
		$this->status[] = $status;
	}
	function push($token, $parser=null) {
		if (is_string($token)) {
			$this->stack[] = $token;
		} else {
			if (is_null($parser)) $parser = $token->type;
			if ($ret = $this->{'parse_'. $parser}($token)) {
				$this->stack[] = $ret;
			}
		}
	}
	function pop($n=null) {
		$poped = array_splice($this->stack, ($n?$n:1) * -1);
		if (is_null($n)) {
			return $poped[0];
		}
		return $poped;
	}
	function parse($stack, $statement=null) {
		if (is_null($statement)) {
			$statement = 'program';
		}
		$this->stack = array();
		return $this->{'parse_'.$statement}($stack);
	}
	function parse_program($stack) {
		foreach ($stack as $token) {
			$this->{'parse_'.$token->type}($token);
		}
		if (isset($this->stack[0])) {
			return preg_replace('/^\(\s*(.*)\s*\)$/', '$1', $this->stack[0]);
		}
		return null;
	}
	function parse_identifier($stack) {
		$identifier = array();
		foreach ((array)$stack->value as $value) {
			if (is_string($value)) {
				// $this->parse_string($value);
				// $identifier[] = $this->pop();
				$identifier[] = $value;
			} else {
				$this->push($value);
				$identifier[] = $this->pop();
			}
		}
		$this->push(join('->', $identifier));
	}

	function parse_object($stack) {
		$this->status('object');
		$this->push($stack, 'identifier');
		$object = $this->pop();
		if (!preg_match('/^\$/', $object)) {
			$object = '$'. $object;
		}
		$this->push($object);
		$this->status(-1);
	}

	function parse_array($array) {
		$indexes = array();
		// $this->status('array');
		$this->status('array_index');
		foreach ((array)$array->value as $value) {
			$this->push($value);
			$indexes[] = $this->pop();
		}
		$this->status(-1);
		$extra = null;
		if ($array->extra) {
			$this->status('extra');
			$this->push($array->extra, 'identifier');
			$extra = '->'. $this->pop();
			$this->status(-1);
		}
		$this->push($array->identifier.'['.join('][', $indexes).']'.$extra);
	}

	function parse_function($function) {
		$args = array();
		// $this->status('function');
		$this->status('function_arguments');
		foreach ((array)$function->args as $arg) {
			$this->push($arg);
			$args[] = $this->pop();
		}
		$args = join(',', $args);
		$this->status(-1);
		$extra = null;
		if ($function->extra) {
			$this->status('extra');
			$this->push($function->extra, 'identifier');
			$extra = '->'. $this->pop();
			$this->status(-1);
		}
		// $this->push($function->method.'('.$args.')'.$extra);
		$cb_obj = '$'.Candy::USER_FUNC_PREFIX.$function->method;
		$cb_func = 'call_user_func('.$cb_obj. (strlen($args)?','.$args:'') .')';
		$php_func = '(function_exists(\''.$function->method.'\')?'.$function->method.'('.$args.'):null)';
		$this->push('(isset('.$cb_obj.')?'.$cb_func.':'.$php_func.')'.$extra);
	}

	function parse_string($stack) {
		$value = $stack->value;
		switch ($this->status()) {
		case 'global':
		case 'array_index':
		case 'function_arguments':
			if (!preg_match('/^\$|\'|"/', $value)) {
				$value = "'$value'";
			}
		}
		$this->push($value);
	}
	function parse_digit($stack) {
		$this->push($stack->value);
	}
	function parse_number($stack) {
		$this->push($stack->value);
	}

	function parse_operator($stack) {
		$expression = null;
		switch ($stack->value) {
		case ':':
			$this->push(':');
			break;
		case '?':
			$operand = $this->pop(4);
			$expression = $operand[0] .'?'. $operand[1] .':'. $operand[2];
			break;
		default:
			$operand = $this->pop(2);
			$expression = $operand[0]. $stack->value . $operand[1];
		}
		if ($expression) {
			$this->push('('.$expression.')');
		}
	}
}



/*
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
					'(is_callable(\''.$token.'\')?'.$ex.$token.'('.$args.')'.
					':null))';
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
 */
?>
