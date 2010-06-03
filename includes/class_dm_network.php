<?php
//-----------------------------------------------------------------------------
// $RCSFile: class_dm_network.php $ $Revision: 1.2 $ $Author: addy $ 
// $Date: 2009/02/21 20:52:55 $
//-----------------------------------------------------------------------------

if (!class_exists('vB_DataManager'))
{
	exit;
}

class vB_DataManager_Network extends vB_DataManager
{
	/**
	* Array of recognised and required fields for forums, and their types
	*
	* @var	array
	*/
	var $validfields = array(
		'networkid'           => array(TYPE_UINT,       REQ_INCR, VF_METHOD, 'verify_nonzero'),
		'name'           => array(TYPE_STR, REQ_YES),
		'admin'           => array(TYPE_STR, REQ_YES),
		'selfid'           => array(TYPE_STR, REQ_YES),
		'password'           => array(TYPE_STR, REQ_NO)
	);

	/**
	* The main table this class deals with
	*
	* @var	string
	*/
	var $table = 'network';
	

	/**
	* Array to store stuff to save to forum table
	*
	* @var	array
	*/
	var $network = array();	

	/**
	* Constructor - checks that the registry object has been passed correctly.
	*
	* @param	vB_Registry	Instance of the vBulletin data registry object - expected to have the database object as one of its $this->db member.
	* @param	integer		One of the ERRTYPE_x constants
	*/
	function vB_DataManager_Network(&$registry, $errtype = ERRTYPE_STANDARD)
	{
		parent::vB_DataManager($registry, $errtype);

		//($hook = vBulletinHook::fetch_hook('forumdata_start')) ? eval($hook) : false;
	}

}

?>