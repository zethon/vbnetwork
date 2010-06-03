<?php
//-----------------------------------------------------------------------------
// $RCSFile: class_dm_networknode.php $ $Revision: 1.2 $ $Author: addy $ 
// $Date: 2009/02/21 20:52:55 $
//-----------------------------------------------------------------------------

if (!class_exists('vB_DataManager'))
{
	exit;
}

class vB_DataManager_NetworkNode extends vB_DataManager
{
	/**
	* Array of recognised and required fields for forums, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'network_nodeid'           => array(TYPE_UINT,       REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'node_code'           => array(TYPE_STR, REQ_YES),
		'networkid'           => array(TYPE_UINT, REQ_YES),
		'node_url'           => array(TYPE_STR, REQ_YES),
		'node_service_url'           => array(TYPE_STR, REQ_YES)
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'network_node';
	

	/**
	* Array to store stuff to save to forum table
	*
	* @var	array
	*/
	var $network_node = array();	

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_NetworkNode(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		//($hook = vBulletinHook::fetch_hook('forumdata_start')) ? eval($hook) : false;
	}

}
?>