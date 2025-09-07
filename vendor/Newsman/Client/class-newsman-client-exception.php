<?php
/* @codingStandardsIgnoreStart */
class Newsman_Client_Exception extends Exception {
	public function __construct( $message, $code = 500 ) {
		parent::__construct( $message, $code );
	}
}
/* @codingStandardsIgnoreEnd */
