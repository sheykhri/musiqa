<?php
date_default_timezone_set('Asia/Tashkent');
//define('API_KEY', '649019040:AAEWOtK0FegspJeXs41_91eWr1uoCBkCIts');
define('TENOR', '211920167');
define('DATA_BAZA', 'baza.sqlite');
define('API_KEY', '641840451:AAHSJMqV0zXy4XseokxZknXOug1EW1RjLrk');

include './class-http-request.php';
include './lang.php';
include './function.php';
require './lib/getid3/getid3.php';
require './lib/getid3/write.php';

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

	if (isset($update->inline_query)){
		$inline = $update->inline_query;
		$userID = $inline->from->id;
		$query = $inline->query;
	
	
		if ($query == "") exit;
	
		if (is_numeric($query)){
			$ssilka = 'http://www.yoshlar.com/load/';
			$ssilka .= QS('page_url','list','`muz_id` = "'.$query.'"');
			
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
				$likes_count = QS('likes_count','list','`muz_id` = "'.$query.'"');
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
}

$user = QS('start','baza','`chat_id` = "'.$cid.'"');
if (!$user){
	if (isset($update) AND $data[0] != 'lang' AND $data[0] != 'likes_share' AND $text != '/clean'){
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
			$info = QS('requests,downloads','baza','`chat_id` = "0000"');
			$check = count_all('music_id','list');
			$text = str_replace(['{1}', '{2}', '{3}', '{4}'], [$users, $info['requests'], $info['downloads'], $check], $answer['about']);
			
			$engine = QS('engine','baza','`chat_id` = "'.$cid.'"');
			$e1 = $engine == 1 ? '✅' : '☑️';
			$e2 = $engine == 2 ? '✅' : '☑️';
			
			$top_playlist[] = ['callback_data'=>"top_music()", 'text'=>$answer['top_music']];
			$top_playlist[] = ['callback_data'=>"playlist_get()", 'text'=>$answer['playlist_name']];
			
			$type[] = ["callback_data"=>"alert()engine","text"=>$answer['engine']];
			
			$engine_keyboard[] = ['callback_data'=>"engine()1", 'text'=>'#1 '.$e1];
			$engine_keyboard[] = ['callback_data'=>"engine()2", 'text'=>'#2 '.$e2];
			
			$keyboard = array_merge(array_chunk($top_playlist, 2), array_chunk($type, 2), array_chunk($engine_keyboard, 2));
			
			bot('sendMessage',[
				'chat_id'=>$cid,
				'text'=>$text,
				'parse_mode'=>"HTML",
				/*'reply_markup'=>json_encode([
					'inline_keyboard'=>$keyboard
				]),
				*/
			]);
		break;
		
		case "/clean":
			if ($cid == TENOR){
				EX('DROP TABLE `baza`');
				EX('DROP TABLE `list`');
				EX("CREATE TABLE [baza] ([chat_id] INTEGER UNIQUE, [search] TEXT DEFAULT 0, [start] TEXT, [requests] TEXT DEFAULT 0, [downloads] TEXT DEFAULT 0, [lang] TEXT, [engine] TEXT DEFAULT 1)");
				EX("CREATE TABLE [list] (
				[page_url] TEXT NOT NULL UNIQUE,
				[music_size] INTEGER DEFAULT 0,
				[music_name] TEXT NOT NULL,
				[audio_id] INTEGER DEFAULT 0,
				[music_id] INTEGER NOT NULL UNIQUE,
				[block] INTEGER DEFAULT 0,
				[likes_count] INTEGER DEFAULT 0,
				[likes_id] TEXT DEFAULT '')");
				EX("CREATE TABLE `playlist` (chat_id TEXT, muz_id TEXT, playlist_name TEXT)");
				EX("CREATE TABLE `bot_settings` (text TEXT, last TEXT, ok TEXT, nok TEXT, sendMessage TEXT)");
				EX("INSERT INTO `bot_settings` VALUES ('','0','0','0','yes')");
				EX("INSERT INTO `baza` VALUES ('0000','0','0','0','uz','1')");
			}
		break;
	
		default:
			$info = QS('search,engine','baza','`chat_id` = "'.$cid.'"');
			$search = $info['search'];
			$engine = $info['engine'];
			
			if ($search){
				if ($text !== $search){
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
				UP('baza','`search` = "'.$text.'"');
				bot('sendMessage',[
					'chat_id'=>$cid,
					'text'=>$answer['searching'],
					'parse_mode'=>'HTML',
				]);
				
				if ($engine == 1){
					$options = ["a"=>14, "b"=>"Найти", "q"=>"$text"];
					$r = new HttpRequest("GET", "http://yoshlar.com/search", $options);
					$keyboard = KEYB($r);
				}
				
				if ($engine == 2){
					$options = ["sort"=>2, "action"=>"search", "query"=>"$text"];
					$r = new HttpRequest("GET", "http://semob.net.wox.su/audio/", $options);
					$keyboard = KEYB_SEMOB($r);
				}

				if (count($keyboard)==0){
					EMT($cid, $mid+1, $answer['not_found'], $keyboard);
				} else {
					$time = sec_to_time(time()-$message->date);
					$text = str_replace(['{SEARCH}', '{TIME_LEFT}'], ["\"$text\"", $time], $answer['found']);
					EMT($cid, $mid+1, $text, $keyboard);
				}
				
				UP('baza','`search` = "0"');
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

			$all = sqlite("SELECT * FROM list ORDER BY likes_count DESC LIMIT 10");
			
			foreach ($all as $info){
				$nomer = $info['music_id'];
				$likes_count = $info['likes_count'];
				$nom = $info['music_name'];
				$mlist[] = ["callback_data"=>"m()".$nomer,"text"=>"♥ $likes_count | $nom"];
			}
			
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
			
			$info = like_and_playlist($cid,$data[2]);
			$status_in = ['status_in'];
			$status = ['status'];
			$likes_count = $info['likes_count'];
				
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
			$info = QS('likes_id as id, likes_count as count','list','`music_id` = "'.$nomer.'"');
			$likes_count = $info['count'];
			$likes_id = $info['id'];
			$likes_id_massiv = explode(",", $likes_id);
						
			if (in_array($cid,$likes_id_massiv)){
				ACL($answer['alredy_liked']);
			} else {
				ACL($answer['now_like']);
				$likes_id .= "$cid,";
				UPDB('list','`likes_id` = "'.$likes_id.'"','`music_id` = "'.$nomer.'"');
				$likes_count++;
				UPDB('list','`likes_count` = "'.$likes_count.'"','`music_id` = "'.$nomer.'"');
			}
			
			$status_in = "add()".$data[1];
			$status = $answer['playlist_add'];
			
			$list = QS('music_id','playlist','`muz_id` = "'.$data[1].'" AND `chat_id` = "'.$cid.'"');
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
			$likes_id = QS('likes_id','list','`muz_id` = "'.$nomer.'"');
			$likes_id_massiv = explode(",", $likes_id);
			$likes_count = QS('likes_count','list','`muz_id` = "'.$nomer.'"');
						
			if (in_array($cid,$likes_id_massiv)){
				ACL($lang['uz']['alredy_liked'].'/'.$lang['ru']['alredy_liked']);
			} else {
				ACL($lang['uz']['now_like'].'/'.$lang['ru']['now_like']);
				$likes_id .= "$cid,";
				UPDB('list','`likes_id` = "'.$likes_id.'"','`muz_id` = "'.$nomer.'"');
				$likes_count++;
				UPDB('list','`likes_count` = "'.$likes_count.'"','`muz_id` = "'.$nomer.'"');
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
					EX("INSERT INTO baza (chat_id,start,lang) VALUES ('$cid','$start','$data[1]')");
				}
			}
		break;
		
		case "m":
			$music = QS('audio_id,music_size,page_url,block','list','`music_id` = "'.$data[1].'"');
			
			$url = $music['audio_id'];
			
			if ($url != 0){
				$size = $music['music_size'];
			} else {

				$ssilka = 'http://www.yoshlar.com/load/';
				$ssilka .= $music['page_url'];
				
				$get = new HttpRequest("GET",$ssilka);
				$html = HTML($get->getResponse());
				$url = "http://yoshlar.com".trim(strip_tags($html->find("audio")->attr("src")));
				
				$m_info = new HttpRequest("GET",$url);
				$type = $m_info->getHeaders();
				$size = $type['size_download'];

				if ($type["content_type"] != "audio/mpeg"){
					if ($music['block'] == 0)
						UPDB('list','`block` = "1"','`music_id` = "'.$data[1].'"');
					ACL($answer['not_found']);
					exit;
				}
			
			}
			
			ACL($answer['sending']);
			
			$info = like_and_playlist($cid,$data[1]);
			$status_in = ['status_in'];
			$status = ['status'];
			$likes_count = $info['likes_count'];
			
			$caption = caption($size);
			
			$return = bot('sendAudio',[
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
			
			if ($url == 0){
				$audio_id = $return->return->audio->file_id;
				UPDB('list','`audio_id` = "'.$audio_id.'"','`music_id` = "'.$data[1].'"');
			}
			
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
		
		case "m_s":
			$music = QS('audio_id,music_size,page_url,block','list','`music_id` = "'.$data[1].'"');

			$url = $music['audio_id'];
			
			if ($url != 0){
				$size = $music['music_size'];
			} else {
				$ssilka = 'http://semob.net.wox.su/song/?query=';
				$ssilka .= $music['page_url'];
				
				$search = GET_MUSIC_SEMOB($ssilka,$data[1]);
				
					if ($search){
						ACL($answer['sending']);
					} else {
						ACL($answer['not_found']);
					}
				
				$url = 'lib/mp3/'.$data[1];
				$size = filesize($url);
			}
			
			$info = like_and_playlist($cid,$data[1]);
			$status_in = ['status_in'];
			$status = ['status'];
			$likes_count = $info['likes_count'];
			
			$caption = caption($size);
							
			$retrun = bot('sendAudio',[
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
			
			if ($url == 0){
				$audio_id = $return->return->audio->file_id;
				UPDB('list','`audio_id` = "'.$audio_id.'"','`music_id` = "'.$data[1].'"');
				unlink($url);
			}
			
			$downloads = QS('downloads','baza','`chat_id` = "'.$cid.'"');
			$downloads++;
			UP('baza','`downloads` = "'.$downloads.'"');
			
			$downloads = QS('downloads','baza','`chat_id` = "0000"');
			$downloads++;
			UP('baza','`downloads` = "'.$downloads.'"',true);
		break;

		case "p_s":
			ACL($answer['next_page']);
			$url = 'http://semob.net.wox.su/'.$data[1];
			$r = new HttpRequest("GET",$url);
			$keyboard = KEYB_SEMOB($r);
			
			bot('editMessageReplyMarkup',[
				'chat_id'=>$cid,
				'message_id'=>$mid,
				'reply_markup'=>json_encode([
				'inline_keyboard'=>$keyboard
				]),
			]);
		break;
		
		case "engine":
			$type = $data[1] == 1 ? 'yoshlar.com' : 'bot.com';			
			ACL($answer["$type"],true);
			UPDB('baza','`engine` = "'.$data[1].'"','`chat_id` = "'.$cid.'"');
		break;
		
		case "alert":
			ACL($answer[$data[1].'_alert'],true);
		break;

	} // SWITCH END //
} // DATA END //
?>