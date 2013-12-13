<?php

/**
 *
 * @author Karol Danutama <karol.danutama@gdpventure.com>
 */
class Kaspay_user_api_client extends Kaspay_api_client {

	const URL_LINK = 'user/link/%s';

	const URL_UNLINK = 'user/unlink/%s/%s';

	/**
	 * 
	 * @param string $merchant_uaccount A uaccount associated with the merchant (API client)
	 * @param string $approve_url A complete URL for Kaspay to redirect the user after user approval.
	 * @param string $approve_url A complete URL for Kaspay to redirect the user after user rejection.
	 * @return object(id,confirmation_url) id:attempt id, confirmation_url: redirect the user to this page for approval
	 */
	public function link($merchant_uaccount, $approve_url, $reject_url)
	{
		$full_url = sprintf($this->base_url . self::URL_LINK, $merchant_uaccount);
		$this->send_request('POST', $full_url, json_encode(array(
			'approve_url' => $approve_url,
			'reject_url' => $reject_url
		)));
	}

	/**
	 * 
	 * @param string $uaccount A uaccount associated with a user
	 * @param string $merchant_uaccount A uaccount associated with the merchant (API client)
	 * @return object(uaccount,message) uaccount:unlinked uaccount, message: info message
	 */
	public function unlink($uaccount, $merchant_uaccount)
	{
		$full_url = sprintf($this->base_url . self::URL_UNLINK, $uaccount, $merchant_uaccount);
		$this->send_request('POST', $full_url, json_encode(array()));
	}

}
