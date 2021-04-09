<?php

class BOT
{

	private $token;
	private $chat_id;
	private $text = '';
	private $fastMode = false;
	
	public function __construct($token)
	{
		$this->token = $token;
	}
	
	public function setChatId($chat_id)
	{
		$this->chat_id = $chat_id;
	}
	
	public function fastMode($command)
	{
		$this->fastMode = $command;
	}
	
	public function chat($chat_id)
	{
		if ($this->chat_id == $chat_id) {
			return true;
		}
		return false;
	}

	public function setText($text)
	{
		$this->text = $text;
	}
	
	public function api($method, $datas = [])
	{
		if (!isset($datas['chat_id'])) {
			$datas['chat_id'] = $this->chat_id;
		}
		$url = 'https://api.telegram.org/bot' . $this->token . '/' . $method;
		$c = curl_init($url);
		curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($c, CURLOPT_POSTFIELDS, $datas);
		
		if ($this->fastMode) {
			$mc = curl_multi_init();
			curl_multi_add_handle($mc, $c);
			do {
		   		curl_multi_exec($mc, $running);
		   		curl_multi_select($mc);
			} while ($running > 0);
			$result = curl_multi_getcontent($c);
			curl_multi_remove_handle($mc, $c);
			curl_multi_close($mc);
		} else {
			$result = curl_exec($c);
			if (curl_error($c)) {
				file_put_contents('crash.log', "\n\nCurl: " . curl_error($c) . "\n\n", FILE_APPEND);
			}
		}
		curl_close($c);
		$return = json_decode($result);
		
		if ($return->ok == false) {
			file_put_contents('crash.log', "\n\nTelegram: " . $result . "\n\n", FILE_APPEND);
		}
		return $return;
	}
	
	public function text(...$word)
	{
		$word = implode('|', $word);
		if (preg_match_all("#\\b(".$word.")\\b#usi", $this->text, $out)) {
			return true;
		}
		return false;
	}
	
	public function __destruct()
	{
		foreach ($this as $key => $value) { 
			unset($this->$key);
		}
	}
	
}

class API extends BOT
{

	protected $bot;
	
	public function __construct($bot)
	{
		$this->bot = $bot;
	}
	
	public function __call(string $method, array $data)
	{
	    return $this->bot->api($method, $data[0]);
	}
	
}
?>