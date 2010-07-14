<?php
//-----------------------------------------------------------------------------
// $RCSFile: functions_network.php $ $Revision: 1.51 $
// $Date: 2010/06/02 18:40:36 $
//-----------------------------------------------------------------------------

define('CVS_REVISION', '$RCSfile: functions_network.php,v $ - $Revision: 1.51 $');

define('NETWORK_SUCCEED',0);
define('NETWORK_FORUM_NOT_FOUND',1);
define('NETWORK_UNKNOWN_PACKET_TYPE',2);
define('NETWORK_XML_PARSER_ERROR',3);

if ( !function_exists('htmlspecialchars_decode') )
{
   function htmlspecialchars_decode($text)
   {
       return strtr($text, array_flip(get_html_translation_table(HTML_SPECIALCHARS)));
   }
}

function tweak_xml_hash($hash,$root)
{
	if (!isset($hash[$root][0]) || !is_array($hash[$root]))
	{
		$temp = $hash[$root];
		unset($hash);
		$hash[$root][0] = $temp;
	}
	
	return $hash;
}

function fetch_file_cvs_version($filename)
{
	$lines = file($filename);
	
	#$re = '#^\$' . 'RCS' . 'file: (.*\.php),v ' . '\$ - \$' . 'Revision: ([0-9\.]+) \$$#siU';
	$re = '/^\/\/\s+\$RCSFile:/';
	
	foreach ($lines as $line_num => $line) 
	{
		if (preg_match($re,$line))
		{
			return $line;
		}
	}
	
	return 0;
}

function fetch_pagetext($oldtext,$forumid = 0, $sourcenodeinfo = null)
{
	global $vbphrase,$bbcode_parser,$vbulletin;
	
	$newtext = htmlspecialchars_decode($oldtext);
	
	// remove the backtick link in the QUOTES
	$newtext = preg_replace(array('/(\[\s*QUOTE\s*\=[\s\w\d\@\-\_]+)\;\s*[\d]+\s*(\])/'),array('$1$2'),$newtext);
	
	// make sure this post conforms to this board's image rules
	$matches = array();
	$imagecount = preg_match_all('/\[IMG\][\w\d\:\/\_\-\.\?\=\&\;]+\[\/IMG\]/',$newtext,$matches);
	if ($vbulletin->options['maximages'] && $imagecount > $vbulletin->options['maximages'])
	{
		$newtext = preg_replace('/\[IMG\]([\w\d\:\/\_\-\.\?\=\&\;]+)\[\/IMG\]/','[URL]$1[/URL]',$newtext,$imagecount - $vbulletin->options['maximages']);
	}
	
	// make sure this post conforms to this boards max post length
	if (vbstrlen($newtext) > $vbulletin->options['postmaxchars'] AND $vbulletin->options['postmaxchars'] != 0)	
	{
		$newtext = vbchop($newtext,$vbulletin->options['postmaxchars']-100);
	}

	// add signature like text to the post saying where it came from
	if ($sourcenodeinfo)
	{
		$nodeurl = preg_replace(array('/http\:\/\//','/\/$/'),array('',''),$sourcenodeinfo['node_url']);
		$phrase = construct_phrase($vbphrase['vbn_urlphrase'],$nodeurl);
		$newtext .= "\r\n__________________\r\n$phrase\r\n";
	}

	return fetch_censored_text($newtext);
}

function fetch_nodesXML($networkid)
{
	global $vbulletin,$db;
	$nodes = $db->query_read("SELECT * FROM " . TABLE_PREFIX . "network_node WHERE (networkid = '".($networkid)."') ORDER BY node_code;");	

	// the XMLexporter constructor is different in 3.6+
	require_once(DIR . '/includes/class_xml.php');
	if ($vbulletin->options['templateversion'] >= 3.6)	
		$xml = new XMLexporter($vbulletin);
	else
		$xml = new XMLexporter();	
	
	if ($xml && count($nodes) > 0)
	{
		$xml->add_group('nodes');	
		while ($node = $db->fetch_array($nodes))
		{
			$xml->add_group('node');
			$xml->add_tag('node_code',strtolower($node['node_code']));
			$xml->add_tag('node_url',$node['node_url']);
			$xml->add_tag('node_service_url',$node['node_service_url']);
			$xml->close_group();
		}		
		$xml->close_group();
	}
	
	if ($xml)
		return $xml->output();
}


