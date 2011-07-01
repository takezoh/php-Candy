<?php

include(dirname(__FILE__).'/cNodeSet.php');

class candyNodeSet extends cNodeSet {

	function __construct($source, $provider=null) {
		parent::__construct($source, $provider);
		$this->classname = __CLASS__;
	}

	function query($expr, $context=null) {
		$elements = parent::query(preg_replace('/(?:attribute::|@)(\w+):(\w+)/', '@'.sprintf(Candy::ATTR_DUMMY_NAME, '$1','$2'), $expr), $context);
		$elements->query = $expr;
		return $elements;
	}

	function attr($key, $value=null) {
		$is_ns = false;
		if (is_string($key) && preg_match('/^(\w+):(.*)$/', $key, $matched)) {
			$is_ns = true;
			$key = sprintf(Candy::ATTR_DUMMY_NAME, $matched[1], $matched[2]);
			if (!is_null($value)) {
				$value = $this->provider->compiler->add_phpcode($value);
			}
		}
		$ret = parent::attr($key, $value);
		if (!$is_ns || is_null($ret)) return $ret;

		$ret = (array)$ret;
		foreach ($ret as &$var) {
			$var = $this->provider->compiler->get_phpcode($var);
		}
		if (count($ret) === 1) {
			return $ret[0];
		}
		return $ret;
	}

	function removeAttr($name) {
		$name = (array)$name;
		foreach ($name as &$var) {
			$var = preg_replace('/^(\w+):(.*)$/', sprintf(Candy::ATTR_DUMMY_NAME, '$1','$2'),  $var);
		}
		return parent::removeAttr($name);
	}

	function php($code) {
		// return $this->provider->compiler->phpcode($code);
		$php = $this->provider->dom->createElement('php');
		$php->appendChild($this->provider->dom->createCDATASection($code));
		return $php;
	}

	function phpwrapper($type, $eval=null) {
		$wrappers = array();
		$wrapper = $this->provider->dom->createElement('phpblock');
		$wrapper->setAttribute('type', $type);
		if (!is_null($eval)) {
			$wrapper->setAttribute('eval', $this->provider->compiler->add_phpcode($eval));
		}
		foreach ($this->nodeList as $node) {
			$block = $wrapper->cloneNode(false);
			$block->appendChild($node->parentNode->replaceChild($block, $node));
			$wrappers[] = $block;
		}
		return new $this->classname($wrappers, $this->provider);
	}

}

?>
