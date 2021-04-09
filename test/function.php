<?php
function bot($method,$datas=[]){
	$url = "https://api.telegram.org/bot".API_KEY."/".$method;
	$ch = curl_init($url);
	$mc = curl_multi_init();
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
	curl_setopt($ch,CURLOPT_POSTFIELDS,$datas);
	curl_multi_add_handle($mc,$ch);
	//$res = curl_exec($ch);
	do {
   		curl_multi_exec($mc, $running);
   		curl_multi_select($mc);
	} while ($running > 0);
	$res = curl_multi_getcontent($mc);
	curl_multi_remove_handle($mc, $ch);
	curl_multi_close($mc);
	return json_decode($res);
}

function SendAll(){
	$db = new SQLite3(DATA_BAZA);
	$last =  QS('last','bot_settings','`sendMessage` = "yes"');
	$text =  QS('text','bot_settings','`sendMessage` = "yes"');
	$users = $db->query("SELECT * FROM baza LIMIT $last,15");
	
	if ($users){
		while ($user = $users->fetchArray()){
			$cid = $user['chat_id'];
			$result = bot('sendMessage',[
				'chat_id'=>$cid,
				'text'=>$text,
			]);
			if ($result->ok){
				$nok =  QS('nok','bot_settings','`sendMessage` = "yes"');
				$nok++;
				UPDB('bot_settings','`nok` = "'.$nok.'"','`sendMessage` = "yes"');
			} else {
				$ok =  QS('ok','bot_settings','`sendMessage` = "yes"');
				$ok++;
				UPDB('bot_settings','`ok` = "'.$ok.'"','`sendMessage` = "yes"');
			}
		}
		$last += 10;
		UPDB('bot_settings','`last` = "'.$last.'"','`sendMessage` = "yes"');
		$db->close();
		sleep(1);
		SendAll();
	} else {
		$ok =  QS('ok','bot_settings','`sendMessage` = "yes"');
		$nok =  QS('nok','bot_settings','`sendMessage` = "yes"');
		bot('sendMessage',[
			'chat_id'=>$cid,
			'text'=>'Yuborish tugadi.
			Yuborilganlar: $ok
			Yuborilmaganlar: $nok',
		]);
		UPDB('bot_settings','`ok` = "0"','`sendMessage` = "yes"');
		UPDB('bot_settings','`nok` = "0"','`sendMessage` = "yes"');
		UPDB('bot_settings','`last` = "0"','`sendMessage` = "yes"');
	}

}

function ACL($text=false){
	global $ida;
	if ($text)
	bot('answerCallbackQuery',[
		'callback_query_id'=>$ida,
		'text'=>$text,
		'show_alert'=>'false',
	]);
	else bot('answerCallbackQuery',['callback_query_id'=>$ida]);
}

function EMT($cid,$mid,$text,$k=null){
	if ($k) $k = json_encode(['inline_keyboard'=>$k]);
	bot('editMessageText',[
		'chat_id'=>$cid,
		'message_id'=>$mid,
		'text'=>$text,
		'reply_markup'=>$k,
	]);
}

function DM($cid,$mid){
	bot('deleteMessage',[
		'chat_id'=>$cid,
		'message_id'=>$mid,
	]);
}

function count_all($type,$table){
	$db = new SQLite3(DATA_BAZA);
	$count = $db->querySingle('SELECT COUNT(`'.$type.'`) FROM `'.$table.'`');
	$db->close();
	return $count;
}

function QS($type,$from,$where){
	$db = new SQLite3(DATA_BAZA);
	$result = $db->querySingle('SELECT `'.$type.'` FROM `'.$from.'` WHERE '.$where);
	$db->close();
	return $result;
}

function QueryDB($query){
	$db = new SQLite3(DATA_BAZA);
	$result = $db->query($query);
	$db->close();
	return $result;
}

function UP($type,$input,$chat=false){
	global $cid;
	$chat_id = $cid;
	if ($chat) $chat_id = "0000";
		EX('UPDATE `'.$type.'` SET '.$input.' WHERE `chat_id` = "'.$chat_id.'"');
}

function UPDB($type,$input,$where){
	EX('UPDATE `'.$type.'` SET '.$input.' WHERE '.$where.'');
}

function IN($type,$values){
	EX('INSERT INTO `'.$type.'` '.$values);
}

function EX($query){
	$db = new SQLite3(DATA_BAZA);
	$db->exec($query);
	$db->close();
}

function sec_to_time($string){
	$day = floor($string/86400);
	$hours = floor(($string/3600)-$day*24);
	$min = floor(($string-$hours*3600-$day*86400)/60);  
	$sec = $string-($min*60+$hours*3600+$day*86400);
	if ($day>=1) $hours = $day." kun va ".$hours;
	return $hours.':'.$min.':'.$sec;
}

