<?php

class DOMCompiler {

	protected $_config = null;
	protected $_regexp = array(
		'open_tag' => '<\s*%s(\s+(?:(?:\'.*?(?<!\\\\)\')|(?:".*?(?<!\\\\)")|[^>])*)?>',
		'close_tag' => '<\s*\/\s*%s\s*>',
		'simple_php' => '(?<!\\\\)\${((?:(?:([\'"]).*?(?<!\\\\)\2)|[^}])*)}',
		'simple_php_escaped' => '\\\\(\${(?:(?:([\'"]).*?(?<!\\\\)\2)|[^}])*})',
		'native_php' => '<\?php\s*((?:([\'"]).*?(?<!\\\\)\2|.)*?)\s*\?>',
	);

	protected $_php_parser = null;
	protected $_compile_triggers = array();
	protected $_xml_compilers = array();
	protected $_ns_compilers = array();

	protected $_phpcode = array();
	protected $_smarty_exclude = array();
	protected $_smarty_header = null;

	protected $_root = null;
	protected $_doctype = null;
	protected $_content_type = null;
	protected $_html_exists = false;
	protected $_head_exists = false;
	protected $_body_exists = false;

	function __construct($config) {
		$this->_config = $config;
		$this->_php_parser = new SimplePhpParser();
	}

	public function add_compiler($selector, $callback) {
		// if ($selector && is_callable($callback)) {
			if (preg_match('/^(\w+):([\w\-\*]+)$/', $selector, $matched)) {
				$this->_ns_compilers[$matched[1]][$matched[2]] = $callback;
			} else {
				$this->_xml_compilers[$selector] = $callback;
			}
			// return true;
		// }
		// return false;
	}

