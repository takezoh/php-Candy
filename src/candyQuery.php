<?php

include(dirname(__FILE__).'/cQuery.php');

class candyQuery extends cQuery {

	private $compiler = null;

	function __construct($source, &$compiler) {
		$this->compiler = $compiler;
		parent::__construct($source);
		$this->nodeset_class = 'candyNodeSet';
	}

	protected function _results_nodeset($nodes) {
		return parent::_results_nodeset($nodes, array('compiler'=>&$this->compiler));
	}

	public function php($code) {
		$php = $this->dom->createElement('php');
		$php->appendChild($this->dom->createCDATASection($code));
		return $php;
	}

	public function func($name, $args_str=null) {
		$func = $this->dom->createElement('function');
		$code = 'extract((array)'. $this->compiler->PHPParse($name .'('. $args_str .')') .');';
		$func->appendChild($this->dom->createCDATASection($code));
		return $func;
	}
}
?>
