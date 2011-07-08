<?php

class PHPCompilers {

	// php:content
	function nodelist_compiler_content($elements, $compiler) {
		$elements->_empty();
		foreach ($elements as $element) {
			$element->before($compiler->php('$='.$compiler->PHPParse($element->attr('php:content')).';'));
			$element->phpwrapper('if', '(bool)$');
			$element->append($compiler->php('echo $;'));
		}
	}

	// php:replace
	function nodelist_compiler_replace($elements, $compiler) {
		foreach ($elements as $element) {
			$element->before($compiler->php('$='. $compiler->PHPParse($element->attr('php:replace')).';'));
			$element->phpwrapper('if', '(bool)$');
			$element->before($compiler->php('echo $;'));
		}
		$elements->remove();
	}

	// php:foreach ~ foeachelse
	function nodelist_compiler_foreach($elements, $compiler) {
		$else = $elements->_next();
		foreach ($elements as $element) {
			if (preg_match('/^(.*?)\s+as\s*(\$[A-Za-z_]\w*)(?:\s*=>\s*(\$[A-Za-z_]\w*))?$/', $element->attr('php:foreach'), $matched)) {
				$var = $compiler->PHPParse(trim($matched[1]));
				$key = $matched[2];
				$val = $matched[3];

				extract((array)$this->node_compiler_cycle($element, $compiler));
				$element->phpwrapper('if', 'count((array)'.$var.')');
				if ($init_cycle) $element->before($init_cycle);
				$element->phpwrapper('foreach', '(array)'.$var.' as '.$key.($val ? ' => '.$val:''));
				if ($do_cycle) $element->before($do_cycle);
			}
		}
		foreach ($else as $else) {
			if (!is_null($else->attr('php:foreachelse'))) {
				$else->phpwrapper('else');
			}
		}

		$elements->removeAttr('php:while');
	}

	// php:while
	function nodelist_compiler_while($elements, $compiler) {
		foreach ($elements as $element) {
			extract((array)$this->node_compiler_cycle($element, $compiler));
			if ($init_cycle) $element->before($init_cycle);
			$element->phpwrapper('while', '(bool)('. $compiler->PHPParse($element->attr('php:while')).')');
			if ($do_cycle) $element->before($do_cycle);
		}
	}

	// php:cycle
	function node_compiler_cycle($element, $compiler) {
		if ($cycle = $element->attr('php:cycle')) {
			if (preg_match_all('/\((.*)\)\s*as\s*(\$[^\s]+)/i', $cycle, $results)) {
				$vars = preg_split('/\s*,\s*/', $results[1][0]);
				foreach ($vars as &$var) {
					if ($var) {
						$var = $compiler->PHPParse($var);
					} else {
						$var = "''";
					}
				}
				$init_cycle = $compiler->php('$:candy_cycle_count=0;$:candy_cycle_vars=array('. join(',', $vars) .');');
				$do_cycle = $compiler->php('if((int)$:candy_cycle_count>='.count($vars).')$:candy_cycle_count=0;'.$results[2][0].'=$:candy_cycle_vars[$:candy_cycle_count++];');
				return compact('init_cycle', 'do_cycle');
			}
		}
		return false;
	}

	// php:if ~ elseif ~ else
	function nodelist_compiler_if($elements, $compiler) {
		foreach ($elements as $element) {
			$wrapper = $element->phpwrapper('if', '(bool)('. $compiler->PHPParse($element->attr('php:if')) .')');
			while (true) {
				$element = $wrapper->_next();
				if (is_null($if = $element->attr('php:elseif'))) {
					$if = $element->attr('php:else');
				}
				$element->removeAttr(array('php:elseif', 'php:else'));

				if ($if) {
					$wrapper = $element->phpwrapper('else if', '(bool)('. $compiler->PHPParse($if) .')');
				} else if (!is_null($if)) {
					$element->phpwrapper('else');
					break;
				} else {
					break;
				}
			}
		}
	}

	// php:attrs
	function nodelist_compiler_attrs($elements, $compiler) {
		foreach ($elements as $element) {
			foreach (explode(',', $element->attr('php:attrs')) as $value) {
				list($name, $value) = explode('=', $value, 2);
				if (!empty($value)) {
					$element->attrPHP(trim($name), 'echo '. $compiler->PHPParse(trim($value)) .';');
				}
			}
		}
	}

	// php:period
	function nodelist_compiler_period($elements, $compiler) {
		foreach ($elements as $element) {
			list($start, $finish) = explode(',', $element->attr('php:period'));
			$element->before($compiler->php('$:candy_period_now=time();$:candy_period_start=@strtotime("'.trim($start).'");$:candy_period_finish=@strtotime("'.trim($finish).'");'));
			$element->phpwrapper('if', '(!$:candy_period_start || $:candy_period_start <= $:candy_period_now) && (!$:candy_period_finish || $:candy_period_finish >= $:candy_period_now)');
		}
	}

	// php:attribute
	function nodelist_compiler_attribute($elements, $compiler) {
		$name = preg_replace('/^\*\[php:(.*)\]$/', '$1', $elements->query);
		foreach ($elements as $element) {
			$value = $element->attr('php:'.$name);
			if (!empty($value)) {
				$element->attrPHP($name, 'echo '. $compiler->PHPParse($value) .';');
			}
		}
	}

}

?>
