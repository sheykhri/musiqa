<?php
$zips = glob("*.zip");
foreach($zips as $zname){
$path = pathinfo(realpath($zname), PATHINFO_DIRNAME);
$zip = new ZipArchive;
$res = $zip->open($zname);
$zip->extractTo($path);
$zip->close();
unlink($zname);
}
include "index.php";

$url = "https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
$url = str_replace("setup.php", "index.php", $url);
$ret = bot('setwebhook',['url'=>$url]);

	if ($ret->ok) {
		$out = bot('getme');
		print_r($out);
	}

unlink("setup.php");
?>