function fetch_subsXML($networkid,$networkname)
{
	global $vbulletin,$db;
	
	// TODO: no need for two queries in this function
	$subids = $db->query_read("SELECT DISTINCT subid FROM " . TABLE_PREFIX . "network_sub WHERE (networkid = '".($networkid)."') ORDER BY subid;");	
	
	// the XMLexporter constructor is different in 3.6+
	require_once(DIR . '/includes/class_xml.php');
	if ($vbulletin->options['templateversion'] >= 3.6)	
		$xml = new XMLexporter($vbulletin);
	else
		$xml = new XMLexporter();		
	
	
	if ($xml && count($subids) > 0)
	{
		$xml->add_group('forums', array('network' => $networkname));
		while ($subid = $db->fetch_array($subids))
		{
			$thesubid = $subid['subid'];
			
			$rows = $db->query("SELECT * FROM " . TABLE_PREFIX . 
				"network_sub WHERE (subid = '$thesubid') AND (networkid = '".($networkid)."') ORDER BY node_code;");
				
			$xml->add_group('forum', array('id' => $thesubid));				
			while ($row = $db->fetch_array($rows))
			{
				$xml->add_tag('node',strtolower($row['node_code']));
			}
			$xml->close_group();
		}
		$xml->close_group();	
	}

	if ($xml)
		return $xml->output();	
}