function download($cid,$file_id){
	$url = json_decode(file_get_contents('https://api.telegram.org/bot'.API_KEY.'/getFile?file_id='.$file_id),true);
	$path = $url['result']['file_path'];
	file_put_contents("$cid.mp3",file_get_contents('https://api.telegram.org/file/bot'.API_KEY.'/'.$path));
}

function humanFileSize($size){
	$units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB');
	$power = $size > 0 ? floor(log($size, 1024)) : 0;
	return number_format($size / pow(1024, $power), 2, '.', ',') . ' ' . $units[$power];
}

function HTML($input){
	return phpQuery::newDocumentHTML($input);
}

function KEYB($r,$cid){
	$html = HTML($r->getResponse());
	$j=1;
	$pages = $html->find('a.swchItem');
	if ($pages){
		foreach ($pages as $pq){
			$pq = pq($pq);
			$itx = trim(strip_tags($pq->html()));
			if ($itx == '»' OR $itx == '«'){
				$itx = str_replace(['»','«'],['▶','◀'],$itx);
				$page[] = ["callback_data"=>"p()".$pq->attr("href"),"text"=>$itx];
				++$j;
			}
		}
	}

	foreach ($html->find('div.eTitle a') as $a){
		$a = pq($a);
		$ssilka = trim($a->attr("href"));
		$ssilka = explode('load/', $ssilka)[1];
					
		$nom = trim(strip_tags($a->html()));
		$nom = htmlspecialchars_decode($nom, ENT_NOQUOTES);
		//$nom = preg_replace("/\([^)]+\)/", '', $nom); 
		$nom = preg_replace("/\[[^\]]*\]/", '', $nom);
		
		if (!QS('url','block','`url` = "'.$ssilka.'"')){
			$uniq = QS('nomer','list','`url` = "'.$ssilka.'"');
			if (!$uniq){
				$uniq = rand(0,9999999999999);
				IN('list','VALUES ("'.$ssilka.'","'.$uniq.'","'.$cid.'",",","0")');
				IN('names','VALUES ("'.$uniq.'","'.$nom.'")');
			}
			$mlist[] = ["callback_data"=>"m()".$uniq, "text"=>$nom];
		}
	}

	if ($j !== 1){
		$keyboard = array_merge(array_chunk($mlist,1),array_chunk($page,2));
	} else {
		$keyboard = array_merge(array_chunk($mlist,1));
	}
	return $keyboard;
}

function KEYB_SEMOB($r,$cid){
	$html = HTML($r->getResponse());
	$j=1;
	$pages = $html->find('div.page a');
	if ($pages){
		foreach ($pages as $pq){
			$pq = pq($pq);

			$itx = trim(strip_tags($pq->html()));
			$itx = str_replace(' Предыдущая', '', $itx);
			$itx = str_replace('Следующая ', '', $itx);
			$itx = trim(explode(' ', $itx)[0]);

			if (!is_numeric($itx)){
				$itx = str_replace(['&gt;&gt;', '&lt;&lt;'], ['▶', '◀'], $itx);
				$ssilka = $pq->attr("href");
				$page[] = ["callback_data"=>"p_s()".$ssilka,"text"=>$itx];
				$j++;
			}
		}
	}

	foreach ($html->find('div.block a') as $a){
		$a = pq($a);
		$ssilka = trim($a->attr("href"));
		$ssilka = explode('query=', $ssilka)[1];
		
		$nom = trim(strip_tags($a->html()));
		$nom = htmlspecialchars_decode($nom, ENT_NOQUOTES);
		$nom = str_replace('гр. ', '', $nom);
		$nom = str_replace('semob', '', $nom);
		$nom = preg_replace("/\([^)]+\)/", '', $nom);
		$nom = preg_replace("/\[[^\]]*\]/", '', $nom);
		
		if (!QS('url','block','`url` = "'.$ssilka.'"')){
			$uniq = QS('nomer','list','`url` = "'.$ssilka.'"');
			if (!$uniq){
				$uniq = rand(0,9999999999999);
				IN('list','VALUES ("'.$ssilka.'","'.$uniq.'","'.$cid.'",",","0")');
				IN('names','VALUES ("'.$uniq.'","'.$nom.'")');
			}
			$mlist[] = ["callback_data"=>"m_s()".$uniq, "text"=>$nom];
		}
	}
	
	if ($j !== 1){
		$keyboard = array_merge(array_chunk($mlist,1),array_chunk($page,2));
	} else {
		$keyboard = array_merge(array_chunk($mlist,1));
	}
	return $keyboard;
}
?>