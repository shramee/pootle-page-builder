<?php

/**
 * Pootle Page Builder Live Editor public class
 * @since 2.0.0
 */
class Pootle_Page_Builder_Live_Editor_Public {

	/**
	 * @var    Pootle_Page_Builder_Live_Editor_Public Instance
	 * @access  private
	 * @since 2.0.0
	 */
	private static $_instance = null;

	/**
	 * @var    mixed Edit title
	 * @access  private
	 * @since 2.0.0
	 */
	private $edit_title = false;

	/**
	 * @var    mixed Edit title
	 * @access private
	 * @since 2.0.0
	 */
	private $post_id = false;

	/**
	 * @var    array Addons to display
	 * @access  private
	 * @since 2.0.0
	 */
	private $addons = array();
	private $user;
	private $nonce;

	/**
	 * Main Pootle Page Builder Live Editor Instance
	 * Ensures only one instance of Storefront_Extension_Boilerplate is loaded or can be loaded.
	 * @since 1.0.0
	 * @return Pootle_Page_Builder_Live_Editor_Public instance
	 */
	public static function instance() {
		if ( null == self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	} // End instance()

	/**
	 * Constructor function.
	 * @access  private
	 * @since   1.0.0
	 */
	private function __construct() {
		$this->token   = Pootle_Page_Builder_Live_Editor::$token;
		$this->url     = Pootle_Page_Builder_Live_Editor::$url;
		$this->path    = Pootle_Page_Builder_Live_Editor::$path;
		$this->version = Pootle_Page_Builder_Live_Editor::$version;
	} // End __construct()

	public function post_status() {
		return get_post_status( $this->post_id );
	}

	/**
	 * Adds the actions anf filter hooks for plugin
	 * @since 1.1.0
	 */
	public function verify() {
		global $post;

		//Checking nonce
		$nonce = filter_input( INPUT_GET, 'ppbLiveEditor' );
		$this->nonce = $nonce ? $nonce : filter_input( INPUT_POST, 'nonce' );
		$user = filter_input( INPUT_POST, 'user' );
		$this->user = $user = $user ? $user : filter_input( INPUT_GET, 'user' );

		//Post ID
		$id            = $post ? $post->ID : filter_input( INPUT_POST, 'post' );
		$this->post_id = $id;

		if ( $this->nonce === get_transient( 'ppb-ios-' . $user ) ) {

			$this->actions();
			add_filter('show_admin_bar', '__return_false');
			add_action( 'wp_head', array( $this, 'ios_bar' ) );

			return true;
		} else if ( wp_verify_nonce( $this->nonce, 'ppb-live-' . $id ) ) {
			$this->actions();
			return true;
		} else {
			return null;
		}
	}

	public function actions() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			function is_plugin_active() {
				return 0;
			}
		}

		$this->addons = apply_filters( 'pootlepb_le_content_block_tabs', array() );

		remove_filter( 'pootlepb_content_block', array(
			$GLOBALS['Pootle_Page_Builder_Content_Block'],
			'auto_embed'
		), 8 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 99 );
		add_action( 'pootlepb_before_pb', array( $this, 'before_pb' ), 7, 4 );
		add_action( 'pootlepb_render_content_block', array( $this, 'edit_content_block' ), 7, 4 );

		if ( ! empty( $_GET['edit_title'] ) ) {
			$this->edit_title = get_the_title();
		}

		add_filter( 'pootlepb_row_cell_attributes', array( $this, 'cell_attr' ), 10, 3 );
		add_filter( 'pootlepb_content_block', array( $this, 'content' ), 5 );
		add_action( 'pootlepb_before_row', array( $this, 'edit_row' ), 7, 2 );
		add_action( 'pootlepb_after_content_blocks', array( $this, 'column' ) );
		add_action( 'pootlepb_after_pb', array( $this, 'add_row' ) );
		add_action( 'pootlepb_after_pb', array( $this, 'preview_styles' ), 11 );
		add_action( 'wp_footer', array( $this, 'dialogs' ), 7 );
		add_filter( 'pootlepb_rag_adjust_elements', '__return_empty_array', 999 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
		add_filter( 'post_class', array( $this, 'post_type_class' ), 10, 3 );

	}

