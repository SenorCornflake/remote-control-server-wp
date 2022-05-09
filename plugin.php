<?php

/*
* Plugin Name: Remote Control Server
* Description: Use this wordpress site as a medium for two computers to communicate remotely
* Author: Baker
* */

// Which computer the command is for
// An authorization code for the computer

// Computer 1 sends command to server, hey this is for Computer 2, this is his identifier
// Server saves command, marks as unexecuted for this identifier
// Computer 2 polls server, are there any unexecuted commands for my identifier?
// Server: yes there is, here this is it, tell me when you're done with it
// Computer 2: Hey server, i've run the command, this is the exit status
// Computer 1: Hey server, did Computer 2 execute my command yet?
// Server: Yes he did, this is the execute code


// STATUS CODES
// 0 success
// 1 unknown request type
// 2 missing request type
// 3 request type demands another argument which is absent
// 4 missing identifier
// 5 Unhandled exception
// 6 Invalid type
// 7 Invalid index

class RCS {
	public function __construct() {
		add_action("admin_menu", function() {
			add_menu_page(
				"Remote Control Settings",
				"Remote Control Settings",
				"manage_options",
				"rcs_settings",
				function () {
					require_once plugin_dir_path( __FILE__ ) . "/partials/settings.php";
				}
			);
		});
		
		add_action("rest_api_init", function() {
			register_rest_route("rcs/v1", "/route", [
				"methods" => "POST",
				"callback" => [ $this, "process_requests" ]
			]);
		});
	}

	public function process_requests( $request ) {
		function response($args) {
			$response = new WP_REST_Response( $args );
			$response->set_status( 200 );
			return $response;
		}

		$body = $request->get_body_params();

		if ( ! array_key_exists( "request_type", $body ) ) {
			return response([
				"message" => 'Provide a request type',
				"code" => 2
			]);
		} elseif ( ! array_key_exists( "identifier", $body ) ) {
			return response([
				"message" => 'Provide an identifier',
				"code" => 4
			]);
		}

		$request_type = $body["request_type"];
		$identifier = $body["identifier"];

		if ( $request_type == "set" ) {
			if ( ! array_key_exists( "commands", $body ) ) {
				return response([
					"message" => 'Provide commands to set',
					"code" => 3
				]);
			}

			$commands = get_option( "rcs_" . $identifier );
			$commands = ( $commands ) ? $commands : [];
			$new_commands = $body["commands"];

			$commands = array_merge( $commands, $new_commands );
			update_option( "rcs_" . $identifier, $commands );

			return response([
				"identifier" => $identifier,
				"commands" => $commands,
				"code" => 0,
			]);
		} elseif ( $request_type == "get" ) {
			$commands = get_option("rcs_" . $identifier);

			if ( $commands ) {
				return response([
					"identifier" => $identifier,
				   	"commands" => $commands,
					"code" => 0,
				]);
			} else {
				return response([
					"identifier" => $identifier,
				   	"commands" => [],
					"code" => 0,
				]);
			}
		} elseif ( $request_type == "remove" ) {
			if ( array_key_exists( "commands", $body ) == 0 ) {
				return response([
					"message" => 'Specifiy commands (by index number)',
					"code" => 3
				]);
			} else {
				foreach ( $body["commands"] as $v) {
					if ( intval( $v ) == 0 && $v != "0" ) {
						return response([
							"message" => "Value \"$v\" cannot be coerced into an integer",
							"code" => 6
						]);
					}
				}
			}

			$commands = get_option("rcs_" . $identifier);
			$indexes_to_remove = $body["commands"];

			if ( $commands && ! empty( $commands ) ) {
				foreach ( $indexes_to_remove as $i ) {
					if ( ! isset( $commands[ $i ] ) ) {
						return response([
							"message" => "Array key $i does not exist",
							"code" => 7
						]);
					}
				}

				foreach ($indexes_to_remove as $i) {
					array_splice($commands, $i, 1);
				}

				update_option( "rcs_" . $body["identifier"], $commands );
				return response([ 
					"identifier" => $identifier,
					"commands" => [],
					"code" => 0
				]);
			} else {
				return response([
					"message" => "Commands are empty",
					"code" => 7
				]);
			}
		} else {
			return response([
				"message" =>  "Unknown request type",
				"code" => 1
			]);
		}

		return response([
			"message" =>  "Unhandled exception",
			"code" => 5
		]);
	}

	public function register_route() {
		register_rest_route("rcs/v1", "/route", [
			"methods" => "POST",
			"callback" => [ $this, "process_requests" ]
		]);
	}
}

new RCS;
