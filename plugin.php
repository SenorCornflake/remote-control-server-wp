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
		$body = $request->get_body_params();

		if ( ! array_key_exists( "request_type", $body ) || ! array_key_exists( "identifier", $body ) ) {
			$response = new WP_REST_Response([ "message" => 'Specify request type and identifier' ]);
			$response->set_status(200);
			return $response;
		}

		$request_type = $body["request_type"];
		$identifier = $body["identifier"];

		if ( $request_type == "add" ) {
			if ( ! array_key_exists( "commands", $body ) ) {
				$response = new WP_REST_Response([ "message" => 'Specifiy commands to add' ]);
				$response->set_status(200);
				return $response;
			}

			$commands = ( get_option("rcs_" . $identifier) ) ? get_option("rcs_" . $identifier) : [];
			$new_commands = $body["commands"];

			$commands = array_merge( $commands, $new_commands);
			update_option( "rcs_" . $identifier, $commands );

			$response = new WP_REST_Response(["identifier" => $identifier, "commands" => $commands]);
			$response->set_status(200);
			return $response;
		} elseif ( $request_type == "get" ) {
			$commands = get_option("rcs_" . $identifier);

			if ( $commands ) {
				$response = new WP_REST_Response(["identifier" => $identifier, "commands" => $commands]);
				$response->set_status(200);
				return $response;
			} else {
				$response = new WP_REST_Response(["identifier" => $body["identifier"], "commands" => []]);
				$response->set_status(200);
				return $response;
			}
		} elseif ( $request_type == "remove" ) {
			if ( array_key_exists( "commands", $body ) == 0 ) {
				$response = new WP_REST_Response([ "message" => 'Specifiy commands (by index number)' ]);
				$response->set_status(200);
				return $response;
			}

			$commands = get_option("rcs_" . $identifier);
			$indexes_to_remove = $body["commands"];

			if ( $commands && ! empty( $commands ) ) {
				foreach ($indexes_to_remove as $i) {
					array_splice($commands, $i, 1);
				}
				update_option( "rcs_" . $body["identifier"], $commands );
				$response = new WP_REST_Response([ "identifier" => $identifier, "commands" => $commands ]);
				$response->set_status(200);
				return $response;
			} else {
				$response = new WP_REST_Response([ "identifier" => $identifier, "commands" => [] ]);
				$response->set_status(200);
				return $response;
			}
		}

		$response = new WP_REST_Response([ "message" =>  "Unhandled exception"]);
		$response->set_status(200);
		return $response;
	}

	public function register_route() {
		register_rest_route("rcs/v1", "/route", [
			"methods" => "POST",
			"callback" => [ $this, "process_requests" ]
		]);
	}
}

new RCS;
