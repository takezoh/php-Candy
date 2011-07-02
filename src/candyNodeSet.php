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

	function php($code) {
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
