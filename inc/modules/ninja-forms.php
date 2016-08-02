<?php
class pootle_page_builder_Ninja_Forms {

	public $token = 'ninja_forms';
	public $slug = 'ninja-forms';
	public $class = 'Ninja_Forms';
	public $name = 'Ninja Forms';

	/** @var pootle_page_builder_Ninja_Forms Instance */
	private static $_instance = null;

	/**
	 * Gets pootle_page_builder_Ninja_Forms instance
	 * @return pootle_page_builder_Ninja_Forms instance
	 * @since 	1.0.0
	 */
	public static function instance() {
		if ( null == self::$_instance ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	} // End instance()

	/**
	 * PootlePB_Meta_Slider constructor.
	 */
	function __construct() {
		add_action( 'init', array( $this, 'init', ) );
	}

	function init() {
		if ( class_exists( $this->class ) ) {
			// Content block panel tabs
			add_filter( 'pootlepb_content_block_tabs', array( $this, 'tab' ) );
			add_filter( 'pootlepb_le_content_block_tabs', array( $this, 'tab' ) );
			// Adding shortcode to content block
			add_action( 'pootlepb_render_content_block', array( $this, 'shortcode' ), 25 );
			// Adding modules to live editor sidebar
			add_action( 'pootlepb_modules', array( $this, 'module' ), 25 );
			// Adding modules plugin to Modules page
			add_action( 'pootlepb_modules_page', array( $this, 'module_plugin' ), 25 );
			// Content block panel fields
			add_filter( 'pootlepb_content_block_fields', array( $this, 'fields' ) );
		}
	}

	public function tab( $tabs ) {
		$tabs[ $this->token ] = array(
			'label' => $this->name,
			'priority' => 5,
		);
		return $tabs;
	}

	public function fields( $fields ) {
		$fields[ $this->token ] = array(
			'name' => 'Form ID',
			'type' => 'number',
			'priority' => 1,
			'tab' => $this->token,
		);
		return $fields;
	}

	public function shortcode( $info ) {
		$set = json_decode( $info['info']['style'], true );
		if ( ! empty( $set[ $this->token ] ) ) {
			echo do_shortcode( "[$this->token id={$set[ $this->token ]}]" );
		}
	}

	public function module( $mods ) {
		$mods["$this->token-form"] = array(
			'label' => $this->name,
			'icon_class' => 'dashicons dashicons-feedback',
			'icon_html' => '',
			'tab' => "#pootle-$this->token-tab",
			'ActiveClass' => 'MetaSliderPlugin',
		);
		return $mods;
	}

	public function module_plugin( $mods ) {
		$mods[ $this->slug ] = array(
			'Name' => $this->name,
			'Description' => 'Adds awesome ninja form module',
			'InstallURI' => admin_url( "plugin-install.php?tab=plugin-information&plugin=$this->slug&TB_iframe=true&width=772&height=460" ),
			'AuthorURI' => 'https://www.metaslider.com',
			'Author' => 'Matcha Labs',
			'Image' => "//ps.w.org/$this->slug/assets/icon-256x256.png?rev=1262610",
			'ActiveClass' => $this->class,
		);
		return $mods;

	}
}

pootle_page_builder_Ninja_Forms::instance();