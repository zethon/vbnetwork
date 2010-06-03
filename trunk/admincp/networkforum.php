<?php
//-----------------------------------------------------------------------------
// $RCSFile: network.php $ $Revision: 1.5 $
// $Date: 2009/06/04 01:33:44 $
//-----------------------------------------------------------------------------

// ######################## SET PHP ENVIRONMENT ###########################
error_reporting(E_ALL & ~E_NOTICE);

// ##################### DEFINE IMPORTANT CONSTANTS #######################
define('CVS_REVISION', '$RCSfile: networkforum.php,v $ - $Revision: 1.5 $');

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

print_cp_header($vbphrase['vbn_network_form_manager']);

if (empty($_REQUEST['do']))
{
	$_REQUEST['do'] = 'list';
}

echo ('<!--('.$_REQUEST['do'].')-->');

if ($_REQUEST['do'] == 'addforuminput')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'networkid'			=> TYPE_UINT,
	));		
	
	print_form_header('networkforum', 'addforum');		
	construct_hidden_code('networkid',$vbulletin->GPC['networkid']);		
	print_table_header('Add a New Network Forum',2);	
	
	print_input_row('Network Forum ID', 'subid','');
	print_description_row('This is the unique <b>numerical</b> identifier for the new network forum. To assign this network forum to a forum on your messageboard use the Forum Manager.<br/>(Example: 123)');
	
	print_submit_row('Add Network Forum','',2);	
}

if ($_REQUEST['do'] == 'addforum')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'networkid'			=> TYPE_UINT,
		'subid'			=> TYPE_UINT,
	));
	
	$networkid = $vbulletin->GPC['networkid'];		
	$self = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network WHERE (networkid = $networkid)");
	
	$testforum = $db->query_first("
		SELECT * FROM " . TABLE_PREFIX . "network_sub
		WHERE (networkid = $networkid) AND (subid = ".$vbulletin->GPC['subid'].")
		");
		
	if ($testforum['network_subid'] > 0)
		print_stop_message('vbn_network_forum_already_exists');		
	
	$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "network_sub
			(subid,node_code,networkid)
		VALUES
			(".$vbulletin->GPC['subid'].",'".$self['selfid']."',$networkid)
		");
		
		define('CP_REDIRECT', 'networkforum.php');
		print_stop_message('vbn_added_network_forum_successfully');			
}

if ($_REQUEST['do'] == 'addboardinput')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'networkid'			=> TYPE_UINT,
		'subid'			=> TYPE_UINT,
	));		

	$networkid = $vbulletin->GPC['networkid'];		
	$self = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network WHERE (networkid = $networkid)");
	$subinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network_sub WHERE (networkid = $networkid) AND (subid = ".$vbulletin->GPC['subid'].")");
	
	print_form_header('networkforum', 'addboard');		
	construct_hidden_code('networkid',$vbulletin->GPC['networkid']);		
	construct_hidden_code('subid',$vbulletin->GPC['subid']);
	print_table_header('Add a Messageboard to Forum Network',2);	
	
	print_cells_row(array('Sub ID',$subinfo['subid']));
	print_input_row('Node Code', 'nodecode','');
	print_description_row('This is the node code of the messageboard you want add to the network forum.<br/>(Example: AMB)');
	
	print_submit_row('Add Board to Network Forum','',2);	
}

if ($_REQUEST['do'] == 'addboard')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'networkid'			=> TYPE_UINT,
		'subid'			=> TYPE_UINT,
		'nodecode'			=> TYPE_STR,
	));	
	
	$networkid = $vbulletin->GPC['networkid'];	
	
	$testboard = $db->query_first("
		SELECT * FROM " . TABLE_PREFIX . "network_sub
		WHERE (networkid = $networkid) AND (subid = ".$vbulletin->GPC['subid'].") AND (node_code = '".$vbulletin->GPC['nodecode']."')
		");
		
	if ($testboard['network_subid'] > 0)
		print_stop_message('vbn_board_already_subscribed_to_network_forum');
		
	unset($testboard);		
		
	$testboard = $db->query_first("
		SELECT * FROM " . TABLE_PREFIX . "network_node
		WHERE (networkid = $networkid) AND (node_code = '".$vbulletin->GPC['nodecode']."')
		");
		
	if ($testboard['network_nodeid'] == 0)
		print_stop_message('vbn_unknown_messageboard');
			
	
	$db->query_write("
		INSERT INTO " . TABLE_PREFIX . "network_sub
			(subid,node_code,networkid)
		VALUES
			(".$vbulletin->GPC['subid'].",'".$vbulletin->GPC['nodecode']."',$networkid)
		");
		
		define('CP_REDIRECT', 'networkforum.php');
		print_stop_message('vbn_added_board_to_forum_successfully');				
}


if ($_REQUEST['do'] == 'removeboardconfirm')
{
	$vbulletin->input->clean_array_gpc('r', 
			array('network_subid' => TYPE_UINT				
			));
			
	print_delete_confirmation('network_sub', $vbulletin->GPC['network_subid'], 'networkforum', 'removeboard', 'network_subid', 0,$vbphrase['saved_network_successfully'],'network_subid');
}

if ($_REQUEST['do'] == 'removeboard')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'network_subid' => TYPE_UINT
	));	
	
	// delete all entries from the network sub
	$db->query("
		DELETE FROM " . TABLE_PREFIX . "network_sub
		WHERE network_subid = ".$vbulletin->GPC['network_subid']
		);
		
		define('CP_REDIRECT', 'networkforum.php');
		print_stop_message('vbn_removed_board_from_forum_successfully');				
}

