<?php

class cQuery {

	const CHUNKER = '/((?:\((?:\([^()]+\)|[^()]+)+\)|\[(?:\[[^\[\]]*\]|[\'"][^\'"]*[\'"]|[^\[\]\'"]+)+\]|\\\\.|[^ >+~,(\[\\\\]+)+|[>+~])(\s*,\s*)?((?:.|\r|\n)*)/s';
	const	TAG_REGEXP = '/^((?:[\w\*\-]|\\\\.)+)(.*)?$/';

	protected $_regexp = array(
		'open_tag' => '<\s*%s(\s+(?:(?:\'.*?(?<!\\\\)\')|(?:".*?(?<!\\\\)")|[^>])*)?>',
		'close_tag' => '<\s*\/\s*%s\s*>',
	);

	private $dom = null;
	private $xpath = null;

	protected $_root = null;
	protected $_contextnode = null;
	protected $_doctype = null;
	protected $_content_type = null;
	protected $_html_exists = false;
	protected $_head_exists = false;
	protected $_body_exists = false;

	function __construct($source) {
		$this->dom = new DOMDocument();
		$this->dom->preserveWhiteSpace = false;
		if (@$this->dom->loadHTML($this->_preload(trim($source)))) {
			$this->xpath = new DOMXPath($this->dom);
			if (!$this->_root || $this->_root === 'body') {
				$this->_contextnode = $this->xpath->query('//body')->item(0);
			} else {
				$this->_contextnode = $this->xpath->query('//'.$this->_root)->item(0);
			}
		}
	}

	function __get($key) {
		return $this->dom->{$key};
	}

	function __call($method, $args) {
		return call_user_func_array(array($this->dom, $method), $args);
	}

	function __invoke($query) {
		return $this->query($query);
	}

	protected function _preload($source, $encoding='UTF-8') {
		// remove BOM
		if ('efbbbf' === strtolower(join('', unpack('H*', substr($source, 0, 3))))) {
			$source = substr($source, 3);
		}
		$source = preg_replace('/<\!--.*?-->|\t/s', '', $source);
		$source = preg_replace('/\r\n|\r/s', "\n", trim($source));
		// $source = mb_convert_encoding($source, 'HTML-ENTITIES', $encoding);

		// get Doctype
		$this->_doctype = null;
		if (preg_match('/('.sprintf($this->_regexp['open_tag'], '\!\s*DOCTYPE').')(.*)$/si', $source, $doctype)) {
			$source = trim($doctype[3]);
			$this->_doctype = $doctype[1];
		}
		if ($this->_html_exists = preg_match('/'.sprintf($this->_regexp['open_tag'].'(.*?)'.$this->_regexp['close_tag'], 'html', 'html').'/si', $source, $html)) {
			$source = trim($html[2]);
		}
		// get Header
		$this->_content_type = null;
		if ($this->_head_exists = preg_match('/^(.*?)('.sprintf($this->_regexp['open_tag'].'(.*?)'.$this->_regexp['close_tag'], 'head', 'head').')(.*)$/si', $source, $head)) {
			$head_dom = new DOMDocument();
			$head_dom->loadHTML($head[2]);
			$head_xpath = new DOMXpath($head_dom);
			if ($meta = $head_xpath->query('//meta[@http-equiv]')) {
				foreach ($meta as $meta) {
					if (strtolower($meta->getAttribute('http-equiv')) === 'content-type') {
						$this->_content_type = '<meta http-equiv="content-type" content="'. $meta->getAttribute('content') .'" />';
						$meta->parentNode->removeChild($meta);
						break;
					}
				}
			}
			$head[0] = trim(preg_replace(
				'/^.*?(<head[^>]*>)(.*?)<\s*\/\s*head\s*>.*$/is',
				'$1<meta http-equiv="content-type" content="text/html; charset=utf8" />$2</head>',
				html_entity_decode($head_dom->saveHTML())
			));
			$source = $head[1] . $head[5];
		}
		// get Body
		if ($this->_body_exists = preg_match('/'.sprintf($this->_regexp['open_tag'].'(.*?)'.$this->_regexp['close_tag'], 'body', 'body').'/si', $source, $body)) {
			$source = trim($body[2]);
		}

		// restore source
		if ($this->_body_exists || !$this->_head_exists) {
			$source = '<body'. (!empty($body[1]) ? ' '. trim($body[1]) : '') .'>'. $source .'</body>';
		}
		$source = $this->_head_exists ? $head[0] . $source : '<head><meta http-equiv="content-type" content="text/html; charset=utf8" /></head>'. $source;
		// $source = $this->_head_exists ? $head[0] . $source : '<head></head>'. $source;
		$source = '<html'. (!empty($html[1]) ? ' '. trim($html[1]) : '') .'>'. $source .'</html>';
		// $source = $this->_doctype ? $this->_doctype . $source : '<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN" "http://www.w3.org/TR/REC-html40/loose.dtd">'. $source;
		$source = $this->_doctype ? $this->_doctype . $source : $source;

		// DOM: ContextNode
		$this->_root = null;
		if ($this->_body_exists && $this->_head_exists || $this->_html_exists) {
			$this->_root = 'html';
		} else if ($this->_body_exists) {
			$this->_root = 'body';
		} else if ($this->_head_exists) {
			$this->_root = 'head';
		}

		return $source;
	}

