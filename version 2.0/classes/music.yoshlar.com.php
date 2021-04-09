<?php

class MUSIC
{
	
	private $resource, $pagination, $music_list, $tempUrl, $curl, $db;
	public $url;
	
	public function __construct($resource)
	{
		$this->resource = phpQuery::newDocumentHTML($resource);
		$this->db = new DB;
	}
	
	private function artist($artist)
	{
		$artist = htmlspecialchars_decode($artist, ENT_NOQUOTES);
		$artist = preg_replace("/\([^)]+\)/", '', $artist);
		$artist = preg_replace("/\[[^\]]*\]/", '', $artist);
		return $artist;
	}
	
	public function pagination()
	{
		$pages = $this->resource->find('a.swchItem');
		if ($pages) {
			foreach ($pages as $page) {
				$page = pq($page);
				$text = trim(strip_tags($page->html()));
				if ($text == '»' OR $text == '«') {
					$text = str_replace(['»', '«'], ['▶', '◀'], $text);
					$button[] = ['callback_data' => 'p()' . $page->attr("href"), 'text' => $text];
				}
			}
			$this->pagination = array_chunk($button, 1);
		}
	}
	
	public function music()
	{
		$links = $this->resource->find('div.vidmusic a[style="width:95%;"]');
		foreach ($links as $link) {
			$music = pq($link);
			$url = $music->attr('href');
			if ($url) {
				$url = explode('load/', $url)[1];
				$artist = $this->artist(trim(strip_tags($music->text())));
				
				$block = $this->db->search(['music' => 'block', 'url' => $url]);
				$unique = $this->db->search(['music' => 'unique_id', 'url' => $url]);
				
				if ($unique){
					if ($block == 0){
						$music_list[] = ['callback_data' => 'm()' . $unique, 'text' => $artist];
					}
				} else {
					$unique = rand(1,99999);
					$insert = [
						'url' => $url,
						'artist' => $artist,
						'unique_id' => $unique,
					];
					$this->db->insert('music', $insert);
				}
			}
		}
		$this->music_list = array_chunk($music_list, 1);
	}
	
	public function getCallback()
	{
		if (is_array($this->pagination)) {
			return array_merge($this->music_list, $this->pagination);
		}
		return $this->music_list;
	}
	
	public function downloadMusic()
	{
		$this->tempUrl = 'http://yoshlar.com' . $this->resource->find('a[target="blank"]')->attr('href');
		$info = $this->getInfoUrl();
		if ($info['content_type'] == 'audio/mpeg') {
			$this->url = $type['url'];
			return true;
		}
		return false;
	}
	
	private function getInfoUrl()
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_URL, $this->tempUrl);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_HEADER, TRUE);
		$this->curl = curl_getinfo($ch);
		curl_close($ch);
	}
	
	public function __destruct()
	{
		foreach ($this as $key => $value) { 
			unset($this->$key);
		}
	}

}

/*
$request = new Request($music_url);
$request->execute();
$music = new MUSIC(HTML($request->getResponse()));
if ($music->downloadMusic()){
	$url = $music->url;
}
*/
?>