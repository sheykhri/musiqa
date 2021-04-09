<?php

require './config.php';

function sec_to_time($string){
	$day = floor($string/86400);
	$hours = floor(($string/3600)-$day*24);
	$min = floor(($string-$hours*3600-$day*86400)/60);
	$sec = $string-($min*60+$hours*3600+$day*86400);
	if ($day >= 1) $hours = $day . '' . $hours;
	return $hours.':'.$min.':'.$sec;
}

$bot = new BOT('990231617:AAFOBq2T5vduUbHqShwXGOq8H3t1mGPHZww');

$language = new i18n();
$language->setPrefix('T');

$api = new API($bot);
$db = new DB();

$update = json_decode(file_get_contents('php://input'));
if (isset($update)) {
	
	if (isset($update->message->text)) {
		$message = $update->message;
		$message_date = $message->date;
		$chat_id = $message->chat->id;
		$from_id = $message->from->id;
		$message_id = $message->message_id;
		$text = $message->text;
		
		$bot->setText($text);
		$bot->setChatId($chat_id);
		
		$user_language = $db->search(['users_list' => 'language', 'chat_id' => $chat_id]);
		
		if ($user_language == '') {	
			$keyboard = new keyboard();
			$keyboard->addInline(['🇺🇿' => 'l;uz', '🇷🇺' => 'l;ru', '🇺🇸' => 'l;en']);
			$api->sendAnimation([
				'animation' => "CAACAgIAAxkBAAI-r147E7xfPcCN87YgS_7w_aRr6E6SAAIOAAOWn4wOD4Dno4KVp9cYBA",
				'reply_markup'=> $keyboard->getInline(),
			]);
			exit;
		}
		
		$language->setFallbackLang($user_language);
		$language->init();
		
	}
	
	if (isset($update->callback_query)) {
		$callback = $update->callback_query;
		$message_date = time(); //$callback->message->date;
		$chat_id = $callback->message->chat->id;
		$from_id = $callback->from->id;
		$message_id = $callback->message->message_id;
		$callback_id = $callback->id;
		$data = $callback->data;
		$data = explode(";",$data);
		
		$bot->setChatId($from_id);
	}
	
}

if (isset($data)) {

	if ($data[0] == 'l') {
		$language->setFallbackLang($data[1]);
		$language->init();
		
		$api->deleteMessage(['message_id' => $message_id]);
		$api->sendMessage(['text' => T::start, 'parse_mode' => 'HTML']);
		
		if ($db->search(['users_list' => 'chat_id', 'chat_id' => $chat_id])) {
			$insert = [
				'language' => $data[1],
				'chat_id' => $chat_id,
			];
			$db->update('users_list', $insert);
			exit;
		}
		
		$insert = [
			'chat_id' => $chat_id,
			'language' => $data[1],
		];
		$db->insert('users_list', $insert);
		exit;
	}
	
	$user_language = $db->search(['users_list' => 'language', 'chat_id' => $chat_id]);
	$language->setFallbackLang($user_language);
	$language->init();
	
	if ($data[0] == 'm') {
		$api->answerCallbackQuery([
			'callback_query_id' => $callback_id,
			'text' => T::sendingMusic,
			'show_alert' => false
		]);
		
		$id = $data[1];
		$text = $data[2];
		$search_start = $data[3];
		
		$music_id = $db->search(['music' => 'file_id', 'unique_id' => $id]);
		if ($music_id) {
			$api->sendAudio(['audio' => $music_id]);
			exit;
		}
		
		$music = new Music($text, $search_start);
		$url = $music->downloadMusicById($id);
		
		$filePath = $chat_id . time() . '.mp3';
		$ok = file_put_contents($filePath, file_get_contents($url));
		if ($ok) {
			$tagged = $music->setMusicIdTags($filePath);
		} else {
			$api->sendMessage(['text' => T::sendingError]);
			exit;
		}
		
		if ($tagged) {
			$result = $api->sendAudio(['audio' => 'https://tursunoff.altervista.org/wp-content/music/' . $filePath]);
			if ($result->ok) {
				$file_id = $result->result->audio->file_id;
				$insert = [
					'file_id' => $file_id,
					'unique_id' => $id
				];
			} else {		
				$insert = [
					'unique_id' => $id,
					'block' => '1'
				];
			}
			$db->insert('music', $insert);
			unlink($filePath);
			exit;
		}
		
		unlink($filePath);
		$api->sendMessage(['text' => T::sendingFail]);
		exit;
	}
	
	if ($data[0] == 'p') {
		$current = $data[1];
		$option = $data[2];
		$text = $data[3];
		$pages_count = $data[4];
		
		if ($option == '-') {
			if ($current == 1) {
				$api->answerCallbackQuery([
					'callback_query_id' => $callback_id,
					'text' => T::noPagesIsset, 
					'show_alert' => false
				]);
				exit;
			} else {
				$natija = $current - 1;
			}
		}
		
		if ($option == '+') {
			$natija = $current + 1;
			if ($natija > $pages_count) {
				$api->answerCallbackQuery([
					'callback_query_id' => $callback_id,
					'text' => T::noPagesIsset,
					'show_alert' => false
				]);
				exit;
			}
		}
		
		$api->answerCallbackQuery([
			'callback_query_id' => $callback_id,
			'text' => T::loadingPage,
			'show_alert' => false
		]);
		
		$search_start = $natija;
		$music = new Music($text, $search_start);
		
		if ($music->getPagesCount()) {
			$music->pagination();
		}
		
		$music->search();
		$time = sec_to_time(time() - $message_date);
		$list = str_replace(['{ALL_PAGES_COUNT}', '{CURRENT_PAGE_NUMBER}', '{SPEND_TIME}', '{MUSIC_LIST}'], [$music->getPagesCount(), $search_start, $time, $music->getList()], T::list);
		
		$api->editMessageText([
			'message_id' => $message_id,
			'text' => $list,
			'parse_mode' => 'HTML',
			'reply_markup' => json_encode([
				'inline_keyboard' => $music->getButtons()
			]),
		]);
		exit;
	}
	
	if ($data[0] == 'd') {
		$api->deleteMessage(['message_id' => $message_id]);
		exit;
	}
	
	if ($data[0] == 'a') {
		$api->answerCallbackQuery(['callback_query_id' => $callback_id]);
		exit;
	}
	
}

