<?php
namespace Slack_Interface;

use Requests;


class Slack {

	private static $api_root = 'https://slack.com/api/';

	
	private $access;

	
	private $slash_commands;

	
	public function __construct( $access_data ) {
		if ( $access_data ) {
			$this->access = new Slack_Access( $access_data );
		}

		$this->slash_commands = array();
	}

	
	public function is_authenticated() {
		return isset( $this->access ) && $this->access->is_configured();
	}

	
	public function do_oauth( $code ) {
		
		$headers = array( 'Accept' => 'application/json' );

		
		$options = array( 'auth' => array( $this->get_client_id(), $this->get_client_secret() ) );

		
		$data = array( 'code' => $code );

		$response = Requests::post( self::$api_root . 'oauth.access', $headers, $data, $options );

		
		$json_response = json_decode( $response->body );

		if ( ! $json_response->ok ) {
			
			throw new Slack_API_Exception( $json_response->error );
		}

		
		$this->access = new Slack_Access(
			array(
				'access_token' => $json_response->access_token,
				'scope' => explode( ',', $json_response->scope ),
				'team_name' => $json_response->team_name,
				'team_id' => $json_response->team_id,
				'incoming_webhook' => $json_response->incoming_webhook
			)
		);

		return $this->access;
	}

	
	public function send_notification( $text, $attachments = array() ) {
		if ( ! $this->is_authenticated() ) {
			throw new Slack_API_Exception( 'Access token not specified' );
		}

		$headers = array( 'Accept' => 'application/json' );

		$url = $this->access->get_incoming_webhook();
		$data = json_encode(
			array(
				'text' => $text,
				'attachments' => $attachments,
				'channel' => $this->access->get_incoming_webhook_channel()
			)
		);

		$response = Requests::post( $url, $headers, $data );

		if ( $response->body != 'ok' ) {
			throw new Slack_API_Exception( 'There was an error when posting to Slack' );
		}
	}

	
	public function register_slash_command( $command, $callback ) {
		$this->slash_commands[$command] = $callback;
	}

	
	public function do_slash_command() {
		
		$token      = isset( $_POST['token'] ) ? $_POST['token'] : '';
		$command    = isset( $_POST['command'] ) ? $_POST['command'] : '';
		$text       = isset( $_POST['text'] ) ? $_POST['text'] : '';
		$user_name  = isset( $_POST['user_name'] ) ? $_POST['user_name'] : '';

		
		if ( ! empty( $token ) && $this->get_command_token() == $_POST['token'] ) {
			header( 'Content-Type: application/json' );

			if ( isset( $this->slash_commands[$command] ) ) {
				
				$response = call_user_func( $this->slash_commands[$command], $text, $user_name );
				echo json_encode( $response );
			} else {
				
				echo json_encode( array(
					'text' => "Sorry, I don't know how to respond to the command."
				) );
			}
		} else {
			echo json_encode( array(
				'text' => 'Oops... Something went wrong.'
			) );
		}

		
		exit;
	}

	
	public function get_client_id() {
		
		if ( defined( 'SLACK_CLIENT_ID' ) ) {
			return SLACK_CLIENT_ID;
		}

		
		if ( getenv( 'SLACK_CLIENT_ID' ) ) {
			return getenv( 'SLACK_CLIENT_ID' );
		}

		
		return '';
	}

	
	private function get_client_secret() {
		
		if ( defined( 'SLACK_CLIENT_SECRET' ) ) {
			return SLACK_CLIENT_SECRET;
		}

		
		if ( getenv( 'SLACK_CLIENT_SECRET' ) ) {
			return getenv( 'SLACK_CLIENT_SECRET' );
		}

	
		return '';
	}

	private function get_command_token() {
		
		if ( defined( 'SLACK_COMMAND_TOKEN' ) ) {
			return SLACK_COMMAND_TOKEN;
		}

		if ( getenv( 'SLACK_COMMAND_TOKEN' ) ) {
			return getenv( 'SLACK_COMMAND_TOKEN' );
		}

		
		return '';
	}

}