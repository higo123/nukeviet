<?php

/**
 * @Project NUKEVIET 4.x
 * @Author VINADES (contact@vinades.vn)
 * @Copyright (C) 2014 VINADES. All rights reserved
 * @License GNU/GPL version 2 or any later version
 * @Createdate Apr 20, 2010 10:47:41 AM
 */

if( ! defined( 'NV_IS_FILE_SITEINFO' ) ) die( 'Stop!!!' );

$lang_siteinfo = nv_get_lang_module( $mod );

// So thanh vien
$number = $db->query( 'SELECT COUNT(*) FROM ' . $db_config['dbsystem'] . '.' . NV_USERS_GLOBALTABLE )->fetchColumn();
if( $number > 0 )
{
	$siteinfo[] = array( 'key' => $lang_siteinfo['siteinfo_user'], 'value' => $number );
}

// So thanh vien doi kich hoat
$number = $db->query( 'SELECT COUNT(*) FROM ' . $db_config['dbsystem'] . '.' . NV_USERS_GLOBALTABLE . '_reg' )->fetchColumn();
if( $number > 0 )
{
	$pendinginfo[] = array(
		'key' => $lang_siteinfo['siteinfo_waiting'],
		'value' => $number,
		'link' => NV_BASE_ADMINURL . 'index.php?' . NV_NAME_VARIABLE . '=' . $mod . '&amp;' . NV_OP_VARIABLE . '=user_waiting'
	);
}