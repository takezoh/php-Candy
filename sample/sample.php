<?php
include('../src/Candy.php');

$candy = new Candy(array(
	'cache-use' => false,
	// 'cache-directory' =>
	// 'template-directory' =>
));

$candy->assign('string', 'world!!');
$candy->assign('int', 0);
$candy->assign('array', array('apple','orange','banana'));
$candy->assign('null', null);
$candy->assign('true', true);
$candy->assign('false', false);

$candy->add_function('document', create_function('$file,$base','
	return "\nOverride Document(): \"document($file)\"\nBase Document(): " . $base->call($file);
'));
$candy->add_compiler('wp:for', create_function('$element, $value, $candy','
	$candy->call("foreach", $element, "\$$value as \$var");
	$candy->call("content", $element, "\$var");
'));

header('Content-Type: text/html; charset=utf8');
echo '<pre><h2>Template</h2>';
echo htmlspecialchars(file_get_contents('./template/test.html'));

echo '<hr><h2>Output</h2>';
echo htmlspecialchars($candy->fetch('test.html'));

echo '<hr><h2>Compiled Cache</h2>';
echo htmlspecialchars(file_get_contents('./cache/'.$candy->get_cachename($candy->get_template_path('test.html'))));
echo '</pre>';
?>
