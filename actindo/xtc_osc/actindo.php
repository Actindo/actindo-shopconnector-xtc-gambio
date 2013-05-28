<?php

/**
 * include various files
 *
 * actindo Faktura/WWS connector
 **
 *
 * @package actindo
 * @author  Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 520 $
 * @copyright Copyright (c) 2007, Patrick Prasse (Schneebeerenweg 26, D-85551 Kirchheim, GERMANY, pprasse@actindo.de)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

define( 'ACTINDO_SHOPCONN_REVISION', '$Revision: 520 $' );
define( 'ACTINDO_PROTOCOL_REVISION', '2.'.substr( ACTINDO_SHOPCONN_REVISION, 11, -2 ) );


/* define some shop constants */
define( 'SESSION_FORCE_COOKIE_USE', 0 );
define( 'SESSION_CHECK_SSL_SESSION_ID', 0 );
define( 'SESSION_CHECK_USER_AGENT', 0 );
define( 'SESSION_CHECK_IP_ADDRESS', 0 );
define( 'DB_CACHE', 'false' );

/* actindo extensions */
define( 'SUPPRESS_REDIRECT', 1 );       // for application_top.php
define( 'SUPPRESS_UPLOAD_CHECKS', 1 );  // for upload.php
define( 'SUPPRESS_DIE', 1 );            // for database.php, xtc_db_error


/* change dir into admin interface and include application_top.php */
if( !strlen($wd) || !strlen($dwd) || $dwd == '/' || !is_file($dwd.'/admin/includes/application_top.php') )
{
  $wd = $_SERVER['SCRIPT_FILENAME'];
  $dwd = realpath( dirname($wd).'/../' );
  if( $dwd === FALSE )
    $dwd = dirname( dirname($wd) );
}
if( !strlen($wd) || !strlen($dwd) || $dwd == '/' || !is_file($dwd.'/admin/includes/application_top.php') )
{
  $wd = $_SERVER['ORIG_SCRIPT_FILENAME'];
  $dwd = realpath( dirname($wd).'/../' );
  if( $dwd === FALSE )
    $dwd = dirname( dirname($wd) );
}
if( !strlen($wd) || !strlen($dwd) || $dwd == '/' || !is_file($dwd.'/admin/includes/application_top.php') )
{
  $wd = trim( $_SERVER['PATH_TRANSLATED'] );
  $dwd = realpath( dirname($wd).'/../' );
  if( $dwd === FALSE )
    $dwd = dirname( dirname($wd) );
}

define( 'ACTINDO_SHOP_BASEDIR', $dwd );


if( !chdir($p=$dwd.'/admin/') )
  _actindo_report_init_error( 14, "Error while chdir to &#39;{$p}&#39;" );


if( !is_readable($f='includes/application_top.php') )
  _actindo_report_init_error( 14, 'file '.$f.' does not exist' );
require_once( $f );



require_once( 'util.php' );

require_once( 'import.php' );
require_once( 'export.php' );



function categories_get( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  $cat = call_user_func_array( 'export_categories', $params );
  if( !is_array($cat) )
    return xmlrpc_error( EINVAL );
  if( !count($cat) )
    return xmlrpc_error( ENOENT );

  return resp( array( 'ok' => TRUE, 'categories' => $cat ) );
}

