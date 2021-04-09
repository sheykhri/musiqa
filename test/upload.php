<?php
include '../index.php';

$cid = $_GET['chat_id'];
$url = $_GET['url'];
$caption = $_GET['caption'];
$cqi = $_GET['cqi'];

function encode($string,$key) {
$key = sha1($key);
$strLen = strlen($string);
$keyLen = strlen($key);
$j = 0;
$hash = "";
for ($i = 0; $i < $strLen; $i++) {
$ordStr = ord(substr($string,$i,1));
if ($j == $keyLen) { $j = 0; }
$ordKey = ord(substr($key,$j,1));
$j++;
$hash .= strrev(base_convert(dechex($ordStr + $ordKey),16,36));
}
return $hash;
}

function decode($string,$key) {
$key = sha1($key);
$strLen = strlen($string);
$keyLen = strlen($key);
$j = 0;
$hash = "";
for ($i = 0; $i < $strLen; $i+=2) {
$ordStr = hexdec(base_convert(strrev(substr($string,$i,2)),36,16));
if ($j == $keyLen) { $j = 0; }
$ordKey = ord(substr($key,$j,1));
$j++;
$hash .= chr($ordStr - $ordKey);
}
return $hash;
}

function closeConnection($message = 'OK!'){
if(php_sapi_name() === 'cli' || isset($GLOBALS['exited'])){
return;
}
ob_end_clean();
header('Connection: close');
ignore_user_abort(true);
ob_start();
ob_end_flush();
flush();
$GLOBALS['exited'] = true;
}

if(!file_exists('bot.lock')) {
touch('bot.lock');
}
$lock = fopen('bot.lock', 'r+');
$try = 1;
$locked = false;
while(!$locked){
$locked = flock($lock, LOCK_EX | LOCK_NB);
if(!$locked){
closeConnection();
if($try++ >= 2){
bot('answerCallbackQuery',[
'callback_query_id'=>$cqi,
'text'=>'Qo\'shiq navbatga qo\'yildi...',
'show_alert'=>'false',
]);
file_put_contents('navbat',$url.'{}'.$cid.'{}'.$caption."\n",PHP_EOL);
exit;
}else{
bot('answerCallbackQuery',[
'callback_query_id'=>$ida,
'text'=>"Qo'shiq yuborilmoqda",
'show_alert'=>'false',
]);
$outs = explode('\n',file_get_contents('navbat'));
foreach($outs as $out){
$d = explode('{}',$out);
bot('sendAudio',[
'chat_id'=>$d[1],
'audio'=>$d[0],
'caption'=>$d[2],
'disable_notification'=>true,
]);
}
unlink('navbat');
}
}
}

bot('answerCallbackQuery',[
'callback_query_id'=>$cqi,
'text'=>'🎷Qo\'shiq yuborilmoqda...',
'show_alert'=>'false',
]);
bot('sendAudio',[
'chat_id'=>$cid,
'audio'=>$url,
'caption'=>$caption,
'disable_notification'=>true,
'reply_markup'=>json_encode(
['inline_keyboard'=>[
[['switch_inline_query'=>explode('ad/',$url)[1],'text'=>"Ulashish ➥"],],
]
]),
]);
?>