	/**
	 * Add pootle-live-editor-active class to body
	 *
	 * @param array $classes
	 *
	 * @return array Content
	 */
	public function body_class( $classes ) {
		$classes[] = 'pootle-live-editor-active';
		return $classes;
	}

	/**
	 * Add post type calss to post
	 *
	 * @param array $classes
	 *
	 * @return string Content
	 */
	public function post_type_class( $classes, $unused, $post ) {
		$classes[] = get_post_type( $post );

		return $classes;
	}

	/**
	 * Print inline CSS
	 * @since 0.1.0
	 */
	public function preview_styles() {
		global $pootlepb_inline_css;

		if ( ! empty( $pootlepb_inline_css ) ) {
			?>
			<!----------Pootle Page Builder Inline Styles---------->
			<style id="pootle-live-editor-styles" type="text/css"
			       media="all"><?php echo $pootlepb_inline_css ?></style><?php
		}

		$pootlepb_inline_css = '';
	}

	/**
	 * Enqueue the required styles
	 * @since 1.1.0
	 */
	public function enqueue() {
		$this->enqueue_scripts();
		$this->l10n_scripts();

		$ver = POOTLEPB_VERSION;
		$url = $this->url . '/assets';
		wp_enqueue_style( 'pootlepb-ui-styles', POOTLEPB_URL . 'css/ppb-jq-ui.css', array(), $ver );
		wp_enqueue_style( 'ppb-panels-live-editor-css', "$url/front-end.css", array(), $ver );
		wp_enqueue_style( 'wp-color-picker' );
	}

