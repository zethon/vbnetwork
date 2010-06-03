<?php
//-----------------------------------------------------------------------------
// $RCSFile: deliver.php $ $Revision: 1.8 $
// $Date: 2009/05/10 14:09:00 $
//-----------------------------------------------------------------------------
error_reporting(E_ALL & ~E_NOTICE);
define('THIS_SCRIPT', 'editpost');
define('CVS_REVISION', '$RCSfile: deliver.php,v $ - $Revision: 1.8 $');
$globaltemplates     = array();
$specialtemplates     = array();
$actiontemplates     = array();
$phrasegroups         = array();

$isconsole = (($_SERVER['argv'][1]) == 'console');
$DEBUG = true;

if ($isconsole) // this means a server cron job is launching this
{
	// allows us to include vbulletin framework in a console script
	define('NO_REGISTER_GLOBALS', 1);
	define('SKIP_SESSIONCREATE', 1);
	define('SESSION_BYPASS', 1);
	define('NOCOOKIES', 1);
	define('DIE_QUIETLY', 1);
	error_reporting(0);	

	chdir('..');
	require_once('./global.php');  
}
else // this means vbulletin's scheduled tasks is running us
{
	$db = $vbulletin->db;
}

require_once('./includes/class_xml.php');
require_once('./includes/class_networklog.php');
//require_once('./network/functions_network.php'); // TODO: come back and figure out how to include this

// create log object
$networklog = new vb_Networklog($vbulletin);

// quit out if there are no outgoing packets for this
if (count(glob('./network/packets/outgoing/*.*')) <= 0)
{
	if ($DEBUG)
		echo "<h1>No packets to send...</h1>";

	die;
}

// loops through all the networks looking for something to deliver
$networks = $db->query_read("SELECT * FROM ". TABLE_PREFIX ."network");
while ($networkinfo = $db->fetch_array($networks))
{
	// loop through all the nodes of this network
	$nodes = $db->query("SELECT * 
									FROM ". TABLE_PREFIX . "network_node 
									WHERE (networkid = '".$networkinfo['networkid']."') 
									AND (node_code != '".$networkinfo['selfid']."')");
									
	while ($nodeinfo = $db->fetch_array($nodes))
	{
		// sanity check
		if (strtolower($nodeinfo['node_code']) == strtolower($networkinfo['selfid']))
			continue;
		
		$packagefile = strtolower("./network/packets/outgoing/$networkinfo[name].$nodeinfo[node_code].xml");		

		if ($DEBUG)
		{
			echo "<b>Network:</b> $networkinfo[name]<br/>";
			echo "<b>Destination Node:</b> $nodeinfo[node_code]<br/>";	
			echo "<b>Node service URL:</b> $nodeinfo[node_service_url]<br/>";
			echo "<b>Package file:</b> ($packagefile)<br/>";			
		}
		
		if (file_exists($packagefile))
		{
			echo "<b>Package size:</b> ".filesize($packagefile)." bytes<br/>";
			
			$networkname = strtolower($networkinfo['name']);
			$requester = strtolower($networkinfo['selfid']);
			$password = $networkinfo['password'];
			
			// TODO: come back and get the $data from the $package object above
			$handle = fopen($packagefile, "r");
			$data = fread($handle, filesize($packagefile));
			fclose($handle);
			
			$curlobj = curl_init();
			curl_setopt($curlobj, CURLOPT_URL, $nodeinfo['node_service_url']);
			curl_setopt($curlobj, CURLOPT_POST, 1 );
			$postfields = "action=accept_package&networkname=$networkname&requester=$requester&password=$password&data=".urlencode($data);
			curl_setopt($curlobj,CURLOPT_POSTFIELDS,$postfields);
			curl_setopt($curlobj, CURLOPT_RETURNTRANSFER, 1);
			$requestResult = curl_exec($curlobj);	
			
			if (curl_errno($curlobj)) 
			{
				$curlerror = curl_error($curlobj);
				
				if ($DEBUG)
			   		print $curlerror;
			   		
				$networklog->Error("curl error: $curlerror",$networkinfo['name'],$nodeinfo['node_code']);
			}
			curl_close($curlobj);
			
			if ($DEBUG)
			{
				print "<b>Response:</b><br/><textarea cols=80 rows=15>";
				print "$requestResult";
				print "</textarea></center>";				
			}				

			// parse the response looking for any errors
			$packetxml = new XMLparser($requestResult, '');
			$response = $packetxml->parse();
			if ($response['result']['type'] == 'success')
			{
				$networklog->Info('package delivered',$networkinfo['name'],$nodeinfo['node_code']);
				unlink($packagefile);
			}
			else
			{
				$networklog->Error("package NOT delivered ($requestResult)",$networkinfo['name'],$nodeinfo['node_code']);
			}
		}	
		
		echo "<hr/>";
		
	}	
	
}
?>