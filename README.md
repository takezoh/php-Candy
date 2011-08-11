PHP製DOMベーステンプレートエンジン
==========================================

* PythonのKidの様に属性値として記述できます。
* PHPのSmartyの様にファイルを設置しインクルードするだけで使えます。
* Smartyとの互換モードを実装しているので、既存のSmartyテンプレートを流用できます。
* jQueryライクなDOM操作でコンパイラの拡張ができます。
* jQueryライクなCSSセレクタを実装しています。

## 使い方

```php
<?php
include('./php-Candy/src/Candy.php');
$candy = new Candy(array(
	'template.directory' => './templates',
	'cache.directory' => './cache'
));

$candy->assign('string', 'world!!');
$candy->assign('array', array('apple', 'orange', 'banana'));
$candy->assing('null_array', array());
$candy->assign('int', 0);
$candy->assign('bool', true);
$candy->assign('src', 'image.jpg');
$candy->assign('alt', 'IMAGE');

$candy->display('template.html');
?>
```

## タグ制御属性

### php:content

php:contentの値をPHPとして評価した結果で要素内を置き換える

```html
<div php:content="hello . $string">php:content</div>
```
```html
<div>helloworld!!</div>
```

*※PHPの評価結果が偽と評価された場合、親要素（例ではdiv）自体を出力しません。*  
*※php:replaceと同じ要素に指定した場合、php:replaceが優先されます。*

### php:replace

php:replaceの値をPHPとして評価した結果で親要素を置き換える

```html
<div php:replace="'hello ' . $string">php:replace</div>
```
```html
hello world!!
```

*※PHPの評価結果が偽と評価された場合、親要素（例ではdiv）自体を出力しません。*  
*※php:contentと同じ要素に指定した場合、php:replaceが優先されます。*  
*※php:attr, php:*を同じ要素に指定した場合、php:attr, php:*は評価されません。*

### php:while

php:whileの値をPHPとして評価した結果が"真"である限り出力します。

```html
<div php:while="$int++ < 3">hello</div>
```
```html
<div>hello</div>
<div>hello</div>
<div>hello</div>
```

*※php:foreachと同じ要素に指定した場合、php:foreachが優先されます。*

### php:foreach

php:foreachの値をPHP::foreachとして評価し、配列要素分繰り返し出力します。

```html
<div php:foreach="$array as $var" php:content="$var">fruit</div>
```
```html
<div>apple</div>
<div>orange</div>
<div>banana</div>
```

*※php:whileと同じ要素に指定した場合、php:foreachが優先されます。*

### php:foreachelse

直前の兄弟要素の php:foreach の出力回数が0回の場合に、自要素を出力する。

```html
<div php:foreach="$null_array as $var" php:content="$var">fruit</div>
<div php:foreachelse="">php:foreachelse</div>
```
```html
<div>foreachelse</div>
```

### php:if

php:ifの値をPHP::ifとして評価し、"真"の場合のみ出力します。

```html
<div php:if="$bool">true</div>
<div php:if="!$bool">false</div>
```
```html
<div>true</div>
```

### php:elseif

直前の兄弟要素の php:if 又は php:elseif の評価が"偽"の場合に PHP:if として評価を行う。  
また、値が空の場合は、PHP:else として評価し条件分岐を終了する。

```html
<div php:if="$string == hello">hello</div>
<div php:elseif="$string == world!!">world!!</div>
```
```html
<div>world!!</div>
```

### php:cycle

左辺の値を右辺へ循環代入する。

```html
<div php:foreach="$string as $var" php:cycle="(odd,even) as $cls" php:class="$cls" php:content="$var">fruit</div>
```
```html
<div class="odd">apple</div>
<div class="even">orange</div>
<div class="odd">banana</div>
```

*※php:foreach, php:while と同じ要素に指定した場合のみ評価します。*

### php:attrs

php:attrの値から親要素の属性を設定します。  
表記は [属性名=値]。複数指定の場合はカンマで区切ります。

```html
<img php:attrs="src=http://example.com/ . $src, alt=$alt" />
```
```html
<img src="http://example.com/image.jpg" alt="IMAGE" />
```

*※php:replaceと同じ要素に指定した場合、評価しません。*

### php:period

php:period="StartTime, FinishTime" と記述し、StartTimeからFinishTimeの期間要素を出力します。

```html
<div php:period="2011-02-15, 2011-03-15">php:period</div>
```

*2011-02-15から2011-03-15の期間出力。*

```html
<div>php:period</div>
```

*※指定フォーマットは、*yyyy-mm-dd hh:mm:ss* です。StartTime, FinishTime どちらか一方の指定も可能です。*  
*※日付部分を省略し *hh:mm:ss* とした場合、実行時の日付として動作します。*

### php:*

php:*の値をPHPとして評価し、結果を親要素の*属性に設定します。

```html
<img php:src="http://example.com/ . $src" php:alt="$alt" />
```
```html
<img src="http://example.com/image.jpg" alt="IMAGE" />
```

*※php:replaceと同じ要素に指定した場合、評価しません。*

## テンプレート関数

### document( $file )

$fileをインクルードし、出力します。

*include.html*

```html
<strong php:content="'hello ' . $string">include.html</strong>
```
```html
<div php:content="document(include.html)">document()</div>
```
```html
<div><strong>Hello World!</strong></div>
```

### date( $format=null, $timstamp=null )

$timestamp を $format にもとづいてフォーマットします。

*strftime()* のラッパーです。

引数の初期値：  
  $format : %Y-%m-%d %H:%M:%S  
  $timestamp : time()

### upper( $string )

$string のアルファベットを大文字にします。  
*strtoupper()* のラッパーです。

### lower( $string )

$string のアルファベットを小文字にします。  
*strtolower()* のラッパーです。

### capitalize( $string )

$string がアルファベットの場合、各単語の最初の文字を大文字にします。  
*ucwords()* のラッパーです。

### format( $format, $string=null )

$format に数値を渡した場合は  
  *number_format( $format )*  
それ以外の場合は  
  *sprintf( $format, $string )*  
を返します。

### truncate( $string, $length, $end='...' )

$string の長さが $length を超える場合、  
$string の先頭から $length までの文字列に、$end を連結して返します。

*※その他、PHPの組込関数や、グローバルスコープでコール可能な関数を利用できます。*

## テンプレート変数の埋め込み

${ 簡易PHP } とすることで、要素の属性値以外の場所へ記述できます。

```html
<div>hello ${$string}</div>
```
```html
<div>hello world!!</div>
```

*※テンプレート関数なども利用可能です。*

## 簡易PHP

文字列に引用符を必要としない基本的にPHPの文法です。  
文字の塊を文字列として認識します。  
空白含む文字列や演算子を文字列として扱いたい場合は、明示的に引用符で括ります。

## Smarty互換モード

クラスインスタンス生成時に下記のパラメータで有効になります。

*smarty* : Smartyのインスタンス

```php
<?php
include('./smarty/Smarty.class.php');
new Candy(array(
	"smarty" => new Smarty(),
	"cache.use" => true,
	"cache.directory" => "./cache/",
	"template.directory" => "./templates/",
));
?>
```