if ($bot->text('start', 'help')) {
	
	$api->sendMessage(['text' => T::start, 'parse_mode' => 'HTML']);

} else if ($bot->text('about')) {

	$api->sendMessage(['text' => T::about, 'parse_mode' => 'HTML']);

} else if ($bot->text('stop')) {
	
	$keyboard = new keyboard();
	$keyboard->addInline(['🇺🇿' => 'l;uz', '🇷🇺' => 'l;ru', '🇺🇸' => 'l;en']);
	
	$api->sendAnimation([
		'animation' => "CAACAgIAAxkBAAI-r147E7xfPcCN87YgS_7w_aRr6E6SAAIOAAOWn4wOD4Dno4KVp9cYBA",
		'reply_markup'=> $keyboard->getInline(),
	]);
	
	$insert = [
		'language' => '',
		'chat_id' => $chat_id,
	];
	$db->update('users_list', $insert);
	exit;
	
} else if ($bot->text('baza') AND $bot->chat('211920167')) {
	
	$table = [
		'chat_id' => 'INTEGER NOT NULL UNIQUE',
		'language' => 'TEXT',
		'blocked' => 'TEXT DEFAULT 0',
	];
	$db->create_table('users_list', $table);
	
	$table = [
		'file_id' => 'TEXT UNIQUE',
		'unique_id' => 'INTEGER NOT NULL UNIQUE',
		'block' => 'INTEGER',
	];
	$db->create_table('music', $table);
	$api->sendMessage(['text' => "<b>Baza tayyor!</b>", 'parse_mode' => 'HTML']);
	exit;
	
} else {
	
	if (4 >= strlen($text)) {
		$api->sendMessage([
			'text' => T::manyText,
			'parse_mode' => 'HTML'
		]);
		exit;
	}
		
	$result = $api->sendMessage([
		'text' => T::searchingMusic,
		'parse_mode' => 'HTML'
	]);
	$message_id = $result->result->message_id;
	
	$search_start = 1;
	$music = new Music($text, $search_start);
	
	if ( ! $music->checkResult()) {
		$api->editMessageText([
			'message_id' => $message_id,
			'text' => T::noResult,
			'parse_mode' => 'HTML',
		]);
		exit;
	}
	
	if ($music->getPagesCount()) {
		$music->pagination();
	}
	
	$music->search();
	$time = sec_to_time(time() - $message_date);
	$list = str_replace(['{ALL_PAGES_COUNT}', '{CURRENT_PAGE_NUMBER}', '{SPEND_TIME}', '{MUSIC_LIST}'], [$music->getPagesCount(), $search_start, $time, $music->getList()], T::list);
	
	$api->editMessageText([
		'message_id' => $message_id,
		'text' => $list,
		'parse_mode' => 'HTML',
		'reply_markup' => json_encode([
			'inline_keyboard' => $music->getButtons()
		]),
	]);
	exit;
}
?>