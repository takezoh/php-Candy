<?php

class simplePhpLexer {

	private $regexp = array(
		'program' => '{<parser><statement>(?P<sentence><expression>(?:<operator><expression>)*)}',
		'parser' => '(?:\w+:)?',
		'statement' => '(?:\w+\s+)?',
		'expression' => '(?:\(<term>(?:<operator><expression>)?\))|(?:<term>(?:<operator><expression>)?)',
		'term' => '(?P<term_function><function>)|(?P<term_factor><factor>)|(?P<term_string><string>)|(?P<term_identifier><identifier>)',
		'factor' => '<identifier>(?P<side_factor>(?:->(?:<function>|<factor>|<identifier>))|<array>)',
		'array' => '(?P<array_index>\[(?:<expression>|)\])+(?P<array_extra>->(?:<factor>|<identifier>))?',
		'function' => '<identifier>\((?P<function_arguments>(?:<expression>(?:\s*,<expression>)*)|)\)(?P<function_extra>->(?:<factor>|<identifier>))?',
		'identifier' => '(?:\+\+|\-\-)?[$_a-zA-Z]\w*(?:\+\+|\-\-)?',
		'operator' => '\?|:|\.|<relational_operator>|<arithmetic_operator>|<boolean_operator>',
		'relational_operator' => '=|==|===|\!=|\!==|<|>|<=|>=',
		'arithmetic_operator' => '(?<!\+)\+(?!\+)|(?<!\-)\-(?!\-)|\*|\/|%',
		'boolean_operator' => '&&|\|\|',
		'string' => '(?!(?:\+\+|\-\-)?\$)(?:(?:\'.*?(?<!\\\\)\')|(?:".*?(?<!\\\\)")|(?:(?<=\().*?(?=\)|,|\s))|(?:(?<=\[).*?(?=\s|\]))|(?:(?<=\{).*?(?=\s|\}))|(?:(?<=).*?(?=\s|\)|\]|}|$)))',
		'foreach' => '{\s*(?P<array_expression><expression>)\s*as\s+(?:(?P<key><identifier>)\s*=>)?\s*(?P<value><identifier>)\s*}',
		'cycle' => '{\s*\((?P<cycle_arguments><string>(?:\s*,<string>)*)\)\s*as\s*<identifier>\s*}',
		'attrs' => '{\s*(?P<attribute>(?P<name>[^=]+)\s*=(?P<value><expression>))(?P<attribute_extra>,\s*[^=]+\s*=<expression>)*\s*}',
	);

	function __construct() {}

	function lexing($code, $statement=null) {
		if (is_null($statement))
			$statement = 'program';
		if ($ret = $this->{'lex_'.$statement}($code)) {
			return $ret;
		}
		// syntax error
	}

	function lex_attrs($code) {
		$attrs = array();
		while (strlen($code) > 2) {
			if (preg_match('/^'.$this->build_lexer_query('attrs').'/', $code, $m)) {
				$code = '{'.trim(substr($m['attribute_extra'], 1)).'}';
				$attrs[] = (object) array(
					'name' => trim($m['name']),
					'value' => $this->lex_expression(trim($m['value'])),
				);
			}
		}
		return $attrs;
	}

	function lex_cycle($code) {
		if (preg_match('/^'.$this->build_lexer_query('cycle').'/', $code, $m)) {
			$identifier = trim($m['identifier']);
			$arguments = trim($m['cycle_arguments']);
			$args = array();
			while (strlen($arguments)) {
				if (preg_match('/^\('.$this->build_lexer_query('string').'/', '('.$arguments.')', $m)) {
					$args[] = trim($m['string']);
					if (($pos = strpos($arguments, ',', strlen($m['string']))) === false) {
						break;
					}
					$arguments = trim(substr($arguments, $pos+1));
				} else {
					break;
				}
			}
			return (object) array(
				'identifier' => $identifier,
				'strings' => $args,
			);
		}
		return false;
	}

	function lex_foreach($code) {
		if (preg_match('/^'.$this->build_lexer_query('foreach').'/', $code, $m)) {
			$foreach = (object) array('array_expression' => $this->lex_expression($m['array_expression']));
			if (!empty($m['key'])) $foreach->key = trim($m['key']);
			$foreach->value = trim($m['value']);
			return $foreach;
		}
		return false;
	}

	function lex_program($code) {
		if (preg_match('/'. $this->build_lexer_query('program').'/', $code, $m) && !empty($m['sentence'])) {
			return $this->lex_expression(trim($m['sentence']));
		}
		return false;
	}

	function lex_expression($code) {
		$stack = $operators = array();
		while (strlen($code)) {
			$operator = null;
			if (preg_match('/^'.$this->build_lexer_query('operator').'/', $code, $m)) {
				$operator = trim($m['operator']);
				$code = trim(substr($code, strlen($operator)));
				$operators[] = (object) array('type'=>'operator', 'value'=>$operator);
			}
			if (strlen($code) && preg_match('/^\(/', $code) && preg_match('/^'.$this->build_lexer_query('expression').'/', $code, $m)) {
				$expression = trim($m['expression']);
				$code = trim(substr($code, strlen($expression)));
				$expression = preg_replace('/^\(\s*(.*)\s*\)$/', '$1', $expression);
				$stack = array_merge($stack, (array)$this->lex_expression($expression));
				continue;
			}
			if (strlen($code) && preg_match('/^'.$this->build_lexer_query('term').'/', $code, $m)) {
				$term = trim($m['term']);
				$code = trim(substr($code, strlen($term)));
				$stack[] = $this->lex_term($m);
			}
		}
		return array_merge($stack, array_reverse($operators));
	}

