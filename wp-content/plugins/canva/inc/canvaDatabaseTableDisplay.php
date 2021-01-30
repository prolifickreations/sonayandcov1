<?php

/**
 *  This file is canva's use of
 *
 * https://wordpress.org/plugins/custom-list-table-example/
 *
 * it is a really good example of how to make use of wp-list-table class.
 *
 * Canva and Justin King cannot take any credit for this code
 *
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}


class canvaDatabaseTableDisplay extends WP_List_Table {


	public $data;  // rows of data like the lines below.

	/**************************************************************************
	 * REQUIRED. Set up a constructor that references the parent constructor. We
	 * use the parent reference to set some default configs.
	 ***************************************************************************/
	function __construct() {

		global $status, $page;

		//Set parent defaults
		parent::__construct( array(
			'singular' => 'canvaImage',     //singular name of the listed records
			'plural'   => 'canvaImages',    //plural name of the listed records
			'ajax'     => false        //does this table support ajax?
		) );
	}

	/**
	 * @return mixed
	 */
	public function getData() {

		if ( is_array( $this->data ) ) {
			return $this->data;
		} else {
			return null;
		}
	}

	/**
	 * @param mixed $data
	 */
	public function setData( $data ) {

		if ( is_array( $data ) ) {
			$this->data = $data;
		}
	}


	/** ************************************************************************
	 * @param array $item A singular item (one full row's worth of data)
	 * @param array $column_name The name/slug of the column to be processed
	 *
	 * @return string Text or HTML to be placed inside the column <td>
	 **************************************************************************/
	function column_default( $item, $column_name ) {

		switch ( $column_name ) {
			case 'created_on':
			case 'last_edited':
				return $item[ $column_name ];
				break;
			case 'image':
				$image_small = str_replace( '.png', '-150x150.png', $item[ $column_name ] );

				return '<img id="' . $item['canvaId'] . '" src="' . $image_small . '" class="canva-150-image attachment-80x60">';
			default:
				return print_r( $item, true ); //Show the whole array for troubleshooting purposes
		}
	}


	/**************************************************************************
	 *
	 * @see WP_List_Table::::single_row_columns()
	 *
	 * @param array $item A singular item (one full row's worth of data)
	 *
	 * @return string Text to be placed inside the column <td> (movie title only)
	 **************************************************************************/

	function column_canvaId( $item ) {

		//Build row actions
		$actions = array(
			'edit' => $this->create_canva_edit_button( $item['canvaId'] ),
		);

		//Return the title contents
		return sprintf( '%1$s <span style="color:silver"></span>%2$s',
			/*$1%s*/
			$item['canvaId'],

			/*$3%s*/
			$this->row_actions( $actions )
		);
	}

	function column_image( $item ) {

		//Build row actions
		$actions     = array(
			'edit' => $this->create_canva_edit_button( $item['canvaId'] ),
		);
		$image_small = $item['image'];


		//Return the title contents
		return sprintf( '%1$s <span style="color:silver"></span>%2$s',
			/*$1%s*/
			'<img id="' . $item['canvaId'] . '" src="' . $image_small . '" class="canva-150-image attachment-80x60">',
			/*$2%s*/
			$this->row_actions( $actions )
		);
	}

	function create_canva_edit_button( $id ) {

		// this could be nicer...
		$api_key = ( get_option( "canva_api_key" ) !== "" ) ? get_option( "canva_api_key" ) : "DBzS0qZ5pjEtYtdwoYVEeKbj";

		return '<span class="canva-design-button" data-design-id="' . trim( $id ) . '" data-label="Edit" data-apikey="' . $api_key . '" data-url-callback="canvadesignEditCallback">Edit in Canva</span>';
	}


	/** ************************************************************************
	 * @see WP_List_Table::::single_row_columns()
	 * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
	 **************************************************************************/
	function get_columns() {

		$columns = array(
			//'cb'          => '<input type="checkbox" />', //Render a checkbox instead of text
			'image'       => 'Image',
			//'canvaId'     => 'Canva Design ID',
			'created_on'  => 'Created',
			'last_edited' => 'Last Edited',
			//'postId'      => 'Wordpress Post ID'
		);

		return $columns;
	}

	/** ************************************************************************
	 * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
	 **************************************************************************/
	function get_sortable_columns() {

		$sortable_columns = array(
			//'canvaId'    => array( 'canvaId', false ),     //true means it's already sorted
			//'postId'     => array( 'postId', false ),
			'created_on'  => array( 'created_on', false ),
			'last_edited' => array( 'last_edited', false ),
			'image'       => array( 'image', false )
		);

		return $sortable_columns;
	}


	/**************************************************************************
	 *
	 * @global WPDB $wpdb
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 **************************************************************************/
	function prepare_items() {

		/**
		 * First, lets decide how many records per page to show
		 */
		$per_page = 10;

		$columns  = $this->get_columns();
		$hidden   = array( 'ID', '' );
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$data = $this->getData();

		/**
		 * This checks for sorting input and sorts the data in our array accordingly.
		 *
		 */
		function usort_reorder( $a, $b ) {

			$orderby = ( ! empty( $_REQUEST['orderby'] ) ) ? $_REQUEST['orderby'] : 'canvaId'; //If no sort, default to title
			$order   = ( ! empty( $_REQUEST['order'] ) ) ? $_REQUEST['order'] : 'asc'; //If no order, default to asc
			$result  = strcmp( $a[ $orderby ], $b[ $orderby ] ); //Determine sort order
			return ( $order === 'asc' ) ? $result : - $result; //Send final sort direction to usort
		}

		usort( $data, 'usort_reorder' );

		$current_page = $this->get_pagenum();

		$total_items = count( $data );

		$data = array_slice( $data, ( ( $current_page - 1 ) * $per_page ), $per_page );

		$this->items = $data;

		$this->set_pagination_args( array(
			'total_items' => $total_items,                  //WE have to calculate the total number of items
			'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
			'total_pages' => ceil( $total_items / $per_page )   //WE have to calculate the total number of pages
		) );
	}

}