	function saveHTML() {
		return $this->save();
	}

	function save() {
		$source = $this->dom->saveHTML();

		if (preg_match('/<'. $this->_root .'[^>]*>(.*)<\/'. $this->_root .'>/s', $source, $matched)) {
			$source = $matched[$this->_root ? 0 : 1];
		}
		if (!$this->_head_exists) {
			$source = preg_replace('/<head[^>]*>.*?<\/head>/s', '', $source);
		} else {
			$source = preg_replace('/<meta.*?http-equiv\s*=\s*([\'"])\s*content-type\s*\1[^>]*>/', $this->_content_type, $source);
		}
		if ($this->_html_exists && $this->_doctype) {
			$source = $this->_doctype . $source;
		}
		return $source;
	}

	function query($expr, $type='css') {
		if (strtolower($type) === 'xpath') {
			return $this->_results_nodeset($this->xpath->query($expr));
		}
		return $this->_results_nodeset($this->_rex_css($expr));
	}

	protected function _results_nodeset($nodes) {
		return new cNodeSet($nodes, (object)array(
			'dom' => &$this->dom,
			'query' => &$this,
		));
	}

	protected function _rex_css($query) {
		$far = $query;

		$extra = null;
		$parts = array();
		while (preg_match(self::CHUNKER, $far, $m)) {
			$far = $m[3];
			$parts[] = $m[1];
			if (!empty($m[2])) {
				$extra = $this->_rex_css($m[3]);
				break;
			}
		}
		return array_merge($this->_parse_css($parts), (array)$extra);
	}

	protected function _parse_css($parts) {
		$query = $this->_context_node_process($parts);
		return $this->_nodeset_process($query);
	}

	protected function _context_attr($matched) {
		$name = '@'. $matched[1];
		if (!isset($matched[2])) {
			return '['.$name.']';
		}
		$quote = $matched[3];
		$var = $matched[4];
		switch ($matched[2]) {
		case '=':
			return '['.$name.'='.$quote.$var.$quote.']';
		case '|=':
			return '[contains(concat("-", normalize-space('.$name.'), "-"), '.$quote.'-'.$var.'-'.$quote.')]';
		case '~=':
			return '[contains(concat(" ", normalize-space('.$name.'), " "), '.$quote.' '.$var.' '.$quote.')]';
		case '^=':
			return '[starts-with('.$name.','.$quote.$var.$quote.')]';
		case '$=':
			return '[substring('.$name.', string-length('.$name.') - string-length('.$quote.$var.$quote.') + 1) = '.$quote.$var.$quote.']';
		case '*=':
			return '[contains('.$name.', '.$quote.$var.$quote.')]';
		}
		return $matched[0];
	}

	protected function _context_child($matched) {
		switch ($matched[1]) {
		case 'first':
			return '/*[position()=1]';
		case 'last':
			return '/*[position()=last()]';
		case 'nth':
			// :nth-child(index/even/odd/equation)
			if (is_numeric($matched[2])) {
				return '[position()='.$matched[2].']';
			}
			switch ($matched[2]) {
			case 'even':
				return '[position() mod 2 =1]';
			case 'odd':
				return '[position() mod 2 =0]';
			default:
				if (isset($matched[4])) {
					return '[position() mod '.$matched[3].' ='.(int)$matched[4].']';
				}
				return '[position() mod '.$matched[3].' =0]';
			}
			break;
		case 'only':
			return '[count(*)=1]/*';
		}
		return $matched[0];
	}

