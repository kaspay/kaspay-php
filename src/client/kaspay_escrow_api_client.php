<?php
/**
 * Kaspay API Client: Escrow
 */
class Kaspay_escrow_api_client extends Kaspay_api_client {

	const URL_HOLD = 'escrow/hold/%s';
	const URL_RELEASE = 'escrow/release/%s';
	const URL_REFUND = 'escrow/refund/%s';
	const URL_STATUS = 'escrow/status/%s';
	
	/**
	 * @param  string $trxid  Perform HOLD operation on this transaction
	 * @return object(status) status: TRUE
	 */
	public function perform_hold($trxid)
	{
		$full_url = sprintf($this->base_url . self::URL_HOLD, $trxid);
		$this->send_request('POST', $full_url, json_encode(array()));
	}
	
	/**
	 * @param  string $trxid  Perform RELEASE operation on this transaction
	 * @return object(status) status: TRUE
	 */
	public function perform_release($trxid)
	{
		$full_url = sprintf($this->base_url . self::URL_RELEASE, $trxid);
		$this->send_request('POST', $full_url, json_encode(array()));
	}
	
	/**
	 * @param  string $trxid  Perform REFUND operation on this transaction
	 * @return object(status) status: TRUE
	 */
	public function perform_refund($trxid)
	{
		$full_url = sprintf($this->base_url . self::URL_REFUND, $trxid);
		$this->send_request('POST', $full_url, json_encode(array()));
	}

	/**
	 * @param  string $trxid  Retrieve STATUS of this transaction's escrow
	 * @return object(status, escrow_status) status: TRUE, escrow_status: Active|Suspended|Released|Refunded
	 */
	public function check_status($trxid)
	{
		$full_url = sprintf($this->base_url . self::URL_STATUS, $trxid);
		$this->send_request('GET', $full_url, json_encode(array()));
	}
}