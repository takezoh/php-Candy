<?php

class DOMCompiler {

	protected $_config = null;

	protected $_php_parser = null;
	protected $_xml_compilers = array();
	protected $_php_compilers = array();
	protected $_ns_compilers = array();

	protected $_phpcode = array();
	protected $_smarty_exclude = array();
	protected $_smarty_header = null;

	protected $_root = null;
	protected $_root_element = null;
	protected $_doctype = null;
	protected $_content_type = null;
	protected $_html_exists = false;
	protected $_head_exists = false;
	protected $_body_exists = false;

	function __construct($compilers, $config) {
		$this->_config = $config;

		// Init "Compilers"
		$defaultCompilers = new CandyDefaultCompilers();
		$this->_php_compilers = array(
			'period' => array('compiler' => array($defaultCompilers, 'nodelist_compiler_period')),
			'foreach' => array('compiler' => array($defaultCompilers, 'nodelist_compiler_foreach')),
			'while' => array('compiler' => array($defaultCompilers, 'nodelist_compiler_while')),
			'if' => array('compiler' => array($defaultCompilers, 'nodelist_compiler_if')),
			'replace' => array('compiler' => array($defaultCompilers, 'nodelist_compiler_replace')),
			'content' => array('compiler' => array($defaultCompilers, 'nodelist_compiler_content')),
			'attrs' => array('compiler' => array($defaultCompilers, 'nodelist_compiler_attrs')),
			'cycle' => null,
			'foreachelse' => null,
			'elseif' => null,
			'else' => null,
		);
		foreach ($compilers as $compiler) {
			$this->add_compiler($compiler['selector'], $compiler['callback'], $compiler['args']);
		}

		// PHP Parser instance
		$this->_php_parser = new SimplePhpParser($this->_functions);
	}

	public function add_compiler($selector, $callback, $args=null) {
		if ($selector && is_callable($callback)) {
			if (preg_match('/^(\w+):([\w\-]+)$/', $selector, $matched)) {
				$this->_ns_compilers[$matched[1]][$matched[2]] = array_merge((array)$args, array('compiler' => $callback));
			} else {
				$this->_xml_compilers[$selector] = array_merge((array)$args, array('compiler' => $callback));
			}
			return true;
		}
		return false;
	}

