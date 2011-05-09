<?php
/**
 * Candy - DOM base HTML Template engine
 *
 * http://github.com/takezoh/php-Candy
 *
 * @author Takehito Gondo
 * @copyright 2011 Takehito Gondo
 * @package Candy
 * @lisence MIT License
 * @version 0.4.8
 */

include(dirname(__FILE__).'/SimplePhpParser.php');
include(dirname(__FILE__).'/TemplateFunction.php');

class Candy {

	const VERSION = "0.4.8";
	const PRIVATE_VARS_PREFIX = '__candy_';
	const USER_FUNC_PREFIX = '__candy_func_';

	private $_config = array(
		'smarty' => array(
			'interchangeable' => false,
		),
		'cache' => array(
			'use' => true,
			'directory' => './cache',
		),
		'template' => array(
			'directory' => './template',
		),
	);

	public $dom = null;
	public $xpath = null;
	public $php_parser = null;
	private $_vars = array();
	private $_filters = array();
	private $_xml_compilers = array();
	private $_php_compilers = array();
	private $_ns_compilers = array();
	private $_functions = array();

	private $_php_code = array();
	private $_smarty_exclude = array();
	private $_smarty_header = null;
	private $_root = null;
	private $_root_element = null;
	private $_doctype = null;
	private $_content_type = null;
	private $_html_exists = false;
	private $_head_exists = false;
	private $_body_exists = false;

	private $_externals = array();