if ($_REQUEST['do'] == 'syncforum')
{
	$vbulletin->input->clean_array_gpc('r', array(
		'networkid' => TYPE_UINT,
		'forumid' => TYPE_UINT
	));		
	
	$threads = $db->query("
		SELECT * FROM " . TABLE_PREFIX . "thread
		WHERE (forumid = ".$vbulletin->GPC['forumid'].")
			AND (network_thread_id = '')
	");
	
	if ($db->num_rows($threads) > 0)
	{
		$networkinfo = $db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "network 
			WHERE (networkid = ".$vbulletin->GPC['networkid'].")
		");
		
		$foruminfo = $db->query_first("
			SELECT * FROM " . TABLE_PREFIX . "forum 
			WHERE (forumid = ".$vbulletin->GPC['forumid'].")
		");			
		
		while ($thread = $db->fetch_array($threads))
		{
			$posts = $db->query("
				SELECT * FROM " . TABLE_PREFIX . "post
				WHERE (threadid = ".$thread['threadid'].")
				ORDER BY dateline
			");
			
			$count = 0;
			while ($post = $db->fetch_array($posts))
			{		
				$post['message'] = $post['pagetext'];
				
				if ($count == 0)
				{
					print("New Thread Title: $thread[title]<br/>");
					que_createpost_packet($networkinfo,$foruminfo,$thread,$post,'NEW_THREAD');
				}
				else
				{
					$thread['network_thread_id'] = strtolower($networkinfo['selfid'].'.'.$thread['threadid']);
					que_createpost_packet($networkinfo,$foruminfo,$thread,$post,'NEW_POST');
				}
				
				print("Post Text: $post[pagetext]<br/>");
				$count++;
			}
			
			print("<hr>");
			
		}
	}
	else
	{
			define('CP_REDIRECT', 'networkforum.php');
			print_stop_message('vbn_no_threads_to_sync');		
	}
	
	define('CP_REDIRECT', 'networkforum.php');
	print_stop_message('vbn_threads_synced_successfully');		
}

if ($_REQUEST['do'] == 'list')
{
		print_form_header('', '');
		print_table_header($vbphrase['network_manager'], 5);
		
		// TODO: add vbphrases for cell names
		print_cells_row(array('Network Name',''), 1, 'tcat');
		
		$networks = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "network");
		
		while ($network = $db->fetch_array($networks))
		{
			if ($network['admin'] != $network['selfid'])
				continue;
			
			print_cells_row(
				array(
					"<a href=\"network.php?do=modify&amp;networkid=".$network['networkid']."\">".$network['name']."</a>",
					'<a href="networkforum.php?do=addforuminput&amp;networkid='.$network['networkid'].'">Add Network Forum</a>'
					));

			$networkforums = $db->query("
				SELECT DISTINCT subid FROM " . TABLE_PREFIX . "network_sub
				WHERE (network_sub.networkid = ".$network['networkid'].")
				ORDER BY subid
				");

			while ($forum = $db->fetch_array($networkforums))
			{
				
				$localforum = $db->query_first("
					SELECT * FROM " . TABLE_PREFIX . "forum
					WHERE (networkid = ".$network['networkid'].")
						AND (network_forum_id = ".$forum['subid'].")
				");
				
				if ($localforum['forumid'] <= 0)
				{
					$localforum['title'] = '<b><i>No Local Forum Assigned!</i></b>';
					$localforum['forumid'] = 0;
					$synclink = '';
				}
				else
				{
					$synclink = '<a href="networkforum.php?do=syncforum&amp;networkid='.$network['networkid'].'&amp;forumid='.$localforum['forumid'].'">Sync Non-Network Threads</a>';
				}
				
				print_cells_row(
					array(
						'-- Network Forum ID: '.$forum['subid'].'<br/> -- Local Forum: '.$localforum['title'].' (ID: '.$localforum['forumid'].')',
						'<a href="networkforum.php?do=addboardinput&amp;networkid='.$network['networkid'].'&amp;subid='.$forum['subid'].'">Add Board to Forum</a><br/>'.$synclink
					));
					
					$forumsnodes = $db->query("
						SELECT * FROM " . TABLE_PREFIX . "network_sub
						LEFT JOIN " . TABLE_PREFIX . "network_node 
								ON (network_sub.node_code = network_node.node_code) 
								AND (network_sub.networkid = network_node.networkid)
						WHERE (network_sub.networkid = ".$network['networkid'].") AND (subid = ".$forum['subid'].")
						ORDER BY network_sub.node_code
						");
						
					while ($node = $db->fetch_array($forumsnodes))
					{
						print_cells_row(
							array(
								'---- '.$node['node_code'],
								'<a href="networkforum.php?do=removeboardconfirm&amp;network_subid='.$node['network_subid'].'">Remove Board from Forum</a>'
							));												
					}
			}
		}
		print_table_footer();


}

print_cp_footer();
?>