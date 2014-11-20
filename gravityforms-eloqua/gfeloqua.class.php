<?php

if( class_exists( 'GFEloqua' ) )
	return;

class GFEloqua extends GFFeedAddOn {

	protected $_version = GFELOQUA_VERSION;
	protected $_min_gravityforms_version = '1.8.17';
	protected $_slug = 'gravityforms-eloqua';
	protected $_path = 'gravityforms-eloqua/gravityforms-eloqua.plugin.php';
	protected $_full_path = __FILE__;
	protected $_url = 'http://www.briandichiara.com';
	protected $_title = 'Gravity Forms Eloqua';
	protected $_short_title = 'Eloqua';

	// Members plugin integration
	protected $_capabilities = array( 'gravityforms_eloqua', 'gravityforms_eloqua_uninstall' );

	// Permissions
	protected $_capabilities_settings_page = 'gravityforms_eloqua';
	protected $_capabilities_form_settings = 'gravityforms_eloqua';
	protected $_capabilities_uninstall = 'gravityforms_eloqua_uninstall';
	protected $_enable_rg_autoupgrade = true;

	private static $_instance = null;

	protected $eloqua;

	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFEloqua();
		}

		return self::$_instance;
	}

	public function init(){
		parent::init();

		$this->eloqua = new Eloqua_API( $this->get_auth_string() );

		add_action( 'wp_ajax_gfeloqua_clear_transient', array( $this, 'clear_eloqua_transient' ) );

		if( $this->is_detail_page() ){
			wp_enqueue_script( 'gform_conditional_logic' );
			wp_enqueue_script( 'gform_gravityforms' );
			wp_enqueue_script( 'gform_form_admin' );
		}
	}

	public function feed_settings_fields() {
		$feed = $this->get_current_feed();

		return array(
			array(
				'fields' => array(
					array(
						'name'     => 'feed_name',
						'label'    => __( 'Name', 'gfeloqua' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
						'tooltip'  => '<h6>' . __( 'Name', 'gfeloqua' ) . '</h6>' . __( 'Enter a feed name to uniquely identify this setup.', 'gfeloqua' ),
					),
					array(
						'label'   => __( 'Eloqua Form', 'gfeloqua' ),
						'type'    => 'eloqua_forms',
						'onchange'   => 'jQuery(this).parents("form").submit();',
						'name'    => 'gfeloqua_form'
					),
					array(
						'name' => 'mapped_fields',
						'label' => 'Map Fields <a href="#gfe-refresh" class="gfe-refresh">Refresh</a>',
						'type' => 'list_fields',
						'dependency' => 'gfeloqua_form',
						'tooltip'    => '<h6>' . __( 'Map Fields', 'gfeloqua' ) . '</h6>' . __( 'Associate your Eloqua custom fields to the appropriate Gravity Form fields by selecting the appropriate form field from the list.', 'gfeloqua' ),
					),
					array(
						'name'       => 'optin',
						'label'      => __( 'Opt In', 'gfeloqua' ),
						'type'       => 'feed_condition',
						'dependency' => 'gfeloqua_form',
						'tooltip'    => '<h6>' . __( 'Opt-In Condition', 'gfeloqua' ) . '</h6>' . __( 'When the opt-in condition is enabled, form submissions will only be exported to Eloqua when the condition is met. When disabled all form submissions will be exported.', 'gfeloqua' ),
					),
				)
			)
		);
	}

	public function styles() {

		$styles = array(
			array(
				'handle'  => 'gfeloqua',
				'src'     => $this->get_base_url() . '/assets/css/gfeloqua.min.css',
				'version' => $this->_version,
				'enqueue' => array(
					array(
						'query' => 'page=gf_edit_forms&view=settings&subview=' . $this->_slug
					)
				)
			)
		);

		return array_merge( parent::styles(), $styles );
	}

	public function scripts() {
		$scripts = array(
			array(
				'handle'  => 'gfeloqua',
				'src'     => $this->get_base_url() . '/assets/js/gfeloqua.min.js',
				'version' => $this->_version,
				'deps'    => array( 'jquery' ),
				'strings' => array(
					'ajax_url'  => admin_url( 'admin-ajax.php' )
				),
				'enqueue' => array(
					array(
						'query' => 'page=gf_edit_forms&view=settings&subview=' . $this->_slug
					)
				)
			),

		);

		return array_merge( parent::scripts(), $scripts );
	}

	public function feed_edit_page( $form, $feed_id ) {

		// ensures valid credentials were entered in the settings page
		if( ! $this->get_auth_string() ){
			$settings_page = $this->get_plugin_settings_url();
			$view = GFELOQUA_PATH . '/views/needs-setup.php';
			include( $view );
			return;
		}

		echo '<script type="text/javascript">var form = ' . GFCommon::json_encode( $form ) . ';</script>';

		parent::feed_edit_page( $form, $feed_id );
	}

	public function feed_list_page( $form = NULL ){
		if( ! $this->get_auth_string() ){
			$settings_page = $this->get_plugin_settings_url();
			$view = GFELOQUA_PATH . '/views/needs-setup.php';
			include( $view );
			return;
		}

		parent::feed_list_page( $form );
	}

	protected function feed_list_columns() {
		return array(
			'feed_name' => __( 'Feed Name', 'gfeloqua' ),
			'gfeloqua_form' => __( 'Eloqua Form Name', 'gfeloqua' ),
		);
	}

	public function get_column_value_feed_name( $feed ){
		return $feed['meta']['feed_name'];
	}

	public function get_column_value_gfeloqua_form( $feed ){
		$form = $this->eloqua->get_form( $feed['meta']['gfeloqua_form'] );
		return $form->name . ' (ID: ' . $feed['meta']['gfeloqua_form'] . ')';
	}

	public function get_auth_string(){
		$auth_string = false;

		if( $this->get_plugin_setting( 'sitename' ) && $this->get_plugin_setting( 'username' ) && $this->get_plugin_setting( 'password' ) ){
			$auth_string = base64_encode( $this->get_plugin_setting( 'sitename' ) . '\\' . $this->get_plugin_setting( 'username' ) . ':' . $this->get_plugin_setting( 'password' ) );
		}

		return $auth_string;
	}

	public function settings_eloqua_forms( $field, $echo = true ){
		$forms = array(
			array(
				'label' => __( 'Select an Eloqua Form', 'gfeloqua' ),
				'value' => ''
			)
		);

		if( $this->eloqua ){
			$eloqua_forms = $this->eloqua->get_forms();
			if( count( $eloqua_forms ) ){
				foreach( $eloqua_forms as $form ){
					$forms[] = array(
						'label' => $form->name . ' (' . $form->currentStatus . ')',
						'value' => $form->id
					);
				}
			} else {
				$forms[] = array(
					'label' => __( 'No Eloqua Forms were found.', 'gfeloqua' ),
					'value' => ''
				);
			}
		}

		$field['type']    = 'select';
		$field['choices'] = $forms;

		$html = $this->settings_select( $field, false );

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	public function settings_list_fields( $field, $echo = true ) {

		$form_id = $this->get_setting( 'gfeloqua_form' );
		$custom_fields = $this->eloqua->get_form_fields( $form_id );

		$field_map = array();

		if( is_array( $custom_fields ) && count( $custom_fields ) ){
			foreach( $custom_fields as $custom_field ){
				if( $custom_field->displayType == 'submit' )
					continue;

				$field_map[] = array(
					'name' => $custom_field->id,
					'label' => $custom_field->name,
					'required' => $this->eloqua->is_field_required( $custom_field )
				);
			}
		}

		$field['type'] = 'field_map';
		$field['field_map'] = $field_map;

		$html = $this->settings_field_map( $field, false );

		if ( $echo ) {
			echo $html;
		}

		return $html;

	}

	public function clear_eloqua_transient(){
		$form_id = isset( $_GET['form_id'] ) ? (int) sanitize_text_field( $_GET['form_id'] ) : false;
		if( $form_id )
			$this->eloqua->clear_transient( 'assets/form/' . $form_id );

		wp_send_json( array( 'success' => true ) );
	}

	public function plugin_settings_fields() {

		$fields = array(
			array(
				'name'    => 'sitename',
				'tooltip' => __( 'Your Site Name is usually your company name without any spaces.', 'gfeloqua' ),
				'label'   => __( 'Site Name', 'gfeloqua' ),
				'type'    => 'text',
				'class'   => 'medium'
			),
			array(
				'name'    => 'username',
				'tooltip' => __( 'Your login user name', 'gfeloqua' ),
				'label'   => __( 'Username', 'gfeloqua' ),
				'type'    => 'text',
				'class'   => 'medium'
			),
			array(
				'name'    => 'password',
				'tooltip' => __( 'Your login password', 'gfeloqua' ),
				'label'   => __( 'Password', 'gfeloqua' ),
				'type'    => 'text',
				'class'   => 'medium'
			)
		);

		if( $this->get_auth_string() ){
			// TODO: Hide credentials, store only AUTH.
			foreach( $fields as &$field ){
				$field['type'] = 'hidden';
			}

			$fields[] = array(
				'type' => 'checkbox',
				'name' => 'disconnect',
				'label' => __( 'Disconnect', 'gfeloqua' ),
				'tooltip' => __( 'Disconnect your Eloqua account from Gravity Forms', 'gfeloqua' ),
				'horizontal' => true,
				'choices' => array(
					array(
						'name' => 'disconnect',
						'label' => __( 'Your Eloqua settings are stored. To clear these settings, check this box and click "Update".', 'gfeloqua' )
					)
				)
			);
		}

		return array(
			array(
				'title'  => 'Eloqua Settings',
				'fields' => $fields
			)
		);
	}

	public function process_feed( $feed, $entry, $form ){
		$form_id = $feed['meta']['gfeloqua_form'];

		$form_submission = new stdClass();
		$form_submission->elements = array(
			'type' => 'FormData',
			'id' => $form_id,
			'fieldValues' => array()
		);

		foreach( $feed['meta'] as $key => $gf_field_id ){

			if( strpos( $key, 'mapped_fields_' ) !== false ){
				if( ! isset( $entry[ $gf_field_id ]) )
					continue;

				$key = str_replace( 'mapped_fields_', '', $key );

				$field_value = new stdClass();
				$field_value->type = 'FieldValue';
				$field_value->id = $key;
				$field_value->value = $entry[ $gf_field_id ];

				$form_submission->elements['fieldValues'][] = $field_value;
			}
		}

		$response = $this->eloqua->submit_form( $form_id, $form_submission );
	}
}
