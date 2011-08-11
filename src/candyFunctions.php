<?php

class candyFunctions {

	function __construct(&$candy) {
		$this->candy =& $candy;
	}

	function document($file) {
		if (preg_match('/\.(?:tpl|html|htm)$/', $file)) {
			return $this->candy->fetch($file);
		}
		ob_start();
		include($file);
		$contents = ob_get_contents();
		ob_end_clean();
		return $contents;
	}

	function date($format=null, $timestamp=null) {
		if (is_int($format)) {
			$timestamp = $format;
			$format = null;
		}
		if (empty($format)) {
			$format = "%Y-%m-%d %H:%M:%S";
		}
		if (is_null($timestamp)) {
			$timestamp = time();
		}
		return strftime($format, $timestamp);
	}

	function upper($string) {
		if (is_string($string)) {
			return strtoupper($string);
		}
		return $string;
	}

	function lower($string) {
		if (is_string($string)) {
			return strtolower($string);
		}
		return $string;
	}

	function capitalize($string) {
		if (is_string($string)) {
			return ucwords($string);
		}
		return $string;
	}

	function format($format, $string=null) {
		if ($format === 'number' || is_null($string) && is_numeric($format)) {
			return number_format($string ? $string : $format);
		}
		if (is_string($string)) {
			return sprintf($format, $string);
		}
		return $string;
	}

	function truncate($string, $length, $end='...') {
		if (is_string($string) && mb_strlen($string, 'UTF-8') < $length) {
			return mb_substr($string, 0, $length, 'UTF-8') . $end;
		}
		return $string;
	}

	/*
	function counter() {
		$args = func_get_args();
		$argc = count($args);
		$start = $skip = $assign = null;
		switch ($argc) {
		case 1:
			if (is_numeric($args[0])) {
				$start = $args[0];
				break;
			}
			$assign =& $args[0];
			break;
		case 2:
			$start = $args[0];
			if (is_numeric($args[1])) {
				$skip = $args[1];
			} else {
				$assign =& $args[1];
			}
			break;
		case 3:
			$start = $args[0];
			$skip = $args[1];
			$assign =& $args[2];
		}
		if (!is_null($start)) {
			if (!$skip) $skip = 1;
		}

	}
	 */

}
?>