function category_action( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  require_once ('includes/classes/categories.php');

  $category = new categories();

  global $export;
  $default_lang = default_lang();

  list( $point, $id, $pid, $aid, $data ) = $params;

  if( $point == 'add' )
  {
    $sort_order = null;
    if( $aid )
    {
      $res = act_db_query( "SELECT `sort_order` FROM ".TABLE_CATEGORIES." WHERE `categories_id`=".(int)$aid." AND `parent_id`=".(int)$pid );
      $row = act_db_fetch_array( $res );
      if( is_array($row) )
        $sort_order = (int)$row['sort_order'];
      act_db_free( $res );
    }

    if( !is_null($sort_order) )
    {
      $res = act_db_query( "UPDATE ".TABLE_CATEGORIES." SET `sort_order`=`sort_order`+1 WHERE `sort_order`>=".(int)$sort_order );
      if( !$res )
        return xmlrpc_error( EIO, 'Datenbank-Fehler beim verschieben der Kategorie' );
    }

    $category_data = array(
//      'id' => $id,
      'sort_order' => (int)$sort_order,
      'status' => 1,
      'groups' => array('all'),
      'products_sorting' => 'p.products_price',
      'products_sorting2'=> 'ASC',
      'categories_template' => 'default',
      'listing_template' => 'default',
      'categories_name' => array(),
      'categories_heading_title' => array(),
      'categories_description' => array(),
      'categories_meta_title' => array(),
      'categories_meta_description' => array(),
      'categories_meta_keywords' => array(),
    );
    $default_lang = default_lang();
    foreach( array_keys(export_shop_languages()) as $_lang_id )
    {
      $desc = isset($data['description'][$_lang_id]) ? $data['description'][$_lang_id] : $data['description'][$default_lang];
      $category_data['categories_name'][$_lang_id] = $desc['name'];
      $category_data['categories_heading_title'][$_lang_id] = isset($desc['title']) ? $desc['title'] : $desc['name'];
      if( isset($desc['description']) )
        $category_data['categories_description'][$_lang_id] = $desc['description'];
      if( isset($desc['meta_title']) )
        $category_data['categories_meta_title'][$_lang_id] = $desc['meta_title'];
      if( isset($desc['meta_description']) )
        $category_data['categories_meta_description'][$_lang_id] = $desc['meta_description'];
      if( isset($desc['meta_keywords']) )
        $category_data['categories_meta_keywords'][$_lang_id] = $desc['meta_keywords'];
    }

    $category->insert_category( $category_data, $pid, 'insert' );

    $res = act_db_query( "SELECT MAX(`categories_id`) AS cid FROM ".TABLE_CATEGORIES." WHERE `parent_id`=".(int)$pid );
    $row = act_db_fetch_array( $res );
    $categories_id = $row['cid'];
    act_db_free( $res );

    return resp( array('ok' => TRUE, 'id'=>(int)$categories_id) );
  }
  else if( $point == 'delete' )
  {
    $category->remove_category( $id );
    return resp( array('ok' => TRUE) );
  }
  else if( $point == 'above' || $point == 'below' || $point == 'append' )
  {
    $res = act_db_query("UPDATE ".TABLE_CATEGORIES."
                   SET parent_id     = '".xtc_db_input($pid)."', last_modified = now()
                   WHERE categories_id = '".xtc_db_input($id)."'");
    if( !$res )
      return xmlrpc_error( EIO, 'Datenbank-Fehler beim verschieben der Kategorie' );
    return resp( array('ok' => TRUE) );
  }
  else if( $point == 'textchange' )
  {
    $res = TRUE;
    foreach( array_keys(export_shop_languages()) as $_lang_id )
    {
      if( !isset($data['description'][$_lang_id]) )
        continue;

      $desc = $data['description'][$_lang_id];
      $category_data = array(
        'categories_name' => $desc['name'],
        'categories_heading_title' => isset($desc['title']) ? $desc['title'] : $desc['name'],
      );
      if( isset($desc['description']) )
        $category_data['categories_description'][$_lang_id] = $desc['description'];
      if( isset($desc['meta_title']) )
        $category_data['categories_meta_title'][$_lang_id] = $desc['meta_title'];
      if( isset($desc['meta_description']) )
        $category_data['categories_meta_description'][$_lang_id] = $desc['meta_description'];
      if( isset($desc['meta_keywords']) )
        $category_data['categories_meta_keywords'][$_lang_id] = $desc['meta_keywords'];

      $set = construct_set( $category_data, TABLE_CATEGORIES_DESCRIPTION );
      $res &= act_db_query( "UPDATE ".TABLE_CATEGORIES_DESCRIPTION." ".$set['set']." WHERE `categories_id`=".(int)$id." AND `language_id`=".(int)$_lang_id );
    }
    if( !$res )
      return xmlrpc_error( EIO, 'Datenbank-Fehler beim umbenennen der Kategorie' );
    return resp( array('ok' => TRUE) );
  }

  return resp( array('ok' => TRUE) );
}



/**
 * @done
 */
function settings_get( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  $settings = call_user_func_array( 'export_shop_settings', $params );
  if( !is_array($settings) )
    return xmlrpc_error( EINVAL );
  if( !count($settings) )
    return xmlrpc_error( ENOENT );

  return resp( array( 'ok' => TRUE, 'settings' => $settings ) );
}


/**
 * @done
 */
function product_count( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  $count = call_user_func_array( 'export_products_count', $params );
  if( !is_array($count) )
    return xmlrpc_error( EINVAL );
  if( !count($count) )
    return xmlrpc_error( ENOENT );

  return resp( array('ok'=>TRUE, 'count'=>$count) );
}


/**
 * @done
 */
function product_get( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  if( !$params[3] )
    $prod = call_user_func_array( 'export_products', $params );
  else
    $prod = call_user_func_array( 'export_products_list', $params );
  if( !$prod['ok'] )
    return xmlrpc_error( $res['errno'], $res['error'] );

  return resp( $prod );
}


/**
 * @done
 */
function product_create_update( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  $res = call_user_func_array( 'import_product', $params );
  if( !$res['ok'] )
    return xmlrpc_error( $res['errno'], $res['error'] );

  return resp( $res );
}


/**
 * @done
 */
function product_update_stock( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  $res = call_user_func_array( 'import_product_stock', $params );
  if( !$res['ok'] )
    return xmlrpc_error( $res['errno'], $res['error'] );

  return resp( $res );
}


/**
 * @done
 */
function product_delete( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  $res = call_user_func_array( 'import_delete_product', $params );
  if( !$res['ok'] )
    return xmlrpc_error( $res['errno'], $res['error'] );

  return resp( $res );
}


/**
 * @done
 */
function orders_count( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  return resp( call_user_func_array('export_orders_count', $params) );
}


/**
 * @done
 */
function orders_list( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  return resp( call_user_func_array('export_orders_list', $params) );
}


/**
 * @done
 */
function orders_list_positions( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  return resp( call_user_func_array('export_orders_positions', $params) );
}


/**
 * @done
 */
function orders_set_status( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  $res = call_user_func_array( 'import_orders_set_status', $params );

  return resp( $res );
}


/**
 * @done
 * @todo xt:C does not yet support this
 */
function orders_set_trackingcode( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  return xmlrpc_error( ENOSYS );
}


/**
 * @done
 */
function customer_set_deb_kred_id( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  $res = call_user_func_array( 'import_customer_set_deb_kred_id', $params );

  return resp( $res );
}


/**
 * @done
 */
function customers_count($params)
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  return resp( call_user_func_array('export_customers_count', $params) );
}


