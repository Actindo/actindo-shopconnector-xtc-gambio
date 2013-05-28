<?php

/**
 * import orders, specifically: set status, etc
 *
 * actindo Faktura/WWS connector
 *
 * @package actindo
 * @author  Patrick Prasse <pprasse@actindo.de>
 * @version $Revision: 145 $
 * @copyright Copyright (c) 2007, Patrick Prasse (Schneebeerenweg 26, D-85551 Kirchheim, GERMANY, pprasse@actindo.de)
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
*/

function import_orders_set_status( $oID, $status, $comments, $notify_customer, $notify_comments=0 )
{
  $smarty = new Smarty;

  $customer_notified = 0;

  $res = TRUE;

  $check_status_query = act_db_query($q="select customers_name, customers_email_address, orders_status, date_purchased, language from ".TABLE_ORDERS." where orders_id = '".act_db_input($oID)."'");
  $check_status = act_db_fetch_array($check_status_query);
  if( $check_status === FALSE )
    return array( 'ok'=>FALSE, 'errno'=>ENOENT );

  if ($check_status['orders_status'] != $status || $comments != '') {
    $res &= act_db_query("update ".TABLE_ORDERS." set orders_status = '".act_db_input($status)."', last_modified = now() where orders_id = '".act_db_input($oID)."'");


    if ($notify_customer)
    {
      require_once (DIR_FS_CATALOG.DIR_WS_CLASSES.'class.phpmailer.php');
      require_once (DIR_FS_INC.'xtc_php_mail.inc.php');

      $orders_statuses = array ();
      $orders_status_array = array ();
      $orders_status_query = act_db_query("SELECT s.orders_status_id, s.orders_status_name FROM ".TABLE_ORDERS_STATUS." AS s, ".TABLE_LANGUAGES." AS l WHERE s.language_id=l.languages_id AND l.directory='".act_db_input($check_status['language'])."'");
      while ($orders_status = act_db_fetch_array($orders_status_query)) {
        $orders_statuses[] = array ('id' => $orders_status['orders_status_id'], 'text' => $orders_status['orders_status_name']);
        $orders_status_array[$orders_status['orders_status_id']] = $orders_status['orders_status_name'];
      }


      $notify_comments = '';
      if ($notify_comments) {
        //$notify_comments = sprintf(EMAIL_TEXT_COMMENTS_UPDATE, $comments)."\n\n";
        $notify_comments = $comments;
      } else {
        $notify_comments = '';
      }

      // assign language to template for caching
      $smarty->assign('language', $_SESSION['language']);
      $smarty->caching = false;

      // set dirs manual
      $smarty->template_dir = DIR_FS_CATALOG.'templates';
      $smarty->compile_dir = DIR_FS_CATALOG.'templates_c';
      $smarty->config_dir = DIR_FS_CATALOG.'lang';

      $smarty->assign('tpl_path', 'templates/'.CURRENT_TEMPLATE.'/');
      $smarty->assign('logo_path', HTTP_SERVER.DIR_WS_CATALOG.'templates/'.CURRENT_TEMPLATE.'/img/');

      $smarty->assign('NAME', $check_status['customers_name']);
      $smarty->assign('ORDER_NR', $oID);
      $smarty->assign('ORDER_LINK', xtc_catalog_href_link(FILENAME_CATALOG_ACCOUNT_HISTORY_INFO, 'order_id='.$oID, 'SSL'));
      $smarty->assign('ORDER_DATE', xtc_date_long($check_status['date_purchased']));
      $smarty->assign('NOTIFY_COMMENTS', $comments);
      $smarty->assign('ORDER_STATUS', $orders_status_array[$status]);

      $html_mail = $smarty->fetch(CURRENT_TEMPLATE.'/admin/mail/'.$check_status['language'].'/change_order_mail.html');
      $txt_mail = $smarty->fetch(CURRENT_TEMPLATE.'/admin/mail/'.$check_status['language'].'/change_order_mail.txt');

      xtc_php_mail(EMAIL_BILLING_ADDRESS, EMAIL_BILLING_NAME, $check_status['customers_email_address'], $check_status['customers_name'], '', EMAIL_BILLING_REPLY_ADDRESS, EMAIL_BILLING_REPLY_ADDRESS_NAME, '', '', EMAIL_BILLING_SUBJECT, $html_mail, $txt_mail);
      $customer_notified = 1;
    }
  }

  $res &= act_db_query("insert into ".TABLE_ORDERS_STATUS_HISTORY." (orders_id, orders_status_id, date_added, customer_notified, comments) values ('".act_db_input($oID)."', '".act_db_input($status)."', now(), '".$customer_notified."', '".act_db_input($comments)."')");

  if( !$res )
    return array( 'ok'=>FALSE, 'errno'=>EIO );

  return array( 'ok'=>TRUE );
}



?>