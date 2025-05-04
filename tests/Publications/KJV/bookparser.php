<?php

echo PHP_EOL;

$filename = "KJV.adoc";
$book = file_get_contents($filename);

for($i = 1; $i < 100; $i++){
   $book = str_replace("== CHAPTER ".strval($i). "\n\n", "== CHAPTER ".strval($i). "\n\n[%hardbreaks]\n", $book);
}

$myfile = fopen($filename, "w") or die("Unable to open file!");
fwrite($myfile, $book);
fclose($myfile);