<?php

class EasyDOMController implements Iterator {

	protected $classname = __CLASS__;
	protected $provider = null;
	protected $nodeList = array();
	protected $length = 0;
	protected $query = null;

	function __construct($source, $provider=null, $query=null) {
		$this->query = $query;
		$this->provider =& $provider;
		if (is_null($this->provider)) {
			$this->provider = (object) null;
		}
		if (is_string($source)) {
			$this->provider->dom = new DOMDocument();
			$this->provider->dom->preserveWhiteSpace = false;
			$this->provider->dom->loadHTML(trim($source));
			$this->provider->xpath = new DOMXPath($this->provider->dom);
			$this->nodeList[] = $this->provider->dom->documentElement;
		}
		else {
			// $this->dom =& $dom;
			// $this->xpath =& $xpath;
			if ($source instanceof DOMNode || $source instanceof DOMNodeList) {
				$source = array($source);
			}
			foreach ((array)$source as $node) {
				if ($node instanceof DOMNode) {
					if ($node->nodeType === XML_ELEMENT_NODE) {
						$this->nodeList[] = $node;
					}
				} else if ($node instanceof DOMNodeList) {
					foreach ($node as $node) {
						if ($node->nodeType === XML_ELEMENT_NODE) {
							$this->nodeList[] = $node;
						}
					}
				} else {
					break;
				}
			}
		}
		$this->length = count($this->nodeList);
	}

	function __get($key) {
		switch ($key) {
		case 'length':
			return $this->length;
		case 'query':
			return $this->query;
		default:
			// $var = $this->node->{$key};
			// if ($var instanceof DOMNode || $var instanceof DOMNodeList) {
				// return new $this->classname($var, $this->provider->dom, $this->xpath);
			// }
			// return $var;
		}
	}

	function __call($method, $args) {
		return call_user_func_array(array($this->provider->dom, $method), $args);
	}

	function saveHTML() {
		return $this->provider->dom->saveHTML();
	}

	function query($expr, $context=null) {
		return new $this->classname($this->provider->xpath->query($expr, $this->node), $this->provider, $expr);
	}

	protected function _to_dom($content) {

	}

	function attr($key, $value=null) {
		$mode = 'set';
		if (!is_null($value)) {
			$properties = array($key => $value);
		} else {
			if (is_array($key)) {
				$properties =& $key;
			} else {
				$mode = 'get';
			}
		}
		$attrs = array();
		foreach ($this->nodeList as $node) {
			if ($node->nodeType === XML_ELEMENT_NODE) {
				switch ($mode) {
				case 'set':
					foreach ($properties as $name => $value) {
						$node->setAttribute($name, $value);
					}
					break;
				case 'get':
					$attrs[] = $node->getAttribute($key);
					break;
				}
			}
		}
		if ($mode === 'set') {
			return $this;
		}
		if ($len = count($attrs)) {
			if ($len === 1) {
				return $attrs[0];
			}
			return $attrs;
		}
		return null;
	}

	function css($key, $value=null) {
	}
	function addClass() {
	}
	function hasClass() {
	}

