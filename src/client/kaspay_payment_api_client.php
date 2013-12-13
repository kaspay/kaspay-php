<?php

/**
 *
 * @author Karol Danutama <karol.danutama@gdpventure.com>
 */
class Kaspay_payment_api_client extends Kaspay_api_client {
	
	const URL_CREATE = "payment/create";
	
	const URL_EXECUTE = "payment/execute/%s";
	
	const URL_REFUND = "payment/refund/%s";
	
	const URL_CANCEL = "payment/cancel/%s";
	
	/**
	 * 
	 * @param stdClass $payment_attempt
	 */
	public function create($payment_attempt)
	{
		$full_url = sprintf($this->base_url . self::URL_CREATE);
		$this->send_request("POST", $full_url, json_encode($payment_attempt));
	}
	
	/**
	 * 
	 * @param string $payment_attempt_id
	 */
	public function execute($payment_attempt_id)
	{
		$full_url = sprintf($this->base_url . self::URL_EXECUTE, $payment_attempt_id);
		$this->send_request("POST", $full_url, json_encode(array()));
	}
	
	/**
	 * 
	 * @param string $payment_attempt_id
	 */
	public function refund($payment_attempt_id)
	{
		$full_url = sprintf($this->base_url . self::URL_REFUND, $payment_attempt_id);
		$this->send_request("POST", $full_url, json_encode(array()));
	}
	
	/**
	 * 
	 * @param string $payment_attempt_id
	 */
	public function cancel($payment_attempt_id)
	{
		$full_url = sprintf($this->base_url . self::URL_CANCEL, $payment_attempt_id);
		$this->send_request("POST", $full_url, json_encode(array()));
	}
	
}