	/**
	 * Wraps the content in .pootle-live-editor-realtime and convert short codes to strings
	 *
	 * @param string $content
	 *
	 * @return string Content
	 */
	public function content( $content ) {
		$content = str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), $content );

		return "<div class='pootle-live-editor-realtime'>$content</div>";
	}

	protected function enqueue_scripts() {
		global $pootlepb_color_deps;
		$url       = $this->url . '/assets';
		$jQui_deps = array(
			'jquery',
			'jquery-ui-slider',
			'jquery-ui-dialog',
			'jquery-ui-tabs',
			'jquery-ui-sortable',
			'jquery-ui-resizable'
		);
		$ppb_js    = POOTLEPB_URL . 'js';
		$ver       = POOTLEPB_VERSION;

		wp_enqueue_media();

		wp_enqueue_style( 'ppb-chosen-style', "$ppb_js/chosen/chosen.css" );
		wp_enqueue_script( 'pootlepb-chosen', "$ppb_js/chosen/chosen.jquery.min.js", array( 'jquery' ), POOTLEPB_VERSION );

		wp_enqueue_script( 'iris', admin_url( 'js/iris.min.js' ), $pootlepb_color_deps );
		wp_enqueue_script( 'wp-color-picker', admin_url( 'js/color-picker.min.js' ), array( 'iris' ) );

		wp_enqueue_script( 'ppb-fields', "$url/ppb-deps.js", array( 'wp-color-picker', ), $ver );
		wp_enqueue_script( 'ppb-ui', "$ppb_js/ppb-ui.js", $jQui_deps, $ver );
		wp_enqueue_script( 'ppb-ui-tooltip', "$ppb_js/ui.admin.tooltip.js" );
		wp_enqueue_script( 'ppble-tmce-view', "$url/tmce.view.js" );
		wp_enqueue_script( 'ppble-tmce-theme', "$url/tmce.theme.js", array( 'ppble-tmce-view' ) );

		wp_enqueue_script( 'ppble-sd', "$url/showdown.min.js", array( 'ppb-ui', 'ppb-fields', ), $ver );

		wp_enqueue_script( 'pootle-live-editor', "$url/front-end.js", array(
			'ppb-ui',
			'ppble-sd',
			'ppb-fields',
		), $ver );

		wp_enqueue_script( "pp-pb-iris", "$ppb_js/iris.js", array( 'iris' ) );

		wp_enqueue_script( 'pp-pb-color-picker', "$ppb_js/color-picker-custom.js", array( 'pp-pb-iris' ) );

		wp_enqueue_script( 'mce-view' );
		wp_enqueue_script( 'image-edit' );

		do_action( 'pootlepb_enqueue_admin_scripts' );

	}

	protected function l10n_scripts() {
		global $post;

		//Grid data
		$panels_data = get_post_meta( $post->ID, 'panels_data', true );
		if ( count( $panels_data ) > 0 ) {
			wp_localize_script( 'pootle-live-editor', 'ppbData', $panels_data );
		}

		//Fix: panels undefined
		wp_localize_script( 'ppb-fields', 'panels', array() );

		//Ajax
		$ppbAjax = array(
			'url'    => admin_url( 'admin-ajax.php' ),
			'action' => 'pootlepb_live_editor',
			'post'   => $post->ID,
			'nonce'  => $this->nonce,
			'user' => $this->user,
		);
		if ( $this->edit_title ) {
			$ppbAjax['title'] = $this->edit_title;
		}
		wp_localize_script( 'pootle-live-editor', 'ppbAjax', $ppbAjax );

		//Colorpicker
		$colorpicker_l10n = array(
			'clear'         => __( 'Clear' ),
			'defaultString' => __( 'Default' ),
			'pick'          => __( 'Select Color' ),
			'current'       => __( 'Current Color' ),
		);
		wp_localize_script( 'pp-pb-color-picker', 'wpColorPicker_i18n', $colorpicker_l10n );
	}

	/**
	 * Saves setting from front end via ajax
	 * @since 1.1.0
	 */
	public function sync() {
		if ( $this->verify() ) {
			$id = $_POST['post'];

			if ( filter_input( INPUT_POST, 'publish' ) ) {
				// Update post
				$live_page_post = array( 'ID' => $id );

				if ( 'Publish' == filter_input( INPUT_POST, 'publish' ) ) {
					$live_page_post['post_status'] = 'publish';
				}
				if ( ! empty( $_POST['title'] ) ) {
					$live_page_post['post_title'] = $_POST['title'];
				}

				// Update PPB data
				update_post_meta( $id, 'panels_data', $_POST['data'] );

				// Generate post content
				$live_page_post['post_content'] = pootlepb_get_text_content( $id );

				// Update post
				wp_update_post( $live_page_post, true );

				echo get_permalink( $id );
			} else {
				foreach ( $_POST['data']['widgets'] as $i => $wid ) {
					if ( ! empty( $wid['info']['style'] ) ) {
						$_POST['data']['widgets'][ $i ]['info']['style'] = stripslashes( $wid['info']['style'] );
						$_POST['data']['widgets'][ $i ]['text']          = stripslashes( $wid['text'] );
					}
				}
				echo $GLOBALS['Pootle_Page_Builder_Render_Layout']->panels_render( $id, $_POST['data'] );
			}
		}
		die();
	}

	/**
	 * Reset panel index
	 * @since 1.1.0
	 */
	public function before_pb() {
		$this->pi = 0;
	}

	/**
	 * Adds front end grid edit ui
	 *
	 * @param array $data
	 * @param int $gi
	 *
	 * @since 1.1.0
	 */
	public function edit_row( $data, $gi = 0 ) {
		?>
		<div class="pootle-live-editor ppb-live-edit-object ppb-edit-row" data-index="<?php echo $gi; ?>"
		     data-i_bkp="<?php echo $gi; ?>">
			<span href="javascript:void(0)" title="Row Sorting" class="dashicons-before dashicons-editor-code">
				<span class="screen-reader-text">Sort row</span>
			</span>
			<span href="javascript:void(0)" title="Row Styling" class="dashicons-before dashicons-admin-appearance">
				<span class="screen-reader-text">Edit Row</span>
			</span>
			<?php /*
			<span href="javascript:void(0)" title="Duplicate Row" class="dashicons-before dashicons-admin-page">
				<span class="screen-reader-text">Duplicate Row</span>
			</span>
			*/ ?>
			<span href="javascript:void(0)" title="Delete Row" class="dashicons-before dashicons-no">
				<span class="screen-reader-text">Delete Row</span>
			</span>
		</div>
		<?php
	}

	/**
	 * Edit content block icons
	 */
	public function edit_content_block( $content_block ) {
		?>
		<div class="pootle-live-editor ppb-live-edit-object ppb-edit-block"
		     data-index="<?php echo $content_block['info']['id']; ?>"
		     data-i_bkp="<?php echo $content_block['info']['id']; ?>">
			<span href="javascript:void(0)" title="Drag and Drop content block"
			      class="dashicons-before dashicons-screenoptions">
				<span class="screen-reader-text">Sort row</span>
			</span>
			<span href="javascript:void(0)" title="Edit Content" class="dashicons-before dashicons-edit">
				<span class="screen-reader-text">Edit Content Block</span>
			</span>
			<span href="javascript:void(0)" title="Insert Image" class="dashicons-before dashicons-format-image">
				<span class="screen-reader-text">Insert Image</span>
			</span>
			<?php
			if ( ! empty( $this->addons ) ) {
				?>
				<span href="javascript:void(0)" title="Addons"
				      class="dashicons-before dashicons-admin-plugins pootle-live-editor-addons">
					<span class="screen-reader-text">Add ons</span>
					<span class="pootle-live-editor-addons-list">
					<?php
					foreach ( $this->addons as $id => $addon ) {
						$addon = wp_parse_args( $addon, array( 'icon' => 'dashicons-star-filled', ) );
						echo
							"<span href='javascript:void(0)' data-id='$id' title='$addon[label]' class='pootle-live-editor-addon dashicons-before $addon[icon]'>" .
							"<span class='addon-text'><span class='addon-label'>$addon[label]</span></span>" .
							"</span>";
					}
					?>
					</span>
				</span>
				<?php
			}
			?>
			<span href="javascript:void(0)" title="Delete Content" class="dashicons-before dashicons-no">
				<span class="screen-reader-text">Delete Content</span>
			</span>
		</div>
		<?php
	}

	/**
	 * Edit content block icons
	 */
	public function cell_attr( $attr, $ci, $gi ) {
		$attr['data-index'] = $ci;

		return $attr;
	}

	/**
	 * Edit content block icons
	 */
	public function add_row() {
		?>
		<div class="pootle-live-editor  ppb-live-add-object add-row">
			<span href="javascript:void(0)" title="Add row" class="dashicons-before dashicons-plus">
				<span class="screen-reader-text">Add row</span>
			</span>
		</div>
		<?php
	}

	/**
	 * Edit content block icons
	 */
	public function column() {
		/*
		<div class="pootle-live-editor ppb-live-add-object add-content">
			<span href="javascript:void(0)" title="Add Content" class="dashicons-before dashicons-plus">
				<span class="screen-reader-text">Add Content</span>
			</span>
		</div>
		*/ ?>
		<div class="pootle-live-editor resize-cells"></div>
		<?php
	}

	/**
	 * Magic __construct
	 * @since 1.1.0
	 */
	public function dialogs() {
		require 'dialogs.php';
	}

	public function ios_bar() {
		?>
		<style>
			.panel-grid:first-child{ margin-top:0 }
			.pootle-live-editor.ppb-live-add-object.add-row, .panel-grid:hover .ppb-edit-row:hover span.dashicons-before, .ppb-block:hover .pootle-live-editor:hover span.dashicons-before { display: none;  }
			.panel-grid:hover .ppb-edit-row:hover span.dashicons-editor-code, .ppb-block:hover .pootle-live-editor:hover span.dashicons-screenoptions { display: inline-block;  }
			.pootle-live-editor-active .ppb-tabs-nav {
				font-size: 16px;
			}

			.ppb-tabs-panel .field > * {
				font-size: 16px;
				font-weight: 300;
			}
			.ppb-tabs-panel .field {
				margin-top: 25px
			}
			.ppb-tabs-nav li {
				margin: 16px 0;
			}

			.pootle-live-editor-active .mce-toolbar .mce-btn-group .mce-btn, .pootle-live-editor-active .qt-dfw {
				margin: 3px
			}

			.pootle-live-editor-active .ppb-dialog .button,
			.pootle-live-editor-active .ppb-dialog button,
			.pootle-live-editor-active .ppb-dialog .ui-button.pootle-live-editor-active,
			.pootle-live-editor-active .wp-color-result,
			.pootle-live-editor-active .wp-color-result:after  {
				font-size: 14px;
				line-height: 34px;
				height: 34px;
				font-weight: 300;
			}
			.pootle-live-editor-active .ppb-dialog .ppb-dialog-buttonpane button.ui-button,
			.pootle-live-editor-active .ppb-dialog [type="text"],
			.pootle-live-editor-active .ppb-dialog [type="number"],
			.pootle-live-editor-active .ppb-dialog .button,
			.pootle-live-editor-active .ppb-dialog button,
			.pootle-live-editor-active .ppb-dialog .ui-button.pootle-live-editor-active,
			.pootle-live-editor-active .wp-color-result:after {
				font-size: 16px;
				padding: 9px 16px !important;
				line-height: 16px;
				height: auto;
				font-weight: 300;
			}

			.pootle-live-editor-active .ppb-dialog [type="number"] {
				width: 120px;
				margin-right: 7px;
			}
			.mce-toolbar div.mce-btn button,
			.mce-toolbar div.mce-btn button.mce-open {
				padding: 7px 9px !important;
			}
			.ppb-dialog-titlebar {
				font-weight: 300;
			}
			.pootle-live-editor-active .ppb-dialog [type="checkbox"] {
				height: 20px;
				width: 20px;
				margin:5px 0;
			}
			.ppb-dialog .ui-button.ppb-dialog-titlebar-close {
				padding: 2px !important;
			}
			.ui-resizable-handle.ui-resizable-w {
				border: none;
				padding: 7px;
				margin-left: -7px;
			}
			.ui-resizable-handle.ui-resizable-w:before {
				content: '';
				display: block;
				position: absolute;
				top: 0;
				bottom: 0;
				margin-left: -1px;
				border-right: 2px dotted #ef4832;
				padding: 0;
				cursor: ew-resize;
			}
			#pootlepb-set-title + .ppb-dialog-buttonpane .ui-button-text-icon-primary {
				background: #0085ba !important;
				border-color: #0073aa #006799 #006799 !important;
				-webkit-box-shadow: 0 1px 0 #006799 !important;
				box-shadow: 0 1px 0 #006799 !important;
				color: #fff !important;
				text-decoration: none;
				text-shadow: 0 -1px 1px #006799,1px 0 1px #006799,0 1px 1px #006799,-1px 0 1px #006799 !important;
				font-weight: 500;
			}
			#ppb-ios-updated-notice {
				padding: 50px 50px;
				position: fixed;
				top: 0;
				bottom: 0;
				left: 0;
				right: 0;
				width: 340px;
				height: 340px;
				-webkit-box-sizing: border-box;
				box-sizing: border-box;
				margin: auto;
				text-align: center;
				background: #0c7;
				z-index: 999;
				border-radius: 50%;
				-webkit-animation: fade-in-out 2.5s 1 both;
				animation: fade-in-out 2.5s 1 both;
				display: none;
			}

			#ppb-ios-updated-notice > * {
				display: inline-block;
				width: auto;
				height: auto;
				vertical-align: middle;
				color: #fff;
				font-size: 25px;
				letter-spacing: 2px;
			}

			#ppb-ios-updated-notice .dashicons:before {
				font-size: 160px;
				color: inherit;
				width: auto;
				height: auto;
				display: block;
				line-height: 1;
			}
			@-webkit-keyframes fade-in-out {
				0%   { opacity: 0; -webkit-transform: translate3d(0,-25%,0) }
				34%  { opacity: 1; -webkit-transform: none                  }
				61%  { opacity: 1; -webkit-transform: none                  }
				100% { opacity: 0; -webkit-transform: translate3d(0,25%,0)  }
			}
			@keyframes fade-in-out {
				0%   { opacity: 0; transform: translate3d(0,-25%,0) }
				34%  { opacity: 1; transform: none                  }
				61%  { opacity: 1; transform: none                  }
				100% { opacity: 0; transform: translate3d(0,25%,0)  }
			}
		</style>
		<div id="ppb-ios-updated-notice">
			<span class="dashicons dashicons-yes"></span><h3>Changes Saved</h3>
		</div>
		<?php
	}
}