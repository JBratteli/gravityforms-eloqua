<?php
/**
 * Plugin Name: Gravity Forms Eloqua
 * Plugin URI: http://www.briandichiara.com
 * Description: Integrate Eloqua into Gravity Forms
 * Version: 1.0.1
 * Author: Brian DiChiara
 * Author URI: http://www.briandichiara.com
 */

define( 'GFELOQUA_VERSION', '1.0.1' );
define( 'GFELOQUA_OPT_PREFIX', 'gfeloqua' );
define( 'GFELOQUA_PATH', plugin_dir_path( __FILE__ ) );
define( 'GFELOQUA_DIR', plugin_dir_url( __FILE__ ) );

add_action( 'gform_loaded', array( 'GFEloqua_Bootstrap', 'load' ), 5 );

class GFEloqua_Bootstrap {

	public static function load(){

		if( ! class_exists( 'GFForms' ) || ! class_exists( 'GFAddOn' ) )
			return;

		if ( ! method_exists( 'GFForms', 'include_feed_addon_framework' ) ) {
			return;
		}

		GFForms::include_feed_addon_framework();

		require_once( GFELOQUA_PATH . '/api/class.eloqua.api.php' );
		require_once( GFELOQUA_PATH . '/includes/helpers.php' );
		require_once( GFELOQUA_PATH . 'gfeloqua.class.php' );

		GFAddOn::register( 'GFEloqua' );
	}
}

function gf_eloqua(){
	return GFEloqua::get_instance();
}
