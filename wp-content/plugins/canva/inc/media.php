<?php

/* Canva Media Class
 * Author: Ash Durham (http://durham.net.au)
 *
 * Updated and modified by Justin King (http://getafixx.com)
 * Last Update : 05/12/2014
 *
 */


/**
 * Class Canva_Media
 */
class Canva_Media {


	/**
	 * @var string - updated path to canja API
	 */
	private $api_path = "https://sdk.canva.com/v1/api.js";

	/**
	 * @var canvaDatabase - database table name
	 */
	public $canvaDatabase;

	/**
	 * @var optionName - have we set things up?
	 */
	private $optionName = 'canva_init' ;

	/**
	 * @var downloadUrl - for sanity checking.
	 */	
	private $downloadUrl = 'https://download.canva.com/' ;

	/**
	 * constructer
	 */
	function __construct() {

		$this->canvaDatabase = new canvaDatabase();

		update_option( 'canva_database_table', 'canva_images' );
		update_option( $this->optionName, 0 );

		// as the activate script doesn't want to work.
		$this->create_database_table();

		add_action( 'admin_init', array( $this, 'global_variables' ) );
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
		add_action( 'init', array( $this, 'mce_editor_styles' ), 1 );
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );


		add_action( 'admin_enqueue_scripts', array( $this, 'all_admin_enqueue' ) );
		add_action( 'admin_menu', array( $this, 'add_config_page' ) );
		add_action( 'wp_ajax_canva_uploader_action', array( $this, "canva_uploader_action" ) );
		add_action( 'wp_ajax_canva_edit_design_action', array( $this, "canva_edit_design_action" ) );
		add_action( 'wp_ajax_canva_mce_edit_design_action', array( $this, "canva_mce_edit_design_action" ) );
		add_action( 'wp_ajax_canva_get_design_id', array( $this, "canva_get_design_id" ) );

		register_activation_hook( CANVA_PATH, array( $this, "activate" ) );
		register_deactivation_hook( CANVA_PATH, array( $this, "deactivate" ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'canva_admin_enqueue' ) );
		add_action( 'admin_head', array( $this, 'admin_head' ), 1 );
		add_action( 'media_buttons_context', array( $this, "media_button" ) );
		add_filter( 'mce_external_plugins', array( $this, 'register_tinymce_javascript' ) );
		add_action( 'before_wp_tiny_mce', array( $this, 'localize_tinymce_javascript' ) );

		// media library hooks to use the id row to be an edit in canva button
		add_filter( 'manage_media_columns', array( $this, 'column_id' ) );
		add_filter( 'manage_media_custom_column', array( $this, 'column_id_row' ), 10, 2 );

