<?php

class DOMCompiler {

	protected $_config = null;
	protected $_regexp = array(
		'open_tag' => '<\s*%s(\s+(?:(?:\'.*?(?<!\\\\)\')|(?:".*?(?<!\\\\)")|[^>])*)?>',
		'simple_php' => '(?<!\\\\)\${((?:(?:([\'"]).*?(?<!\\\\)\2)|[^}])*)}',
		'simple_php_escaped' => '\\\\(\${(?:(?:([\'"]).*?(?<!\\\\)\2)|[^}])*})',
		'native_php' => '<\?php\s*((?:([\'"]).*?(?<!\\\\)\2|.)*?)\s*\?>',
	);

	protected $_php_parser = null;
	protected $_compile_triggers = array();
	protected $_compilers = array();

	protected $_phpcode = array();
	protected $_smarty_exclude = array();
	protected $_smarty_header = null;

	function __construct($config) {
		$this->_config = $config;
		$this->_php_parser = new SimplePhpParser();
	}

	public function add_compiler($expr, $callback) {
		$this->_compile_triggers[] = $expr;
		$this->_compilers[$expr] = $callback;
	}

	protected function _preload($source) {
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
			$name = sprintf(Candy::ATTR_DUMMY_NAME, trim($matched[1]), $name);
			$this->_compile_triggers[] = $name;
		}
		$value = $matched[3];
		$value = preg_replace_callback('/'.$this->_regexp['native_php'].'/', array($this, '_cb_attr_native_php'), $value);
		$value = preg_replace_callback('/'.$this->_regexp['simple_php'].'/', array($this, '_cb_attr_simple_php'), $value);
		return ' '. $name .'='. $value;
	}
	protected function _cb_attr_native_php($matched) {
		if (isset($matched[1])) {
			return $this->add_phpcode($matched[1], 'phpset');
		}
		return null;
	}
	protected function _cb_attr_simple_php($matched) {
		if (isset($matched[1])) {
			return $this->add_phpcode('echo '. $this->_php_parser->parse($matched[1]).';', 'phpset');
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
			if ($matched[1] === 'phpset') {
				return '<?php '. $php .' ?>';
			}
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

	// compiler
	public function compile($source) {
		$query = new candyQuery($this->_preload(trim($source)), $this);

		foreach (array_unique($this->_compile_triggers) as $expr) {
			$is_nscompiler = false;
			if (preg_match('/^(\w+):([\w\-]+)$/', $expr, $matched)) {
				$ns = $matched[1];
				$name = $matched[2];
				$is_nscompiler = true;
			}
			if (isset($this->_compilers[$expr])) {
				$compiler =& $this->_compilers[$expr];
			} else if ($is_nscompiler && isset($this->_compilers[$ns.':*'])) {
				$compiler =& $this->_compilers[$ns.':*'];
			}
			if ($is_nscompiler) {
				$expr = '//*[@'. $ns .':'. $name .']';
			}
			$elements = $query->query($expr, $context);
			if (is_callable($compiler) && $elements->length) {
				call_user_func($compiler, $elements, $this);
			}
			if ($is_nscompiler) {
				$elements->removeAttr($ns.':'.$name);
			}
		}
		return  array(
			'source' => $query->save(),
			'smarty_header' => $this->_smarty_header,
		);
	}

	// php parser
	public function PHPParse($code) {
		return $this->_php_parser->parse($code);
	}
}

?>
