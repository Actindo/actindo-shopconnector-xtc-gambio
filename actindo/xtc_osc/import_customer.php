<?php

/**
 * import customer cid
 *
 * actindo Faktura/WWS connector
 *
 * @package actindo
 * @author  Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 145 $
 * @copyright Copyright (c) 2007, Patrick Prasse (Schneebeerenweg 26, D-85551 Kirchheim, GERMANY, pprasse@actindo.de)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

function import_customer_set_deb_kred_id( $customer_id, $deb_kred_id )
{
  if( !$customer_id || !$deb_kred_id )    // TODO
    return array( 'ok'=>FALSE, 'errno'=>EINVAL );

  if( act_shop_is(SHOP_TYPE_OSCOMMERCE) )
    return array( 'ok'=>FALSE, 'errno'=>ENOSYS );

  $res = act_db_query( "UPDATE ".TABLE_CUSTOMERS." SET `customers_cid`=".(int)$deb_kred_id." WHERE `customers_id`=".(int)$customer_id );
  if( !$res )
    return array( 'ok'=>FALSE, 'errno'=>EIO );

  return array( 'ok'=>TRUE );
}



?>