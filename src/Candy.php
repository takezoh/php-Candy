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
 * @version 0.5.0
 */

include(dirname(__FILE__).'/templateFunction.php');

class Candy {

	const VERSION = "0.5.0";
	const PRIVATE_VARS_PREFIX = '__candy_';
	const USER_FUNC_PREFIX = '__candy_func_';

	private $_defaults = array(
		'cache.use' => true,
		'cache.directory' => './cache',
		'template.directory' => './template',
		'log.type' => false,
		'log.directory' => './logs',
		'debugging' => false,
		'smarty' => false,
		'html5' => false,
	);

	private $_config = null;

	private $_vars = array();
	private $_functions = array();
	private $_compilers = array();

	private $_logger = null;
	private $_externals = array();

	function __construct($config=array(), $_vars=array()) {
		// Make config
		$this->_get_external_file();
		$this->_defaults = array_merge($this->_defaults, $config);
		$this->_config = (object) null;
		foreach ($this->_defaults as $key => $value) {
			$ref =& $this->_config;
			foreach (explode(".", $key, 2) as $type) {
				$ref =& $ref->{$type};
			}
			$ref = $value;
			unset($ref);
		}
		// Get current directory
		if (!($current_dir = getcwd())) {
			return false;
		}
		// Get abstruct directory path
		foreach (array('cache', 'template', 'log') as $conf) {
			if (preg_match('/^(?!\/)(\.\/|.{0})/', $this->_config->{$conf}->directory, $matched)) {
				$this->_config->{$conf}->directory = $current_dir .'/'. substr($this->_config->{$conf}->directory, strlen($matched[1]));
			}
		}
		// Logger instance
		// if ($this->_config->log->type) {
			// if (!class_exists('Log')) include('Log.php');
			// $this->_logger = Log::factory($this->_config->log->type, $this->_config->log->directory.'/error.log', 'Candy.php');
		// }
		// Init "Template Functions"
		$this->_functions = array(
			self::USER_FUNC_PREFIX.'document' => new TemplateFunction('document', array($this, '_func_document')),
		);

		// extens vars
		$this->_vars =& $_vars;
	}
	public function get_template_path($filename) {
		if (preg_match('/^\//', $filename)) {
			$path = $filename;
		} else {
			$path = $this->_config->template->directory .'/'. $filename;
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
		$cache = $this->_config->cache->directory .'/'. $this->get_cachename($tpl);

		$tpl_exists = file_exists($tpl);
		$cache_exists = file_exists($cache);

		// need compile ?
		$is_compile = !$cache_exists;
		if (!$is_compile && $tpl_exists) {
			if (!$this->_config->cache->use || (int)filectime($cache) < (int)filectime($tpl)) {
				$is_compile = true;
			}
			if (!$is_compile) {
				$externals = $this->_get_externals_info();
				list($header,) = explode("\n\n", file_get_contents($cache), 2);
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
		}

		// Compile!!
		if ($is_compile && $tpl_exists) {
			if (!class_exists('DOMCompiler')) {
				include(dirname(__FILE__).'/simplePhpParser.php');
				include(dirname(__FILE__).'/candyNodeSet.php');
				include(dirname(__FILE__).'/candyQuery.php');
				include(dirname(__FILE__).'/DOMCompiler.php');
				include(dirname(__FILE__).'/candyDefaultCompiler.php');
			}
			$defaultCompilers = new CandyDefaultCompilers();
			$compiler = new DOMCompiler($this->_config);
			foreach (array('period', 'foreach', 'while', 'if', 'replace', 'content', 'attrs', 'cycle', 'foreachelse', 'elseif', 'else') as $phpcompiler) {
				$compiler->add_compiler('php:'.$phpcompiler, array($defaultCompilers, 'nodelist_compiler_'.$phpcompiler));
			}
			$compiler->add_compiler('php:*', array($defaultCompilers, 'nodelist_compiler_attribute'));
			foreach ($this->_compilers as $user_compiler) {
				$compiler->add_compiler($user_compiler['selector'], $user_compiler['callback'], $user_compiler['args']);
			}
			$compiled = $compiler->compile(file_get_contents($tpl));
			file_put_contents($cache,
				"<?php /* ". json_encode(array(
					'version'=>self::VERSION,
					'externals'=>$this->_get_externals_info(),
					'smarty_header'=>$compiled['smarty_header']
				))." */ ?>\n\n". $compiled['source']
			);
		}
		// include compiled cache!!
		if (file_exists($cache)) {
			$_sandbox = create_function('$'.self::PRIVATE_VARS_PREFIX.'compiled, $'.self::PRIVATE_VARS_PREFIX.'vars, $'.self::PRIVATE_VARS_PREFIX.'functions', '
				ob_start();
				extract($'.self::PRIVATE_VARS_PREFIX.'vars);
				extract($'.self::PRIVATE_VARS_PREFIX.'functions);
				include($'.self::PRIVATE_VARS_PREFIX.'compiled);
				$'.self::PRIVATE_VARS_PREFIX.'ret = ob_get_contents();
				ob_end_clean();
				return $'.self::PRIVATE_VARS_PREFIX.'ret;
			');
			return $_sandbox($cache, $this->_vars, $this->_functions);
		}
		// fetch error
		return false;
	}
	public function display($tpl) {
		echo $this->fetch($tpl);
	}
	public function assign($name, $value) {
		if ($name && !preg_match('/^'.self::PRIVATE_VARS_PREFIX.'/', $name)) {
			return $this->_vars[$name] =& $value;
		}
		return false;
	}
	public function assing_ref($name, &$value) {
		if ($name && !preg_match('/^'.self::PRIVATE_VARS_PREFIX.'/', $name)) {
			return $this->_vars[$name] =& $value;
		}
		return false;
	}
	public function add_compiler($selector, $callback) {
		$this->_get_external_file();
		return $this->_compilers[] = compact('selector', 'callback');
	}
	public function add_function($name, $function) {
		$this->_get_external_file();
		if (is_callable($function) && $name) {
			$this->_functions[self::USER_FUNC_PREFIX.$name] = new TemplateFunction($name, $function);
			return true;
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

	function cloneInstance() {
		return new Candy($this->_defaults, $this->_vars);
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

}

?>
