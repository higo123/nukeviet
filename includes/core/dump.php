<?php

/**
 * @Project NUKEVIET 4.x
 * @Author VINADES.,JSC (contact@vinades.vn)
 * @copyright 2010
 * @License GNU/GPL version 2 or any later version
 * @Createdate 1/20/2010 20:48
 */

if( ! defined( 'NV_MAINFILE' ) ) die( 'Stop!!!' );

/**
 * dumpsave
 *
 * @package
 * @author NUKEVIET commercial version 1.0
 * @copyright Anh Tu Nguyen
 * @version 2010
 * @access public
 */
class dumpsave
{
	var $savetype;
	var $filesavename;
	var $mode;
	var $comp_level = 9;
	var $fp = false;

	/**
	 * dumpsave::dumpsave()
	 *
	 * @param mixed $save_type
	 * @param mixed $filesave_name
	 * @return
	 */
	function dumpsave( $save_type, $filesave_name )
	{
		$this->filesavename = $filesave_name;
		if( $save_type == 'gz' and extension_loaded( 'zlib' ) )
		{
			$this->savetype = 'gz';
			$this->mode = 'wb' . $this->comp_level;
		}
		else
		{
			$this->savetype = 'sql';
			$this->mode = 'wb';
		}
	}

	/**
	 * dumpsave::open()
	 *
	 * @return
	 */
	function open()
	{
		$this->fp = call_user_func_array( ( $this->savetype == 'gz' ) ? 'gzopen' : 'fopen', array( $this->filesavename, $this->mode ) );
		return $this->fp;
	}

	/**
	 * dumpsave::write()
	 *
	 * @param mixed $content
	 * @return
	 */
	function write( $content )
	{
		if( $this->fp )
		{
			return @call_user_func_array( ( $this->savetype == 'gz' ) ? 'gzwrite' : 'fwrite', array( $this->fp, $content ) );
		}
		return false;
	}

	/**
	 * dumpsave::close()
	 *
	 * @return
	 */
	function close()
	{
		if( $this->fp )
		{
			$return = @call_user_func( ( $this->savetype == 'gz' ) ? 'gzclose' : 'fclose', $this->fp );
			if( $return )
			{
				@chmod( $this->filesavename, 0666 );
				return true;
			}
		}
		return false;
	}
}

/**
 * nv_dump_save()
 *
 * @param mixed $params
 * @return
 */
