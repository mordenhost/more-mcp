<?php

namespace Mordenhost_MCP\MCP;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Pending_Exception extends Refused_Exception {

	private $result;

	public function __construct( string $message, array $result ) {
		parent::__construct( $message );
		$this->result = $result;
	}

	public function result(): array {
		return $this->result;
	}
}