	protected function _preloadString($source, $encoding='UTF-8') {
		// remove BOM
		if ('efbbbf' === strtolower(join('', unpack('H*', substr($source, 0, 3))))) {
			$source = substr($source, 3);
		}
		$open_tag_regexp = '<\s*%s(\s+(?:(?:\'.*?(?<!\\\\)\')|(?:".*?(?<!\\\\)")|[^>])*)?>';
		$close_tag_regexp = '<\s*\/\s*%s\s*>';
		$source = preg_replace('/<\!--.*?-->|\t/s', '', $source);
		$source = preg_replace('/\r\n|\r/s', "\n", trim($source));
		// $source = mb_convert_encoding($source, 'HTML-ENTITIES', $encoding);

		// get Doctype
		$this->_doctype = null;
		if (preg_match('/('.sprintf($open_tag_regexp, '\!\s*DOCTYPE').')(.*)$/si', $source, $doctype)) {
			$source = trim($doctype[3]);
			$this->_doctype = $doctype[1];
		}
		if ($this->_html_exists = preg_match('/'.sprintf($open_tag_regexp.'(.*?)'.$close_tag_regexp, 'html', 'html').'/si', $source, $html)) {
			$source = trim($html[2]);
		}
		// get Header
		$this->_content_type = null;
		if ($this->_head_exists = preg_match('/^(.*?)('.sprintf($open_tag_regexp.'(.*?)'.$close_tag_regexp, 'head', 'head').')(.*)$/si', $source, $head)) {
			$head_dom = new DOMDocument();
			$head_dom->loadHTML($head[2]);
			$head_xpath = new DOMXpath($head_dom);
			if ($meta = $head_xpath->query('//meta[@http-equiv]')) {
				foreach ($meta as $meta) {
					if (strtolower($meta->getAttribute('http-equiv')) === 'content-type') {
						$this->_content_type = '<meta http-equiv="content-type" content="'. $meta->getAttribute('content') .'" />';
						$meta->parentNode->removeChild($meta);
						break;
					}
				}
			}
			$head[0] = trim(preg_replace(
				'/^.*?(<head[^>]*>)(.*?)<\s*\/\s*head\s*>.*$/is',
				'$1<meta http-equiv="content-type" content="text/html; charset=utf8" />$2</head>',
				html_entity_decode($head_dom->saveHTML())
			));
			$source = $head[1] . $head[5];
		}
		// get Body
		if ($this->_body_exists = preg_match('/'.sprintf($open_tag_regexp.'(.*?)'.$close_tag_regexp, 'body', 'body').'/si', $source, $body)) {
			$source = trim($body[2]);
		}

		// restore source
		if ($this->_body_exists || !$this->_head_exists) {
			$source = '<body'. (!empty($body[1]) ? ' '. trim($body[1]) : '') .'>'. $source .'</body>';
		}
		$source = $this->_head_exists ? $head[0] . $source : '<head><meta http-equiv="content-type" content="text/html; charset=utf8" /></head>'. $source;
		// $source = $this->_head_exists ? $head[0] . $source : '<head></head>'. $source;
		$source = '<html'. (!empty($html[1]) ? ' '. trim($html[1]) : '') .'>'. $source .'</html>';
		// $source = $this->_doctype ? $this->_doctype . $source : '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">'. $source;
		$source = $this->_doctype ? $this->_doctype . $source : $source;

		// DOM: ContextNode
		$this->_root = null;
		if ($this->_body_exists && $this->_head_exists || $this->_html_exists) {
			$this->_root = 'html';
		} else if ($this->_body_exists) {
			$this->_root = 'body';
		} else if ($this->_head_exists) {
			$this->_root = 'head';
		}

		// replace extended attributes
		$this->nslist = join('|', array_merge((array)'php', (array)array_keys($this->_ns_compilers)));
		$source = preg_replace_callback('/'.sprintf($open_tag_regexp, '[^\/\!\-\[]\w*').'/s', array($this, '_cb_open_tags'), $source);
		// replace inner template code
		$ctl_statement = 'if|elseif|else|\/if|foreach|foreachelse|\/foreach|while|\/while';
		$source = preg_replace_callback('/(?<!\\\\)\${(?:\s*('.$ctl_statement.')\s*)?((?:(?:([\'"]).*?(?<!\\\\)\2)|[^}])*)}/s', array($this, '_cb_php_innercode'), $source);
		// replace native php code
		$source = preg_replace_callback('/<\?php\s*((?:([\'"]).*?(?<!\\\\)\2|.)*?)\s*\?>/s', array($this, '_cb_php_native'), $source);
		// Smarty interchangeable
		if ($this->_config->smarty instanceof Smarty) {
			$source = $this->_smarty_interchangeable($this->_config->smarty, $source);
		}
		// escaped template code
		$source = preg_replace('/\\\\(\${(?:(?:([\'"]).*?(?<!\\\\)\2)|[^}])*})/s', '$1', $source);
		return $source;
	}
	protected function _save() {
		if ($this->dom) {
			$source = $this->dom->saveHTML();
			if (preg_match('/<'. $this->_root_element->tagName .'[^>]*>(.*)<\/'. $this->_root_element->tagName .'>/s', $source, $matched)) {
				$source = $matched[$this->_root ? 0 : 1];
			}
			if (!$this->_head_exists) {
				$source = preg_replace('/<head[^>]*>.*?<\/head>/s', '', $source);
			} else {
				$source = preg_replace('/<meta.*?http-equiv\s*=\s*([\'"])\s*content-type\s*\1[^>]*>/', $this->_content_type, $source);
			}
			if ($this->_html_exists && $this->_doctype) {
				$source = $this->_doctype . $source;
			}
			return $source;
		}
		return null;
	}

	protected function _cb_open_tags($matched) {
		// return preg_replace_callback('/\s('.$this->nslist.'):([^=]+)/i', array($this, '_cb_php_triggers'), $matched[0]);
		return preg_replace_callback('/\s(\w+):([^=]+)/i', array($this, '_cb_php_triggers'), $matched[0]);
	}
	protected function _cb_php_triggers($matched) {
		if ($matched[1] === 'php' && !array_key_exists($matched[2], $this->_php_compilers)) {
			$this->_php_compilers[$matched[2]] = array('name' => $matched[2]);
		}
		return ' candy-'. $matched[1]. '_'. $matched[2];
	}
	public function add_phpcode($php, $type='phpcode') {
		$len = count($this->_phpcode);
		$this->_phpcode[] = $php;
		return '%@CANDY:'.$type.'='.$len.'%';
	}
	public function get_phpcode($label) {
		if (is_array($label)) $label = $label[0];
		if (preg_match('/^%@CANDY:([^=]+)=([^%]+)%$/', $label, $matched)) {
			$php = $this->_phpcode[(int)$matched[2]];
			if ($matched[1] === 'phpblock') {
				return '<?php '. $php .' ?>';
			}
			return $php;
		}
		return $label;
	}

	protected function _cb_php_native($matched) {
		$len = count($this->_php_code);
		$this->_php_code[] = $matched[1];
		return '%@CANDY:phpcode='. $len .'%';
	}
	protected function _cb_php_innercode($matched) {
		if (!empty($matched[2])) {
			$len = count($this->_php_code);
			$this->_php_code[] = 'echo '. $this->_php_parser->parse($matched[2]) .';';
			return '%@CANDY:phpcode='. $len .'%';
		}
		return null;
	}