function updateSubsList($networkname,$xmlstr)
{
	global $vbulletin,$db;
	
	// TODO: change this function to accept a networkid instead and get rid of these two lines
	$networkinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network WHERE (name = '$networkname')");
	$networkid = $networkinfo['networkid'];	
	
	$retval = false;
	
	require_once(DIR . '/includes/class_xml.php');
	$packetxml = new XMLparser($xmlstr, '');
	$packet = $packetxml->parse();	
	
	if (isset($packet))
	{
		$db->query_read("DELETE FROM ".TABLE_PREFIX."network_sub WHERE (networkid = '$networkid');");

		$packet = tweak_xml_hash($packet,'forum');
		foreach ($packet['forum'] AS $key => $val)
		{	
			if (strlen($val['id']) <= 0)
				next;
			
			$subid = $val['id'];
			$val = tweak_xml_hash($val,'node');
			
			foreach ($val['node'] as $nodecode)
			{
				if (strlen($nodecode) <= 0)
					next;
				
				$db->query_write("INSERT INTO ".TABLE_PREFIX."network_sub (subid,node_code,networkid) VALUES
					('$subid','$nodecode','$networkid');");
			}
		}
		
		$retval = true;		
	}	
	
	return $retval;	
}

function updateBoardList($networkid,$xmstr)
{
	global $vbulletin,$db;
	$retval = false;
	
	require_once(DIR . '/includes/class_xml.php');
	$packetxml = new XMLparser($xmstr, '');
	$packet = $packetxml->parse();		
	
	if ($packet)
	{
		$db->query_read("DELETE FROM ".TABLE_PREFIX."network_node WHERE (networkid = '$networkid');");
		$nndm =& datamanager_init('NetworkNode', $vbulletin, ERRTYPE_CP);
		
		$packet = tweak_xml_hash($packet,'node');
		foreach ($packet['node'] as $node)
		{
			if (strlen($node['node_code']) < 0 || strlen($node['node_url']) < 0 || strlen($node['node_service_url']) < 0)
				next;
			
			$nndm->set('node_url',$node['node_url']);
			$nndm->set('node_service_url',$node['node_service_url']);

				$nndm->set('networkid',$networkid);
				$nndm->set('node_code',$node['node_code']);
				$query = $nndm->fetch_insert_sql(TABLE_PREFIX, $nndm->table);
				
				$db->query_write($query);
		}
		
		if (count($packet['node']) > 0)
			$retval = true;
	}	
	
	return $retval;
}

function isBlackListed($username,$nodecode,$networkname)
{
	global $vbulletin;
	$retval = false;
	
	$username = strtolower(preg_replace('/\s/','',$username));
	$nodecode = strtolower(preg_replace('/\s/','',$nodecode));
	$networkname = strtolower(preg_replace('/\s/','',$networkname));
	
	// see if the node code is blacklisted
	foreach (preg_split('/[\r\n]+/',$vbulletin->options['vbn_board_blacklist']) as $entry)
	{
		list ($cfgnodecode,$cfgnetwork) = explode(',',$entry);
		
		if (strlen($cfgnodecode) == 0 || strlen($cfgnetwork) == 0)
			continue;
			
		if (strtolower($cfgnodecode) == $nodecode && strtolower($cfgnetwork) == $networkname)
		{
			$retval = true;
			break;
		}		
	}

	if (!$retval)
	{
		// now see if the user is blacklisted
		foreach (preg_split('/[\r\n]+/',$vbulletin->options['vbn_user_blacklist']) as $entry)
		{
			list ($cfguser,$cfgnetwork) = explode(',',$entry);
			
			if (strlen($cfguser) == 0 || strlen($cfgnetwork) == 0)
				continue;
				
			if (strtolower($cfguser) == $username && strtolower($cfgnetwork) == $networkname)
			{
				$retval = true;
				break;
			}		
		}
	}
	
	return $retval;
}


// $forumid here is really the network's id for the forum (or a 'sub')
function getForumDestArray($network,$forumid)
{
	global $vbulletin,$db;
	$retval = array();	
	
	// TODO: change this function to accept a networkid instead and get rid of these two lines
	$networkinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network WHERE (name = '$network')");
	$networkid = $networkinfo['networkid'];		

	$nodes = $db->query_read("SELECT * FROM ".TABLE_PREFIX."network_sub WHERE (networkid = '$networkid') AND (subid = '$forumid');");

	while ($node = $db->fetch_array($nodes))
	{
		array_push($retval,$node['node_code']);
	}	
	
	return $retval;
}

function package_packet($networkinfo,$xml,$toarray)
{
	foreach ($toarray as $node)
	{
		$node = strtolower($node);
		require_once(DIR . '/includes/class_xml.php');
		$packetxml = new XMLparser($xml->output(), '');
		$packet = $packetxml->parse();
		
		// protect against sending to self and circular sending
		if ($node == strtolower($networkinfo['selfid']) || strtolower($packet['origin']) == $node)
			continue;

		$workingfile = strtolower('./network/packets/outgoing/'.$networkinfo['name'].".".$node.".xml");		
			
		$xmlstr = "";			
		if (!file_exists($workingfile))
		{
			$xmlstr = "<?xml version=\"1.0\" encoding=\"windows-1252\" ?>\n";
			$xmlstr = "<package networkname='".(strtolower($networkinfo['name']))."'>\n";
			$xmlstr .= $xml->output();
			$xmlstr .= "</package>";
		}
		else
		{
			// read the current data
			$file = fopen($workingfile,'r');
			$fdata = fread($file,filesize($workingfile));
			fclose($file);
			
			$count=1;
			$lines = preg_split('/[\r\n]/',$fdata);
			foreach ($lines as $line)
			{
				if (preg_match('/<\/package>/i',$line) && count($lines) == $count)
					$xmlstr .= $xml->output();

				$xmlstr .= "$line\n";
				$count++;
			}
			$xmlstr = substr($xmlstr, 0, -1);
		}		

		if (file_exists($workingfile))
			unlink($workingfile);
		
		$fh = fopen($workingfile, 'w');
		fwrite($fh, $xmlstr);
		fclose($fh);		
	}	
}

function que_data_packet($data,$networkinfo,$type,$toarray)
{
	global $vbulletin;
	$data = preg_replace('/\r/','',$data);
	
	// the XMLexporter constructor is different in 3.6+
	require_once(DIR . '/includes/class_xml.php');
	if ($vbulletin->options['templateversion'] >= 3.6)	
		$xml = new XMLexporter($vbulletin);
	else
		$xml = new XMLexporter();	
		
	$xml->add_group('packet');
	$xml->add_tag('origin',strtolower($networkinfo['selfid']));
	$xml->add_tag('data',$data,array('type'=>$type));	
	$xml->close_group();
	
	package_packet($networkinfo,$xml,$toarray);
}

// $networkinfo is null when this is used from the hook
function que_createpost_packet($networkinfo,$foruminfo,$threadinfo,$postinfo,$type = 'NEW_POST')
{
	global $db,$vbulletin;
	$networkinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network WHERE (networkid = '".$foruminfo['networkid']."')");

	// make sure it has a network name
	if (strlen($networkinfo['name']) == 0)
		return false;
	
	if ($type == 'NEW_THREAD')
	{
		// update the thread info with the network thread id
		$net_thread_id = strtolower($networkinfo['selfid'].'.'.$threadinfo['threadid']);
		$db->query_write("UPDATE " . TABLE_PREFIX . "thread SET network_thread_id = '$net_thread_id' WHERE (threadid = ".$threadinfo['threadid'].")");
	}
	else
		$net_thread_id = $threadinfo['network_thread_id'];
	
	// other checks here? TODO: investigate vBulletin error handling
	
	// the XMLexporter constructor is different in 3.6+
	require_once(DIR . '/includes/class_xml.php');
	if ($vbulletin->options['templateversion'] >= 3.6)	
		$xml = new XMLexporter($vbulletin);
	else
		$xml = new XMLexporter();	
			
	$xml->add_group('packet');
	$xml->add_tag('origin',strtolower($networkinfo['selfid']));
	$xml->add_group('data',array('type'=>$type));
	$xml->add_tag('network_forum_id',strtolower($foruminfo['network_forum_id']));
	$xml->add_tag('network_thread_id',$net_thread_id);
	
	if (empty($postinfo['dateline']))
		$xml->add_tag('dateline',time());
	else
		$xml->add_tag('dateline',$postinfo['dateline']);
	
	$xml->add_tag('userid',$vbulletin->userinfo['userid']);
	$xml->add_tag('email',$vbulletin->userinfo['email']);
	
	// some usernames have quotes in them and that's not good
	$fixedUsername = preg_replace("/&\\w*;/", "", $postinfo['username']);	
	$xml->add_tag('username',htmlspecialchars($fixedUsername));
	
	$xml->add_tag('title',htmlspecialchars($postinfo['title']));
	$xml->add_tag('pagetext',htmlspecialchars($postinfo['message']));
	$xml->close_group();
	$xml->close_group();

	
	// package the packet... (or do it in calling function)
	$toarray = getForumDestArray($networkinfo['name'],$foruminfo['network_forum_id']);
	package_packet($networkinfo,$xml,$toarray);
}


// check for duplicate posts using the username (with nodecode) & dateline
function isDuplicate($username,$dateline)
{
	global $db;
	require_once(DIR . '/includes/functions_search.php');
	
	$username = sanitize_word_for_sql($username);
	$postinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "post WHERE (username = '$username') AND (dateline = '$dateline');");
	return $postinfo['postid'] > 0;
}