	function append($content) {
		foreach ($this->nodeList as $node) {
			$new = $content->cloneNode(true);
			$node->appendChild($new);
		}
		return $this;
	}
	function before($content) {
		foreach ($this->nodeList as $node) {
			$new = $content->cloneNode(true);
			$node->parentNode->insertBefore($new, $node);
		}
		return $this;
	}
	function after($content) {
		foreach ($this->nodeList as $node) {
			$new = $content->cloneNode(true);
			if ($ref = $node->nextSibling) {
				$node->parentNode->insertBefore($new, $ref);
			} else {
				$node->parentNode->appendChild($new);
			}
		}
		return $this;
	}
	function replace($content) {
		foreach ($this->nodeList as &$node) {
			$new = $content->cloneNode(true);
			$node->parentNode->replaceChild($new, $node);
			$node = $new;
		}
		return $this;
	}
	// function children($selector=null) {
		// if (!is_null($selector)) {
			// $ret = $this->xpath->query('./'.$selector, $this->dom);
		// }
	// }
	// function contents() {
		// $ret = $this->xpath->query('./*', $this->dom);
	// }
	function each($callback, $provider=null, $options=array()) {
		if (is_callable($callback)) {
			foreach ($this->nodeList as $node) {
				call_user_func($callback, new $this->classname($node, $this->provider), $provider);
			}
		}
		return $this;
	}
	function _empty() {
		foreach ($this->nodeList as &$node) {
			$new = $node->cloneNode(false);
			$node->parentNode->replaceChild($new, $node);
			$node = $new;
		}
		return $this;
	}
	function find($selector) {
	}
	function filter($selector) {
	}
	function not($selector) {
	}
	function _next($selector=null) {
		$nodelist = array();
		foreach ($this->nodeList as $node) {
			while ($node = $node->nextSibling) {
				if ($node->nodeType === XML_ELEMENT_NODE) {
					$nodelist[] = $node;
					break;
				}
			}
		}
		return new $this->classname($nodelist, $this->provider);
	}
	function nextAll($selector=null) {
		$nodelist = array();
		foreach ($this->nodeList as $node) {
			while ($node = $node->nextSibling) {
				if ($node->nodeType === XML_ELEMENT_NODE) {
					$nodelist[] = $node;
				}
			}
		}
		return new $this->classname($nodelist, $this->provider);
	}
	function parent($selector=null) {
		$nodes = array();
		foreach ($this->nodeList as $node) {
			while ($node = $node->parentNode) {
				if ($node->nodeType === XML_ELEMENT_NODE) {
					$nodes[] = $node;
					break;
				}
			}
		}
		return new $this->classname($nodes, $this->provider);
	}
	function parents($selector=null) {
		$nodes = array();
		foreach ($this->nodeList as $node) {
			while ($node = $node->parentNode) {
				if ($node->nodeType === XML_ELEMENT_NODE) {
					$nodes[] = $node;
				}
			}
		}
		return new $this->classname($nodes, $this->provider);
	}
	function remove($selector=null) {
		foreach ($this->nodeList as $node) {
			$node->parentNode->removeChild($node);
		}
		return $this;
	}
	function removeAttr($name) {
		foreach ($this->nodeList as $node) {
			if ($node->nodeType === XML_ELEMENT_NODE) {
				foreach ((array)$name as $attr) {
					$node->removeAttribute($attr);
				}
			}
		}
		return $this;
	}
	function removeClass($name) {
	}
	function siblings($selector=null) {
		$nodes = array();
		foreach ($this->nodeList as $node) {
			foreach (array('previousSibling', 'nextSibling') as $property) {
				$sibling = $node;
				while ($sibling = $sibling->{$property}) {
					if ($sibling === XML_ELEMENT_NODE) {
						$nodes[] = $sibling;
					}
				}
			}
		}
		return new $this->classname($nodes, $this->provider);
	}
	function html($val=null) {
	}
	function text($val=null) {
	}
	function val($val=null) {
	}
	function wrap($elem) {
	}
	function wrapAll($elem) {
	}
	function wrapInner($elem) {
	}

	function add($selector) {
	}
	function eq($index) {
		return new $this->classname(isset($this->nodeList[$index]) ? $this->nodeList[$index] : null, $this->provider);
	}
	function get($index=null) {
		if (is_null($index)) {
			return $this->nodeList;
		}
		if (isset($this->nodeList[$index])) {
			return $this->nodeList[$index];
		}
		return null;
	}
	function slice($start, $end=null) {
		$len = count($this->nodeList);
		$nodes = array();
		if ($start < $len) {
			if (is_null($end)) {
				$nodes = array_slice($this->nodeList, $start);
			} else {
				if ($end >= $len) {
					$end = $len - 1;
				}
				$nodes = array_slice($this->nodeList, $start, $end - $start);
			}
		}
		return new $this->classname($nodes, $this->provider);
	}


	// Iterator
	function rewind() { $this->_iter_ = 0; }
	function current() { return new $this->classname($this->nodeList[$this->_iter_], $this->provider); }
	function key() { return $this->_iter_; }
	function next() { ++$this->_iter_; }
	function valid() { return isset($this->nodeList[$this->_iter_]); }
}


?>
