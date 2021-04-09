<?php
function deltree($folder) {
    if (is_dir($folder)) {
    	$all = glob("$folder/*");
    	foreach ($all as $file) {
    		if (is_file($file)) {
    				echo "delete: $file<br>";
    				unlink("$file");
    		} else {
    			deltree($file);
    		}
    	}
    } else {
    unlink("$folder");
    }
    rmdir("$folder");
}

mkdir('vendor');

copy("https://github.com/TobiaszCudnik/phpquery/archive/master.zip", "phpQuery.zip");
$zip_name = "phpQuery.zip";
$path = pathinfo(realpath($zip_name), PATHINFO_DIRNAME);
$zip = new ZipArchive;
$res = $zip->open($zip_name);
if ($res === TRUE) {
  $zip->extractTo($path);
  $zip->close();
  rename("phpquery-master/phpQuery", "vendor/phpQuery");
  rename("phpquery-master/.idea", "phpquery-master/idea");
  unlink("phpquery-master/idea/.name");
  deltree("phpquery-master");
  unlink($zip_name);
}

copy("https://github.com/JamesHeinrich/getID3/archive/master.zip", "mp3.zip");
$zip_name = "mp3.zip";
$path = pathinfo(realpath($zip_name), PATHINFO_DIRNAME);
$zip = new ZipArchive;
$res = $zip->open($zip_name);
if ($res === TRUE) {
  $zip->extractTo($path);
  $zip->close();
  rename("getID3-master/getid3", "vendor/getid3");
  deltree("getID3-master");
  unlink($zip_name);
}