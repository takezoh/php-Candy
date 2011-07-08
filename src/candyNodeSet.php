<?php

include(dirname(__FILE__).'/cNodeSet.php');

class candyNodeSet extends cNodeSet {

	function __construct($source, $provider=null, $query=null) {
		parent::__construct($source, $provider, $query);
		$this->classname = __CLASS__;
	}

	function attr($key, $value=null) {
		$ret = parent::attr($key, $value);
		if (is_string($ret) && preg_match('/^%@CANDY:.*%$/', $ret)) {
			return $this->provider->compiler->get_phpcode($ret);
		}
		return $ret;
	}

	function attrPHP($key, $value=null) {
		if (!empty($value)) {
			$value = $this->provider->compiler->add_phpcode($value, 'phpset');
		}
		return $this->attr($key, $value);
	}

	function phpwrapper($type, $eval=null) {
		$wrappers = array();
		$wrapper = $this->provider->dom->createElement('phpblock');
		$wrapper->setAttribute('type', $type);
		if (!is_null($eval)) {
			$eval = $this->provider->compiler->prepare($eval);
			$wrapper->setAttribute('eval', $this->provider->compiler->add_phpcode($eval));
		}
		foreach ($this->nodeList as $node) {
			$block = $wrapper->cloneNode(false);
			$block->appendChild($node->parentNode->replaceChild($block, $node));
			$wrappers[] = $block;
		}
		return $this->_new_nodeset($wrappers);
	}

	function php($code=null) {
		if (!is_null($code) && isset($this->elements[0])) {
			return $this->elements[0]->nodeValue;
		}
		$this->_empty();
		$this->append($this->provider->dom->createCDATASection($code));
	}

	function bind($compiler_name, $attribute=null) {
		if (!is_null($attribute)) {
			$this->attr($compiler_name, $attribute);
		}
		$this->provider->compiler->do_compiler($compiler_name, $this);
		$this->removeAttr($compiler_name);
	}

	function html($val=null) {
		return preg_replace_callback('/%@CANDY:[^%]+%/', array($this->provider->compiler, 'get_phpcode'), parent::html($val));
	}
}

?>
