<?php

ini_set('display_errors', 'on');
error_reporting(E_ALL ^ E_NOTICE);

chdir('..');
header('Content-Type: text/html; charset=utf8');
echo '<pre>';

echo '<h2>PHPUnit</h2>';
system('phpunit tests 2>&1');

include('src/Candy.php');

$candy = new Candy(array(
	'log.type' => 'file',
	'log.directory' => 'sample/logs',
	'debugging' => true,
	'cache.use' => false,
	'cache.directory' => 'sample/cache',
	'template.directory' => 'sample/template',
));

$candy->assign('string', 'world!!');
$candy->assign('int', 0);
$candy->assign('array', array('apple','orange','banana'));
$candy->assign('null', null);
$candy->assign('true', true);
$candy->assign('false', false);

// $candy->add_function('now', 'add_function');
// $candy->add_compiler('wp:for', 'add_compiler');
// function add_function($file, $base) {
	// return "\nOverride Document(): \"document($file)\"\nBase Document(): " . $base->call($file);
// }
// function add_function() {
	// return date('Y-m-d H:i:s');
// }
// function add_compiler($element, $value, $candy) {
	// $candy->call("foreach", $element, '$'.$value.' as $var');
	// $candy->call("content", $element, '$var');
// }

echo '<hr><h2>Template</h2>';
echo htmlspecialchars(file_get_contents('sample/template/test.html'));

echo '<hr><h2>Output</h2>';
echo htmlspecialchars($candy->fetch('test.html'));

echo '<hr><h2>Compiled Cache</h2>';
echo htmlspecialchars(file_get_contents('sample/cache/'.$candy->get_cachename($candy->get_template_path('test.html'))));

echo '</pre>';

?>
