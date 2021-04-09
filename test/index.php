<?php
date_default_timezone_set('Asia/Tashkent');
//define('API_KEY', '649019040:AAEWOtK0FegspJeXs41_91eWr1uoCBkCIts');
define('DATA_BAZA', 'baza.sqlite');
define('API_KEY', '641840451:AAHSJMqV0zXy4XseokxZknXOug1EW1RjLrk');

include './class-http-request.php';
include './lang.php';
include './function.php';

$update = json_decode(file_get_contents('php://input'));
if (isset($update)){
	if (isset($update->message)){
		$message = $update->message;
		$cid = $message->chat->id;
		$fid = $message->from->id;
		$mid = $message->message_id;
		$text = $message->text;
	}
	if (isset($update->callback_query)){
		$callback = $update->callback_query;
		$cid = $callback->from->id;
		$chat_id = $callback->chat->id;
		$ida = $callback->id;
		$mid = $callback->message->message_id;
		$data = $callback->data;
		$data = explode("()",$data);
	}
}

	if (isset($update->inline_query)){
		$inline = $update->inline_query;
		$userID = $inline->from->id;
		$query = $inline->query;
	
	
		if ($query == "") exit;
	
		if (is_numeric($query)){
			$ssilka = 'http://www.yoshlar.com/load/';
			$ssilka .= QS('url','list','`nomer` = "'.$query.'"');
			
			$get = new HttpRequest("GET",$ssilka);
			$html = HTML($get->getResponse());
			$url = "http://yoshlar.com".trim(strip_tags($html->find("audio")->attr("src")));
			
			$m_info = new HttpRequest("GET",$url);
			$type = $m_info->getHeaders();
			
			if ($type["content_type"] !== "audio/mpeg"){
				$content[] = [
					'type'=>'article',
					'id'=>1,
					'title'=>'Qo\'shiqning ID raqami xato!',
					'input_message_content'=>[
					'message_text'=>'Bu ID raqamli qo\'shiq Yoshlar.com bazasidan topilmadi.',
				],];
				$content[] = [
					'type'=>'article',
					'id'=>2,
					'title'=>'ID музыки неправильно!',
					'input_message_content'=>[
					'message_text'=>'Музыка с таким ID номером не существует на базе yoshlar.com.',
				],];
	
			} else {
				$likes_count = QS('likes_count','list','`nomer` = "'.$query.'"');
				$info = "🎧 | ".humanFileSize($type['size_download'])."\n🖤 | via @SoundUzBot";
				$performer = QS('name','names','`muz_id` = "'.$query.'"');
				$content[] = [
					'type'=>'audio',
					'id'=>1,
					'audio_url'=>$type['url'],
					'title'=>'Qo\'shiqni yuborish | Отправить музыку',
					'performer'=>$performer,
					'caption'=>$info,
					'reply_markup'=>[
						'inline_keyboard'=>[
							[['callback_data'=>"likes_share()".$query,'text'=>"🖤 $likes_count"],],
							[['switch_inline_query'=>$query, 'text'=>"Ulashish | Поделиться ➥"],],
						]
					],
				];
			}
	
		}
	
		bot('answerInlineQuery',[
			'inline_query_id'=>$inline->id,
			'cache_time'=>1,
			'results'=>json_encode($content),
		]);
	}

$user = QS('start','baza','`chat_id` = "'.$cid.'"');
if (!$user){
	if (isset($update) AND $data[0] != 'lang' AND $data[0] != 'likes_share'){
		bot('sendMessage',[
			'chat_id'=>$cid,
			'text'=>'Tilni tanlang | Выберите язык',
			'reply_markup'=>json_encode([
				'inline_keyboard'=>[
					[['callback_data'=>"lang()uz",'text'=>"UZ"],['callback_data'=>"lang()ru",'text'=>"RU"],],
				]
			]),
		]);
		exit;
	}
}

$user_lang = QS('lang','baza','`chat_id` = "'.$cid.'"');
$answer = $lang["$user_lang"];

