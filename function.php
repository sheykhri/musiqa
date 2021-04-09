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
	$res = curl_multi_getcontent($ch);
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

function ACL($text=false,$show=false){
	global $ida;
	if ($text)
	bot('answerCallbackQuery',[
		'callback_query_id'=>$ida,
		'text'=>$text,
		'show_alert'=>$show,
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

function error_report($error){
	bot('sendMessage',[
		'chat_id'=>TENOR,
		'text'=>$error,
	]);
}

function sqlite($query){
	try {
		$DB = new PDO("sqlite:DATA_BAZA");
		$DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$DB->exec('PRAGMA journal_mode=WAL');
		$DB->exec('BEGIN IMMEDIATE');
		
			if (mb_stripos($query, 'SELECT')) {
				$result = $DB->query($query);
				$result->setFetchMode(PDO::FETCH_ASSOC);
				$result = $result->fetchAll();
			} else {
				$result = $DB->exec($query);
			}
		
		$DB->exec('COMMIT');
		$DB = null;
		return $result;
	}
	catch(PDOException $e) {
    	error_report($e->getMessage());
    	return false;
	}
}

function count_all($type,$table){
	$db = new SQLite3(DATA_BAZA);
	$count = $db->querySingle('SELECT COUNT(`'.$type.'`) FROM `'.$table.'`');
	$db->close();
	return $count;
}

function QS($type, $from, $where){
	$result = sqlite("SELECT '$type' FROM '$from' WHERE '$where'");
	return $result;
}

function QueryDB($query){
	$result = sqlite($query);
	return $result;
}

function UP($type, $input, $chat=false){
	global $cid;
	$chat_id = $cid;
	if ($chat) $chat_id = "0000";	
	$result = sqlite("UPDATE '$type' SET '$input' WHERE chat_id = '$chat_id'");
	return $result;
}

function UPDB($type, $input, $where){
	$result = sqlite("UPDATE '$type' SET '$input' WHERE '$where'");
	return $result;
}

function IN($type,$values){
	$r = EX('INSERT INTO `'.$type.'` '.$values);
	return $r;
}

function EX($query){
	$db = new SQLite3(DATA_BAZA);
	$r = $db->exec($query);
	$db->close();
	return $r;
}

function sec_to_time($string){
	$day = floor($string/86400);
	$hours = floor(($string/3600)-$day*24);
	$min = floor(($string-$hours*3600-$day*86400)/60);  
	$sec = $string-($min*60+$hours*3600+$day*86400);
	if ($day>=1) $hours = $day." kun va ".$hours;
	return $hours.':'.$min.':'.$sec;
}

function download($file_name,$file_id){
	$url = json_decode(file_get_contents('https://api.telegram.org/bot'.API_KEY.'/getFile?file_id='.$file_id),true);
	$path = $url['result']['file_path'];
	file_put_contents($file_name,file_get_contents('https://api.telegram.org/file/bot'.API_KEY.'/'.$path));
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
		
		$info = QS('block,music_id','list','`page_url` = "'.$ssilka.'"');
	
		if ($info){
			$block = $info['block'];
			$uniq = $info['music_id'];
			if ($block == 0){
				$mlist[] = ["callback_data"=>"m()".$uniq, "text"=>$nom];
			}
		} else {
			$uniq = rand(0,9999999999999);
			IN('list',"(page_url,music_name,music_id) VALUES ('$ssilka','$nom','$uniq')");
		}
		
	}

	if ($j !== 1){
		$keyboard = array_merge(array_chunk($mlist,1),array_chunk($page,2));
	} else {
		$keyboard = array_merge(array_chunk($mlist,1));
	}
	return $keyboard;
}

function KEYB_SEMOB($r,$cid=null){
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
		
		$info = QS('block,music_id','list','`page_url` = "'.$ssilka.'"');
			
		if ($info){
			$block = $info['block'];
			$uniq = $info['music_id'];
			if ($block == 0){
				$mlist[] = ["callback_data"=>"m_s()".$uniq, "text"=>$nom];
			}
		} else {
			$uniq = rand(0,9999999999999);
			IN('list',"(page_url,music_name,music_id) VALUES ('$ssilka','$nom','$uniq')");
		}
	}
	
	if ($j !== 1){
		$keyboard = array_merge(array_chunk($mlist,1),array_chunk($page,2));
	} else {
		$keyboard = array_merge(array_chunk($mlist,1));
	}
	return $keyboard;
}

function GET_MUSIC_SEMOB($ssilka,$id){
	$get = new HttpRequest("GET",$ssilka);
	$html = HTML(explode('<div class="razdel2">',$get->getResponse())[0]);
	
	$music = $html->find('li a');
	$url = trim($music->attr("href"));
	
	$m_info = new HttpRequest("GET",$url);
	$type = $m_info->getHeaders();
	
		if ($type["content_type"] == null){
		return GET_MUSIC_SEMOB($ssilka,$id);
		}
		if ($type["content_type"] != "audio/mpeg"){
			UPDB('list','`block` = "1"','`music_id` = "'.$id.'"');
			return false;
		}
		
	mkdir('lib/mp3');
	
	$file = "lib/mp3/$id";
	
	$ok = file_put_contents($file,file_get_contents($url));
		if ($ok){
			$ok = set_tag($file);
				if (!$ok) return false;
		} else {
			return false;
		}
	return true;
}

function set_tag($music){
	$getID3 = new getID3;
	$getID3->setOption(array('encoding'=>'UTF-8'));
	$result = $getID3->analyze($music);
	
	$title = $result["tags"]["id3v2"]["title"]["0"];
	$artist = $result["tags"]["id3v2"]["artist"]["0"];
	$year = $result["tags"]["id3v2"]["year"]["0"];
	
	$artist = str_replace('Semob.Net_', '', $artist);
	$artist = str_replace('semob.net_', '', $artist);

	$tagwriter = new getid3_writetags;
	$tagwriter->filename = $music;
	$tagwriter->tagformats = array('id3v2.4');
	$tagwriter->overwrite_tags = true;
	$tagwriter->tag_encoding = 'UTF-8';
	$tagwriter->remove_other_tags = true;
	
	$TagData['title'][] = $title;
	$TagData['artist'][] = $artist;
	$TagData['album'][] = 't.me/sounduzbot';
	$TagData['year'][] = $year;
	$TagData['attached_picture'][0]['data'] = file_get_contents("lib/logo.png");
	$TagData['attached_picture'][0]['picturetypeid'] = 0x13;
	$TagData['attached_picture'][0]['description'] = "t.me/sounduzbot";
	$TagData['attached_picture'][0]['mime'] = image_type_to_mime_type(exif_imagetype("lib/logo.png"));
	
	$tagwriter->tag_data = $TagData;
	
		if (!$tagwriter->WriteTags()){
			$error = 'Failed to write tags!\n'.implode("\n\n", $tagwriter->errors);
			bot('sendMessage',[
				'chat_id'=>TENOR,
				'text'=>$error,
			]);
			return false;
		}
	return true;
}

function caption($size){
	return "🎧 | ".humanFileSize($size)."\n🖤 | via @SoundUzBot";
}

function like_and_playlist($cid,$data){
	$status_in = "add()".$data;
	$status = $answer['playlist_add'];
	
	$list = QS('muz_id','playlist','`muz_id` = "'.$data.'" AND `chat_id` = "'.$cid.'"');
	if ($list){
		$status_in = "delete()".$data;
		$status = $answer['playlist_delete'];
	}
	
	$likes_count = QS('likes_count','list','`music_id` = "'.$data.'"');

	return ['likes_count'=>$likes_count, 'status'=>$status, 'status_in'=>$status_in];
}
?>