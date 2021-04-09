<?php

class keyboard
{

	private $inline, $button;
	
	public function addButton($input)
	{
		$this->button[] = $this->buttonReplace($input);
	}
	
	public function getButtonArray()
	{
		return $this->button;
	}
	
	public function getButton($option)
	{
		return json_encode(array_merge(['keyboard' => $this->button, 'resize_keyboard' => true], $option));
	}
	
	private function buttonReplace($input)
	{
		foreach ($input as $text) {
			$array[] = ['text' => $text];
		}
		return $array;
	}
	
	public function addInline($input)
	{
		$this->inline[] = $this->inlineReplace($input);
	}
	
	public function getInlineArray()
	{
		return $this->inline;
	}
	
	public function getInline()
	{
		return json_encode(['inline_keyboard' => $this->inline]);
	}
	
	private function inlineReplace($input)
	{
		foreach ($input as $text => $data) {
			$array[] = ['text' => $text, filter_var($data, FILTER_VALIDATE_URL) ? 'url' : 'callback_data' => $data];
		}
		return $array;
	}
	
	
	function __destruct()
	{
		foreach ($this as $key => $value) { 
			unset($this->$key);
		}
	}

}
?>