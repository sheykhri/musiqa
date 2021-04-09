<?php

class DB
{

	private $query, $pdo;
	
	public function __construct($baza = 'sqlite:baza.sqlite')
	{
		$PDO = new PDO($baza);
		$PDO->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->pdo = $PDO;
	}
	
	private function sqlite()
	{
		try
		{
			if (preg_match("/^(" . implode("|", array("select", "describe", "pragma")) . ")\\s/i", $this->query)){
				$query = $this->pdo->query($this->query);
				$result = $query->fetchAll();
			} else {
				$result = $this->pdo->exec($this->query);
			}
			return $result;
		}
		catch(PDOException $e)
		{
	    	$this->error_report("#XATO:\n\n".$e->getMessage()."\n\n".$this->query);
	    	return false;
		}
	}
	
	public function create_table($table, array $datas)
	{
		foreach ($datas as $name => $options) {
			$data[] = "[$name] $options";
		}

		$value = implode(', ', $data);
		
		$this->query = "CREATE TABLE [$table] ($value)";
		$result = $this->sqlite();
		return $result;
	}
	
	public function insert($table, array $datas)
	{		
		foreach ($datas as $field => $value) {
			$fields[] = $this->quote($field);
			$values[] = $this->quote("$value");
		}
		
		$fields = implode(',', $fields);
		$values = implode(',', $values);
		
		$this->query = "INSERT INTO $table ($fields) VALUES ($values)";
		$result = $this->sqlite();
		return $result;
	}
	
	// return boolean
	public function update($table, array $info)
	{
		$keys = array_keys($info);
		
		$input = $keys[0];
		$input_value = $this->quote($info[$input]);
		
		$where = $keys[1];
		$where_value = $this->quote($info[$where]);
		
		$this->query = "UPDATE $table SET $input = $input_value WHERE $where = $where_value";
		$result = $this->sqlite();
		return $result;
	}
	
	public function countdb(array $info, $attr = '')
	{
		$keys = array_keys($info);
		
		$table = $keys[0];
		$type = $info["$table"];
		
		$this->query = "SELECT COUNT($type) FROM $table $attr";
		$result = $this->sqlite();
		return $result[0][0];
	}
	
	/*
	search(['table' => 'select', 'where' => 'value']);
	*/
	public function search(array $info, $returnAll = false, $simvol = '=')
	{
		$keys = array_keys($info);
		
		$table = $keys[0];
		$select = $info["$table"];
		
		$where = $keys[1];
		$value = $this->quote($info[$where]);

		$this->query = "SELECT $select FROM $table WHERE $where $simvol $value";
		$result = $this->sqlite();
		if ($returnAll) {
			return $result;
		}
		return $result[0][0];
	}
	
	
	private function quote($string)
	{
		return $this->pdo->quote($string);
	}
	
	private function error_report($log)
	{
		file_put_contents('crash.log', "\n\n" . $log . "\n\n", FILE_APPEND);
	}
	
	function __destruct()
	{
		foreach ($this as $key => $value) { 
			unset($this->$key);
		}
	}
	
}
?>