/**
 * @done
 */
function customers_list( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  ob_start();
  return resp( call_user_func_array('export_customers_list', $params) );
}


/**
 * @done
 */
function actindo_set_token( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  actindo_check_config();
  if( empty($params[0]) || empty($params[1]) || empty($params[2]) )
    return xmlrpc_error( EINVAL, 'Invalid parameters' );

  $res = xtc_db_query( "UPDATE ".TABLE_CONFIGURATION." SET `configuration_value`='".esc($params[0])."' WHERE `configuration_key`='ACTINDO_MAND_ID'" );
  $res &= xtc_db_query( "UPDATE ".TABLE_CONFIGURATION." SET `configuration_value`='".esc($params[1])."' WHERE `configuration_key`='ACTINDO_USERNAME'" );
  $res &= xtc_db_query( "UPDATE ".TABLE_CONFIGURATION." SET `configuration_value`='".esc($params[2])."' WHERE `configuration_key`='ACTINDO_TOKEN'" );
  $res &= xtc_db_query( "UPDATE ".TABLE_CONFIGURATION." SET `configuration_value`='' WHERE `configuration_key`='ACTINDO_SID'" );
  $res &= xtc_db_query( "UPDATE ".TABLE_CONFIGURATION." SET `configuration_value`='true' WHERE `configuration_key`='ACTINDO_ACTIVE'" );
  if( !$res )
    return xmlrpc_error( EIO, 'Error inserting into DB' );

  return resp( array('ok'=>TRUE) );
}