if (isset($text)){
	switch ($text){
	
		case "/ping":
			$start = $_SERVER['REQUEST_TIME'];
			$mid = bot('sendMessage',[
						'chat_id'=>$cid,
						'text'=>"...",
					]);
			$mid = $mid->result->message_id;
			$time = time() - $start;
			$ttime = time() - $message->date;
			EMT($cid,$mid,"Bot ping: $time s\nTelegram ping: $ttime s");
		break;
		
		case '/stop':
				UP('baza','`lang` = "none"');
				bot('sendMessage',[
					'chat_id'=>$cid,
					'text'=>'Tilni tanlang | Выберите язык',
					'reply_markup'=>json_encode([
						'inline_keyboard'=>[
							[['callback_data'=>"lang()uz",'text'=>"UZ"],['callback_data'=>"lang()ru",'text'=>"RU"],],
						]
					]),
				]);
				
		break;
		
		case '/start':
			$user = QS('start','baza','`chat_id` = "'.$cid.'"');
			if ($user){
				bot('sendMessage',[
					'chat_id'=>$cid,
					'text'=>$answer['start'],
					'parse_mode'=>"HTML",
				]);
			} else {
				bot('sendMessage',[
					'chat_id'=>$cid,
					'text'=>'Tilni tanlang | Выберите язык',
					'reply_markup'=>json_encode([
						'inline_keyboard'=>[
							[['callback_data'=>"lang()uz",'text'=>"UZ"],['callback_data'=>"lang()ru",'text'=>"RU"],],
						]
					]),
				]);
			}
		break;
		
		case '/about':
			$users = count_all('start','baza');
			$req = QS('requests','baza','`chat_id` = "0000"');
			$down = QS('downloads','baza','`chat_id` = "0000"');
			$check = count_all('nomer','list');
			$text = str_replace(['{1}', '{2}', '{3}', '{4}'], [$users, $req, $down, $check], $answer['about']);
			bot('sendMessage',[
				'chat_id'=>$cid,
				'text'=>$text,
				'parse_mode'=>"HTML",
				/*'reply_markup'=>json_encode([
					'inline_keyboard'=>[
						[['callback_data'=>"top_music()", 'text'=>$answer['top_music']],['callback_data'=>"playlist_get()", 'text'=>$answer['playlist_name']],],
					]
				]),*/
			]);
		break;
		
		case "/clean":
			if ($cid == '211920167'){
				$db = new SQLite3("baza.sqlite");
				$db->exec('DROP TABLE `users`');
				$db->exec('DROP TABLE `music`');
				$db->exec('DROP TABLE `list`');
				$db->exec('DROP TABLE `baza`');
				$db->exec('DROP TABLE `block`');
				$db->exec("CREATE TABLE `baza` (chat_id TEXT, start TEXT, requests TEXT, downloads TEXT, lang TEXT)");
				$db->exec("CREATE TABLE `users` (status TEXT, chat_id TEXT)");
				$db->exec("CREATE TABLE `list` (url TEXT, nomer TEXT, chat_id TEXT, likes_id TEXT, likes_count TEXT)");
				$db->exec("CREATE TABLE `playlist` (chat_id TEXT, muz_id TEXT, playlist_name TEXT)");
				$db->exec("CREATE TABLE `block` (url TEXT)");
				$db->exec("CREATE TABLE `names` (muz_id TEXT, name TEXT)");
				$db->exec("CREATE TABLE `bot_settings` (text TEXT, last TEXT, ok TEXT, nok TEXT, sendMessage TEXT)");
				$db->close();
				EX("INSERT INTO `bot_settings` VALUES ('','0','0','0','yes')");
				EX("INSERT INTO `baza` VALUES ('0000','0','0','0','uz')");
			}
		break;
	
		default:
			$status = QS('status','users','`chat_id` = "'.$cid.'"');
			
			if ($status){
				if ($text !== $status){
					DM($cid,$mid);
					bot('sendMessage',[
						'chat_id'=>$cid,
						'text'=>$answer['have_request'],
						'parse_mode'=>'HTML',
					]);
					sleep(3);
					DM($cid,$mid+1);
				}
			
			} else {
				EX("INSERT INTO `users` VALUES ('$text','$cid')");
				bot('sendMessage',[
					'chat_id'=>$cid,
					'text'=>$answer['searching'],
					'parse_mode'=>'HTML',
				]);
				
				/*
				$options = ["a"=>14, "b"=>"Найти", "q"=>"$text"];
				$r = new HttpRequest("GET", "http://yoshlar.com/search", $options);
				$keyboard = KEYB($r,$cid);
				*/

				$options = ["sort"=>2, "action"=>"search", "query"=>"$text"];
				$r = new HttpRequest("GET", "http://semob.net.wox.su/audio/", $options);
				$keyboard = KEYB_SEMOB($r,$cid);

				if (count($keyboard)==0){
					EMT($cid, $mid+1, $answer['not_found'], $keyboard);
				} else {
					$time = sec_to_time(time()-$message->date);
					$text = str_replace(['{SEARCH}', '{TIME_LEFT}'], ["\"$text\"", $time], $answer['found']);
					EMT($cid, $mid+1, $text, $keyboard);
				}
				
				EX('DELETE FROM `users` WHERE `chat_id` = "'.$cid.'"');
			}
			
			$requests = QS('requests','baza','`chat_id` = "'.$cid.'"');
			$requests++;
			UP('baza','`requests` = "'.$requests.'"');
			
			$requests = QS('requests','baza','`chat_id` = "0000"');
			$requests++;
			UP('baza','`requests` = "'.$requests.'"',true);
		break;
	
	} // SWITCH
} // IF TEXT


