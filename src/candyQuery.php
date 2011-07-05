<?php

include(dirname(__FILE__).'/cQuery.php');

class candyQuery extends cQuery {

	private $compiler = null;

	function __construct($source, &$compiler) {
		$this->compiler = $compiler;
		parent::__construct($source);
		$this->dom;
	}

	protected function _results_nodeset($nodes) {
		return new candyNodeSet($nodes, (object) array(
			'dom' => &$this->dom,
			'xpath' => &$this->xpath,
			'query' => &$this,
			'compiler' => &$this->compiler,
		), $this->expr);
	}
}
?>
