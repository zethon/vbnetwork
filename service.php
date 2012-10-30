<?
//-----------------------------------------------------------------------------
// $Workfile: service.php $ $Revision: 1.14 $
// $Date: 2010/06/02 18:40:36 $
//-----------------------------------------------------------------------------
error_reporting(E_ALL & ~E_NOTICE);
define('CVS_REVISION', '$RCSfile: service.php,v $ - $Revision: 1.14 $');
define('BOARDWARE','vbulletin');

chdir('..');
require_once('./global.php');
require_once('./includes/class_xml.php');
require_once('./network/functions_network.php');

require_once('./includes/class_dm.php');
require_once('./includes/class_dm_threadpost.php');
require_once('./includes/class_networklog.php');
require_once('./includes/functions_misc.php');

function printError($xml)
{
	// close the main group
	$xml->close_group();
	
	// send the output to the requester
	header('Content-Type: text/xml;');
	echo $xml->output();	
	exit;
}

// grab the uri's action
$action = $_POST['action'];

// create log object
$networklog = new vb_Networklog($vbulletin);

// create the xml return object
if ($vbulletin->options['templateversion'] >= 3.6)
	$response = new XMLexporter($vbulletin);
else
	$response = new XMLexporter();
	
$response->add_group('response');
$response->add_tag('action',$action ? $action : 'null');

switch ($action)
{
	case 'echo_post':
		foreach ($_POST as $key => $val)
		{
			$response->add_tag($key,$val);
		}		
	break;	
	
	case 'check_password':
		if ($vbulletin->options['vbn_allowpasswordcheck'])
		{
			$networkname = $_POST['networkname'];
			$password = $_POST['password'];	
			$md5 = $_POST['md5'];
			
			if (!isset($networkname) || !isset($password))
			{
				$response->add_tag('result','invalid parameters',array('type'=>'error'));
				printError($response);
			}		
			
			$networkinfo = $db->query_first(sprintf("SELECT * 
														FROM " . TABLE_PREFIX . "network 
														WHERE (name = '%s')",
														$vbulletin->db->escape_string($networkname)											
														);
														
			if ($networkinfo['networkid'] <= 0)
			{
				$response->add_tag('result',"invalid \$networkinfo for nework ($networkname)",array('type'=>'error'));
				printError($response);
			}
			
			$password = ($md5 == '1') ? $password : md5($password);
			
			if ($networkinfo['password'] == $password)
			{
				$response->add_tag('result','valid network password',array('type'=>'success'));				
			}
			else
			{
				$response->add_tag('result','invalid network password',array('type'=>'error'));
				printError($response);
			}				
		}
	break;
	
	case 'accept_package':
		$networkname = $_POST['networkname'];
		$requester = $_POST['requester'];
		$password = $_POST['password'];
		$data = $_POST['data'];
		
		// make sure we've got all the data we need
		if (!isset($data) || !isset($requester) || !isset($networkname) || !isset($password))
		{
			$response->add_tag('result','invalid parameters',array('type'=>'error'));
			$networklog->Warn('invalid parameters to accept_packege');
			printError($response);
		}
		
		$networkinfo = $db->query_first(sprintf("SELECT * 
													FROM " . TABLE_PREFIX . "network 
													WHERE (name = '%s')",
													$vbulletin->db->escape_string($networkname)											
													);
													
		if ($networkinfo['networkid'] <= 0)
		{
			$response->add_tag('result',"invalid \$networkinfo for nework ($networkname)",array('type'=>'error'));
			$networklog->Warn("invalid network info for ($networkname)");
			printError($response);
		}
		
		if ($networkinfo['password'] != $password)
		{
			$response->add_tag('result','invalid network password',array('type'=>'error'));
			$networklog->Warn('invalid network password');
			printError($response);
		}
		
		if (strtolower($requester) == strtolower($networkinfo['selfid']))
		{
			$response->add_tag('result','trying to send packet to self!',array('type'=>'error'));			
			$networklog->Info('trying to send a packet to yourself?');
			printError($response);
		}
		
		$requesterinfo = $db->query_first(sprintf("SELECT * 
													FROM " . TABLE_PREFIX . "network_node 
													WHERE (networkid = '".$networkinfo['networkid']."') 
													AND (node_code = '%s')",
													$vbulletin->db->escape_string($requester)													
													);
													
		if ($requesterinfo['network_nodeid'] <= 0)
		{
			$response->add_tag('result','unknown requester',array('type'=>'error'));
			$networklog->Warn("unknown requester ($requester)",$networkinfo['name']);
			printError($response);
		}
		
		$newfilename = strtolower('./network/packets/incoming/'.$networkinfo['name'].'.'.($networkinfo['selfid']).'.'.(time()).'.xml');
							
		// write the file
		$fh = fopen($newfilename, 'w');
		if (!$fh)
		{
			$response->add_tag('result','unable to create incoming packet file',array('type'=>'error'));
			$networklog->Warn("unable to create incoming packet file ($newfilename)",$networkinfo['name']);
			printError($response);
		}

		fwrite($fh, $data);
		fclose($fh);	
		
		$ret = process_incoming_package($networkinfo['name']);
		if ($ret == NETWORK_SUCCEED)
		{
			$response->add_tag('result',null,array('type'=>'success'));		
			$networklog->Info('recieved packet',$networkinfo['name'],$requesterinfo['node_code']);
		}
		else
		{
			$response->add_tag('result',$ret,array('type'=>'error'));		
			$networklog->Warn("error processing incoming package ($ret)",$networkinfo['name'],$requesterinfo['node_code']);
			printError($response);
		}
		
	break;
	
	default:
		if (defined('CVS_REVISION'))
		{
			$re = '#^\$' . 'RCS' . 'file: (.*\.php),v ' . '\$ - \$' . 'Revision: ([0-9\.]+) \$$#siU';
			$cvsversion = preg_replace($re, '\1, CVS v\2', CVS_REVISION);
			
		}	
		
		
		$response->add_group('result',array('type'=>'info'));
		$response->add_tag('boardware',BOARDWARE);
		$response->add_tag('boardwareversion',$vbulletin->versionnumber);
		$response->add_tag('service',CVS_REVISION);
			
		$cvsver =  fetch_file_cvs_version('./network/functions_network.php');
		if (strlen($cvsver) > 0)
		{
			$response->add_tag('library',$cvsver);			
		}
		
		$cvsver =  fetch_file_cvs_version('./network/deliver.php');
		if (strlen($cvsver) > 0)
		{
			$response->add_tag('deliver',$cvsver);	
		}
		
		$response->close_group();
		$networklog->Info('service info sent');
	break;
}

// close the main group
$response->close_group();

// send the output to the requester
header('Content-Type: text/xml;');
echo $response->output();
	
?>