// DATA START //
if (isset($data)){
	switch ($data[0]){
	
		case 'playlist_get':
		
		break;
		
		case 'top_music':
			ACL($answer['top_music']);

			$db = new SQLite3(DATA_BAZA);
			$all = $db->query("SELECT * FROM `list` ORDER BY likes_count DESC LIMIT 10");
			
			while ($info = $all->fetchArray()){
				$nomer = $info['nomer'];
				$likes_count = $info['likes_count'];
				$nom = QS('name','names','`muz_id` = "'.$nomer.'"');
				$mlist[] = ["callback_data"=>"m()".$nomer,"text"=>"♥ $likes_count | $nom"];
			}
			$db->close();
			
			$keyboard = array_merge(array_chunk($mlist,1));
			bot('sendMessage',[
				'chat_id'=>$cid,
				'text'=>$answer['top_list'],
				'parse_mode'=>"HTML",
				'reply_markup'=>json_encode([
					'inline_keyboard'=>$keyboard,
				]),
			]);
		break;
		
		case 'playlist':
			if ($data[1] == 'add'){
				ACL($answer['playlist_adding']);
				IN('playlist', 'VALUES ("'.$cid.'","'.$data[2].'","")');
			}
			
			if ($data[1] == 'delete'){
				ACL($answer['playlist_deleting']);
				EX('DELETE FROM `playlist` WHERE `muz_id` = "'.$data[2].'"');
			}
			
			$status_in = "add()".$data[2];
			$status = $answer['playlist_add'];
			
			$list = QS('muz_id','playlist','`muz_id` = "'.$data[2].'" AND `chat_id` = "'.$cid.'"');
			if ($list){
				$status_in = "delete()".$data[2];
				$status = $answer['playlist_delete'];
			}
			
			$likes_count = QS('likes_count','list','`nomer` = "'.$data[2].'"');
				
			bot('editMessageReplyMarkup',[
				'chat_id'=>$cid,
				'message_id'=>$mid,
				'reply_markup'=>json_encode([
					'inline_keyboard'=>[
						[['callback_data'=>"playlist()$status_in",'text'=>"$status"], ['callback_data'=>"likes()".$data[2],'text'=>"🖤 $likes_count"],],
						[['switch_inline_query'=>$data[2], 'text'=>$answer['share']],],
					]
				]),
			]);
		break;
		
		case 'likes':
			$nomer = $data[1];
			$likes_id = QS('likes_id','list','`nomer` = "'.$nomer.'"');
			$likes_id_massiv = explode(",", $likes_id);
			$likes_count = QS('likes_count','list','`nomer` = "'.$nomer.'"');
						
			if (in_array($cid,$likes_id_massiv)){
				ACL($answer['alredy_liked']);
			} else {
				ACL($answer['now_like']);
				$likes_id .= "$cid,";
				UPDB('list','`likes_id` = "'.$likes_id.'"','`nomer` = "'.$nomer.'"');
				$likes_count++;
				UPDB('list','`likes_count` = "'.$likes_count.'"','`nomer` = "'.$nomer.'"');
			}
			
			$status_in = "add()".$data[1];
			$status = $answer['playlist_add'];
			
			$list = QS('muz_id','playlist','`muz_id` = "'.$data[1].'" AND `chat_id` = "'.$cid.'"');
			if ($list){
				$status_in = "delete()".$data[1];
				$status = $answer['playlist_delete'];
			}
				
			bot('editMessageReplyMarkup',[
				'chat_id'=>$cid,
				'message_id'=>$mid,
				'reply_markup'=>json_encode([
					'inline_keyboard'=>[
						[['callback_data'=>"playlist()$status_in",'text'=>"$status"], ['callback_data'=>"likes()".$data[1],'text'=>"🖤 $likes_count"],],
						[['switch_inline_query'=>$data[1], 'text'=>$answer['share']],],
					]
				]),
			]);
		break;
		
		case 'likes_share':
			$nomer = $data[1];
			$likes_id = QS('likes_id','list','`nomer` = "'.$nomer.'"');
			$likes_id_massiv = explode(",", $likes_id);
			$likes_count = QS('likes_count','list','`nomer` = "'.$nomer.'"');
						
			if (in_array($cid,$likes_id_massiv)){
				ACL($lang['uz']['alredy_liked'].'/'.$lang['ru']['alredy_liked']);
			} else {
				ACL($lang['uz']['now_like'].'/'.$lang['ru']['now_like']);
				$likes_id .= "$cid,";
				UPDB('list','`likes_id` = "'.$likes_id.'"','`nomer` = "'.$nomer.'"');
				$likes_count++;
				UPDB('list','`likes_count` = "'.$likes_count.'"','`nomer` = "'.$nomer.'"');
			}
				
			bot('editMessageReplyMarkup',[
				'chat_id'=>$chat_id,
				'message_id'=>$mid,
				'reply_markup'=>json_encode([
					'inline_keyboard'=>[
						[['callback_data'=>"likes_share()".$data[1],'text'=>"🖤 $likes_count"],],
						[['switch_inline_query'=>$data[1], 'text'=>"Ulashish | Поделиться ➥"],],
					]
				]),
			]);
		break;
		
		case 'lang':
			if ($user_lang == 'uz' OR $user_lang == 'ru'){
				ACL($answer['lang']);
			} else {
				$user_lang = $data[1];
				$answer = $lang["$user_lang"];
				bot('sendMessage',[
					'chat_id'=>$cid,
					'text'=>$answer['lang'],
					'parse_mode'=>"HTML",
				]);
				bot('sendMessage',[
					'chat_id'=>$cid,
					'text'=>$answer['start'],
					'parse_mode'=>"HTML",
				]);
				$start = date('d/m/Y H:i:s');
				$user = QS('start','baza','`chat_id` = "'.$cid.'"');
				
				if ($user){
					UP('baza','`lang` = "'.$data[1].'"');
				} else {
					EX("INSERT INTO `baza` VALUES ('$cid','$start','0','0','$data[1]')");
				}
			}
		break;
		
		case "m":
			$ssilka = 'http://www.yoshlar.com/load/';
			$ssilka .= QS('url','list','`nomer` = "'.$data[1].'"');
			
			$get = new HttpRequest("GET",$ssilka);
			$html = HTML($get->getResponse());
			$url = "http://yoshlar.com".trim(strip_tags($html->find("audio")->attr("src")));
			
			$m_info = new HttpRequest("GET",$url);
			$type = $m_info->getHeaders();
			
			if ($type["content_type"] != "audio/mpeg"){
				if (!QS('url','block','`url` = "'.$ssilka.'"'))
					IN('block','VALUES ("'.$ssilka.'")');
				ACL($answer['not_found']);
				exit;
			}
				ACL($answer['sending']);
			
			
			$status_in = "add()".$data[1];
			$status = $answer['playlist_add'];
			
			$list = QS('muz_id','playlist','`muz_id` = "'.$data[1].'" AND `chat_id` = "'.$cid.'"');
			if ($list){
				$status_in = "delete()".$data[1];
				$status = $answer['playlist_delete'];
			}
			
			$likes_count = QS('likes_count','list','`nomer` = "'.$data[1].'"');
			
			$caption = "🎧 | ".humanFileSize($type['size_download'])."\n🖤 | via @SoundUzBot";
							
			bot('sendAudio',[
				'chat_id'=>$cid,
				'audio'=>$url,
				'caption'=>$caption,
				'reply_markup'=>json_encode([
					'inline_keyboard'=>[
						[['callback_data'=>"playlist()$status_in",'text'=>"$status"], ['callback_data'=>"likes()".$data[1],'text'=>"🖤 $likes_count"],],
						[['switch_inline_query'=>$data[1], 'text'=>$answer['share']],],
					]
				]),
			]);
			
			$downloads = QS('downloads','baza','`chat_id` = "'.$cid.'"');
			$downloads++;
			UP('baza','`downloads` = "'.$downloads.'"');
			
			$downloads = QS('downloads','baza','`chat_id` = "0000"');
			$downloads++;
			UP('baza','`downloads` = "'.$downloads.'"',true);
		break;
		
		case "m_s":
			$ssilka = 'http://semob.net.wox.su/song/?query=';
			$ssilka .= QS('url','list','`nomer` = "'.$data[1].'"');
			
			$get = new HttpRequest("GET",$ssilka);
			$html = HTML(explode('<div class="razdel2">',$get->getResponse())[0]);

			$music = $html->find('li a');
			$url = trim($music->attr("href"));

			$m_info = new HttpRequest("GET",$url);
			$type = $m_info->getHeaders();

			if ($type["content_type"] == null){

				$get = new HttpRequest("GET",$ssilka);
				$html = HTML(explode('<div class="razdel2">',$get->getResponse())[0]);
				
				$music = $html->find('li a');
				$url = trim($music->attr("href"));
				
			} else {
				if ($type["content_type"] != "audio/mpeg"){
					if (!QS('url','block','`url` = "'.$ssilka.'"'))
						IN('block','VALUES ("'.$ssilka.'")');
					ACL($answer['not_found']);
					exit;
				}
				ACL($answer['sending']);
			}
			
			$status_in = "add()".$data[1];
			$status = $answer['playlist_add'];
			
			$list = QS('muz_id','playlist','`muz_id` = "'.$data[1].'" AND `chat_id` = "'.$cid.'"');
			if ($list){
				$status_in = "delete()".$data[1];
				$status = $answer['playlist_delete'];
			}
			
			$likes_count = QS('likes_count','list','`nomer` = "'.$data[1].'"');
			
			$caption = "🎧 | ".humanFileSize($type['size_download'])."\n🖤 | via @SoundUzBot";
							
			bot('sendAudio',[
				'chat_id'=>$cid,
				'audio'=>$url,
				'caption'=>$caption,
				'reply_markup'=>json_encode([
					'inline_keyboard'=>[
						[['callback_data'=>"playlist()$status_in",'text'=>"$status"], ['callback_data'=>"likes()".$data[1],'text'=>"🖤 $likes_count"],],
						[['switch_inline_query'=>$data[1], 'text'=>$answer['share']],],
					]
				]),
			]);
			
			$downloads = QS('downloads','baza','`chat_id` = "'.$cid.'"');
			$downloads++;
			UP('baza','`downloads` = "'.$downloads.'"');
			
			$downloads = QS('downloads','baza','`chat_id` = "0000"');
			$downloads++;
			UP('baza','`downloads` = "'.$downloads.'"',true);
		break;

		case "p":
			ACL($answer['next_page']);
			$url = str_replace("//","http://",$data[1]);
			$r = new HttpRequest("GET",$url);
			$keyboard = KEYB($r,$cid);
			
			bot('editMessageReplyMarkup',[
				'chat_id'=>$cid,
				'message_id'=>$mid,
				'reply_markup'=>json_encode([
				'inline_keyboard'=>$keyboard
				]),
			]);
		break;

	} // SWITCH END //
} // DATA END //
?>