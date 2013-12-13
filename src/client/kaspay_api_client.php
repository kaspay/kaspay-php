<?php
/**
 * Kaspay API Client: Base Object
 * @uses cURL
 * @uses Kaspay_api_call, Kaspay_api_cryptor_exception
 */
class Kaspay_api_client {

	const BASE_URL = 'https://www.kaspay.com/api/v1/';
	const DEV_BASE_URL = 'http://kaspay17.dnsd.me/kaspay17/api/v1/';
	const API_VERSION = 1;
	const TIMEOUT = 30;

	const PARAM_UACCOUNT = 'uaccount';
	const PARAM_TIMESTAMP = 'timestamp';
	const PARAM_DATA = 'data';
	const KEY_ERROR_MESSAGE = 'error';

	const NO_ERROR = 0;
	const ERROR_CURL = 1;
	const ERROR_RESPONSE = 2;
	const ERROR_DECRYPTION = 3;
	const ERROR_JSON = 4;

	const DEFAULT_CERTIFICATE = 'Kaspay.com.crt';

	/**
	 * @var string  Prefix of all API URL
	 */
	protected $base_url;
	/**
	 * @var string
	 */
	protected $uaccount;
	/**
	 * @var string  Encryption key in binary
	 */
	protected $enc_key;
	/**
	 * @var string  MAC key in binary
	 */
	protected $mac_key;
	/**
	 * @var Kaspay_api_call
	 */
	protected $api_call;
	/**
	 * @var boolean  Should cURL verify SSL certificate?
	 */
	protected $verify_ssl = TRUE;
	/**
	 * @var string  Location of certificate file, default to CURRENT_DIR/Kaspay.com.crt
	 */
	protected $ssl_certificate = '';

	protected $last_error_code;
	protected $last_error = '';
	protected $last_response = '';
	protected $last_raw_response = '';
	protected $last_http_code = '';
	
	public function __construct($uaccount, $enc_key, $mac_key, $is_base64 = TRUE)
	{
		$this->base_url = self::BASE_URL;
		$this->uaccount = $uaccount;
		if ($is_base64)
		{
			$enc_key = base64_decode($enc_key);
			$mac_key = base64_decode($mac_key);
		}
		$this->enc_key = $enc_key;
		$this->mac_key = $mac_key;
		$this->api_call = new Kaspay_api_call();
		$this->ssl_certificate = __DIR__ . '/' . self::DEFAULT_CERTIFICATE;
	}

	// FOR TESTING PURPOSE ONLY
	public function set_base_url($base_url)
	{
		$this->base_url = rtrim($base_url, '/') . '/';
	}
	public function set_api_call($api_call)
	{
		$this->api_call = $api_call;
	}

	/**
	 * Set certificate file location (must be absolute path)
	 * @param string $file  Empty (null/false/'') means don't verify
	 */
	public function set_certificate($file)
	{
		if (empty($file))
		{
			$this->verify_ssl = FALSE;
		}
		else
		{
			$this->verify_ssl = TRUE;
			$this->ssl_certificate = $file;
		}
	}

	/**
	 * Build the required format and send
	 * @param  string $verb GET/POST/PUT/DELETE
	 * @param  string $url  Kaspay API URL
	 * @param  string $data Plaintext data to send
	 * @return string       Response from server or curl error
	 */
	public function send_request($verb, $url, $data)
	{
		$timestamp = time();
		$this->api_call->set_parameters($this->enc_key, $this->mac_key, 
			$verb, $url, $this->uaccount, $timestamp);
		$encrypted = $this->api_call->encrypt_request($data);
		
		$params = array(
			self::PARAM_UACCOUNT => $this->uaccount,
			self::PARAM_TIMESTAMP => $timestamp,
			self::PARAM_DATA => base64_encode($encrypted),
		);

		$this->send_via_curl($verb, $url, $params);
	}

	protected function process_response($http_code, $response, $curl_error)
	{
		$this->last_raw_response = $response;
		$this->last_response = json_decode($response);

		if ($response === FALSE)
		{
			$this->last_error_code = self::ERROR_CURL;
			$this->last_error = $curl_error;
			$this->last_http_code = 0;
		}
		else if ($this->last_response === NULL)
		{
			$this->last_error_code = self::ERROR_JSON;
			$this->last_error = 'JSON Parse Error';
			$this->last_http_code = $http_code;
		}
		else if ($http_code != Kaspay_api_call::STATUS_OK)
		{
			$this->last_error_code = self::ERROR_RESPONSE;
			$this->last_error = $this->last_response->{self::KEY_ERROR_MESSAGE};
			$this->last_http_code = $http_code;
		}
		else
		{
			try
			{
				$encrypted = base64_decode(json_decode($response));
				$decrypted = json_decode($this->api_call->decrypt_response(Kaspay_api_call::STATUS_OK, $encrypted));

				$this->last_response = $decrypted;
				$this->last_error_code = self::NO_ERROR;
				$this->last_error = '';
				$this->last_http_code = Kaspay_api_call::STATUS_OK;
			}
			catch (Kaspay_api_cryptor_exception $exc)
			{
				$this->last_error_code = self::ERROR_DECRYPTION;
				$this->last_error = $exc->getMessage();
				$this->last_http_code = Kaspay_api_call::STATUS_OK;
			}
		}
	}

	/**
	 * This is the low level process of sending an API request
	 * @param  string $method GET/POST/PUT/DELETE
	 * @param  string $url  Kaspay API URL
	 * @param  array|string $params API params (uaccount, timestamp, encrypted data)
	 */
	public function send_via_curl($method, $url, $params)
	{
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_HEADER, FALSE);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_TIMEOUT, self::TIMEOUT);

		$params = is_array($params) ? http_build_query($params) : $params;
		$headers = array("Accept: application/json");

		switch ($method)
		{
			case 'GET':
				curl_setopt($curl, CURLOPT_HTTPGET, TRUE);
				$url = $url.'?'.$params;
				break;
			case 'POST':
				curl_setopt($curl, CURLOPT_POST, TRUE);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
				break;
			case 'PUT':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
				$headers[] = 'Content-Length: '.strlen($params);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
				break;
			case 'DELETE':
				curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
				$headers[] = 'Content-Length: '.strlen($params);
				curl_setopt($curl, CURLOPT_POSTFIELDS, $params);
				break;
		}

		curl_setopt($curl, CURLOPT_URL, $url);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
		if ($this->verify_ssl)
		{
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, TRUE);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($curl, CURLOPT_CAINFO, $this->ssl_certificate);
		}
		else
		{
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		}

		$response = curl_exec($curl);
		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$this->process_response($http_code, $response, curl_error($curl));

		curl_close($curl);
	}

	public function get_response()
	{
		return $this->last_response;
	}

	public function get_raw_response()
	{
		return $this->last_raw_response;
	}

	public function get_error()
	{
		return $this->last_error;
	}

	public function get_error_code()
	{
		return $this->last_error_code;
	}

	public function get_http_code()
	{
		return $this->last_http_code;
	}

	public function is_ok()
	{
		return $this->last_error_code === self::NO_ERROR;
	}

	public function set_as_development()
	{
		$this->set_base_url(self::DEV_BASE_URL);
	}
	
	public function set_as_local_debug()
	{
		$this->set_base_url(site_url('api/v1') . '/');
	}
}