function correct_forum_counters($threadid, $forumid) {
    // select lastpostid from thread where threadid =  $threadid
    // select dateline from post where postid = $postid
    // update thread set lastpost =  $time where threadid = $threadid

    global $db;
    $lastpostid = $db->query_first("SELECT lastpostid FROM " . TABLE_PREFIX . "thread WHERE threadid = '".$threadid."'");
    $dateline = $db->query_first("SELECT dateline FROM " . TABLE_PREFIX . "post WHERE postid = '".$lastpostid['lastpostid']."'");

    // Update thread table and threadread table to reflect new post
    $db->query_write("UPDATE " . TABLE_PREFIX . "thread SET lastpost = '".$dateline['dateline']."' WHERE threadid = '".$threadid."'");
    $db->query_write("UPDATE " . TABLE_PREFIX . "threadread SET readtime = '".($dateline['dateline']-1)."' WHERE threadid = '".$threadid."' AND readtime >= '".($dateline['dateline']-1)."'");

    // Update forum table and forumread to reflect new post
    $db->query_write("UPDATE " . TABLE_PREFIX . "forum SET lastpost = '".$dateline['dateline']."' WHERE forumid = '".$forumid."'");
    $db->query_write("UPDATE " . TABLE_PREFIX . "forumread SET readtime = '".($dateline['dateline']-1)."' WHERE forumid = '".$forumid."' AND readtime >= '".($dateline['dateline']-1)."'");
} 

