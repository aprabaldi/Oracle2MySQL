<?php
include_once 'env.php';

class Migrate {
	
	var $oracle_connection;
	var $mysql_connection;
	
	/**
	 * Get configuration file and connect to DBs
	 */
	function __construct(){
		
		if ( ! defined('ENVIRONMENT') OR ! file_exists($file_path = realpath(dirname(__FILE__).'/config/'.ENVIRONMENT.'/database.php'))){
			if ( ! file_exists($file_path = realpath(dirname(__FILE__).'/config/database.php'))){
				$this->doLog('Configuration file not found.');
				die();
			}
			else
				include_once 'config/database.php';
		}
		else
			include_once 'config/'.ENVIRONMENT.'/database.php';
		
		if(!$this->oracle_connect($db) || !$this->mysql_connect($db))
			return false;
	}
	
	/**
	 * Connect to Oracle DB
	 * @param  array $db db connection config array
	 * @return boolean
	 */
	private function oracle_connect($db){
		$this->oracle_connection = oci_connect($db['oracle']['username'],
										$db['oracle']['password'],
										$db['oracle']['hostname'],
										$db['oracle']['char_set']);
		if (!$this->oracle_connection) {
			$e = oci_error();
			$this->doLog($e['message'], True);
			return false;
		}
		return true;
	}

	/**
	 * Connect to MySQL DB
	 * @param  array $db db connection config array
	 * @return boolean
	 */
	private function mysql_connect($db){
		$this->mysql_connection = mysql_connect($db['mysql']['hostname'],
										$db['mysql']['username'],
										$db['mysql']['password']);
		if (!$this->mysql_connection) {
			$e = mysql_error();
			$this->doLog($e['message'], True);
			return false;
		}
		mysql_select_db($db['mysql']['database'],$this->mysql_connection);
		mysql_set_charset($db['mysql']['char_set'], $this->mysql_connection);
		return true;
	}

	/**
	 * Close DB connections
	 */
	function __destruct(){
		mysql_close($this->mysql_connection);
		oci_close($this->oracle_connection);
	}
	
	/**
	 * Execute the migration
	 * @return boolean
	 */
	public function execute(){
		
		$section = 2000;
		$this->doLog(date("d-m-Y H:i:s")." - Start of Migration:");
		
		include_once './database_tables.php';

		if ($tablefields) {
			foreach ($tablefields as $table => $data){
							
				$newname = $data['newname'];
				$fields = $data['fields'];
				
				$this->doLog(date("d-m-Y H:i:s")." - Migrating: ".$newname);
				
				$sql = 'DELETE FROM '.$newname;
				mysql_query($sql, $this->mysql_connection);

				$initial = 1;
				$final = $section;
				
				$stid = oci_parse($this->oracle_connection, 'SELECT MAX(ID) as COUNT FROM '.$table);

				$r = oci_execute($stid);
				$count = oci_fetch_array($stid, OCI_ASSOC+OCI_RETURN_NULLS);

				while( $initial < $count['COUNT'] ){

					$sql = 'SELECT '. implode(',', $fields) .' FROM '.$table;
					if($table == 'tb_SUBRUBROS_WEB')
						$postsql = ' WHERE RUBRO_ID >= '.$initial.' AND RUBRO_ID <= '.$final;
					else
						$postsql = ' WHERE ID >= '.$initial.' AND ID <= '.$final;
					
					$sql = $sql . $postsql;
					
					$stid = oci_parse($this->oracle_connection, $sql);
					$r = oci_execute($stid);
					oci_fetch_all($stid, $datos, null, null, OCI_FETCHSTATEMENT_BY_ROW);
					oci_free_statement($stid);
					
					$bulksql= 'INSERT INTO '. $newname .'('. strtolower(implode(',',$fields)) .') VALUES';

					foreach ($datos as $key1 => $row){
						foreach ($row as $key2 => $value)
							$datos[$key1][$key2] = $this->escape($value);
					}
					
					foreach ($datos as $dato)
						$bulksql .= '('. implode(',', $dato) .'),';
					
					$bulksql = substr($bulksql, 0, -1);
					
					$query = mysql_query($bulksql,$this->mysql_connection);
					if(!$query){
						$this->doLog("ERROR: ".mysql_error($this->mysql_connection)."\nLAST_QUERY_INFO: ".mysql_info($this->mysql_connection), True);
						return false;
					}
					
					$initial+= $section;
					$final+= $section;
				}
				
			}
		}
		$this->doLog(date("d-m-Y H:i:s")." - Migration complete!");
		return true;
	}
	
	/**
	 * escape variables according to type to insert them into MySQL DB
	 * @param  var $str
	 * @return var $str
	 */
	private function escape($str)
	{
		if (is_string($str))
		{
			$str = "'".$this->escape_str($str)."'";
		}
		elseif (is_bool($str))
		{
			$str = ($str === FALSE) ? 0 : 1;
		}
		elseif (is_null($str))
		{
			$str = 'NULL';
		}
	
		return $str;
	}

	/**
	 * MySQL escape_str
	 * @param $str
	 * @param $like
	 *
	 * @return $str
	 */
	private escape_str($str, $like = FALSE)
	{
		if (function_exists('mysqli_real_escape_string') AND is_object($this->mysql_connection))
		{
			$str = mysqli_real_escape_string($this->mysql_connection, $str);
		}
		else
		{
			$str = addslashes($str);
		}
		return $str;
	}
	
	/**
	 * Logging function
	 * @param  string  $str
	 * @param  boolean $error
	 */
	private function doLog($str, $error = False){
		if($error)
			trigger_error(htmlentities($str, ENT_QUOTES), E_USER_ERROR);
		else 
			echo($str."\n");
	}
}

//Execute the migration
$migration = new Migrate();
$migration->execute();