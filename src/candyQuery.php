<?php

include(dirname(__FILE__).'/cQuery.php');

class candyQuery extends cQuery {

	private $_expr = null;
	private $compiler = null;

	function __construct($source, &$compiler) {
		$this->compiler = $compiler;
		parent::__construct($source);
		$this->dom;
	}

	function query($expr, $contextnode=null, $type='css') {
		$this->_expr = $expr;
		$expr = preg_replace('/\[\s*(\w+):([\w\*\-]+)(\s*\S?=\s*([\'"]).*?(?<!\\\\)\4)?\s*\]/', '['.sprintf(Candy::ATTR_DUMMY_NAME, '$1','$2').'$3]', $expr);
		return parent::query($expr, $contextnode, $type);
	}

	protected function _results_nodeset($nodes) {
		return new candyNodeSet($nodes, (object) array(
			'dom' => &$this->dom,
			'query' => &$this,
			'compiler' => &$this->compiler,
		), $this->_expr);
	}
}
?>
