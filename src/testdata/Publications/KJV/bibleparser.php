<?php

echo PHP_EOL;

$book = file_get_contents("KJV.adoc");
$books = explode("== ", $book);

$i = 0;
foreach($books as &$b){

    $myfile = fopen(strval($i).".adoc", "w") or die("Unable to open file!");
    fwrite($myfile, "= ".$b);
    fclose($myfile);

    $i++;
}