	protected function _smarty_interchangeable(&$smarty, $source) {
		// preprocess: Smarty conflict escape
		$smarty_compile_dir = $smarty->compile_dir;
		$source = preg_replace_callback('/<\s*(style|script)[^>]*>.*?<\s*\/\s*\1\s*>/is', array($this, '_cb_smarty_exclude'), $source);
		$source = preg_replace_callback('/\\\\(\${(?:(?:([\'"]).*?(?<!\\\\)\2)|[^}])*})/s', array($this, '_cb_smarty_exclude'), $source);
		$source = preg_replace_callback('/<\s*a\s+(.*?)href\s*=\s*([\'"])\s*(javascript\s*:.*?)(?<!\\\\)\2([^>]*)>/is', array($this, '_cb_smarty_exclude'), $source);
		// mainprocess: Smarty compile resource
		$resource = $this->_config->cache->directory.'/'.uniqid();
		file_put_contents($resource, $source);
		$smarty->compile_dir = $this->_config->cache->directory;
		$smarty->_compile_resource($resource, $resource);
		if (preg_match('/^\s*<\?php\s*\/\*\s*(.*?)\s*\*\/\s*\?>(.*)$/si', file_get_contents($resource), $matched)) {
			$this->_smarty_header = $matched[1];
			$source = trim($matched[2]);
			$source = preg_replace('/\$this->_tpl_vars\[([\'"])(.*?)(?<!\\\\)\1\]/si', '\$$2', $source);
		}
		// postprocess
		$smarty->compile_dir = $smarty_compile_dir;
		$source = preg_replace_callback('/%@Smarty:exclude=([0-9]+)%/', array($this, '_cb_smarty_exclude_rep'), $source);
		$source = preg_replace_callback('/\s*<\?php\s*((?:([\'"]).*?(?<!\\\\)\2|.)*?)\s*\?>\s*/s', array($this, '_cb_php_native'), $source);
		unlink($resource);
		return $source;
	}
	protected function _cb_smarty_exclude_rep($matched) {
		return $this->_smarty_exclude[$matched[1]];
	}
	protected function _cb_smarty_exclude($matched) {
		$len = count($this->_smarty_exclude);
		if ($matched[3]) {
			$this->_smarty_exclude[] = $matched[3];
			return '<a '. trim($matched[1]) .' href='.$matched[2].'%@Smarty:exclude='.$len.'%'.$matched[2].' '. trim($matched[4]) .'>';
		}
		if (preg_match('/^\\\\/', $matched[0]))
			$this->_smarty_exclude[] = $matched[1];
		else
			$this->_smarty_exclude[] = $matched[0];
		return '%@Smarty:exclude='.$len.'%';
	}
	public function compile($source) {
		$this->dom = new CandyDOMController($this->_preloadString(trim($source)), (object)array('compiler'=>&$this));
		if (!$this->_root || $this->_root === 'body') {
			$this->_root_element = $this->dom->query('//body')->get(0);
		} else {
			$this->_root_element = $this->dom->query('//'.$this->_root)->get(0);
		}

		// foreach ((array)$this->_xml_compilers as $selector => $compiler) {
			// if (is_callable($compiler['compiler'])) {
				// $elements = $this->dom->query($selector, $this->_root_element);
				// if ($elements->length) {
					// call_user_func($compiler['compiler'], [>$compiler,<] $elements, $this);
				// }
			// }
		// }
		// foreach ((array)$this->_ns_compilers as $ns => $compilers) {
			// foreach ((array)$compilers as $name => $compiler) {
				// if (is_callable($compiler['compiler'])) {
					// foreach ($this->dom->query('//@candy-'. $ns .'_'. $name, $this->_root_element) as $node) {
						// $value = $node->nodeValue;
						// $element = $node->parentNode;
						// $node->parentNode->removeAttributeNode($node);
						// call_user_func($compiler['compiler'], [>$compiler,<] $element, $value, $this);
					// }
				// }
			// }
		// }
		foreach ($this->_php_compilers as $name => $compiler) {
			$elements = $this->dom->query('//*[@php:'. $name.']', $this->_root_element);
			if ($elements->length) {
				if (isset($compiler['compiler']) && is_callable($compiler['compiler'])) {
					call_user_func($compiler['compiler'], $elements, $this);
				} else {
					call_user_func(array('CandyDefaultCompilers', 'nodelist_compiler_attribute'), $elements, $this);
				}
			}
		}
		$source = $this->_save();
		$source = preg_replace('/<php>(.*?)<\/php>/s', '<?php $1 ?>', $source);
		$source = preg_replace('/<phpblock type="(.*?)" eval="(.*?)">(.*?)<\/phpblock>/s', '<?php $1($2){ ?>$3<?php } ?>', $source);
		$source = preg_replace('/<phpblock type="(.*?)">(.*?)<\/phpblock>/s', '<?php $1{ ?>$3<?php } ?>', $source);
		$source = preg_replace_callback('/%@CANDY:[^%]+%/', array($this, 'get_phpcode'), $source);

		unset($this->dom);
		return  array(
			'source' => $source,
			'smarty_header' => $this->_smarty_header,
		);
	}

	public function PHPParse($code) {
		return $this->_php_parser->parse($code);
	}
}

?>
