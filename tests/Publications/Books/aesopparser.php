<?php

$text = file_get_contents('./AesopsFables.adoc');

$sections = explode('image::https://www.gutenberg.org/cache/epub/18732/images/image',$text);

unset($text);

$text = '';

$i = 0;
foreach ($sections as $s)
{
   $s = 'image::https://www.gutenberg.org/cache/epub/18732/images/image'.$i.'.png[]'.$s;
   $text .= $s;
   $i++;
}

//$sections = implode(' ', $sections);

file_put_contents('parseroutput.adoc', $text);