<?php
/**
* @package WPNC
* @version 1.7
*/
/*
Plugin Name: WPNC
Plugin URI:
Description:  Wordpress Notification Center
Author: Anton Vodkin
Version: 1.7
Author URI:
*/

ini_set('display_errors','on');
error_reporting(1);

define('WPNC_PLUGIN_DIR', dirname(__FILE__));
define('WPNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPNC_API_URL', 'http://flexidb.devmd.co.uk/api/v1/');
define('WPNC_API_KEY', 'y55p653c0fas2qp9jrbvmhh4qf7l1viqqd5m9ypz4qhw6xwgey2r69mnfky657nl');
define('WPNC_SECRET_KEY', 'odl9lzbrpnwpfakgzzf74syxudyt1ief');
define('WPNC_ENCODE', true);


require WPNC_PLUGIN_DIR.'/wpnc_api.php';
require WPNC_PLUGIN_DIR.'/wpnc_blowfish.php';
require WPNC_PLUGIN_DIR.'/wpnc_error.php';

function wpnc_scripts() {
  wp_enqueue_script('md_api', WPNC_PLUGIN_URL.'main.js');
  wp_enqueue_script('wpnc', WPNC_PLUGIN_URL.'wpnc.js', array('md_api'), false, true);
}

function wpnc_custom_js() {
  //Maxim, use these connection settings or run the same request in JS
  $connection_settings = wpnc_get_connection_settings();

?>
  <script language="JavaScript">
    console.log('WPNC Plugin: Hi! Insert your JS here!');
    console.log('NC Connection Settings: <?php echo json_encode($connection_settings) ?>');
    const wpncSettings = {
      connectionSettings: <?php echo json_encode($connection_settings) ?>,
      wp: {
        isPostsPage: <?php echo is_home() ? 'true' : 'false';?>,
        isPost: <?php echo is_single() ? get_the_ID() : 'false';?>,
        postPageUrl: "<?php echo get_post_type_archive_link( 'post' ); ?>",
        permalinks: "<?php echo get_option('permalink_structure');?>"
      }
    }
  </script>

<?php

}

add_action( 'wp_head', 'wpnc_custom_js' );
add_action( 'wp_enqueue_scripts', 'wpnc_scripts' );


function load_template_part($template_name, $part_name=null) {
  ob_start();
  get_template_part($template_name, $part_name);
  $var = ob_get_contents();
  ob_end_clean();
  return $var;
}

function wp_apply_filter_content($response, $post, $request) {
  global $wp_filters;
  $post_id = get_the_ID();
  $post_type = get_post_type($post_id);
  if ($post_type === 'post') {
    $_data = $response->data;
    // $_data['test'] = get_the_ID();
    $_data['rendered_post'] = load_template_part( 'template-parts/content', 'single' );
    $_data['filters'] = $wp_filters;
    $response->data = $_data;
  }
  return $response;
}
add_filter( "rest_prepare_post", 'wp_apply_filter_content', 12, 3 );




function wpnc_update_post($post_id, $post, $updated)
{ 
  if ($updated) {
    if ($post->post_status == 'trash') {
      wpnc_send_flexidb_request('delete', $post_id);
    }
    else {
      wpnc_send_flexidb_request('put', $post_id);
    }
  }
  else {
    wpnc_send_flexidb_request('post', $post_id);
  }
}

add_action('wp_insert_post', 'wpnc_update_post', 10, 3);

// function wpnc_delete_post($post_id)
// { 
//   if (is_numeric($post_id)) {
//     wpnc_send_flexidb_request('delete', $post_id);
//   }
// }

// add_action('delete_post', 'wpnc_delete_post', 10, 1);


function wpnc_send_flexidb_request($method, $post_id)
{
  $keys = array(
    'api_key' => WPNC_API_KEY,
    'secret_key' => WPNC_SECRET_KEY
  );
  
  $api = new DFX_API_Client(WPNC_API_URL, $keys);
  
  $result = $api->execRequest('wpnc/post', $method, array('id' => $post_id), WPNC_ENCODE);
  
  if (is_fx_error($result)) {
    $result = $result->get_error_message();
    file_put_contents(WPNC_PLUGIN_DIR.'/log.txt', $result."\n---------\n", FILE_APPEND);    
  }
  
  return $result;
}

function wpnc_get_connection_settings()
{
  $keys = array(
    'api_key' => WPNC_API_KEY,
    'secret_key' => WPNC_SECRET_KEY
  );
  
  $api = new DFX_API_Client(WPNC_API_URL, $keys);

  $result = $api->execRequest('wpnc/nc_connection', 'get', array(), WPNC_ENCODE);
  
  if (is_fx_error($result)) {
    $result = $result->get_error_message();
    file_put_contents(WPNC_PLUGIN_DIR.'/log.txt', $result."\n---------\n", FILE_APPEND);
  }
  
  return $result;
}