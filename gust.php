<?php 
/*
Plugin Name: Gust
Plugin URI: https://github.com/ideag/gust
Description: A port of the Ghost admin interface
Author: Arūnas Liuiza
Version: 0.4.0
Author URI: http://wp.tribuna.lt/
*/
//error_reporting(-1);
define ('GUST_NAME',          'gust');
define ('GUST_SUBPATH',       gust_get_subpath());
define ('GUST_ROOT',          GUST_SUBPATH.'/'.GUST_NAME);
define ('GUST_API_ROOT',      '/api/v0\.1');
define ('GUST_TITLE',         'Gust');
define ('GUST_VERSION',       'v0.4.0');
define ('GUST_PLUGIN_PATH',   plugin_dir_path(__FILE__));
define ('GUST_PLUGIN_URL',    plugin_dir_url(__FILE__));

register_activation_hook(__FILE__,'gust_install');
function gust_install(){
  gust_init_rewrites();
  flush_rewrite_rules();
  gust_permalink_check();
} 

add_action('init','gust_init_rewrites');
add_action('pre_get_posts','gust_drop_in',1);
// monitor for permalink changes
add_action('admin_init','gust_permalink_check');

function gust_permalink_check(){
  if (!gust_is_pretty_permalinks()) {
    add_action( 'admin_notices', 'gust_no_permalink_notice',1000 );   
  }
}
function gust_no_permalink_notice() {
    ?>
    <div class="error">
        <p><?php _e('Gust: You do not use pretty permalinks. Please enable them <a href="options-permalink.php">here</a> to use Gust.', 'gust' ); ?></p>
    </div>
    <?php
}

function gust_is_pretty_permalinks(){
  global $wp_rewrite;
  if ($wp_rewrite->permalink_structure == '')
    return false;
  else
    return true;
}

function gust_init_rewrites() {
  add_rewrite_tag( '%gust_api%', '(ghost|'.GUST_NAME.'|api)'); 
  add_rewrite_tag( '%gust_q%', '(.*)'); 
  add_permastruct('gust_calls', '%gust_api%/%gust_q%',array('with_front'=>false));
}

function gust_drop_in($q) {
  if ((get_query_var('gust_api')=='ghost'||get_query_var('gust_api')==GUST_NAME||get_query_var('gust_api')=='api' )&& $q->is_main_query()) {
    define('WP_ADMIN',true);
    require_once(GUST_PLUGIN_PATH.'/assets/dispatch/dispatch.php');
    D::config('dispatch.views', GUST_PLUGIN_PATH.'views');
    D::config('dispatch.layout', false);
    D::config('dispatch.url', get_bloginfo('url'));
    require_once('gust.class.php');
    if (get_query_var('gust_api')=='api' && $q->is_main_query()) {
      require_once('gust-api.php');
      D::on('POST',  '/'.GUST_API_ROOT.'/session',                array('Gust_API', 'login'));
      D::on('POST',  '/'.GUST_API_ROOT.'/password',               array('Gust_API', 'forgotten'));
      D::on('GET',   '/'.GUST_API_ROOT.'/posts',                  array('Gust_API', 'posts'));
      D::on('GET',   '/'.GUST_API_ROOT.'/post(/:id@[0-9]+)',      array('Gust_API', 'post'));
      D::on('POST',  '/'.GUST_API_ROOT.'/post(/:id@[0-9]+)',      array('Gust_API', 'post_save'));
      D::on('DELETE','/'.GUST_API_ROOT.'/post(/:id@[0-9]+)',      array('Gust_API', 'post_delete'));
      D::on('GET',   '/'.GUST_API_ROOT.'/autosave/:id@[0-9]+',    array('Gust_API', 'autosave_get'));
      D::on('POST',  '/'.GUST_API_ROOT.'/autosave/:id@[0-9]+',    array('Gust_API', 'autosave'));
      D::on('POST',  '/'.GUST_API_ROOT.'/upload/:id@[0-9]+',      array('Gust_API', 'upload'));
      D::on('DELETE','/'.GUST_API_ROOT.'/upload',                 array('Gust_API', 'upload_delete'));
      D::on('GET',   '/'.GUST_API_ROOT.'/:type@tags|categories',  array('Gust_API', 'tax'));

    } else if (
        (get_query_var('gust_api')==GUST_NAME || get_query_var('gust_api')=='ghost')
        &&
        ($q->is_main_query())
      ) {
      require_once('gust-views.php');
      D::on('GET',  '/ghost(/:q@.*)',                        array('Gust_views', 'ghost'));
      D::on('GET',  '/'.GUST_NAME,                           array('Gust_views', 'root'));
      D::on('GET',  '/'.GUST_NAME.'/login',                  array('Gust_views', 'login'));
      D::on('GET',  '/'.GUST_NAME.'/signout',                array('Gust_views', 'signout'));
      D::on('GET',  '/'.GUST_NAME.'/forgotten',              array('Gust_views', 'forgotten'));
      D::on('GET',  '/'.GUST_NAME.'/:type@post|page',        array('Gust_views', 'post_type'));
      D::on('GET',  '/'.GUST_NAME.'/editor',                 array('Gust_views', 'editor_default'));
      D::on('GET',  '/'.GUST_NAME.'/editor/:type@post|page', array('Gust_views', 'editor_new'));
      D::on('GET',  '/'.GUST_NAME.'/editor/:id@[0-9]+',      array('Gust_views', 'editor'));
      D::on('POST', '/'.GUST_NAME.'/coffee',                 array('Gust',       'paypal_submit'));
      D::on('*',    '/'.GUST_NAME.'/coffee/confirm',         array('Gust_views', 'coffee_confirm'));
    }
    D::dispatch();
    die('');
  }
}

/*
function gust_uuid_post($post_id) {
  $uuid = get_post_meta($post_id,'_uuid',true);
  if (!$uuid) {
    $uuid = gust_gen_uuid();
    update_post_meta( $post_id, '_uuid', $uuid );
  }
  return $uuid;
}

function gust_gen_uuid() {
    return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        // 32 bits for "time_low"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

        // 16 bits for "time_mid"
        mt_rand( 0, 0xffff ),

        // 16 bits for "time_hi_and_version",
        // four most significant bits holds version number 4
        mt_rand( 0, 0x0fff ) | 0x4000,

        // 16 bits, 8 bits for "clk_seq_hi_res",
        // 8 bits for "clk_seq_low",
        // two most significant bits holds zero and one for variant DCE1.1
        mt_rand( 0, 0x3fff ) | 0x8000,

        // 48 bits for "node"
        mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
    );
}
*/
function get_avatar_url($id_or_email, $size=96, $default='', $alt=false){
    $get_avatar = get_avatar( $id_or_email, $size, $default, $alt );
    preg_match("/src='(.*?)'/i", $get_avatar, $matches);
    return $matches[1];
}

function gust_get_subpath(){
  $url = get_bloginfo('url');
  $url = parse_url($url);
  $url = isset($url['path'])?$url['path']:'';
  return $url;
}

?>