function nv_dump_save( $params )
{
	global $db, $sys_info, $global_config, $db_config;

	if( $sys_info['allowed_set_time_limit'] )
	{
		set_time_limit( 1200 );
	}

	if( ! isset( $params['tables'] ) or ! is_array( $params['tables'] ) or $params['tables'] == array() )
	{
		return false;
	}

	$params['tables'] = array_map( 'trim', $params['tables'] );
	$tables = array();
	$dbsize = 0;
	$result = $db->query( 'SHOW TABLE STATUS' );
	$a = 0;
	while( $item = $result->fetch() )
	{
		unset( $m );
		if( in_array( $item['name'], $params['tables'] ) )
		{
			$tables[$a]['name'] = $item['name'];
			$tables[$a]['size'] = intval( $item['data_length'] ) + intval( $item['index_length'] );
			$tables[$a]['limit'] = 1 + round( 1048576 / ( $item['avg_row_length'] + 1 ) );
			$tables[$a]['numrow'] = $item['rows'];
			$tables[$a]['charset'] = ( preg_match( '/^([a-z0-9]+)_/i', $item['collation'], $m ) ) ? $m[1] : '';
			$tables[$a]['type'] = isset( $item['engine'] ) ? $item['engine'] : $item['t'];
			++$a;
			$dbsize += intval( $item['data_length'] ) + intval( $item['index_length'] );
		}
	}
	$result->closeCursor();

	if( empty( $a ) )
	{
		return false;
	}

	$dumpsave = new dumpsave( $params['savetype'], $params['filename'] );
	if( ! $dumpsave->open() )
	{
		return false;
	}
	$path_dump = '';
	if( file_exists( NV_ROOTDIR . '/themes/' . $global_config['site_theme'] . '/system/dump.tpl' ) )
	{
		$path_dump = NV_ROOTDIR . '/themes/' . $global_config['site_theme'] . '/system/dump.tpl';
	}
	else
	{
		$path_dump = NV_ROOTDIR . '/themes/default/system/dump.tpl';
	}

	$template = explode( '@@@', file_get_contents( $path_dump ) );

	$patterns = array( "/\{\|SERVER_NAME\|\}/", "/\{\|GENERATION_TIME\|\}/", "/\{\|SQL_VERSION\|\}/", "/\{\|PHP_VERSION\|\}/", "/\{\|DB_NAME\|\}/", "/\{\|DB_COLLATION\|\}/" );
	$replacements = array( $db->server, gmdate( "F j, Y, h:i A", NV_CURRENTTIME ) . " GMT", $db->getAttribute( PDO::ATTR_SERVER_VERSION ), PHP_VERSION, $db->dbname, $db_config['collation'] );

	if( ! $dumpsave->write( preg_replace( $patterns, $replacements, $template[0] ) ) )
	{
		return false;
	}

	$db->query( 'SET SQL_QUOTE_SHOW_CREATE = 1' );

	$a = 0;
	foreach( $tables as $table )
	{
		$content = $db->query( 'SHOW CREATE TABLE ' . $table['name'] )->fetchColumn( 1 );
		$content = preg_replace( '/(KEY[^\(]+)(\([^\)]+\))[\s\r\n\t]+(USING BTREE)/i', '\\1\\3 \\2', $content );
		$content = preg_replace( '/(default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP|DEFAULT CHARSET=\w+|COLLATE=\w+|character set \w+|collate \w+|AUTO_INCREMENT=\w+)/i', ' \\1', $content );

		$patterns = array( "/\{\|TABLE_NAME\|\}/", "/\{\|TABLE_STR\|\}/" );
		$replacements = array( $table['name'], $content );

		if( ! $dumpsave->write( preg_replace( $patterns, $replacements, $template[1] ) ) )
		{
			return false;
		}

		if( $params['type'] == 'str' )
		{
			continue;
		}

		if( ! empty( $table['numrow'] ) )
		{
			$patterns = array( "/\{\|TABLE_NAME\|\}/" );
			$replacements = array( $table['name'] );
			if( ! $dumpsave->write( preg_replace( $patterns, $replacements, $template[2] ) ) )
			{
				return false;
			}

			$columns = array();
			$columns_array = $db->columns_array( $table['name'] );
			foreach ( $columns_array as $col )
			{
				$columns[$col['field']] = preg_match( '/^(\w*int|year)/', $col['type'] ) ? 'int' : 'txt';
			}

			$maxi = ceil( $table['numrow'] / $table['limit'] );
			$from = 0;
			$a = 0;
			for( $i = 0; $i < $maxi; ++$i )
			{
				$db->sqlreset()
					->select( '*' )
					->from( $table['name'] )
					->limit( $table['limit'] )
					->offset( $from );
				$result = $db->query( $db->sql() );
				while( $row = $result->fetch() )
				{
					$row2 = array();
					foreach( $columns as $key => $kt )
					{
						$row2[] = isset( $row[$key] ) ? ( ( $kt == 'int' ) ? $row[$key] : "'" . addslashes( $row[$key] ) . "'" ) : 'NULL';
					}
					$row2 = NV_EOL . '(' . implode( ', ', $row2 ) . ')';

					++$a;
					if( $a < $table['numrow'] )
					{
						if( ! $dumpsave->write( $row2 . ', ' ) )
						{
							return false;
						}
					}
					else
					{
						if( ! $dumpsave->write( $row2 . ';' ) )
						{
							return false;
						}
						break;
					}
				}
				$result->closeCursor();
				$from += $table['limit'];
			}
		}
	}

	if( ! $dumpsave->close() )
	{
		return false;
	}
	return array( $params['filename'], $dbsize );
}

function nv_dump_restore( $file )
{
	global $db, $db_config, $sys_info;
	if( $sys_info['allowed_set_time_limit'] ) set_time_limit( 1200 );

	//kiem tra file
	if( ! file_exists( $file ) ) return false;

	//bat doc doc file
	$arr_file = explode( '/', $file );
	$ext = nv_getextension( end( $arr_file ) );
	$str = ( $ext == 'gz' ) ? @gzfile( $file ) : @file( $file );

	$sql = $insert = '';
	$query_len = 0;
	$execute = false;

	foreach( $str as $st )
	{
		if( empty( $st ) || preg_match( '/^(#|--)/', $st ) )
		{
			continue;
		}
		else
		{
			$query_len += strlen( $st );

			unset( $m );
			if( empty( $insert ) && preg_match( "/^(INSERT INTO `?[^` ]+`? .*?VALUES)(.*)$/i", $st, $m ) )
			{
				$insert = $m[1] . ' ';
				$sql .= $m[2];
			}
			else
			{
				$sql .= $st;
			}

			if( $sql )
			{
				if( preg_match( "/;\s*$/", $st ) )
				{
					$sql = rtrim( $insert . $sql, ';' );
					$insert = '';
					$execute = true;
				}

				if( $query_len >= 65536 && preg_match( "/,\s*$/", $st ) )
				{
					$sql = rtrim( $insert . $sql, ',' );
					$execute = true;
				}

				if( $execute )
				{
					$sql = preg_replace( array( "/\{\|prefix\|\}/", "/\{\|lang\|\}/" ), array( $db_config['prefix'], NV_LANG_DATA ), $sql );
					try
					{
						$db->query( $sql );
					}
					catch (PDOException $e)
					{
						return false;
					}
					$sql = '';
					$query_len = 0;
					$execute = false;
				}
			}
		}
	}
	return true;
}