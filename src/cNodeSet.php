<?php

class cNodeSet implements Iterator {

	protected $classname = __CLASS__;
	protected $provider = null;
	protected $nodeList = array();
	protected $length = 0;
	protected $query = null;

	function __construct($source=null, $provider=null, $query=null) {
		$this->query = $query;
		$this->provider =& $provider;
		if (is_null($this->provider)) {
			$this->provider = (object) null;
		}

		if ($source instanceof DOMNode || $source instanceof DOMNodeList || $source instanceof $this->classname) {
			$source = array($source);
		}
		foreach ((array)$source as $node) {
			if ($node instanceof DOMNode) {
				// if ($node->nodeType === XML_ELEMENT_NODE && !in_array($node, $this->nodeList, true)) {
				if (!in_array($node, $this->nodeList, true)) {
					$this->nodeList[] = $node;
				}
			} else if ($node instanceof DOMNodeList) {
				foreach ($node as $node) {
					// if ($node->nodeType === XML_ELEMENT_NODE && !in_array($node, $this->nodeList, true)) {
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

	function attr($key, $value=null) {
		if (!is_null($value)) {
			$properties = array($key => $value);
		} else {
			if (is_array($key)) {
				$properties =& $key;
			} else {
				// get
				$key = $this->provider->query->ns_to_dummy($key);
				if (isset($this->nodeList[0]) && $this->nodeList[0]->hasAttribute($key)) {
					$key = $this->provider->query->ns_to_dummy($key);
					return $this->nodeList[0]->getAttribute($key);
				}
				return null;
			}
		}
		foreach ($this->nodeList as $node) {
			foreach ($properties as $name => $value) {
				$name = $this->provider->query->ns_to_dummy($name);
				$node->setAttribute($name, $value);
			}
		}
		return $this;
	}
	function removeAttr($name) {
		foreach ($this->nodeList as $node) {
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
				if (isset($this->nodeList[0])) {
					$style = $this->_css_to_array($this->nodeList[0]->getAttribute('style'));
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
		foreach ($this->nodeList as $node) {
			$oldstyle = $this->_css_to_array($node->getAttribute('style'));
			$newstyle = array_merge($oldstyle, $style);
			$node->setAttribute('style', $this->_css_to_string($newstyle));
		}
		return $this;
	}

	function addClass($name) {
		foreach ($this->nodeList as $node) {
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
		foreach ($this->nodeList as $node) {
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

	protected function _to_dom($contents) {
		$ret = array();
		if (is_string($contents)) {
			$dom = new DOMDocument();
			$dom->loadHTML('<html><body>'.$contents.'</body></html>');
			foreach ($dom->documentElement->firstChild->childNodes as $node) {
				$ret[] = $this->provider->dom->importNode($node, true);
			}
			return $ret;
		}
		if ($contents instanceof DOMNodeList) {
			for ($i=0,$len=$contents->length; $i<$len; ++$i) {
				$ret[] = &$contents->item($i);
			}
			return $ret;
		}
		if ($contents instanceof DOMNode) {
			return array($contents);
		}
		if ($contents instanceof $this->classname) {
			return $contents->nodeList;
		}
		return array();
	}

	function append($contents) {
		$contents = $this->_to_dom($contents);
		foreach ($this->nodeList as $node) {
			foreach ($contents as $content) {
				$new = $content->cloneNode(true);
				$node->appendChild($new);
			}
		}
		return $this;
	}
	function before($contents) {
		$contents = $this->_to_dom($contents);
		foreach ($this->nodeList as $node) {
			foreach ($contents as $content) {
				$new = $content->cloneNode(true);
				$node->parentNode->insertBefore($new, $node);
			}
		}
		return $this;
	}
	function after($contents) {
		$contents = $this->_to_dom($contents);
		foreach ($this->nodeList as $node) {
			foreach ($contents as $content) {
				$new = $content->cloneNode(true);
				if ($ref = $node->nextSibling) {
					$node->parentNode->insertBefore($new, $ref);
				} else {
					$node->parentNode->appendChild($new);
				}
			}
		}
		return $this;
	}
	function replace($contents) {
		$contents = $this->_to_dom($contents);
		foreach ($this->nodeList as &$node) {
			foreach ($contents as $content) {
				$new = $content->cloneNode(true);
				$node->parentNode->insertBefore($new, $node);
			}
			$node->parentNode->removeChild($node);
		}
	}
	function remove($selector=null) {
		foreach ($this->nodeList as $node) {
			$node->parentNode->removeChild($node);
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
		return new $this->classname($nodes, $this->provider);
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
		if (is_null($val) && isset($this->nodeList[0])) {
		}
		$this->_empty();
		$this->append($this->_to_dom($val));
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
		$this->append($this->_to_dom($val));
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
		$add = $this->provider->query->query($selector);
		return new $this->classname(array_merge($this->nodeList, (array)$add), $this->provider);
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
