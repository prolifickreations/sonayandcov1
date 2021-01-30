<?php
/**
 * User: justin King (http://getafixx.com)
 * Date: 29/10/2014
 *
 */


/**
 * Class canvaDatabase
 *
 * A class for storing information on canva designs
 *
 */
class canvaDatabase {


	/**
	 * Set the table name being used for the canva database
	 *
	 * @return string
	 */
	function table_name() {

		global $wpdb;

		$table_name = get_option( 'canva_database_table' );
		if ( isset( $table_name ) && ! is_null( $table_name ) ) {
			$canva_database_table = $wpdb->prefix . $table_name;
		} else {
			$canva_database_table = $wpdb->prefix . 'canva_images';
			update_option( 'canva_database_table', 'canva_images' );
		}

		return $canva_database_table;
	}

	/**
	 * This will query the database and return an array of canva database items or
	 * a single item.
	 *
	 * If no id is used then it will return all elements
	 * if an id is used and $elem is also set, it tries to find a single row
	 * using id and $elem
	 *
	 * @return mixed
	 */
	function getCanvaElements( $id = null, $elem = 'canvaId' ) {

		global $wpdb;

		$table_name = $this->table_name();

		$where = '';
		if ( isset( $id ) and ! is_null( $id ) ) {
			$where = "WHERE `" . $elem . "` = '" . trim( $id ) . "' LIMIT 1";
		}

		$query = 'SELECT * FROM ' . $table_name . ' ' . $where;

		$CanvaElements = $wpdb->get_results( $query, ARRAY_A );

		return $CanvaElements;

	}

	/**
	 * Checks that the given canva design id exists as a row in
	 * the canva database.
	 *
	 * @param $canvaId
	 *
	 * @return bool
	 */
	function check_design_id_exists( $canvaId ) {

		$results = $this->getCanvaElements( $canvaId, 'canvaId' );

		if ( isset( $results[0] ) ) {
			return $results[0];
		} else {
			return false;
		}
	}

	/**
	 * For inserting timestamps into database
	 *
	 * @return bool|string
	 */
	function date_time() {

		return date( "Y-m-d H:i:s" );
	}

	/**
	 * Insert a row into the canva database.
	 *
	 * @param $data
	 */
	function insert_canva_row( $data ) {

		global $wpdb;

		$table_name = $this->table_name();

		if ( isset( $data['canvaId'] ) ) {
			$row_exists = $this->check_design_id_exists( $data['canvaId'] );

			if ( $row_exists === false ) {

				$insert_data = array(
					'canvaId'     => $data['canvaId'],
					'postId'      => $data['postId'],
					'created_on'  => $this->date_time(),
					'last_edited' => '0000-00-00 00:00:00',
					'image'       => $data['image']
				);

				$format = array( '%s', '%d', '%s', '%s', '%s' );

				$wpdb->insert( $table_name, $insert_data, $format );
			}
		}


	}


	/**
	 * Update a row into the canva database.
	 *
	 * @param $data
	 *
	 * @return null
	 */
	function update_canva_row( $data ) {

		global $wpdb;
		$table_name = $this->table_name();

		if ( isset( $data['canvaId'] ) ) {
			$row_exists = $this->check_design_id_exists( $data['canvaId'] );

			if ( isset( $row_exists ) ) {

				$row = $row_exists;

				// basically jsut update teh last edited time!
				$insert_data = array(
					'last_edited' => $this->date_time()
				);
				$format      = array( '%s' );

				$where = array( 'canvaId' => $row['canvaId'] );

				$where_format = array( '%s' );

				$wpdb->update( $table_name, $insert_data, $where, $format, $where_format );

				return $wpdb->last_error;
			}
		}

		return null;
	}
}