		// show the edit in canva button in the edit media window
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_canva_edit_button' ), 10, 2 );
 		

		
	}


	/**
	 * Creates a button with the correct canva design ID in it for use in the
	 * media library attachment details window.
	 *
	 * @param $form_fields
	 * @param $post
	 *
	 * @return array
	 */
	function attachment_canva_edit_button( $form_fields, $post ) {

		$canva_designId = get_post_meta( $post->ID, 'canva_designId', true );

		if ( ! is_null( $canva_designId ) && isset( $canva_designId ) ) {

			$tmp         = array(
				'canva_designId' => array(
					'label' => 'Edit in Canva',
					'input' => 'html',
					'html'  => $this->create_canva_edit_button( $canva_designId, $post->ID )
				)
			);
			$form_fields = array_merge( $tmp, $form_fields );
		}

		return $form_fields;
	}


	/**
	 * We use this function to hijack the id row of the media library
	 * to show the 'edit in canva' button
	 *
	 * @param $columns
	 *
	 * @return mixed
	 */
	function column_id( $columns ) {

		$columns['colID'] = __( 'Canva Design' );

		return $columns;
	}


	/**
	 * We use this function to hijack the id row of the media library
	 * to show the 'edit in canva' button
	 *
	 * @param $columnName
	 * @param $columnID
	 */
	function column_id_row( $columnName, $columnID ) {


		if ( $columnName == 'colID' ) {

			$canvaData = $this->canvaDatabase->getCanvaElements( $columnID, 'postId' );
			if ( isset( $canvaData[0] ) ) {
				echo $this->create_canva_edit_button( $canvaData[0]['canvaId'] );
			}
			// if you want to send to Canva add an else here and use $this->create_send_to_canva_button( $columnID );
			// but as yest there is no call back avail able in the canva.ja, so it doesn't work correctly.
		}
	}

	/**
	 * Check if the canva design ID looks valid
	 *
	 * @param $canva_designId
	 *
	 * @return bool
	 */
	function validate_canva_id( $canva_designId ) {

		if ( strlen( $canva_designId ) != 11 ) {
			return false;
		}
		
		preg_match( "/D([\w\-\_A-Za-z]*)/", $canva_designId, $output_array );

		if(!isset($output_array[0]))
		{
			return false;
		}
		
		return true;
	}


	/**
	 * Create an edit in Canva button from the validated canva design ID
	 *
	 * @param $canva_designId
	 * @param null $postId
	 *
	 * @return string
	 */
	function create_canva_edit_button( $canva_designId, $postId = null ) {

		$api_key = $this->canva_api_key();

		if ( $this->validate_canva_id( $canva_designId ) ) {

			return ' <span class="canva-design-button" data-design-id="' . trim( $canva_designId ) . '" data-label="Edit" data-apikey="' . $api_key . '" data-url-callback="canvadesignEditCallback">Edit in Canva</span>';

		} else {
			return NULL;
			//return $this->create_send_to_canva_button( $postId );
		}

	}


	/**
	 * Will create the "use in canva" button, but this functionality does not work yet
	 * The new image will be uploaded to Canva, but there is no way of getting
	 * it back to wordpress yet. Once this functionality is there, use this function
	 * to make the correct button.
	 *
	 * @param $postId
	 *
	 * @return string
	 */
	function create_send_to_canva_button( $postId ) {

		$image_attributes = wp_get_attachment_image_src( $postId, 'full' );

		if ( is_array( $image_attributes ) ) {

			$design_type = get_option( 'canva_design_type' );
			$api_key     = $this->canva_api_key();

			return '<div style="position:relative; "><img class="canva-design-uploader canva-150-image"  data-type="' . $design_type . '" data-apikey="' . $api_key . '" src="' . $image_attributes[0] . '" /></div>';

		} else {
			return '<span>Not a valid image</span>';
		}
	}

	/*
	 * Activate the Canva Plugin
	 *
	 * This does not get called by wordpress...
	 *
	 */
	function activate() {

		global $wpdb;
		// Do some checks
		if ( ! function_exists( "curl_init" ) && ! ini_get( "allow_url_fopen" ) ) {
			deactivate_plugins( CANVA_BASENAME );
			wp_die( __( '<b>cURL</b> or <b>allow_url_fopen</b> needs to be enabled. Please consult your server Administrator.', 'canva' ) );
		}
	}


	/**
	 *
	 * This creates the canva database table if it doesn't exist.
	 *
	 */
	function create_database_table() {

		global $wpdb;

		$canva_init = get_option( $this->optionName );
		
		if ( isset( $canva_init ) && $canva_init !== '1' ) {
			
			$table_name = $this->canvaDatabase->table_name();

			// Do some checks
			if ( ! function_exists( "curl_init" ) && ! ini_get( "allow_url_fopen" ) ) {
				deactivate_plugins( CANVA_BASENAME );
				wp_die( __( '<b>cURL</b> or <b>allow_url_fopen</b> needs to be enabled. Please consult your server Administrator.', 'canva' ) );
			}

			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

			$charset_collate = '';

			if ( ! empty( $wpdb->charset ) ) {
				$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
			}

			if ( ! empty( $wpdb->collate ) ) {
				$charset_collate .= " COLLATE {$wpdb->collate}";
			}

			if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) != $table_name ) {
				//
				$sql = "CREATE TABLE " . $table_name . "  (
						id  bigint(20) unsigned NOT NULL AUTO_INCREMENT,
						canvaId  varchar(30) NOT NULL,
						postId  bigint(20) unsigned NOT NULL,
						created_on  datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
						last_edited  datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
						image  text NOT NULL,
						UNIQUE KEY (id)
						) " . $charset_collate . ";";


				dbDelta( $sql );

			}

			update_option( $this->optionName, '1' );
		}
	}

	/*
	 * Deactivate the Canva Plugin
	 *
	 * This does not get  called by workdpress ...
	 */
	function deactivate() {

		// at the moment we have chosen NOT to clear the data from the
		// database deliberately

		update_option( $this->optionName, '0' );

	}


	/**
	 * If we hooked up the "clear canva datbase" function
	 *
	 * Unused at the moment, but usedful for debugging
	 *
	 */
	function tuncate_canva_database() {

		global $wpdb;

		$table_name = $this->canvaDatabase->table_name();

		$sql = "TRUNCATE TABLE `" . $table_name . "`;";
		$res = $wpdb->query( $sql );

	}

	/**
	 *
	 * Load Translations
	 *
	 */
	function load_textdomain() {

		load_plugin_textdomain( 'canva', false, dirname( CANVA_BASENAME ) . '/languages/' );
	}

	/**
	 *
	 * Load Settings variables
	 *
	 */
	function global_variables() {

		register_setting( 'canva-settings', 'canva_design_type' );
		register_setting( 'canva-settings', 'canva_api_key' );

		add_option( 'canva_design_type', 'blogGraphic' );
		add_option( 'canva_api_key', '' );

	}

	/**
	 * Run when plugins are loaded
	 */
	function plugins_loaded() {

	}

	/**
	 * Enqueue required scripts
	 */
	function all_admin_enqueue() {

		wp_register_style( 'canva-wp', plugins_url( 'css/canva-wp.css', dirname( __FILE__ ) ), false, '1.11' );
		wp_enqueue_style( 'canva-wp' );

		$api_key = $this->canva_api_key();
		wp_enqueue_script( 'media-editor' );
		wp_enqueue_style( 'media-views' );

		wp_enqueue_script( 'canva-func', plugins_url( 'js/canva-func.js', dirname( __FILE__ ) ), array( 'jquery' ) );

		wp_localize_script( 'canva-func', 'canva_ajax',
			array(
				'ajaxurl'           => admin_url( 'admin-ajax.php' ),
				'ajaxnonce'         => wp_create_nonce( 'C4nv4' ),
				'canvaapikey'       => $api_key,
				'canvadesigntype'   => get_option( 'canva_design_type' ),
				'canvadesignbutton' => $this->media_button_for_js(),
				'ttest'             => 'test',
			) );

		wp_enqueue_script( 'canva-api', $this->api_path, array(), null, true );

	}


	/**
	 * This was used to add certain functions to certain pages
	 * This is not used like this any more.
	 *
	 * @param $hook
	 */
	function canva_admin_enqueue( $hook ) {

		$api_key = $this->canva_api_key();
		wp_enqueue_script( 'media-editor' );

		
		wp_enqueue_script( 'canva-func', plugins_url( 'js/canva-func.js', dirname( __FILE__ ) ), array( 'jquery' ));
		
		wp_localize_script( 'canva-func', 'canva_ajax',
			array(
				'ajaxurl'           => admin_url( 'admin-ajax.php' ),
				'ajaxnonce'         => wp_create_nonce( 'C4nv4' ),
				'canvaapikey'       => $api_key,
				'canvadesigntype'   => get_option( 'canva_design_type' ),
				'canvadesignbutton' => $this->media_button_for_js(),
				'ttest'             => 'test',
			) );

		wp_enqueue_script( 'canva-api', $this->api_path, array(), null, true );

		//}
	}

	/**
	 *
	 * enqueue the tinymce scripts
	 *
	 */
	function mce_editor_styles() {

		add_editor_style( plugins_url( 'css/canva-wp.css', dirname( __FILE__ ) ) );
	}

	/**
	 * Enqueue required scripts
	 *
	 * canvadesignCallback - used when a NEW design is created.
	 * canvaAnimShow - canva animation insert when we update an image in the tinymce editor and come back and are procesing the image update.
	 * canvadesignMediaWindowCallback - used in the media library when we edit a canva design
	 * canvaMceEditWindowCallback - used when we edit a canva design in the tinyMCE window.
	 * canvadesignEditCallback - used when we edit a design from the canva database window.
	 *
	 */
	function admin_head() {


		echo '<script type="text/javascript">
        window.canvadesignCallback = function (url, design_id) {
            if (typeof(url) == "undefined") url = $("#canvasrc").val();
            if (typeof(design_id) == "undefined") design_id = 0;
            var filename = jQuery("#canvanewfilenameid").val();
            var post_id = jQuery("#post_ID").val();
            jQuery("body").prepend("<div id=\'canvamask\'><div class=\'canva embed loading animation\'><img class=\'canva-embed loading-gif\' src=\'' . plugins_url( 'images/canva_logo_loading.gif',
				dirname( __FILE__ ) ) . '\' alt=\'loading canva...\' /></div></div>");
            jQuery.post(canva_ajax.url, {action: "canva_uploader_action", ajaxnonce : canva_ajax.ajaxnonce, canvaimageurl:url, canvadesignid:design_id, canvanewfilename:filename, post_id:post_id}, function(result) {
                jQuery("#canvamask").fadeOut(500, function() {
                    jQuery(this).remove();
                });
                 //alert("canvadesignCallback " + result);
                window.send_to_editor(result);
            });
        };

        /*
			this has been added so we can show the canva animation when we update an image in
			the tinymce editor
        */
		window.canvaAnimShow = function (){
			jQuery("body").prepend("<div id=\'canvamask\'><div class=\'canva embed loading animation\'><img class=\'canva-embed loading-gif\' src=\'' . plugins_url( 'images/canva_logo_loading.gif',
				dirname( __FILE__ ) ) . '\' alt=\'loading canva...\' /></div></div>");
				};

         window.canvadesignMediaWindowCallback = function (url, design_id) {
            if (typeof(url) == "undefined") url = $("#canvasrc").val();
            if (typeof(design_id) == "undefined") design_id = 0;
            var filename = jQuery("#canvanewfilenameid").val();
            var post_id = jQuery("#post_ID").val();
            jQuery("body").prepend("<div id=\'canvamask\'><div class=\'canva embed loading animation\'><img class=\'canva-embed loading-gif\' src=\'' . plugins_url( 'images/canva_logo_loading.gif',
				dirname( __FILE__ ) ) . '\' alt=\'loading canva...\' /></div></div>");
            jQuery.post(canva_ajax.url, {action: "canva_uploader_action", ajaxnonce : canva_ajax.ajaxnonce, canvaimageurl:url, canvadesignid:design_id, canvanewfilename:filename, post_id:post_id}, function(result) {
                jQuery("#canvamask").fadeOut(500, function() {
                    jQuery(this).remove();
                });
                wp.media.frame.content.get("gallery").collection.props.set({ignore: (+ new Date())});
            });
        };

        window.canvaMceEditWindowCallback = function (url, design_id) {
            if (typeof(url) == "undefined") url = $("#canvasrc").val();
            if (typeof(design_id) == "undefined") design_id = 0;
            var filename = jQuery("#canvanewfilenameid").val();
            jQuery("body").prepend("<div id=\'canvamask\'><div class=\'canva embed loading animation\'><img class=\'canva-embed loading-gif\' src=\'' . plugins_url( 'images/canva_logo_loading.gif',
				dirname( __FILE__ ) ) . '\' alt=\'loading canva...\' /></div></div>");
            jQuery.post(canva_ajax.url, {action: "canva_mce_edit_design_action", ajaxnonce : canva_ajax.ajaxnonce, canvaimageurl:url, canvadesignid:design_id, canvanewfilename:filename}, function(result) {
                jQuery("#canvamask").fadeOut(500, function() {
                    jQuery(this).remove();
                });
                window.send_to_editor(result);
            });
        };

         window.canvadesignEditCallback = function (url, design_id) {

           if (typeof(url) == "undefined") url = $("#canvasrc").val();
            if (typeof(design_id) == "undefined") design_id = 0;
            var filename = jQuery("#canvanewfilenameid").val();
            var post_id = jQuery("#post_ID").val();
            jQuery("body").prepend("<div id=\'canvamask\'><div class=\'canva embed loading animation\'><img class=\'canva-embed loading-gif\' src=\'' . plugins_url( 'images/canva_logo_loading.gif',
				dirname( __FILE__ ) ) . '\' alt=\'loading canva...\' /></div></div>");
            jQuery.post(ajaxurl, {action: "canva_edit_design_action", ajaxnonce : canva_ajax.ajaxnonce, canvaimageurl:url, canvadesignid:design_id, canvanewfilename:filename, post_id:post_id}, function(result) {
                jQuery("#canvamask").fadeOut(500, function() {
                    jQuery(this).remove();
                });
				// update the image timestamps to beat the caching
				jQuery(\'img[src*="\'+design_id+\'"]\').each(function(index, value) {
                    var item = jQuery(this).attr("src") + "?" + jQuery.now();
                    jQuery(this).attr("src", item);
				});

            });
        };
         </script>';
	}

	/**
	 * Return the canva API key from just one place
	 * as we had many places, and it is just neater this way
	 *
	 * @return string
	 */
	function canva_api_key() {

		$api_key = ( get_option( "canva_api_key" ) !== "" ) ? get_option( "canva_api_key" ) : "DBzS0qZ5pjEtYtdwoYVEeKbj";

		return $api_key;
	}

	/**
	 * every new canva design has a link attached to it,
	 *
	 * @return string
	 */
	function made_in_canva_link() {

		$URL              = 'http://canva.com';
		$wordpress_domain = site_url();
		$wordpress_domain = preg_replace( '#^https?://#', '', $wordpress_domain );

		$CANVA_API_KEY = $this->canva_api_key();

		$full_url = $URL . '/?utm_campaign=canva_wordpress_plugin&utm_source=' . $wordpress_domain . '-' . $CANVA_API_KEY;

		$final_link = '<span style="">Designed in <a href="' . $full_url . '">Canva</a></span>';

		return $final_link;
	}


	/**
	 * Ajax handler for new canva designs
	 * Canva will return a link to a picture that we need
	 * to save and then add to canva database and save in wordoress
	 * and create thumbnails and finally insert into post.
	 *
	 */
	function canva_uploader_action() {


		if ( $_POST['canvaimageurl'] ) {
			$imageurl    = $_POST['canvaimageurl'];
			$imageurl    = stripslashes( $imageurl );
			
			$canva_url = strstr($imageurl, $this->downloadUrl);
           	if($canva_url !== false) 
           	{
			
				$designId    = $_POST['canvadesignid'];
				$uploads     = wp_upload_dir();
				$post_id     = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
				$newfilename = $_POST['canvanewfilename'] . ".png";

				if ( $post_id == 0 ) {
					// Editing image - look for WP details on the designID
					$existing = new WP_Query( "post_type=attachment&meta_key=canva_designId&meta_value=$designId" );
					if ( $existing->have_posts() ) {
						// Delete existing and replace
						while ( $existing->have_posts() ) {
							$existing->the_post();
						}
					}
				}

				$filename         = wp_unique_filename( $uploads['path'], $newfilename, $unique_filename_callback = null );
				$fullpathfilename = $uploads['path'] . "/" . $filename;

				try {
					$image_string = file_get_contents( $imageurl, false );
					$fileSaved    = file_put_contents( $uploads['path'] . "/" . $filename, $image_string );
					if ( ! $fileSaved ) {
						throw new Exception( "The file cannot be saved." );
					}

					$attachment = array(
						'post_mime_type' => 'image/png',
						'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
						'post_content'   => '',
						'post_status'    => 'inherit',
						'guid'           => $uploads['url'] . "/" . $filename
					);
					$attach_id  = wp_insert_attachment( $attachment, $fullpathfilename, $post_id );
					if ( ! $attach_id ) {
						throw new Exception( "Failed to save record into database." );
					}
					require_once( ABSPATH . "wp-admin" . '/includes/image.php' );
					$attach_data = wp_generate_attachment_metadata( $attach_id, $fullpathfilename );

					wp_update_attachment_metadata( $attach_id, $attach_data );

					update_post_meta( $attach_id, 'canva_designId', $designId );

					$image_attributes = wp_get_attachment_image_src( $attach_id, 'full' );

					$saveData = array( 'postId' => $attach_id, 'canvaId' => $designId, 'image' => $image_attributes[0] );

					$this->canvaDatabase->insert_canva_row( $saveData );

					echo wp_get_attachment_image( $attach_id, 'full' ) . '<br>' . $this->made_in_canva_link();

				} catch ( Exception $e ) {
					$error = '<div id="message" class="error"><p>' . $e->getMessage() . '</p></div>';
					echo $error;
				}
			}
		}
		die;
	}

	/**
	 * Ajax handler for editing existing canva designs
	 *
	 * This is basically the same as the function canva_uploader_action,
	 * but the actions after the image has been processed are different
	 *
	 * Canva will return a link to a picture that we need
	 * to save and then add to canva database and save in wordoress
	 * and create thumbnails and finally insert into post.
	 *
	 */
	function canva_edit_design_action( $output_to_editor = false ) {

		if ( $_POST['canvaimageurl'] ) {
			$imageurl = $_POST['canvaimageurl'];
			$imageurl = stripslashes( $imageurl );
			
			$canva_url = strstr($imageurl, $this->downloadUrl);
           	if($canva_url !== false) 
           	{
			
				$designId = $_POST['canvadesignid'];
				$CanvaDesignData = $this->canvaDatabase->check_design_id_exists( $designId );
				if ( $CanvaDesignData !== false ) {
					// this should be true if we are editing
					$attach_id = $CanvaDesignData['postId'];
					// create temp file name just to store the file at first
					$temp_filename = tempnam( sys_get_temp_dir(), $_POST['canvadesignid'] );
					try {
						$image_string = file_get_contents( $imageurl, false );
						$fileSaved    = file_put_contents( $temp_filename, $image_string );
						if ( ! $fileSaved ) {
							throw new Exception( "The temp file cannot be saved." );
						}
						// path of old file
						$current_file = get_attached_file( $attach_id );

						// save original file permissions
						$original_file_perms = fileperms( $current_file ) & 0777;

						// at this moment we want to just delete the file not all the references
						unlink( $current_file );
						// Move new file to old location/name
						//move_uploaded_file($_FILES["userfile"]["tmp_name"], $current_file);
						$fileSaved = file_put_contents( $current_file, $image_string );
						if ( ! $fileSaved ) {
							throw new Exception( "The canva design cannot be saved." );
						}

						$saveData = array( 'canvaId' => $designId );
						//$this->log->lwrite("update_canva_row save data  ". var_dump($saveData) );
						$this->canvaDatabase->update_canva_row( $saveData );

						// Chmod new file to original file permissions
						chmod( $current_file, $original_file_perms );

						require_once( ABSPATH . "wp-admin" . '/includes/image.php' );

						$attach_data = wp_generate_attachment_metadata( $attach_id, $current_file );
						wp_update_attachment_metadata( $attach_id, $attach_data );
						update_post_meta( $attach_id, 'canva_designId', $designId );

						$image_attributes = wp_get_attachment_image_src( $attach_id );
						$return_filename = $image_attributes[0];
						$return_filename = $return_filename . '?' . filemtime( $current_file );

						if ( $output_to_editor ) {

							echo wp_get_attachment_image( $attach_id, 'full' ) . '<br>' . $this->made_in_canva_link();
						} else {
							echo $return_filename;
						}

					} catch ( Exception $e ) {
						$error = '<div id="message" class="error"><p>' . $e->getMessage() . '</p></div>';
						echo $error;
					}

				}
			}
		}
		die;
	}

	/**
	 * This is just a wrapper function to canva_edit_design_action
	 *
	 */
	function canva_mce_edit_design_action() {

		$this->canva_edit_design_action( true );
		die();
	}

	
	/**
	 * Used in the tinymce AJAX call, to determine if an attachment,
	 * is a canva opject
	 *
	 */
	function canva_get_design_id() {

		if ( wp_verify_nonce( $_POST['_ajax_nonce'], 'C4nv4' ) ) {
			$url = $_POST['metadata']['url'];
			preg_match( "/D([\w\-\_A-Za-z]*)/", $url, $output_array );
			if ( isset( $output_array[0] ) ) {
				$canvaData = $this->canvaDatabase->getCanvaElements( $output_array[0] );
				if ( isset( $canvaData[0] ) ) {
					//echo "Edit in Canva " . $columnID;
					echo $canvaData[0]['canvaId'];
				}
			}
		}
		die;
	}
	/**
	 * Add Canva button next to Media on HTML Editor in edit screen
	 *
	 * @return string
	 */
	function media_button() {

		$design_type = get_option( 'canva_design_type' );
		$api_key     = $this->canva_api_key();
		$context     = '<span class="canva-design-button" data-type="' . $design_type . '" data-apikey="' . $api_key . '" data-url-callback="canvadesignCallback" data-filename="canvanewfilenameid" data-thumbnail="canvaimagethumbnail" data-input="canvaimageurl" data-label="Design in Canva">Design in Canva</span>';

		$context .= '<div class="describe" style="visibility:hidden;height:0;">
                <input id="canvasrc" type="text" name="canvaimageurl">
                <img id="canvaimagethumbnail" />
                <input type="text" name="canvanewfilename" style="width:200px" id="canvanewfilenameid">
                <input type="button" id="action-download" />
                </div>';

		return $context;
	}
	
	
	/**
	 * Add Canva button next to Media on HTML Editor in edit screen
	 * 
	 * @param string $callback
	 *
	 * @return string
	 */
	function media_button_for_js( $callback = 'canvadesignCallback' ) {

		$design_type = get_option( 'canva_design_type' );
		$api_key     = $this->canva_api_key();
		$context     = '<span class="canva-design-button" data-type="' . $design_type . '" data-apikey="' . $api_key . '" data-url-callback="' . $callback . '" data-filename="canvanewfilenameid" data-thumbnail="canvaimagethumbnail" data-input="canvaimageurl" data-label="Design in Canva">Design in Canva</span>';

		return $context;
	}


	/**
	 * Scripts to add edit button to the editor
	 */
	function localize_tinymce_javascript() {

		$api_key = $this->canva_api_key();
		$output  = "<script type='text/javascript'>"
		           . "var canva_ajax = {};"
		           . "canva_ajax.url = '" . admin_url( 'admin-ajax.php' ) . "';"
		           . "canva_ajax.ajaxnonce = '" . wp_create_nonce( 'C4nv4' ) . "';"
		           . "canva_ajax.canvaapikey = '" . $api_key . "';"
		           . "canva_ajax.canvadesigntype = '" . get_option( 'canva_design_type' ) . "';"
		           . "canva_ajax.canvadesignbutton = '" . $this->media_button_for_js() . "';"
		           . "canva_ajax.canvadesignmediabutton = '" . $this->media_button_for_js( 'canvadesignMediaWindowCallback' ) . "';"
		           . "canva_ajax.canvamceeditwindowcallback = '" . $this->media_button_for_js( 'canvaMceEditWindowCallback' ) . "';"
		           . "</script>";
		echo $output;
	}

	/**
	 * @param $plugin_array
	 *
	 * @return mixed
	 */
	function register_tinymce_javascript( $plugin_array ) {
		$plugin_array['canva_tinymce4'] = CANVA_URL . '/js/canva_tinymce4.js';
		return $plugin_array;
	}
	
	/**
	 * Creates the Canva menu on the wordpress admin menu
	 *
	 */
	function add_config_page() {

		// works up to version 4.0
		//add_utility_page( 'Canva', 'Canva Settings', 'manage_options', 'canva_settings', array( $this, 'canva_settings_page' ), CANVA_URL . '/images/canva-icon_16x16.png' );
		//add_submenu_page( 'canva_settings', 'Canva Database', 'Canva Database', 8, 'canva_database', array( $this, 'canva_database_page' ) );
		
		// works in 4.5+ 
		add_menu_page( 'Canva', 'Canva', 'manage_options', 'canva_settings', array( $this, 'canva_settings_page' ), CANVA_URL . '/images/canva-icon_16x16.png' );
	    add_submenu_page('canva_settings', 'Canva Database', 'Canva Database', 'manage_options', 'sub-page', array( $this, 'canva_database_page' ));

	}

	
	/**
	 *  Function that will display the settings page
	 */
	function canva_settings_page() {

		include CANVA_PATH . '/screens/config.php';
	}

	/**
	 *
	 * Function that will display the canva databse page
	 *
	 */
	function canva_database_page() {

		$testListTable = new canvaDatabaseTableDisplay();

		$data = $this->canvaDatabase->getCanvaElements();

		$testListTable->setData( $data );

		$testListTable->prepare_items();

		?>
		<div class="wrap">

			<div id="icon-users" class="icon32"><br/></div>
			<div id="icon-options-general" class="icon32 icon32-posts-canva_menu"><h2>Canva <?php _e( "Image", 'canva' ) ?> <?php _e( "Database", 'canva' ) ?></h2></div>

			<!-- Forms are NOT created automatically, so you need to wrap the table in one to use features like bulk actions -->
			<form id="movies-filter" method="get">
				<!-- For plugins, we also need to ensure that the form posts back to our current page -->
				<input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
				<!-- Now we can render the completed list table -->
				<?php $testListTable->display() ?>
			</form>

		</div>
	<?php

	}
}


new Canva_Media();