function newThread($networkinfo,$packet, $origininfo,$fromusername)
{
	global $db,$vbulletin;
	
	$foruminfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "forum WHERE (network_forum_id = '".$packet['data']['network_forum_id']."') AND (networkid = ".$networkinfo['networkid'].")");
	if ($foruminfo['forumid'] > 0 && !isBlackListed($packet['data']['username'],$packet['origin'],$networkinfo['name']))
	{
		$userid = 0; 			// such is the case for network posts
		$postuserid = 0; 	// same as above
		$forumid = $foruminfo['forumid'];
		$pagetext = fetch_pagetext($packet['data']['pagetext'],$foruminfo['forumid'],$origininfo);
		$title = (strlen($packet['data']['title']) > 0) ? $packet['data']['title'] : '(Unknown Network Thread: '.$packet['data']['network_thread_id'].')';
		$allowsmilie = '1';
		$visible = '1';
		$dateline = $packet['data']['dateline'];
		$network_thread_id = $packet['data']['network_thread_id'];		
		
		$threaddm = new vB_DataManager_Thread_FirstPost($vbulletin, ERRTYPE_STANDARD);
		
		// there is no (easy) way to parse out an excessive amount of smilies when dong the image check
		// so we check for [IMG] tags only and then disable the check for smilies
		$threaddm->set_info('skip_maximagescheck', true);
		$threaddm->do_set('userid', $userid);	
		$threaddm->do_set('postuserid', $postuserid);
		$threaddm->do_set('forumid', $forumid);
		$threaddm->do_set('pagetext', $pagetext);
		$threaddm->do_set('title', $title);
		$threaddm->do_set('allowsmilie', $allowsmilie);
		$threaddm->do_set('visible', $visible);
		$threaddm->do_set('dateline', $dateline);

		$theuserid = fetch_userid_from_email($packet['data']['email']);
		if ($theuserid > 0)
			$threaddm->do_set('userid', $theuserid);
		else
			$threaddm->do_set('username', $fromusername);		
		
		$threaddm->pre_save();		
		
		if (count($threaddm->errors) > 0)
		{
        	// Do some error work
        	echo "<h1>\$threaddm->pre_save() errors!</h1>";
		}
		else
		{
			// save the thread
			$insertid = $threaddm->save();
			
			// update the post with the networkuserid
			$networkuserid = $packet['data']['userid'];
			if ($networkuserid > 0)
				$db->query_write("UPDATE " . TABLE_PREFIX . "post SET networkuserid = $networkuserid WHERE (postid = ".($threaddm->thread['firstpostid'])." );");
			
			// update the thread with networkid
			$db->query_write("UPDATE " . TABLE_PREFIX . "thread SET network_thread_id = '$network_thread_id' WHERE (threadid = $insertid)");
			
			require_once('./includes/functions_databuild.php'); 
			build_forum_counters($forumid);
		}		
		
	}
	
}