	protected function _context_node_process($parts) {
		$query = null;
		for ($i=0,$len=count($parts); $i<$len; ++$i) {
			// :not(selector)
			// $part = preg_replace('/:not(\((?:([\'"]).*?(?<!\\\\)\2|(?1)|[^)])*\))/', '[not$1]', $part);

			// ノード集合処理
			// :header /h1, h2, h3
			// :image
			// :input
			// :password
			// :radio
			// :reset
			// :select
			// :submit
			// :text


			$dim = '//';

			switch ($part = $parts[$i]) {
			case '+':
				preg_match(self::TAG_REGEXP, $parts[++$i], $m);
				$query .= '/following-sibling::*[1][self::'.(empty($m[1])? '*':$m[1]).']';
				$part = $m[2];
				$dim = '';
				break;

			case '~':
				preg_match(self::TAG_REGEXP, $parts[++$i], $m);
				$query .= '/following-sibling::'.(empty($m[1])? '*':$m[1]);
				$part = $m[2];
				$dim = '';
				break;

			case '>':
				$dim = '/';
				$part = $parts[++$i];
				// Fall through

			default:
				preg_match(self::TAG_REGEXP, $part, $m);
				if (empty($m[1])) $part = '*' . $part;
			}
			if (empty($part)) {
				continue;
			}

			// #id
			$part = preg_replace('/#((?:[\w\-_]|\\\\.)+)/', '[id="$1"]', $part);
			// .class
			$part = preg_replace('/\.((?:[\w\-_]|\\\\.)+)/', '[class~="$1"]', $part);
			// [attr\S="var"]
			$part = preg_replace_callback('/\[\s*((?:[\w\-_]|\\\\.)+)\s*(?:(\S?=)\s*(?:([\'"])(.*?)(?<!\\\\)\3)|)\s*\]/', array($this, '_context_attr'), $part);
			// :contents(text)
			$part = preg_replace('/:contents\(([\'"])(.*?)(?<!\\\\)\1\)/', '[contains(text(), $1$2$1)]', $part);
			// :*-child / (index/even/odd/equation)
			$part = preg_replace_callback('/:(first|last|nth|only)-child(?:\(\s*(even|odd|(?:[+\-]?(\d+)(?:n\s*(?:[+\-]\s*(\d+))?)?))\s*\))?/', array($this, '_context_child'), $part);
			// :empty
			$part = preg_replace('/:empty/', '[not(*) and not(text())]', $part);
			// :parent
			$part = preg_replace('/:parent/', '[count(*) or count(text())]', $part);

			$query .= $dim . $part;
		}
		return $query;
	}

	protected function _nodeset_process($query) {
		$nodes = array($this->_contextnode);
		while (preg_match('/^(.*?)(?:(?<!:):(\w+)(?:\(([0-9]+)(n)?(?:\s*\+\s*([0-9]+))?\))?(.*)?)?$/', $query, $m)) {
			if (!empty($m[1])) {
				$lists = array();
				foreach ($nodes as $node) {
					$m[1] = preg_replace('/^\//', './', $m[1]);
					$m[1] = preg_replace('/^\[/', 'self::*[', $m[1]);
					$elements = $this->xpath->query($m[1], $node);
					if ($elements && $elements->length > 0) {
						foreach ($elements as $element) {
							$lists[] = $element;
						}
					}
				}
				$nodes = $lists;
			}
			if (empty($m[2])) {
				break;
			}
			if (count($nodes)) {
				$results = array();
				switch ($m[2]) {
				case 'first':
					$results[] = $nodes[0];
					break;
				case 'last':
					$results[] = $nodes[count($nodes) -1];
					break;
				case 'eq':
					$results[] = $nodes[(int)$m[3]];
					break;
				case 'gt':
					$results = array_slice($nodes, (int)$m[3] + 1);
					break;
				case 'lt':
					$results = array_slice($nodes, 0, (int)$m[3] + 1);
					break;
				case 'nth':
					$a = (int)$m[5];
					if (empty($m[4])) {
						$results[] = $nodes[(int)$m[3]];
						break;
					} else {
						$r = $m[3];
					}
					// Fall through
				case 'even':
				case 'odd':
					switch ($m[2]) {
					case 'even':
						$a = 0;
						$r = 2;
						break;
					case 'odd':
						$a = 1;
						$r = 2;
						break;
					}
					for ($i=0,$len=count($nodes); $i<$len; ++$i) {
						if ($i%$r===$a) {
							$results[] = $nodes[$i];
						}
					}
					break;
				}
				$nodes = $results;
			}
			if (empty($m[6])) {
				break;
			}
			$query = $m[6];
		}
		return $nodes;
	}
}

?>
