<?php

class TemplateFunction {

	private static $bases = array();
	private $base = null;
	private $func = null;

	function __construct($name, $func) {
		if (array_key_exists($name, self::$bases)) {
			$this->base = self::$bases[$name];
		}
		$this->func = $func;
		self::$bases[$name] = $this;
	}
	function call() {
		$debugtrace = debug_backtrace();
		return call_user_func_array(array($this, '__invoke'), $debugtrace[0]['args']);
	}
	function __invoke() {
		if (is_callable($this->func)) {
			$debugtrace = debug_backtrace();
			$args = array_merge($debugtrace[0]['args'], array($this->base));
			return call_user_func_array($this->func, $args);
		}
		return null;
	}
}

?>
