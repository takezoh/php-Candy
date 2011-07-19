<?php
ini_set('display_errors', 'on');
error_reporting(E_ALL ^ E_NOTICE);

$loop = 1;
$cashing = false;
$vars = array(
	'Name' => 'Fred Irving Johnathan Bradley Peppergill',
	'FirstName' => array('John', 'Mary', 'James', 'Henry'),
	'LastName' => array('Doe', 'Smith', 'Johnson', 'Case'),
	'Class' => array(array('A', 'B', 'C', 'D'), array('E', 'F', 'G', 'H'), array('I', 'J', 'K', 'L'), array('M', 'N', 'O', 'P')),
	'contacts' => array(array('phone' => '1', 'fax' => '2', 'cell' => '3'), array('phone' => '555-4444', 'fax' => '555-3333', 'cell' => '760-1234')),

	'option_values' => array('NY', 'NE', 'KS', 'IA', 'OK', 'TX'),
	'option_output' => array('New York', 'Nebraska', 'Kansas', 'Iowa', 'Oklahoma', 'Texas'),
	'option_selected' => 'NE',
);

$smarty_dir = dirname(dirname(__FILE__)). '/smarty/Smarty-3.0.8';
$candy_dir = dirname(dirname(__FILE__));
require($smarty_dir . '/libs/Smarty.class.php');
require($candy_dir.'/src/Candy.php');

$start = microtime(true);
$output = null;
for ($i=0; $i<$loop; ++$i) {
	$output = bench_smarty($smarty_dir, $caching, $vars);
}
echo 'Smarty: '. (microtime(true) - $start) .'<br>';

$start = microtime(true);
$output = null;
for ($i=0; $i<$loop; ++$i) {
	$output = bench_candy($candy_dir, $caching, $vars);
}
echo 'Candy: '. (microtime(true) - $start) .'<br>';
echo $output;



function bench_smarty($dir, $caching, $vars) {
	$smarty = new Smarty();
	$smarty->caching = $cashing;
	$smarty->cache_lifetime= 120;
	$smarty->cache_dir = $dir .'/demo/cache';
	$smarty->compile_dir = $dir .'/demo/templates_c';
	$smarty->template_dir = $dir .'/demo/templates';
	$smarty->config_dir = $dir . '/demo/configs';

	foreach ($vars as $key => $value) {
		$smarty->assign($key, $value);
	}
	$smarty->assign('Name', $vars['Name'], true);
	return $smarty->fetch('index.tpl');
}

function bench_candy($dir, $caching, $vars) {
	$candy = new Candy(array(
		'cache.use' => $caching,
		'cache.directory' => $dir.'/sample/cache',
		'template.directory' => $dir.'/sample/template/benchmark',
	));
	foreach ($vars as $key => $value) {
		$candy->assign($key, $value);
	}
	return $candy->fetch('index.tpl');
}
