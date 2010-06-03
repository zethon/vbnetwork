<?php
//-----------------------------------------------------------------------------
// $RCSFile: network.php $ $Revision: 1.14 $
// $Date: 2009/05/10 05:40:32 $
//-----------------------------------------------------------------------------

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile: network.php,v $ - $Revision: 1.14 $');

// #################### PRE-CACHE TEMPLATES AND DATA ######################
$phrasegroups = array();
$specialtemplates = array();

// ########################## REQUIRE BACK-END ############################
require_once('./global.php');
require_once(DIR . '/includes/adminfunctions_template.php');
require_once(DIR . '/includes/adminfunctions_forums.php');
require_once('./includes/class_xml.php');
require_once('./network/functions_network.php');

// ######################## CHECK ADMIN PERMISSIONS #######################
if (!can_administer('canadminforums'))
{
	print_cp_no_permission();
}


// ########################################################################
// ######################### START MAIN SCRIPT ############################
// ########################################################################

print_cp_header($vbphrase['network_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'modify';
}

echo ('<!--('.$_REQUEST['do'].')-->');

// ADD A NEW NETWORK FORM
if ($_REQUEST['do'] == 'add')
{
	print_form_header('network', 'update');	
	print_table_header($vbphrase['add_new_network']);
	
	print_input_row('Network Name', 'network[name]', $network['name'], true,10,10);
	print_description_row('This is the unique name for the network of boards you are creating or joining.');
	print_input_row('Network Admin', 'network[admin]', $network['admin'],true,10,10);
	print_description_row('This is the node code of the network admin. If you are creating your own network, this is the same as self-id. Otherwise, this will be supplied to you by the network admin.');
	print_input_row('Self ID', 'network[selfid]', $network['selfid'],true,10,10);
	print_description_row('This is the node code of YOUR messageboard. If you are creating your own network, this is the same as network admin above. Otherwise, this will be supplied to you by the network admin.');
	
	print_input_row('Network Password','network[password]',$network['password'],true,10,10);
	print_description_row('This is the network password. If you are creating your own network, select a password and remember it, there is no way to retrieve it. This cannot be blank but has a minimum of 1 character. If you are joining a network this will be supplied to you by the network admin.');

	print_submit_row($vbphrase['save'],'');
	
	print_table_break();
}

// UPDATE THE NETWORK PASSWORD
if ($_REQUEST['do'] == 'password')
{
	$vbulletin->input->clean_array_gpc('r', 
			array('newpassword' => TYPE_STR,
						'networkid' => TYPE_UINT				
			));
			
	$networkinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network WHERE (networkid = '".$vbulletin->GPC['networkid']."')");	
	if ($networkinfo['networkid'] > 0)
	{
			$networkdata =& datamanager_init('Network', $vbulletin, ERRTYPE_CP);
			
			foreach ($networkinfo AS $varname => $value)
			{
				$networkdata->set($varname, $value);
			}						
			
			$networkdata->set_condition("networkid = '".($networkinfo['networkid'])."'");
			$networkdata->set('password', md5($vbulletin->GPC['newpassword']));
			$networkid = $networkdata->save();
				
			define('CP_REDIRECT', 'network.php?networkid='.$networkinfo['networkid']);
			print_stop_message('vbn_password_updated');				
	}
	else
		print_stop_message('vbn_invalid_network');	
		
	print_stop_message('saved_network_successfully',$networkinfo['name']);		
}

if ($_REQUEST['do'] == 'sendupdates')
{
	
	
	$vbulletin->input->clean_array_gpc('p', 
			array('sendnodes' => TYPE_BOOL,
						'sendsubs' => TYPE_BOOL,
						'nodes'				=> TYPE_ARRAY,
						'networkid' => TYPE_UINT				
			));	

	$networkinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network WHERE (networkid = '".$vbulletin->GPC['networkid']."')");	
	
	if ($networkinfo['networkid'] > 0 && strtolower($networkinfo['selfid']) == strtolower($networkinfo['admin']))
	{
		$toarray = array();
		foreach ($vbulletin->GPC['nodes'] as $key => $val)
		{
			if ($key != $networkinfo['selfid'])
				array_push($toarray,$key);
		}
			
		if (count($toarray) > 0 && ($vbulletin->GPC['sendnodes'] || $vbulletin->GPC['sendsubs']))
		{
			if ($vbulletin->GPC['sendnodes'])
			{
				$nodexml = fetch_nodesXML($networkinfo['networkid']);
				que_data_packet($nodexml,$networkinfo,'NODE_LIST',$toarray);	
			}
			
			if ($vbulletin->GPC['sendsubs'])	
			{
				$subs = fetch_subsXML($networkinfo['networkid'],$networkinfo['name']);
				que_data_packet($subs,$networkinfo,'SUBS_LIST',$toarray);	
			}
		}
		else
			print_stop_message('vbn_nothing_to_do');	
	}	
	
	define('CP_REDIRECT', 'network.php');
	print_stop_message('vbn_updates_sent',$networkinfo['name']);	
}

if ($_REQUEST['do'] == 'delete')
{
	$vbulletin->input->clean_array_gpc('r', 
			array('networkid' => TYPE_UINT				
			));
			
	print_delete_confirmation('network', $vbulletin->GPC['networkid'], 'network', 'kill', 'networkid', 0,$vbphrase['saved_network_successfully'],'networkid');
}

if ($_POST['do'] == 'kill')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'networkid' => TYPE_UINT
	));

	// delete the network from the network table
	$forumdata =& datamanager_init('Network', $vbulletin, ERRTYPE_CP);
	$forumdata->set_condition("FIND_IN_SET('" . $vbulletin->GPC['networkid'] . "', networkid)");
	$forumdata->delete();
	
	// delete all entries from the network_node table
	$networknode =& datamanager_init('NetworkNode', $vbulletin, ERRTYPE_CP);
	$networknode->set_condition("FIND_IN_SET('" . $vbulletin->GPC['networkid'] . "', networkid)");
	$networknode->delete();	
	
	// delete all entries from the network sub
	$db->query("
		DELETE FROM " . TABLE_PREFIX . "network_sub
		WHERE networkid = ".$vbulletin->GPC['networkid']
		);

	// remove the network from any forums
	$db->query_write("
		UPDATE " . TABLE_PREFIX . "forum
		SET networkid = 0, network_forum_id = 0
		WHERE networkid = ".$vbulletin->GPC['networkid']
		);

	define('CP_REDIRECT', 'network.php');
	print_stop_message('vbn_deleted_network_successfully');
}

