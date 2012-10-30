<?php

// LOG LEVELS
// 0 - OFF
// 1 - FATAL
// 2 - ERROR
// 3 - WARN
// 4 - INFO
// 5 - DEBUG

define('LOGLEVEL_OFF',		0);
define('LOGLEVEL_FATAL',	1);
define('LOGLEVEL_ERROR',	2);
define('LOGLEVEL_WARN',		3);
define('LOGLEVEL_INFO',		4);
define('LOGLEVEL_DEBUG',	5);

class vb_Networklog
{
	
	/**
	* The vBulletin registry object
	*
	* @var	vB_Registry
	*/
	var $registry = null;	
	
	function vb_Networklog(&$registry)
	{
		$this->registry =& $registry;		
	}
	
	function Write($message,$loglevel,$networkname=null, $networknode=null)
	{
		global $db,$vbulletin;
		
		if ($loglevel <=  $vbulletin->options['vbn_errorlog'])
		{
			$db->query(sprintf("INSERT INTO ".TABLE_PREFIX."network_log 
					(scriptname,message,networkname,networknode,remoteip,loglevel) 
					VALUES
					('$_SERVER[SCRIPT_FILENAME]','%s','%s','%s','$_SERVER[REMOTE_ADDR]',$loglevel)",
					$vbulletin->db->escape_string($message),
					$vbulletin->db->escape_string($networkname),
					$vbulletin->db->escape_string($networknode)));
		}
	}
	
	function Fatal($message,$networkname=null,$networknode=null)
	{
		$this->Write($message,LOGLEVEL_FATAL,$networkname,$networknode);
	}

	function Error($message,$networkname=null,$networknode=null)
	{
		$this->Write($message,LOGLEVEL_ERROR,$networkname,$networknode);
	}
	
	function Warn($message,$networkname=null,$networknode=null)
	{
		$this->Write($message,LOGLEVEL_WARN,$networkname,$networknode);
	}
	
	function Info($message,$networkname=null,$networknode=null)
	{
		$this->Write($message,LOGLEVEL_INFO,$networkname,$networknode);
	}	

	function Debug($message,$networkname=null,$networknode=null)
	{
		$this->Write($message,LOGLEVEL_DEBUG,$networkname,$networknode);
	}		
}

?>