	function __construct($config=array(), $_vars=array()) {
		$this->_get_external_file();
		foreach ($config as $key => $value) {
			list($type, $name) = explode("-", $key, 2);
			if (array_key_exists($type, $this->_config) && array_key_exists($name, $this->_config[$type])) {
				$this->_config[$type][$name] = $value;
			}
		}
		if (!($current_dir = getcwd())) {
			return false;
		}
		foreach (array('cache', 'template') as $conf) {
			if (preg_match('/^\.\//', $this->_config[$conf]['directory'])) {
				$this->_config[$conf]['directory'] = $current_dir .'/'. substr($this->_config[$conf]['directory'], 2);
			}
		}
		$this->_functions = array(
			self::USER_FUNC_PREFIX.'document' => new TemplateFunction('document', array($this, '_func_document')),
		);
		$this->_php_compilers = array(
			'period' => array('compiler' => array($this, '_node_compiler_php_period')),
			'foreach' => array('compiler' => array($this, '_node_compiler_php_foreach')),
			'while' => array('compiler' => array($this, '_node_compiler_php_while')),
			'if' => array('compiler' => array($this, '_node_compiler_php_if')),
			'replace' => array('compiler' => array($this, '_node_compiler_php_replace')),
			'content' => array('compiler' => array($this, '_node_compiler_php_content')),
			'attrs' => array('compiler' => array($this, '_node_compiler_php_attrs')),
			'cycle' => null,
		);
		$this->php_parser = new SimplePhpParser(&$this->_functions);
		$this->_vars =& $_vars;
	}
	public function get_template_path($filename) {
		if (preg_match('/^\//', $filename)) {
			$path = $filename;
		} else {
			$path = $this->_config['template']['directory'] .'/'. $filename;
		}
		return $path;
	}
	public function get_cachename($template_path) {
		return str_replace('/', '%', $template_path);
	}
	public function get_var($name) {
		return $this->_vars[$name];
	}
	public function fetch($tpl) {
		$tpl = $this->get_template_path($tpl);
		$cache = $this->get_cachename($tpl);
		$cache_dir = $this->_config['cache']['directory'];
		$is_compile = false;
		if (!$this->_config['cache']['use'] || (int)@filectime($cache_dir.'/'.$cache) < (int)@filectime($tpl)) {
			$is_compile = true;
		}
		if (!$is_compile) {
			$externals = $this->_get_externals_info();
			list($header,) = explode("\n\n", @file_get_contents($cache_dir.'/'.$cache), 2);
			if (preg_match('/<\?php\s*\/\*\s*(.*?)\s*\*\/\s*\?>/', $header, $header)) {
				$header = json_decode(trim($header[1]));
				if ($header->version != self::VERSION || count($externals) != count((array)$header->externals) || count(array_diff(array_keys($externals), array_keys((array)$header->externals)))) {
					$is_compile = true;
				} else {
					foreach ((array)$header->externals as $ex_file => $c_time) {
						if ($c_time < $externals[$ex_file]) {
							$is_compile = true;
							break;
						}
					}
				}
			} else {
				$is_compile = true;
			}
		}
		if ($is_compile) {
			if (!$this->_loadFile($tpl) || !$this->_make_xpath() || !$this->_compile($cache)) {
				// compile error
			}
		}
		if (file_exists($cache_dir.'/'.$cache)) {
			$_sandbox = create_function('$'.self::PRIVATE_VARS_PREFIX.'compiled, $'.self::PRIVATE_VARS_PREFIX.'vars, $'.self::PRIVATE_VARS_PREFIX.'functions', '
				extract($'.self::PRIVATE_VARS_PREFIX.'vars);
				extract($'.self::PRIVATE_VARS_PREFIX.'functions);
				include($'.self::PRIVATE_VARS_PREFIX.'compiled);
				return ob_get_contents();
			');
			ob_start();
			$source = $_sandbox($cache_dir.'/'.$cache, $this->_vars, $this->_functions);
			ob_end_clean();
			if (is_callable($this->_filters['fetch_filter']['filter'])) {
				$source = call_user_func($this->_filters['fetch_filter']['filter'], $this->_filters['fetch_filter'], $source);
			}
			return $source;
		}
		return false;
	}
	public function display($tpl) {
		echo $this->fetch($tpl);
	}
	public function assign($name, $value) {
		if ($name && !preg_match('/^'.self::PRIVATE_VARS_PREFIX.'/', $name)) {
			$this->_vars[$name] =& $value;
		}
		return false;
	}
	public function add_filter($name, $filter, $args=null) {
		$this->_get_external_file();
		if (is_callable($filter)) {
			$this->_filters[$name] = array_merge((array)$args, array('filter' => $filter));
			return true;
		}
		return false;
	}
	public function add_compiler($selector, $compiler, $args=null) {
		$this->_get_external_file();
		if ($selector && is_callable($compiler)) {
			if (preg_match('/^(\w+):([\w\-]+)$/', $selector, $matched)) {
				$this->_ns_compilers[$matched[1]][$matched[2]] = array_merge((array)$args, array('compiler' => $compiler));
			} else {
				$this->_xml_compilers[$selector] = array_merge((array)$args, array('compiler' => $compiler));
			}
			return true;
		}
		return false;
	}
	public function call($name, $element, $code) {
		if ($this->_php_compilers[$name]['compiler'] && $element->nodeType == XML_ELEMENT_NODE) {
			return call_user_func($this->_php_compilers[$name]['compiler'], $element, $code, $this);
		}
		return null;
	}
	public function add_function($name, $function) {
		$this->_get_external_file();
		if (is_callable($function) && $name) {
			$this->_functions[self::USER_FUNC_PREFIX.$name] = new TemplateFunction($name, $function);
			return true;
		}
		return false;
	}
	private function _preloadString($source, $encoding='UTF-8') {
		if ('efbbbf' == strtolower(join('', unpack('H*', substr($source, 0, 3))))) {
			$source = substr($source, 3);
		}
		if (isset($this->_filters['pre_load_filter'])) {
			$source = call_user_func($this->_filters['pre_load_filter']['filter'], $this->_filters['pre_load_filter'], $source);
		}
		$open_tag_regexp = '<\s*%s(\s+(?:(?:\'.*?(?<!\\\\)\')|(?:".*?(?<!\\\\)")|[^>])*)?>';
		$close_tag_regexp = '<\s*\/\s*%s\s*>';
		$source = preg_replace('/<\!--.*?-->|\t/s', '', $source);
		$source = preg_replace('/\r\n|\r/s', "\n", trim($source));
		// $source = mb_convert_encoding($source, 'HTML-ENTITIES', $encoding);
		$this->_doctype = null;
		if (preg_match('/('.sprintf($open_tag_regexp, '\!\s*DOCTYPE').')(.*)$/si', $source, $doctype)) {
			$source = trim($doctype[3]);
			$this->_doctype = $doctype[1];
		}
		if ($this->_html_exists = preg_match('/'.sprintf($open_tag_regexp.'(.*?)'.$close_tag_regexp, 'html', 'html').'/si', $source, $html)) {
			$source = trim($html[2]);
		}
		$this->_content_type = null;
		if ($this->_head_exists = preg_match('/^(.*?)('.sprintf($open_tag_regexp.'(.*?)'.$close_tag_regexp, 'head', 'head').')(.*)$/si', $source, $head)) {
			$head_dom = new DOMDocument();
			$head_dom->loadHTML($head[2]);
			$head_xpath = new DOMXpath($head_dom);
			if ($meta = $head_xpath->query('//meta[@http-equiv]')) {
				foreach ($meta as $meta) {
					if (strtolower($meta->getAttribute('http-equiv')) == 'content-type') {
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
		if ($this->_body_exists = preg_match('/'.sprintf($open_tag_regexp.'(.*?)'.$close_tag_regexp, 'body', 'body').'/si', $source, $body)) {
			$source = trim($body[2]);
		}

		if ($this->_body_exists || !$this->_head_exists) {
			$source = '<body'. (!empty($body[1]) ? ' '. trim($body[1]) : '') .'>'. $source .'</body>';
		}
		$source = $this->_head_exists ? $head[0] . $source : '<head><meta http-equiv="content-type" content="text/html; charset=utf8" /></head>'. $source;
		// $source = $this->_head_exists ? $head[0] . $source : '<head></head>'. $source;
		$source = '<html'. (!empty($html[1]) ? ' '. trim($html[1]) : '') .'>'. $source .'</html>';
		// $source = $this->_doctype ? $this->_doctype . $source : '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">'. $source;
		$source = $this->_doctype ? $this->_doctype . $source : $source;

		$this->_root = null;
		if ($this->_body_exists && $this->_head_exists || $this->_html_exists) {
			$this->_root = 'html';
		} else if ($this->_body_exists) {
			$this->_root = 'body';
		} else if ($this->_head_exists) {
			$this->_root = 'head';
		}

		$this->nslist = join('|', array_merge((array)'php', (array)array_keys($this->_ns_compilers)));
		$source = preg_replace_callback('/'.sprintf($open_tag_regexp, '[^\/\!\-\[]\w*').'/s', array($this, '_cb_open_tags'), $source);
		$ctl_statement = 'if|elseif|else|\/if|foreach|foreachelse|\/foreach|while|\/while';
		$source = preg_replace_callback('/(?<!\\\\)\${(?:\s*('.$ctl_statement.')\s*)?((?:(?:([\'"]).*?(?<!\\\\)\2)|[^}])*)}/s', array($this, '_cb_php_innercode'), $source);
		$source = preg_replace_callback('/<\?php\s*((?:([\'"]).*?(?<!\\\\)\2|.)*?)\s*\?>/s', array($this, '_cb_php_native'), $source);
		// Smarty interchangeable
		$source = $this->_smarty_interchangeable($this->_config['smarty']['interchangeable'], $source);
		// END, Smarty interchangeable
		$source = preg_replace('/\\\\(\${(?:(?:([\'"]).*?(?<!\\\\)\2)|[^}])*})/s', '$1', $source);
		return $source;
	}
	private function _loadFile($path) {
		if ($source = @file_get_contents($path)) {
			$source = $this->_preloadString($source);
			$this->dom = new DOMDocument();
			$this->dom->preserveWhiteSpace = false;
			return (bool)@$this->dom->loadHTML($source);
		}
		return false;
	}
	private function _get_external_file() {
		$backtrace = debug_backtrace();
		$this->_externals[] = $backtrace[1]['file'];
	}
	private function _get_externals_info() {
		$externals = array();
		foreach (array_unique($this->_externals) as $ex_file) {
			$externals[$ex_file] = (int)@filectime($ex_file);
		}
		return $externals;
	}
	private function _make_xpath() {
		if ($this->xpath = new DOMXpath($this->dom)) {
			$root_elem = $this->xpath->query('//'. ($this->_root ? $this->_root : 'body'));
			$this->_root_element = $root_elem->item(0);
			return true;
		}
		return false;
	}
	private function _cb_open_tags($matched) {
		return preg_replace_callback('/\s('.$this->nslist.'):([^=]+)/i', array($this, '_cb_php_triggers'), $matched[0]);
	}
	private function _cb_php_triggers($matched) {
		if ($matched[1] == 'php' && !array_key_exists($matched[2], $this->_php_compilers)) {
			$this->_php_compilers[$matched[2]] = array('name' => $matched[2]);
		}
		return ' candy-'. $matched[1]. '_'. $matched[2];
	}
	private function _cb_php_phpcode($matched) {
		list($key, $value) = explode('=', $matched[1], 2);
		switch ($key) {
		case 'phpcode':
			if ($this->_php_code[$value]) {
				return '<?php '. $this->_php_code[$value] .' ?>';
			}
			break;

		case 'phpblock':
			switch ($value) {
			case 'close':
				return '<?php } ?>';
			}
			break;
		}
	}
	private function _cb_php_native($matched) {
		$len = count($this->_php_code);
		$this->_php_code[] = $matched[1];
		return '%@CANDY:phpcode='. $len .'%';
	}
	private function _cb_php_innercode($matched) {
		if (!empty($matched[2])) {
			$len = count($this->_php_code);
			$this->_php_code[] = 'echo '. $this->php_parser->parse($matched[2]) .';';
			return '%@CANDY:phpcode='. $len .'%';
		}
		return null;
	}
	public function phpcode($code) {
		$len = count($this->_php_code);
		$this->_php_code[] = $code;
		return $this->dom->createTextNode('%@CANDY:phpcode='. $len .'%');
	}
	public function phpblock($type) {
		return $this->dom->createTextNode('%@CANDY:phpblock='. $type .'%');
	}
	private function _smarty_interchangeable(&$smarty, $source) {
		if ($smarty instanceof Smarty) {
			// preprocess: Smarty conflict escape
			$smarty_compile_dir = $smarty->compile_dir;
			$source = preg_replace_callback('/<\s*(style|script)[^>]*>.*?<\s*\/\s*\1\s*>/is', array($this, '_cb_smarty_exclude'), $source);
			$source = preg_replace_callback('/\\\\(\${(?:(?:([\'"]).*?(?<!\\\\)\2)|[^}])*})/s', array($this, '_cb_smarty_exclude'), $source);
			$source = preg_replace_callback('/<\s*a\s+(.*?)href\s*=\s*([\'"])\s*(javascript\s*:.*?)(?<!\\\\)\2([^>]*)>/is', array($this, '_cb_smarty_exclude'), $source);
			// mainprocess: Smarty compile resource
			$resource = $this->_config['cache']['directory'].'/'.uniqid();
			file_put_contents($resource, $source);
			$smarty->compile_dir = $this->_config['cache']['directory'];
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
		}
		return $source;
	}
	private function _cb_smarty_exclude_rep($matched) {
		return $this->_smarty_exclude[$matched[1]];
	}
	private function _cb_smarty_exclude($matched) {
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
	private function _save() {
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
	private function _compile($cachename) {
		foreach ((array)$this->_xml_compilers as $selector => $compiler) {
			if (is_callable($compiler['compiler'])) {
				$elements = $this->xpath->query($selector, $this->_root_element);
				if ($elements->length) {
					call_user_func($compiler['compiler'], /*$compiler,*/ $elements, $this);
				}
			}
		}
		foreach ((array)$this->_ns_compilers as $ns => $compilers) {
			foreach ((array)$compilers as $name => $compiler) {
				if (is_callable($compiler['compiler'])) {
					foreach ($this->xpath->query('//@candy-'. $ns .'_'. $name, $this->_root_element) as $node) {
						$value = $node->nodeValue;
						$element = $node->parentNode;
						$node->parentNode->removeAttributeNode($node);
						call_user_func($compiler['compiler'], /*$compiler,*/ $element, $value, $this);
					}
				}
			}
		}
		foreach (array_keys($this->_php_compilers) as $name) {
			$elements = $this->xpath->query('//@candy-php_'. $name, $this->_root_element);
			if ($elements->length) {
				if (method_exists($this, '_nodelist_compiler_php_'. $name)) {
					$this->{'_nodelist_compiler_php_'. $name}($this->_php_compilers[$name], $elements, $this);
				} else {
					$this->_nodelist_compiler_php_attribute($this->_php_compilers[$name], $elements, $this);
				}
			}
		}
		$source = $this->_save();
		$source = preg_replace_callback('/%@CANDY:([^%]+)%/', array($this, '_cb_php_phpcode'), $source);
		if (is_callable($this->_filters['post_compile_filter']['filter'])) {
			$source = call_user_func($this->_filters['post_compile_filter']['filter'], $this->_filters['post_compile_filter'], $source);
		}
		unset($this->dom);
		unset($this->xpath);
		return (bool)file_put_contents($this->_config['cache']['directory'].'/'.$cachename, "<?php /* ". json_encode(array('version'=>self::VERSION,'externals'=>$this->_get_externals_info(),'smarty_header'=>$this->_smarty_header))." */ ?>\n\n". $source);
	}
	private function _node_compiler_php_cycle($element, $code, $candy) {
		if ($cycle = $element->getAttributeNode('candy-php_cycle')) {
			if (preg_match_all('/\((.*)\)\s*as\s*(\$[^\s]+)/i', $cycle->nodeValue, $results)) {
				$vars = explode(',', $results[1][0]);
				foreach ($vars as &$var) {
					if ($var) {
						$var = $candy->php_parser->parse($var);
					} else {
						$var = "''";
					}
				}
				$var_cycle_vars = '$'.self::PRIVATE_VARS_PREFIX.'tmp_'. uniqid();
				$var_cycle_cnt = '$'.self::PRIVATE_VARS_PREFIX.'tmp_'. uniqid();
				$init_cycle = $candy->phpcode($var_cycle_cnt.'=0;'.$var_cycle_vars.'=array('. join(',', $vars) .');');
				$do_cycle = $candy->phpcode('if((int)'.$var_cycle_cnt.'>='.count($vars).')'.$var_cycle_cnt.'=0;'.$results[2][0].'='.$var_cycle_vars.'['.$var_cycle_cnt.'++];');
				$candy->remove($cycle);
				return compact('init_cycle', 'do_cycle');
			}
		}
		return false;
	}
	private function _node_compiler_php_foreach($element, $code, $candy) {
		if (preg_match('/^(.*?)\s+as\s*(\$[A-Za-z_]\w*)(?:\s*=>\s*(\$[A-Za-z_]\w*))?$/', $code, $matched)) {
			$var = $candy->php_parser->parse(trim($matched[1]));
			$key = $matched[2];
			$val = $matched[3];
			extract((array)$this->_node_compiler_php_cycle($element, null, $candy));
			if ($init_cycle) {
				$candy->before($init_cycle, $element);
			}
			$candy->before($candy->phpcode('if(count((array)'.$var.')){foreach((array)'.$var.' as '.$key.($val ? ' => '.$val:'').'){'), $element);
			if ($do_cycle) {
				$candy->before($do_cycle, $element);
			}
			$candy->after($candy->phpblock('close'), $element);
			$candy->after($candy->phpblock('close'), $element);
		}
	}
	private function _node_compiler_php_foreachelse($element, $code, $candy) {
		$candy->before($candy->phpcode('}else{'), $element);
		return $element;
	}
	private function _node_compiler_php_while($element, $code, $candy) {
		extract((array)$this->_node_compiler_php_cycle($element, null, $candy));
		if ($init_cycle) {
			$candy->before($init_cycle, $element);
		}
		$candy->before($candy->phpcode('while((bool)('. $candy->php_parser->parse($code) .')){'), $element);
		if ($do_cycle) {
			$candy->before($do_cycle, $element);
		}
		$candy->after($candy->phpblock('close'), $element);
	}
	private function _node_compiler_php_if($element, $code, $candy) {
		$candy->before($candy->phpcode('if((bool)('. $candy->php_parser->parse($code) .')){'), $element);
		if (($elseif = $candy->nextElement($element)) && ($elseif_attr = $elseif->getAttributeNode('candy-php_elseif'))) {
			$element = $this->_node_compiler_php_elseif($elseif, $elseif_attr->nodeValue, $candy);
			$candy->remove($elseif_attr);
		}
		$candy->after($candy->phpblock('close'), $element);
		return $element;
	}
	private function _node_compiler_php_elseif($element, $code, $candy) {
		if (!empty($code)) {
			$candy->before($candy->phpcode('}elseif((bool)('. $candy->php_parser->parse($code) .')){'), $element);
			if (($elseif = $candy->nextElement($element)) && ($elseif_attr = $elseif->getAttributeNode('candy-php_elseif'))) {
				$element = $this->_node_compiler_php_elseif($elseif, $elseif_attr->nodeValue, $candy);
				$candy->remove($elseif_attr);
			}
		} else {
			$candy->before($candy->phpcode('}else{'), $element);
		}
		return $element;
	}
	private function _node_compiler_php_content($element, $code, $candy) {
		while ($candy->pop($element));
		$varname = '$'.self::PRIVATE_VARS_PREFIX.'tmp_'. uniqid();
		$candy->before($candy->phpcode($varname .'='. $candy->php_parser->parse($code) .';if((bool)'. $varname .'){'), $element);
		$candy->append($this->phpcode('echo '. $varname .';'), $element);
		$candy->after($this->phpblock('close'), $element);
	}
	private function _node_compiler_php_replace($element, $code, $candy) {
		$varname = '$'.self::PRIVATE_VARS_PREFIX.'tmp_'. uniqid();
		$candy->before($candy->phpcode($varname .'='. $candy->php_parser->parse($code) .';if((bool)'. $varname .'){echo '. $varname .';}'), $element);
		$candy->remove($element);
	}
	private function _node_compiler_php_attrs($element, $code, $candy) {
		foreach (explode(',', $code) as $value) {
			list($name, $value) = explode('=', $value, 2);
			if (!empty($value)) {
				$element->setAttribute(trim($name), $candy->phpcode('echo '. $this->php_parser->parse(trim($value)) .';')->nodeValue);
			}
		}
	}
	private function _node_compiler_php_period($element, $code, $candy) {
		list($start, $finish) = explode(',', $code);
		$candy->before($candy->phpcode('$'.self::PRIVATE_VARS_PREFIX.'period_now=time();$'.self::PRIVATE_VARS_PREFIX.'period_start=@strtotime("'.trim($start).'");$'.self::PRIVATE_VARS_PREFIX.'period_finish=@strtotime("'.trim($finish).'");'), $element);
		$candy->call("if", $element, '(!$'.self::PRIVATE_VARS_PREFIX.'period_start || $'.self::PRIVATE_VARS_PREFIX.'period_start <= $'.self::PRIVATE_VARS_PREFIX.'period_now) && (!$'.self::PRIVATE_VARS_PREFIX.'period_finish || $'.self::PRIVATE_VARS_PREFIX.'period_finish >= $'.self::PRIVATE_VARS_PREFIX.'period_now)');
	}
	private function _nodelist_compiler_php_foreach($compiler, $nodelist, $candy) {
		foreach ($nodelist as $node) {
			$element = $node->parentNode;
			$this->_node_compiler_php_foreach($element, $node->nodeValue, $candy);
			$candy->remove($candy->nextText($element));
			$candy->remove($candy->nextText($element));
			if ($if = $element->getAttributeNode('candy-php_if')) {
				$element = $this->_node_compiler_php_if($element, $if->nodeValue, $candy);
				$candy->remove($if);
			}
			$candy->after($candy->phpblock('close'), $element);
			if (($foreachelse = $candy->nextElement($element)) && ($foreachelse_attr = $foreachelse->getAttributeNode('candy-php_foreachelse'))) {
				$element = $this->_node_compiler_php_foreachelse($foreachelse, null, $candy);
				$candy->remove($foreachelse_attr);
			}
			$candy->after($candy->phpblock('close'), $element);
			$candy->remove($node);

			if ($while = $element->getAttributeNode('candy-php_while')) {
				$candy->remove($while);
			}
		}
		foreach ($candy->xpath->query('//@candy-php_foreachelse') as $foreachelse) {
			$candy->remove($foreachelse);
		}
	}
	private function _nodelist_compiler_php_while($compiler, $nodelist, $candy) {
		foreach ($nodelist as $node) {
			$element = $node->parentNode;
			$this->_node_compiler_php_while($element, $node->nodeValue, $candy);
			$candy->remove($candy->nextText($element));
			if ($if = $element->getAttributeNode('candy-php_if')) {
				$element = $this->_node_compiler_php_if($element, $if->nodeValue, $candy);
				$candy->remove($if);
			}
			$candy->after($candy->phpblock('close'), $element);
			$candy->remove($node);
		}
		foreach ($candy->xpath->query('//@candy-php_cycle') as $cycle) {
			$candy->remove($cycle);
		}
	}
	private function _nodelist_compiler_php_if($compiler, $nodelist, $candy) {
		foreach ($nodelist as $node) {
			$this->_node_compiler_php_if($node->parentNode, $node->nodeValue, $candy);
			$candy->remove($node);
		}
		foreach ($candy->xpath->query('//@candy-php_elseif') as $elseif) {
			$candy->remove($elseif);
		}
	}
	private function _nodelist_compiler_php_attrs($compiler, $nodelist, $candy) {
		foreach ($nodelist as $node) {
			$this->_node_compiler_php_attrs($node->parentNode, $node->nodeValue, $candy);
			$candy->remove($node);
		}
	}
	private function _nodelist_compiler_php_attribute($compiler, $nodelist, $candy) {
		foreach ($nodelist as $node) {
			if (!empty($node->nodeValue)) {
				$node->parentNode->setAttribute($compiler['name'], $candy->phpcode('echo '. $this->php_parser->parse($node->nodeValue) .';')->nodeValue);
			}
			$candy->remove($node);
		}
	}
	private function _nodelist_compiler_php_content($compiler, $nodelist, $candy) {
		foreach ($nodelist as $node) {
			$this->_node_compiler_php_content($node->parentNode, $node->nodeValue, $candy);
			$candy->remove($node);
		}
	}
	private function _nodelist_compiler_php_replace($compiler, $nodelist, $candy) {
		foreach ($nodelist as $node) {
			$this->_node_compiler_php_replace($node->parentNode, $node->nodeValue, $candy);
			// $candy->remove($node);
		}
	}
	private function _nodelist_compiler_php_period($compiler, $nodelist, $candy) {
		foreach ($nodelist as $node) {
			$this->_node_compiler_php_period($node->parentNode, $node->nodeValue, $candy);
			if ($node->parentNode->hasAttribute('candy-php_foreach')) {
				$this->_nodelist_compiler_php_foreach($compiler, array($node->parentNode), $candy);
			}
			$candy->remove($node);
		}
	}
	function cloneInstance() {
		$config = array();
		foreach ((array)$this->_config as $confType => $conf) {
			if (is_array($conf)) {
				foreach ((array)$conf as $confName => $value) {
					$config[$confType .'-'. $confName] = $value;
				}
			} else {
				$config[$confType] = $conf;
			}
		}
		return new Candy($config, &$this->_vars);
	}
	function _func_document($file) {
		$candy = $this->cloneInstance();
		if (preg_match('/\.(?:tpl|html|htm)$/', $file)) {
			return $candy->fetch($file);
		}
		ob_start();
		include($file);
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}

	public function make_dom($tpl) {
		if ($this->_loadString($this->fetch($tpl))) {
			return (bool)$this->xpath = new DOMXpath($this->dom);
		}
		return false;
	}
	public function selector($selector) {
		if ($this->xpath) {
			return $this->xpath->query($selector);
		}
		return false;
	}
	public function text($textString) {
		return $this->dom->createTextNode($textString);
	}
	public function element($tagName, $nodeValue='', $attributes=array()) {
		$element = $this->dom->createElement($tagName, $nodeValue);
		foreach ($attributes as $name => $value) {
			$element->setAttribute($name, $value);
		}
		return $element;
	}
	public function attr($name, $value) {
		return $this->_dom->createAttribute($name, $value);
	}
	public function cdata($data) {
		return $this->dom->createCDATASection($data);
	}
	public function css($cssString) {
		$css = $this->dom->createElement("style");
		$css->setAttribute("type", "text/css");
		$css->appendChild($this->dom->createCDATASection($cssString));
		return $css;
	}
	public function js($jsString) {
		$js = $this->dom->createElement("script");
		$js->setAttribute("type", "text/javascript");
		$js->appendChild($this->dom->createCDATASection($jsString));
		return $js;
	}
	public function nextElement($node) {
		return $this->nextNode($node, XML_ELEMENT_NODE);
	}
	public function prevElement($node) {
		return $this->prevNode($node, XML_ELEMENT_NODE);
	}
	public function nextText($node) {
		return $this->nextNode($node, XML_TEXT_NODE);
	}
	public function prevText($node) {
		return $this->prevNode($node, XML_TEXT_NODE);
	}
	public function nextNode($node, $nodeType) {
		while ($node->nextSibling) {
			if ($node->nextSibling->nodeType == $nodeType) {
				return $node->nextSibling;
			}
			$node = $node->nextSibling;
		}
		return null;
	}
	public function prevNode($node, $nodeType) {
		while ($node->previousSibling) {
			if ($node->nextSibling->nodeType == $nodeType) {
				return $node->previousSibling;
			}
			$node = $node->previousSibling;
		}
		return null;
	}
	public function before($newnode, $refnode) {
		return $refnode->parentNode->insertBefore($newnode, $refnode);
	}
	public function after($newnode, $refnode) {
		if ($refnode->nextSibling) {
			return $refnode->parentNode->insertBefore($newnode, $refnode->nextSibling);
		} else {
			return $refnode->parentNode->appendChild($newnode);
		}
	}
	public function append($newnode, $refnode) {
		return $this->push($newnode, $refnode);
	}
	public function push($newnode, $refnode) {
		if ($newnode->nodeType == XML_ATTRIBUTE_NODE) {
			return $refnode->setAttributeNode($newnode);
		} else {
			return $refnode->appendChild($newnode);
		}
	}
	public function unshift($newnode, $refnode) {
		if ($refnode->firstChild) {
			return $refnode->insertBefore($newnode, $refnode->firstChild);
		} else {
			return $refnode->appendChild($newnode);
		}
	}
	public function pop($node) {
		if ($node instanceof DOMNodeList) {
			if ($node->length) {
				return $node->removeChild($node->item($node->length -1));
			}
			return false;
		}
		if ($node->lastChild) {
			return $node->removeChild($node->lastChild);
		}
		return false;
	}
	public function shift($node) {
		if ($node instanceof DOMNodeList) {
			if ($node->length) {
				return $node->removeChild($node->item(0));
			}
			return false;
		}
		if ($node->firstChild) {
			return $node->removeChild($node->firstChild);
		}
		return false;
	}
	public function remove($node) {
		if ($node->nodeType == XML_ATTRIBUTE_NODE) {
			return $node->parentNode->removeAttributeNode($node);
		} else {
			return $node->parentNode->removeChild($node);
		}
	}
	public function replace($newnode, $refnode) {
		return $node->parentNode->replaceChild($newnode, $refnode);
	}
}

?>