	protected function _preloadString($source, $encoding='UTF-8') {
		// remove BOM
		if ('efbbbf' === strtolower(join('', unpack('H*', substr($source, 0, 3))))) {
			$source = substr($source, 3);
		}
		$source = preg_replace('/<\!--.*?-->|\t/s', '', $source);
		$source = preg_replace('/\r\n|\r/s', "\n", trim($source));
		// $source = mb_convert_encoding($source, 'HTML-ENTITIES', $encoding);

		// get Doctype
		$this->_doctype = null;
		if (preg_match('/('.sprintf($this->_regexp['open_tag'], '\!\s*DOCTYPE').')(.*)$/si', $source, $doctype)) {
			$source = trim($doctype[3]);
			$this->_doctype = $doctype[1];
		}
		if ($this->_html_exists = preg_match('/'.sprintf($this->_regexp['open_tag'].'(.*?)'.$this->_regexp['close_tag'], 'html', 'html').'/si', $source, $html)) {
			$source = trim($html[2]);
		}
		// get Header
		$this->_content_type = null;
		if ($this->_head_exists = preg_match('/^(.*?)('.sprintf($this->_regexp['open_tag'].'(.*?)'.$this->_regexp['close_tag'], 'head', 'head').')(.*)$/si', $source, $head)) {
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
		if ($this->_body_exists = preg_match('/'.sprintf($this->_regexp['open_tag'].'(.*?)'.$this->_regexp['close_tag'], 'body', 'body').'/si', $source, $body)) {
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
		$source = preg_replace_callback('/'.sprintf($this->_regexp['open_tag'], '[^\/\!\-\[]\w*').'/s', array($this, '_cb_element_tags'), $source);
		// replace simple php code
		$source = preg_replace_callback('/'.$this->_regexp['simple_php'].'/s', array($this, '_cb_simple_php'), $source);
		// replace native php code
		$source = preg_replace_callback('/'.$this->_regexp['native_php'].'/s', array($this, '_cb_native_php'), $source);
		// escaped simple php code
		$source = preg_replace_callback('/'.$this->_regexp['simple_php_escaped'].'/s', array($this, '_cb_simple_php_escaped'), $source);
		// Smarty interchangeable
		if (isset($this->_config->smarty) && $this->_config->smarty instanceof Smarty) {
			$source = $this->_smarty_interchangeable($this->_config->smarty, $source);
		}
		return $source;
	}
	protected function _save($source) {
		if (preg_match('/<'. $this->_root .'[^>]*>(.*)<\/'. $this->_root .'>/s', $source, $matched)) {
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
		$source = preg_replace('/<php>(?:<\!\[CDATA\[)?(.*?)(?:\]\]>)?<\/php>/s', '<?php $1 ?>', $source);
		$source = preg_replace('/<phpblock type="(.*?)" eval="(.*?)">(.*?)<\/phpblock>/s', '<?php $1($2){ ?>$3<?php } ?>', $source);
		$source = preg_replace('/<phpblock type="(.*?)">(.*?)<\/phpblock>/s', '<?php $1{ ?>$3<?php } ?>', $source);
		$source = preg_replace_callback('/%@CANDY:[^%]+%/', array($this, 'get_phpcode'), $source);
		return $source;
	}

	protected function _cb_element_tags($matched) {
		return preg_replace_callback('/\s(?:(\w+):)?([^=]+)\s*=\s*(([\'"]).*?(?<!\\\\)\4)/i', array($this, '_cb_attr'), $matched[0]);
	}
	protected function _cb_attr($matched) {
		$name = trim($matched[2]);
		if (!empty($matched[1])) {
			$ns = trim($matched[1]);
			$this->_compile_triggers[$ns][] = $name;
			$name = 'candy-'.$ns.'_'.$name;
		}
		$value = $matched[3];
		$value = preg_replace_callback('/'.$this->_regexp['native_php'].'/', array($this, '_cb_attr_native_php'), $value);
		$value = preg_replace_callback('/'.$this->_regexp['simple_php'].'/', array($this, '_cb_attr_simple_php'), $value);
		return ' '. $name .'='. $value;
	}
	protected function _cb_attr_native_php($matched) {
		if (isset($matched[1])) {
			return $this->add_phpcode($matched[1], 'phpblock');
		}
		return null;
	}
	protected function _cb_attr_simple_php($matched) {
		if (isset($matched[1])) {
			return $this->add_phpcode('echo '. $this->_php_parser->parse($matched[1]).';', 'phpblock');
		}
		return null;
	}
	protected function _cb_native_php($matched) {
		if (!empty($matched[1])) {
			return '<php><![CDATA['. $matched[1] .']]></php>';
		}
		return null;
	}
	protected function _cb_simple_php($matched) {
		if (!empty($matched[1])) {
			return '<php><![CDATA[echo '. $this->_php_parser->parse($matched[1]) .';]]></php>';
		}
		return null;
	}
	protected function _cb_simple_php_escaped($matched) {
		if (!empty($matched[1])) {
			return htmlspecialchars($matched[1]);
		}
		return null;
	}
	public function add_phpcode($php, $type='phpcode') {
		if (is_array($php)) $php = $php[0];
		$len = count($this->_phpcode);
		$this->_phpcode[] = $php;
		return '%@CANDY:'.$type.'='.$len.'%';
	}
	public function get_phpcode($label) {
		if (is_array($label)) $label = $label[0];
		if (preg_match('/^%@CANDY:([^=]+)=([^%]+)%$/', $label, $matched)) {
			$php = $this->_phpcode[(int)$matched[2]];
			/*
			if ($matched[1] === 'phpblock') {
				return '<?php '. $php .' ?>';
			}
			 */
			return $php;
		}
		return $label;
	}

	protected function _smarty_interchangeable(&$smarty, $source) {
		// preprocess: Smarty conflict escape
		$smarty_compile_dir = $smarty->compile_dir;
		$source = preg_replace_callback('/<\s*(style|script)[^>]*>.*?<\s*\/\s*\1\s*>/is', array($this, '_cb_smarty_exclude'), $source);
		// $source = preg_replace_callback('/'.$this->_regexp['simple_php_escaped'].'/s', array($this, '_cb_smarty_exclude'), $source);
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
		$source = preg_replace_callback('/'.$this->_regexp['native_php'].'/', array($this, '_cb_native_php'), $source);
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
		// if (preg_match('/^\\\\/', $matched[0]))
			// $this->_smarty_exclude[] = $matched[1];
		// else
			$this->_smarty_exclude[] = $matched[0];
		return '%@Smarty:exclude='.$len.'%';
	}
	public function compile($source) {
		$dom = new CandyDOMController($this->_preloadString(trim($source)), (object)array('compiler'=>&$this));
		if (!$this->_root || $this->_root === 'body') {
			$context = $dom->query('//body')->get(0);
		} else {
			$context = $dom->query('//'.$this->_root)->get(0);
		}

		foreach ((array)$this->_xml_compilers as $selector => $compiler) {
			if (is_callable($compiler)) {
				$elements = $dom->query($selector, $context);
				if ($elements->length && is_callable($compiler)) {
					call_user_func($compiler, $elements, $this);
				}
			}
		}
		foreach ($this->_compile_triggers as $ns => $triggers) {
			foreach (array_unique($triggers) as $name) {
				$elements = $dom->query('//*[@'. $ns .':'. $name.']', $context);
				if ($elements->length) {
					if (isset($this->_ns_compilers[$ns][$name]) && is_callable($this->_ns_compilers[$ns][$name])) {
						call_user_func($this->_ns_compilers[$ns][$name], $elements, $this);
					} else if (isset($this->_ns_compilers[$ns]['*']) && is_callable($this->_ns_compilers[$ns]['*'])) {
						call_user_func($this->_ns_compilers[$ns]['*'], $elements, $this);
					}
					$elements->removeAttr($ns.':'.$name);
				}
			}
		}
		return  array(
			'source' => $this->_save($dom->saveHTML()),
			'smarty_header' => $this->_smarty_header,
		);
	}

	public function PHPParse($code) {
		return $this->_php_parser->parse($code);
	}
}

?>