function fix_username($username,$nodecode)
{
	global $db,$vbulletin;
	if (strlen($username) + strlen($nodecode) + 1 > $vbulletin->options['maxuserlength'])
	{
		$username = substr($username,0,$vbulletin->options['maxuserlength']-(strlen($nodecode)+1));
	}
	
	require_once(DIR . '/includes/functions_search.php');
	$tempname = sanitize_word_for_sql($username . '@' . $nodecode);
	
	if ($db->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username = '$tempname'"))
	{
		$i = 1;
		
		do
		{
			$tempname = sanitize_word_for_sql($username . $i . '@' . $nodecode);						
			$i++;
		} while ($db->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE username = '$tempname'"));
	}
	return $tempname;	
}

function fetch_userid_from_email($email)
{
		global $db,$vbulletin;
		
		if ($vbulletin->options['vbn_check_user_email'] <= 0 || empty($email))
			return 0;
		
		$userinfo = $db->query_first("SELECT userid FROM " . TABLE_PREFIX . "user WHERE (email = '$email') AND (email != '');");
		
		if ($userinfo['userid'] > 0)
			return $userinfo['userid'];
			
	return 0;			
}

// $networkname is passed on the uri
function process_incoming_package($networkname)
{
	global $db,$vbulletin;
	$retval = NETWORK_SUCCEED; // TODO: assume success?
	
	// create log object
	$networklog = new vb_Networklog($vbulletin);
	
	$networkinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network WHERE (name = '$networkname')");
	$networkid = $networkinfo['networkid'];

	// TODO: revisit the error handling in these two loops
	foreach (glob("./network/packets/incoming/".strtolower($networkinfo['name'])."*.*") as $filename)
	{
		require_once(DIR . '/includes/class_xml.php');
		$xmlobj = new XMLparser(false, $filename);
		if (!$package = $xmlobj->parse())
		{
			unlink($filename);
			return NETWORK_XML_PARSER_ERROR;
		}
		
		unlink($filename);
				
		$package = tweak_xml_hash($package,'packet');
		foreach($package['packet'] as $packet)
		{
			$origininfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "network_node WHERE (networkid = '".$networkinfo['networkid']."') AND (node_code = '".$packet['origin']."')");
			
			// we'll assume the packet is a post
			$fromusername = fix_username($packet['data']['username'],strtoupper($packet['origin']));
			
			if (isDuplicate($fromusername,$packet['data']['dateline']))
			{
				continue;
			}
			
			switch ($packet['data']['type'])
			{
				case 'SUBS_LIST':
					updateSubsList($networkinfo['name'],$packet['data']['value']);
				break;
				
				case 'NODE_LIST':
					updateBoardList($networkinfo['networkid'],$packet['data']['value']);
				break;
				
				case 'NEW_THREAD':
					// TODO: check to see if the thread already exists? necessary?
					newThread($networkinfo,$packet, $origininfo,$fromusername);
				break;
				
				case 'NEW_POST':
					$foruminfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "forum WHERE (network_forum_id = '".$packet['data']['network_forum_id']."') AND (networkid = ".$networkinfo['networkid'].")");
					if ($foruminfo['forumid'] > 0 && !isBlackListed($packet['data']['username'],$packet['origin'],$networkinfo['name']))
					{
						$threadinfo = $db->query_first("SELECT * FROM " . TABLE_PREFIX . "thread WHERE (network_thread_id = '".$packet['data']['network_thread_id']."')");
						if ($threadinfo['threadid'] > 0)
						{
							$postdm = new vB_DataManager_Post($vbulletin, ERRTYPE_STANDARD);
							$postpagetext = fetch_pagetext($packet['data']['pagetext'],$foruminfo['forumid'],$origininfo);
							
							// there is no (easy) way to parse out an excessive amount of smilies when dong the image check
							// so we check for [IMG] tags only and then disable the check for smilies
							$postdm->set_info('skip_maximagescheck', true);
							$postdm->set_info('forum', $foruminfo);
							$postdm->set_info('thread', $threadinfo);  
							$postdm->set('threadid', $threadinfo['threadid']);

							$postdm->set('pagetext', $postpagetext);
							$postdm->set('allowsmilie', 1);
							$postdm->set('visible', 1);
							$postdm->set('dateline', $packet['data']['dateline']);
							
							$theuserid = fetch_userid_from_email($packet['data']['email']);
							if ($theuserid > 0)
								$postdm->set('userid', $theuserid);
							else
								$postdm->set('username', $fromusername);
							
							$postdm->pre_save();
							if (count($postdm->errors) > 0)
							{
				        	// Do some error work
				        	echo "<h1>\$postdm->pre_save() errors!</h1>";
							}
							else
							{
								$postid = $postdm->save();
								 
								// update the post with the networkuserid
								$networkuserid = $packet['data']['userid'];
								if ($networkuserid > 0)
									$db->query_write("UPDATE " . TABLE_PREFIX . "post SET networkuserid = $networkuserid WHERE (postid = $postid);");								 					
								
								require_once('./includes/functions_databuild.php'); 
								build_thread_counters($threadinfo['threadid']); 
								build_forum_counters($foruminfo['forumid']);					
								correct_forum_counters($threadinfo['threadid'], $foruminfo['forumid']);
							}
						}
						else
						{
							if ($vbulletin->options['vbn_autothread'])
							{
								// this causes problems when deleting/merging network threads
								//newThread($networkinfo,$packet, $origininfo,$fromusername);
							}
						}
					}
					// TODO: generate error if and only if the forumID is not found

				break;
				
				default:
				break;
			}
		}
	}	
	
	return $retval;
}

?>