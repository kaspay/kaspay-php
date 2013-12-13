<?php
/**
 * To unite the 5 parts of Kaspay API call
 */
class Kaspay_api_parameter {

	/**
	 * @var string GET/POST/PUT/DELETE
	 */
	protected $verb;
	/**
	 * @var string Full URL
	 */
	protected $url;
	/**
	 * @var string Merchant's identifier
	 */
	protected $uaccount;
	/**
	 * @var int UNIX timestamp
	 */
	protected $timestamp;
	/**
	 * @var string binary string
	 */
	protected $data;
	
	public function __construct($verb, $url, $uaccount, $timestamp, $data)
	{
		$this->verb = $verb;
		$this->url = $url;
		$this->uaccount = $uaccount;
		$this->timestamp = $timestamp;
		$this->data = $data;
	}

	public function get_verb()
	{
		return $this->verb;
	}

	public function get_url()
	{
		return $this->url;
	}

	public function get_uaccount()
	{
		return $this->uaccount;
	}

	public function get_timestamp()
	{
		return $this->timestamp;
	}

	public function get_data()
	{
		return $this->data;
	}
}