if ($_REQUEST['do'] == 'deletenode')
{
	$vbulletin->input->clean_array_gpc('r', 
			array('network_nodeid' => TYPE_UINT,
						'networkid' => TYPE_UINT				
			));
			
	print_delete_confirmation('network_node', $vbulletin->GPC['network_nodeid'], 'network', 'killnode', 'network_node', 0,0,'network_nodeid');
}

if ($_POST['do'] == 'killnode')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'network_nodeid' => TYPE_UINT
	));

	// get the node data before we nuke it
	$nodedata = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network_node WHERE (network_nodeid = ".$vbulletin->GPC['network_nodeid'].")");

	// remove the node from the network_node table
	$forumdata =& datamanager_init('NetworkNode', $vbulletin, ERRTYPE_CP);
	$forumdata->set_condition("FIND_IN_SET('" . $vbulletin->GPC['network_nodeid'] . "', network_nodeid)");
	$forumdata->delete();
	
	// remove any references to this node in the network_sub table
	$db->query("
		DELETE FROM " . TABLE_PREFIX . "network_sub
		WHERE (networkid = ".$nodedata['networkid'].") AND (node_code = '".$nodedata['node_code']."')
		");

	define('CP_REDIRECT', 'network.php');
	print_stop_message('vbn_deleted_network_node_successfully');
}

if ($_REQUEST['do'] == 'update')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'network'				=> TYPE_ARRAY
	));

	$networkdata =& datamanager_init('Network', $vbulletin, ERRTYPE_CP);
	foreach ($vbulletin->GPC['network'] AS $varname => $value)
	{
		if ($varname == 'password')
			$value = md5($value);
			
		$networkdata->set($varname, $value);
		
		if ($value == '')
			print_stop_message('vbn_invalid_network_info');	
	}		
	$networkid = $networkdata->save();	
	
	define('CP_REDIRECT', 'network.php');
	print_stop_message('vbn_created_network_successfully');
}

if ($_REQUEST['do'] == 'save')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'network'				=> TYPE_ARRAY
	));
	
	$newinfo = $vbulletin->GPC['network'];
	$networkinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network WHERE (networkid = '".$newinfo['networkid']."')");
	
	// save the normal form data
	$networkdata =& datamanager_init('Network', $vbulletin, ERRTYPE_CP);
	$networkdata->set('name',$newinfo['name']);
	$networkdata->set('selfid',$newinfo['selfid']);
	$networkdata->set('admin',$newinfo['admin']);
	$networkdata->set_condition("networkid = '".($newinfo['networkid'])."'");
	$networkdata->save();		
	
	define('CP_REDIRECT', 'network.php');
	print_stop_message('saved_network_successfully',$newinfo['name']);
}

