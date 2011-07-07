<?php

class varsHelper {

	protected $refs = array();
	protected $vars = array();

	function __set($key, $value) {
		return $this->assign($key, $value);
	}

	function &__get($key) {
		return $this->get_var($key);
	}

	function assign($name, $var) {
		$this->vars[$name] = $var;
		if (!isset($this->refs[$name])) {
			$this->refs[$name] =& $this->vars[$name];
		}
		return $this->refs[$name];
	}

	function assign_ref($name, &$var) {
		$this->vars[$name] =& $var;
		if (!isset($this->refs[$name])) {
			$this->refs[$name] =& $this->vars[$name];
		}
		return $this->refs[$name];
	}

	function &get_var($name) {
		if (isset($this->refs[$name])) {
			return $this->refs[$name];
		}
		return false;
	}

	function export() {
		return $this->refs;
	}
}

?>