	function lex_term($m) {
		foreach (array('function', 'factor', 'identifier', 'string') as $type) {
			if (isset($m['term_'.$type]) && strlen($m['term_'.$type])) {
				$value = trim($m['term_'.$type]);
				if (method_exists($this, 'lex_'.$type)) {
					return $this->{'lex_'.$type}($value);
				}
				if (is_numeric($value)) {
					if (preg_match('/^[0-9]+\.[0-9]+$/', $value)) {
						$type = 'float';
					} else {
						$type = 'digit';
					}
				}
				return (object) array('type'=>$type, 'value'=>$value);
			}
		}
	}

	function lex_factor($code) {
		if (preg_match('/^'.$this->build_lexer_query('factor').'/', $code, $m)) {
			$factor = null;
			$identifier = trim($m['identifier']);
			$side = isset($m['side_factor']) ? $m['side_factor'] : null;
			$extra = null;

			if (strlen($side)) {
				if (substr($side, 0, 2) === '->') {
					$side = substr($side, 2);
					$factor = $this->lex_object($identifier, $side);
				} else if (substr($side, 0, 1) === '[') {
					if (isset($m['array_extra'])) {
						$extra = substr($m['array_extra'], 2);
						$side= substr($side, 0, strlen($m['array_extra']) * -1);
					}
					$factor = $this->lex_array($identifier, $side, $extra);
				}
			} else {
				$factor = (object) array('type'=>'identifier', 'value'=>$identifier);
			}

			return $factor;
		}
	}

	function lex_array($identifier, $side, $extra) {
		$value = array();
		while (strlen($side)) {
			if (preg_match('/'.$this->build_lexer_query('array').'/', $side, $m)) {
				if (isset($m['expression'])) {
					$expression = $m['expression'];
					$value = array_merge($value, (array)$this->lex_expression($expression));
				}
				$side= trim(substr($side, 0, strlen($m['array_index']) * -1));
			}
		}
		if (strlen($extra) && preg_match('/^'.$this->build_lexer_query('term').'/', $extra, $m)) {
			$extra = $this->lex_term($m);
			$extra->type = 'object';
		} else {
			$extra = null;
		}
		return (object) array('type'=>'array', 'identifier'=>$identifier, 'value'=>array_reverse($value), 'extra'=>$extra);
	}

	function lex_object($identifier, $extra) {
		$value = array($identifier);
		if (preg_match('/^'.$this->build_lexer_query('term').'/', $extra, $m)) {
			$extra = $this->lex_term($m);
			if ($extra->type === 'function') {
				$extra = array($extra);
			} else {
				$extra = $extra->value;
			}
			$value = array_merge($value, (array)$extra);
		}
		return (object) array('type'=>'object', 'value'=>$value);
	}

	function lex_function($code) {
		if (preg_match('/'.$this->build_lexer_query('function').'/', $code, $m)) {
			$args = array();
			$method = $m['identifier'];
			$expression = isset($m['expression']) ? $m['expression'] : null;
			$arguments = isset($m['function_arguments']) ? $m['function_arguments'] : null;

			while (strlen($expression)) {
				$args = array_merge($args, (array)$this->lex_expression($expression));
				if (($pos = strpos($arguments, ',', strlen($expression))) === false) {
					break;
				}
				$arguments = trim(substr($arguments, $pos+1));
				$expression = null;
				if (preg_match('/^'.$this->build_lexer_query('expression').'/', $arguments, $m_expression)) {
					$expression = $m_expression[0];
				}
			}

			$extra = null;
			if (isset($m['function_extra'])) {
				if (preg_match('/^'.$this->build_lexer_query('term').'/', substr($m['function_extra'], 2), $m)) {
					$extra = $this->lex_term($m);
					$extra->type = 'object';
				}
			}
			return (object) array('type' => 'function', 'method'=>$method, 'args'=>$args, 'extra'=>$extra);
		}
	}

	function build_lexer_query($name) {
		$this->lex = $this->regexp;
		return $this->_build_lexer_query(array(null, $name));
	}
	function _build_lexer_query($m) {
		if (array_key_exists($m[1], $this->lex)) {
			$ret = $this->lex[$m[1]];
			unset($this->lex[$m[1]]);
			$ret = preg_replace_callback('/<_(\w+)_>/', array($this, '_build_lexer_inline'), $ret);
			$ret = preg_replace_callback('/(?<!P|&)<(\w+)>/', array($this, '_build_lexer_query'), $ret);
			return '\s*(?P<'. $m[1].'>'. $ret .')\s*';
		}
		return '\s*(?&'. $m[1].')\s*';
	}
	function _build_lexer_inline($m) {
		if (array_key_exists($m[1], $this->regexp)) {
			$ret = $this->regexp[$m[1]];
			$ret = preg_replace_callback('/<(\w+)>/', array($this, '_build_lexer_inline'), $ret);
			return $ret;
		}
	}
}
?>
