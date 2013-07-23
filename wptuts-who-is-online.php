<?php                                                                                                                                                                                          
/*
Plugin Name: Who Is Online?
Version: 0.1
Description: A simple plug-in to alert users when other users log-in and out of WordPress. Demonstarting the Heartbeat API
Plugin URI: http://stephenharris.info
Author: Stephen Harris
Author URI:  http://stephenharris.info
License: GPLv3
*/
/*  Copyright 2013 Stephen Harris (contact@stephenharris.info)
 
I ask you retain this copyright notice unaltered.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/
/**
 * This plug-in is (only) intended for demonstrating the Heartbeat API and requires WordPress 3.6+
 */

/**
 * Update current user status as offline
*/
function whoisonline_logged_out(){
	$user_id = get_current_user_id();
	update_user_meta( $user_id, 'whoisonline_is_online', false );
}
add_action('wp_logout', 'whoisonline_logged_out');

/**
 * Update current user status as online
 * Update last active timestamp
*/
function whoisonline_logged_in( $username, $user ){
	update_user_meta( $user->ID, 'whoisonline_is_online', true );
	update_user_meta( $user->ID, 'whoisonline_last_active', time() );
}
add_action('wp_login', 'whoisonline_logged_in', 10, 2);


/**
 * Loads scripts & styles on front and back-end if the user is logged in
 */
function whoisonline_load_scripts() {

	/* Ony load scripts when you need to - in this case, everywhere if the user is logged-in */
	if( is_user_logged_in() ){
		wp_enqueue_script( 'whoisonline-jquery-notice', plugin_dir_url( __FILE__ ) . 'jquery.notice.js', array( 'jquery' ) );	
		wp_enqueue_style( 'whoisonline-jquery-notice', plugin_dir_url( __FILE__ ) . 'jquery.notice.css' );	
	        wp_enqueue_script( 'whoisonline',  plugin_dir_url( __FILE__ ) . 'who-is-online.js', array( 'heartbeat', 'whoisonline-jquery-notice' ) );	
	}
}
add_action( 'admin_enqueue_scripts', 'whoisonline_load_scripts' );
add_action( 'wp_enqueue_scripts', 'whoisonline_load_scripts' );


/**
 * Returns an array of usernames indexed by user IDs of the users who are currently online
 * @param array $args Optional array to adapt query
 * @return array
*/
function who_is_online( $args = array() ) {

	  //Get users active in last 24 hours
        $args = wp_parse_args( $args, array(
            'meta_key' => 'whoisonline_last_active',
            'meta_value' => time() - 24*60*60, 
            'meta_compare' => '>',
            'count_total' => false,
        ));
        $users = get_users( $args );
    
        //Initiate array
        $online_users = array();
    
        foreach( $users as $user ) {
            if( ! get_user_meta( $user->ID, 'whoisonline_is_online', true ) )
                 continue;
    
            $online_users[$user->ID] = $user->user_login;
	}

	return $online_users;
}

/**
 * Hearbeat server-side handling. Hooked onto 'heartbeat_received'
 * Checks first that the data from the browser is requesting "who's online"
 * If so, returns an array of user names (indexed by ID)
*/
function whoisonline_check_who_is_online( $response, $data, $screen_id) {

	//Update user's activity
	$user_id = get_current_user_id();
	update_user_meta( $user_id, 'whoisonline_last_active', time() );
	update_user_meta( $user_id, 'whoisonline_is_online', true );

	if( !empty( $data['who-is-online'] ) ){

		//Attach data to be sent
		$response['whoisonline'] = who_is_online();

	}
	return $response;
}
add_filter( 'heartbeat_received', 'whoisonline_check_who_is_online', 10, 3 );