if ($_REQUEST['do'] == 'xmlupdate')
{
	$vbulletin->input->clean_array_gpc('p', array(
		'network'				=> TYPE_ARRAY
	));
	
	$newinfo = $vbulletin->GPC['network'];
	$networkinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network WHERE (networkid = '".$newinfo['networkid']."')");	

	if (strlen($newinfo['nodes'])>0)
	{
		if (!updateBoardList($newinfo['networkid'],$newinfo['nodes']))
			print_stop_message('vbn_invalid_boards_data');
	}

	if (strlen($newinfo['subs']) > 0)
	{
		if (!updateSubsList($networkinfo['name'],$newinfo['subs']))
			print_stop_message('invalid_subs_file_data');
	}	
	
	define('CP_REDIRECT', 'network.php');
	print_stop_message('saved_network_successfully',$networkinfo['name']);		
}

if ($_REQUEST['do'] == 'newboard')
{
	$vbulletin->input->clean_array_gpc('r', 
			array('nodecode' => TYPE_STR,
						'nodeurl' => TYPE_STR,
						'nodeserviceurl' => TYPE_STR,
						'networkid' => TYPE_UINT						
			));	
	
	$nodeinfo = $db->query_first("
		SELECT * 
		FROM " . TABLE_PREFIX . "network_node
		WHERE (networkid = ".$vbulletin->GPC['networkid']." 
						AND 
						(
						node_code = '".$vbulletin->GPC['nodecode']."'							
						OR node_url = '".$vbulletin->GPC['nodeurl']."'
						OR node_service_url = '".$vbulletin->GPC['nodeserviceurl']."'
						)
					)
		");
		
		if ($nodeinfo['network_nodeid'] > 0)
		{
			print_stop_message('vbn_node_already_exists');		
		}
		else
		{
			$db->query_write("
				INSERT INTO " . TABLE_PREFIX . "network_node
					(node_code,networkid,node_url,node_service_url)
				VALUES
					('".$vbulletin->GPC['nodecode']."',".$vbulletin->GPC['networkid'].",'".$vbulletin->GPC['nodeurl']."','".$vbulletin->GPC['nodeserviceurl']."')
				");
				
			define('CP_REDIRECT', 'network.php');
			print_stop_message('vbn_added_node_successfully');				
		}		
}

// MODIFYING EXISTING NETWORK
if ($_REQUEST['do'] == 'modify')
{
	
	$vbulletin->input->clean_array_gpc('g', array(
		'networkid'			=> TYPE_UINT,
	));	
	
	$networkid = $vbulletin->GPC['networkid'];
	
	if ($networkid > 0)
	{
		$networkinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network WHERE (networkid = '$networkid')");
		$subs = fetch_subsXML($networkid,$networkinfo['name']);
		$nodes = fetch_nodesXML($networkid);

		print_form_header('network', 'save');
		construct_hidden_code('network[networkid]',$networkid);	
		print_table_header($networkinfo['name']);
		print_input_row('Network Name', 'network[name]', $networkinfo['name'],true,10,10);
		print_input_row('Network Admin', 'network[admin]', $networkinfo['admin'],true,10,10);
		print_input_row('Self ID', 'network[selfid]', $networkinfo['selfid'],true,10,10);		
		
//		print_table_header("Network Info for (".$networkinfo['name'].")");
//		print_textarea_row('Network Nodes', 'network[nodes]',$nodes);
//		print_description_row('If you are the network admin, this XML should be sent to new boards once they have been added to the network. Clicking Update will save the information in the XML to your network.');
//		print_textarea_row('Network Forums', 'network[subs]',$subs);
//		print_description_row('If you are the network admin, this XML should be sent to new boards once they have been added to the network. Clicking Update will save the information in the XML to your network.');		
		print_submit_row($vbphrase['update'],$vbphrase['cancel']);

		// list of network nodes (messageboards)
		$nodes = $db->query("
				SELECT * 
				FROM " . TABLE_PREFIX . "network_node
				WHERE (networkid = $networkid)
			");
		
			print_form_header('', '');		
			print_table_header("Network Nodes",4);	
			
			print_cells_row(array('Node Code','Node URL','Node Service URL',''), 1, 'tcat');
			
			while ($node = $db->fetch_array($nodes))
			{
				if (strtolower($networkinfo['admin']) == strtolower($networkinfo['selfid']))
					$delete = "<a href=\"network.php?do=deletenode&amp;network_nodeid=".$node['network_nodeid']."&amp;networkid=$networkid\">Delete</a>";
				else
					$delete = '';
				
				print_cells_row(array(
					$node['node_code'],
					"<a target=_new href='".$node['node_url']."'>".$node['node_url']."</a>",
					"<a target=_new href='".$node['node_service_url']."'>".$node['node_service_url']."</a>",
					$delete,
					));
			}	
			print_table_footer(4);											

		// add a new node
		if (strtolower($networkinfo['admin']) == strtolower($networkinfo['selfid']))
		{
			print_form_header('network', 'newboard');		
			construct_hidden_code('networkid',$networkid);		
			print_table_header('Add a New Messageboard',2);	
		
			print_input_row('Node Code', 'nodecode','');
			print_description_row('This is the unique identifier for the new messageboard. Should be less than 5 characters long. <br/>(Example: AMB)');
			
			print_input_row('Board URL', 'nodeurl','');
			print_description_row('The URL for the main forums page of the new messageboard. <br/>(Example: http://www.anothermessageboard.com)');

			print_input_row('Board Service URL', 'nodeserviceurl','');
			print_description_row('The URL for service.php script of the messageboard. This is usually in the /network directory. <br/>(Example: http://www.anothermessageboard.com/network/service.php)');
		
			print_submit_row('Save Board','',2);
		}

		// SEND UPDATES HTML TABLE
		$nodes = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "network_node WHERE (networkid = '".($networkid)."');");
		if ($db->num_rows($nodes) > 0 && strtolower($networkinfo['admin']) == strtolower($networkinfo['selfid']))
		{
			print_form_header('network', 'sendupdates');
			construct_hidden_code('networkid',$networkid);	
			print_table_header('Network Updates',2);	
			print_checkbox_row("Send Nodes Update",'sendnodes');
			print_checkbox_row("Send Subs Update",'sendsubs');
			
			print_cells_row(array("Node Code", "Node URL"), 1, 'tcat');
			while ($node = $db->fetch_array($nodes))
			{
				if (strtolower($node['node_code']) != strtolower($networkinfo['selfid']))
					print_cells_row(array("<input type='checkbox' name='nodes[".$node['node_code']."]'>&nbsp;".$node['node_code'],"<a target=_new href='".$node['node_url']."'>".$node['node_url']."</a>"));
			}					
			print_submit_row('Send Updates','',4);
		}
		
		// UPDATE NETWORK PASSWORD TABLE
		if ($networkinfo['networkid'] > 0)
		{
			print_form_header('network', 'password');
			construct_hidden_code('networkid',$networkid);	
			print_table_header('Update Network Password',2);	
			print_input_row('New Password','newpassword');
			print_description_row('If you are a network admin and you change the network password, all admins on your network will have to update their messageboards with the new password.');
			print_submit_row($vbphrase['vbn_update_password_text'],'',4);
		}		
		
		// XML update table
		$subs = fetch_subsXML($networkid,$networkinfo['name']);
		$nodes = fetch_nodesXML($networkid);
				
		print_form_header('network', 'xmlupdate');
		construct_hidden_code('network[networkid]',$networkid);			
		print_table_header("XML Update");
		print_textarea_row('Network Nodes', 'network[nodes]',$nodes);
		print_description_row('If you are the network admin, this XML should be sent to new boards once they have been added to the network. Clicking Update will save the information in the XML to your network.');
		print_textarea_row('Network Forums', 'network[subs]',$subs);
		print_description_row('If you are the network admin, this XML should be sent to new boards once they have been added to the network. Clicking Update will save the information in the XML to your network.');		
		print_submit_row($vbphrase['save']);
									
	}
	else if ($networkid <= 0)
	{
		print_form_header('forum', 'doorder');
		print_table_header($vbphrase['network_manager'], 5);
		
		// TODO: add vbphrases for cell names
		print_cells_row(array('Network Name', 'Self ID', 'Admin',''), 1, 'tcat');
		
		$networks = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "network");
		
		while ($network = $db->fetch_array($networks))
		{
			print_cells_row(
				array(
					"<a href=\"network.php?do=modify&amp;networkid=".$network['networkid']."\">".$network['name']."</a>",
					$network['selfid'], 
					$network['admin'],
					"<a href=\"network.php?do=delete&amp;networkid=".$network['networkid']."\">Delete</a>"
					));
		}
		print_table_footer();
	}
	
}

print_cp_footer();
?>