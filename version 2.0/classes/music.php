<?php

class Music
{
	
	private $text, $search_start, $resource, $pages_count;
	private $pagination, $music_list, $list, $music_info;
	
	public function __construct($text, $search_start)
	{
		$this->text = $text;
		$this->search_start = $search_start;
		
		$options = [
			'do' => 'search',
			'subaction' => 'search', 
			'story' => $text,
			'search_start' => $search_start
		];
		
		$request = new Request('https://bisyor.me/');
		$request->setRequestType('GET');
		$request->setPostFields($options);
		$request->execute();
		
		$this->resource = phpQuery::newDocumentHTML($request->getResponse());
		$this->pagesCount();
	}
	
	private function pagesCount()
	{
		$this->pages_count = $this->resource->find('div.navigation a:last')->text();
	}
	
	public function checkResult()
	{
		return $this->resource->find('div[id^="entryID"]')->text();
	}
	
	public function pagination()
	{
		$pagination[] = ['text' => '◀️', 'callback_data' => 'p;' . $this->search_start . ';-;' . $this->text . ';' . $this->pages_count];
		$pagination[] = ['text' => '️❌', 'callback_data' => 'd;'];
		$pagination[] = ['text' => '▶️', 'callback_data' => 'p;' . $this->search_start . ';+;' . $this->text . ';' . $this->pages_count];
		$this->pagination = $pagination;
	}
	
	public function search()
	{
		$i = 1;
		foreach ($this->resource->find('div[id^="entryID"]') as $info) {
			$info = pq($info);
			$music_id = str_replace('entryID', '', $info->attr('id'));
			$title = $info->attr('data-title');
			$track_time = trim(strip_tags($info['div[class="track-time-musc"]']));
			
			$list .= "<b>$i.</b> $title | $track_time\n";
			$text = $this->text;
			$search_start = $this->search_start;
			
			$music_list[] = ['text' => $i, 'callback_data' => 'm;' . $music_id . ';' . $text . ';' . $search_start];
			
			$i++;
		}
		$this->list = $list;
		$this->music_list = $music_list;
	}
	
	public function getButtons()
	{
		if (count($this->pagination) >= 1) {
			$button = array_merge(array_chunk($this->music_list, 5), array_chunk($this->pagination, 3));
		} else {
			$button = array_merge(array_chunk($this->music_list, 5));
		}
		return $button;	
	}
	
	public function getList()
	{
		return $this->list;
	}
	
	public function getPagesCount()
	{
		return $this->pages_count;
	}
	
	public function downloadMusicById($id)
	{
		$info = $this->resource->find('div[id="entryID'.$id.'"]');
		$this->music_info = $info->attr('data-title');
		return $info->attr('data-track');
	}
	
	public function setMusicIdTags($music)
	{
		require 'vendor/getid3/getid3.php';
		require 'vendor/getid3/write.php';
		
		$getID3 = new getID3;
		$getID3->setOption(array('encoding' => 'UTF-8'));
		
		$info = explode(' - ', $this->music_info);
		$title = $info[1];
		$artist = $info[0];
		
		$tagwriter = new getid3_writetags;
		$tagwriter->filename = $music;
		$tagwriter->tagformats = array('id3v2.4');
		$tagwriter->overwrite_tags = true;
		$tagwriter->tag_encoding = 'UTF-8';
		$tagwriter->remove_other_tags = true;
		
		$TagData['title'][] = $title;
		$TagData['artist'][] = $artist . ' (t.me/freelistenbot)';
		$TagData['album'][] = 't.me/freelisten';
		$TagData['attached_picture'][0]['data'] = file_get_contents('vendor/logo.jpg');
		$TagData['attached_picture'][0]['picturetypeid'] = 0x13;
		$TagData['attached_picture'][0]['description'] = 't.me/freelisten';
		$TagData['attached_picture'][0]['mime'] = image_type_to_mime_type(exif_imagetype('vendor/logo.jpg'));
		
		$tagwriter->tag_data = $TagData;
		
		if ( ! $tagwriter->WriteTags()) {
			$error = 'ID3 TAGS!\n'.implode("\n\n", $tagwriter->errors);
			DB::error_report($error);
			return false;
		} else {
			return true;
		}
	}
	
	function __destruct()
	{
		foreach ($this as $key => $value) { 
			unset($this->$key);
		}
	}

}
?>