/**
 * @done
 */
function actindo_get_time( $params )
{
  if( !parse_args($params,$ret) )
    return $ret;

  $res = act_db_query( "SHOW VARIABLES LIKE 'version'" );
  $v_db = act_db_fetch_array( $res );
  act_db_free( $res );

  $res = act_db_query( "SELECT NOW() as datetime" );
  $time_database = act_db_fetch_array( $res );
  act_db_free( $res );

  if( version_compare($v_db['Value'], "4.1.1") > 0 )
  {
    $res = act_db_query( "SELECT UTC_TIMESTAMP() as datetime" );
    $utctime_database = act_db_fetch_array( $res );
    act_db_free( $res );
  }
  else
  {
    // we hope that utctime_database is the same as gmtime-server
    $utctime_database = array( 'datetime'=> '' );
  }


  $arr = array(
    'time_server' => date( 'Y-m-d H:i:s' ),
    'gmtime_server' => gmdate( 'Y-m-d H:i:s' ),
    'time_database' => $time_database['datetime'],
    'gmtime_database' => $utctime_database['datetime'],
  );

  if( !empty($arr['gmtime_database']) )
  {
    $diff = strtotime( $arr['time_database'] ) - strtotime( $arr['gmtime_database'] );
  }
  else
  {
    $diff = strtotime( $arr['time_server'] ) - strtotime( $arr['gmtime_server'] );
  }
  $arr['diff_seconds'] = $diff;
  $diff_neg = $diff < 0;
  $diff = abs( $diff );
  $arr['diff'] = ($diff_neg ? '-':'').sprintf( "%02d:%02d:%02d", floor($diff / 3600), floor( ($diff % 3600) / 60 ), $diff % 60 );

  return resp( $arr );
}


/**
 * @done
 */
function shop_get_connector_version( &$arr, $params )
{
  $ver = PROJECT_VERSION;
  if( act_shop_is( SHOP_TYPE_GAMBIOGX ) )
  {
    ob_start();
    if( @include_once( '../release_info.php' ) )
    {
      if( !isset($gx_version) )
        $ver = 'Gambio GX '.strtr(trim(ob_get_contents()), array("\n"=>" ", "\r"=>""));
      else
        $ver = 'Gambio GX '.$gx_version;
    }
    else
    {
      $ver = 'Gambio GX [UNKNOWN VERSION]';
    }
    ob_end_clean();
  }

  $revision = '$Revision: 520 $';
  $arr = array(
    'revision' => $revision,
    'protocol_version' => '2.'.substr( $revision, 11, -2 ),
    'shop_type' => act_get_shop_type( ),
    'shop_version' => $ver,
    'capabilities' => act_shop_get_capabilities(),
  );
}


/**
 * @done
 */
function act_shop_get_capabilities()
{
  $is_xtcommerce = act_shop_is( SHOP_TYPE_XTCOMMERCE ) || act_shop_is( SHOP_TYPE_GAMBIOGX ) ? 1 : 0;
  $is_oscommerce = act_shop_is( SHOP_TYPE_OSCOMMERCE ) ? 1 : 0;
  $extendedAttributes = act_shop_is(SHOP_TYPE_GAMBIOGX) ? 1 : 0;
  return array(
    'artikel_vpe' => $is_xtcommerce,
    'artikel_shippingtime' => $is_xtcommerce,
    'artikel_properties' => 0,
    'artikel_contents' => 1,
    'wg_sync' => 1,
    'multi_livelager' => 1,
    'artikel_attributsartikel' => $extendedAttributes,
  );
}

?>
