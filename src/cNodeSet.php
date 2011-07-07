<?php

class cNodeSet implements Iterator {

	protected $classname = __CLASS__;
	protected $provider = null;
	protected $nodeList = array();
	protected $elements = array();
	protected $length = 0;
	protected $query = null;

	function __construct($source=null, $provider=null, $query=null) {
		$this->nodeList = $this->elements = array();
		if (is_null($this->query)) $this->query = $query;
		if (is_null($this->provider)) $this->provider = $provider;
		if (is_null($this->provider)) $this->provider = (object) null;

		if ($source instanceof DOMNode || $source instanceof DOMNodeList || $source instanceof $this->classname) {
			$source = array($source);
		}
		foreach ((array)$source as $node) {
			if ($node instanceof DOMNode) {
				if (!in_array($node, $this->nodeList, true)) {
					$this->nodeList[] = $node;
				}
			} else if ($node instanceof DOMNodeList) {
				foreach ($node as $node) {
					if (!in_array($node, $this->nodeList, true)) {
						$this->nodeList[] = $node;
					}
				}
			} else if ($node instanceof $this->classname) {
				for ($i=0,$len=$node->length; $i<$len; ++$i) {
					$n = $node->get($i);
					if (!in_array($n, $this->nodeList, true)) {
						$this->nodeList[] = $n;
					}
				}
			} else {
				break;
			}
		}
		foreach ($this->nodeList as &$node) {
			if ($node->nodeType === XML_ELEMENT_NODE) {
				$this->elements[] =& $node;
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
			if (isset($this->nodeList[0])) {
				return $this->nodeList[0]->{$key};
			}
		}
		return null;
	}

	function __call($method, $args) {
		if (isset($this->nodeList[0])) {
			return call_user_func_array(array($this->nodeList[0], $method), $args);
		}
	}

	protected function _new_nodeset($nodes) {
		return new $this->classname($nodes, $this->provider);
	}

	function attr($key, $value=null) {
		if (!is_null($value)) {
			$properties = array($key => $value);
		} else {
			if (is_array($key)) {
				$properties =& $key;
			} else {
				// get
				$key = $this->provider->query->ns_to_dummy($key);
				if (isset($this->elements[0]) && $this->elements[0]->hasAttribute($key)) {
					$key = $this->provider->query->ns_to_dummy($key);
					return $this->elements[0]->getAttribute($key);
				}
				return null;
			}
		}
		foreach ($this->elements as $node) {
			foreach ($properties as $name => $value) {
				$name = $this->provider->query->ns_to_dummy($name);
				$node->setAttribute($name, $value);
			}
		}
		return $this;
	}
	function removeAttr($name) {
		foreach ($this->elements as $node) {
			foreach ((array)$name as $attr) {
				$attr = $this->provider->query->ns_to_dummy($attr);
				$node->removeAttribute($attr);
			}
		}
		return $this;
	}
	protected function _css_to_array($css_str) {
		$style = array();
		foreach (explode(';', $css_str) as $token) {
			if (!empty($token)) {
				list($name, $value) = explode(':', $token, 2);
				$style[ trim($name) ] = trim($value);
			}
		}
		return $style;
	}
	protected function _css_to_string($css_arr) {
		$style = array();
		foreach ($css_arr as $name => $value) {
			if (is_numeric($value)) {
				$value = $value .'px';
			}
			$style[] = $name.':'.$value.';';
		}
		return join(' ', $style);
	}
	function css($key, $value=null) {
		if (is_null($value)) {
			if (is_string($key)) {
				// get
				if (isset($this->elements[0])) {
					$style = $this->_css_to_array($this->elements[0]->getAttribute('style'));
					return $style[$key];
				}
				return null;
			}
			if (is_array($key)) {
				$style =& $key;
			}
		}
		if (is_string($key)) {
			$style = array($key => $value);
		}
		foreach ($this->elements as $node) {
			$oldstyle = $this->_css_to_array($node->getAttribute('style'));
			$newstyle = array_merge($oldstyle, $style);
			$node->setAttribute('style', $this->_css_to_string($newstyle));
		}
		return $this;
	}

	function addClass($name) {
		foreach ($this->elements as $node) {
			$class = $node->getAttribute('class');
			$class = preg_split('/\s+/', $class);
			$class = array_merge($class, (array)$name);
			$node->setAttribute('class', trim(join(' ', $class)));
		}
	}
	function hasClass($name) {
		$class = $this->attr('class');
		if (is_array($class)) {
			$class = join(' ', $class);
		}
		$class = preg_split('/\s+/', $class);
		return in_array($name, $class);
	}
	function removeClass($name) {
		foreach ($this->elements as $node) {
			$class = $node->getAttribute('class');
			$class = preg_split('/\s+/', $class);
			foreach ((array)$name as $name) {
				if (($pos = array_search($name, $class)) !== false) {
					array_splice($class, $pos, 1);
				}
			}
			$node->setAttribute('class', join(' ', $class));
		}
	}

	function append($contents) {
		$nodes = array();
		$contents = $this->provider->query->dom($contents);
		foreach ($this->elements as $node) {
			foreach ($contents as $content) {
				$new = $content->cloneNode(true);
				$nodes[] = $node->appendChild($new);
			}
		}
		$contents->remove();
		$contents->__construct($nodes);
		return $this;
	}
	function before($contents) {
		$nodes = array();
		$contents = $this->provider->query->dom($contents);
		foreach ($this->elements as $node) {
			if ($node->parentNode) {
				foreach ($contents as $content) {
					$new = $content->cloneNode(true);
					$nodes[] = $node->parentNode->insertBefore($new, $node);
				}
			}
		}
		$contents->remove();
		$contents->__construct($nodes);
		return $this;
	}
	function after($contents) {
		$nodes = array();
		$contents = $this->provider->query->dom($contents);
		foreach ($this->elements as $node) {
			if ($node->parentNode) {
				foreach ($contents as $content) {
					$new = $content->cloneNode(true);
					if ($ref = $node->nextSibling) {
						$nodes[] = $node->parentNode->insertBefore($new, $ref);
					} else {
						$nodes[] = $node->parentNode->appendChild($new);
					}
				}
			}
		}
		$contents->remove();
		$contents->__construct($nodes);
		return $this;
	}
	function replace($contents) {
		$nodes = array();
		$contents = $this->provider->query->dom($contents);
		foreach ($this->nodeList as &$node) {
			if ($node->parentNode) {
				foreach ($contents as $content) {
					$new = $content->cloneNode(true);
					$nodes[] = $node->parentNode->insertBefore($new, $node);
				}
				$node->parentNode->removeChild($node);
			}
		}
		$contents->remove();
		$contents->__construct($nodes);
		$this->__construct($nodes);
	}
	function remove($selector=null) {
		foreach ($this->nodeList as &$node) {
			if ($node->parentNode) {
				$node = $node->parentNode->removeChild($node);
			}
		}
		return $this;
	}
	function _empty() {
		foreach ($this->nodeList as &$node) {
			if ($node->parentNode) {
				$new = $node->cloneNode(false);
				$node->parentNode->replaceChild($new, $node);
				$node = $new;
			}
		}
		return $this;
	}

	function children($selector=null) {
		// if (!is_null($selector)) {
			return $this->find('*', 'xpath');
		// }
	}
	function contents() {
		return $this->find('*|text()', 'xpath');
	}
	function each($callback, $provider=null, $options=array()) {
		if (is_callable($callback)) {
			foreach ($this->nodeList as $node) {
				call_user_func($callback, new $this->classname($node, $this->provider), $provider);
			}
		}
		return $this;
	}

	function find($expr, $type='css') {
		$nodes = array();
		foreach ($this->nodeList as $node) {
			$nodes[] = $this->provider->query->query($expr, $node, $type);
		}
		return $this->_new_nodeset($nodes);
	}
	function filter($selector) {
	}
	function not($selector) {
	}
	function _next($selector=null) {
		return $this->find('following-sibling::*[position()=1]', 'xpath');
	}
	function nextAll($selector=null) {
		return $this->find('following-sibling::*', 'xpath');
	}
	function parent($selector=null) {
		return $this->find('parent::*', 'xpath');
	}
	function parents($selector=null) {
		return $this->find('ancestor::*', 'xpath');
	}
	function siblings($selector=null) {
		return $this->find('preceding-sibling::*|following-sibling::*', 'xpath');
	}
	function html($val=null) {
		if (is_null($val) && isset($this->elements[0])) {
		}
		$this->_empty();
		$this->append($this->provider->query->dom($val));
	}
	function text($val=null) {
		if (is_null($val)) {
			$texts = array();
			if ($text = $this->provider->xpath->query('.//text()')) {
				foreach ($text as $text) {
					$texts[] = $text->nodeValue;
				}
			}
			return join('', $texts);
		}
		$val = htmlspecialchars($val);
		$this->_empty();
		$this->append($this->provider->query->dom($val));
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
		$add = $this->provider->query->dom($selector);
		return $this->_new_nodeset(array_merge($this->nodeList, (array)$add));
	}
	function eq($index) {
		return $this->_new_nodeset(isset($this->nodeList[$index]) ? $this->nodeList[$index] : null);
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
		return $this->_new_nodeset($nodes);
	}


	// Iterator
	function rewind() { $this->_iter_ = 0; }
	function current() { return $this->_new_nodeset($this->nodeList[$this->_iter_]); }
	function key() { return $this->_iter_; }
	function next() { ++$this->_iter_; }
	function valid() { return isset($this->nodeList[$this->_iter_]); }
}


?>
