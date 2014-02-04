<?php

/**
 * A tool to help making one API call
 */

class Kaspay_api_call {

	const STATUS_OK = 200;

	const SIGNATURE_SIZE = 32; // 256-bit

	const MAC = "sha256";

	const SEPARATOR = "&";

	/**
	 * @var string Key for encryption in binary string
	 */
	protected $encrypt_key;
	/**
	 * @var string Key for mac in binary string
	 */
	protected $mac_key;
	/**
	 * @var string GET/POST/PUT/DELETE
	 */
	protected $verb;
	/**
	 * @var string Full URL
	 */
	protected $url;
	/**
	 * @var string User's identifier
	 */
	protected $uaccount;
	/**
	 * @var int UNIX timestamp
	 */
	protected $timestamp;
	/**
	 * @var Kaspay_api_cryptor
	 */
	protected $cryptor;

	public function __construct($encrypt_key = NULL, $mac_key = NULL, $verb = NULL, $url = NULL, $uaccount = NULL, $timestamp = NULL, $cryptor = NULL)
	{
		$this->set_parameters($encrypt_key, $mac_key, $verb, $url, $uaccount, $timestamp, $cryptor);
	}

	public function set_parameters($encrypt_key, $mac_key, $verb, $url, $uaccount, $timestamp, $cryptor = NULL)
	{
		$this->encrypt_key = $encrypt_key;
		$this->mac_key = $mac_key;
		$this->verb = $verb;
		$this->url = $url;
		$this->uaccount = $uaccount;
		$this->timestamp = $timestamp;
		$this->cryptor = empty($cryptor) ? Kaspay_api_cryptor::get_cryptor() : $cryptor;
	}

	/**
	 * @param  string $ciphertext Binary string
	 * @return string             HMAC of $ciphertext in binary string
	 */
	protected function sign($ciphertext)
	{
		$to_sign = $this->verb . self::SEPARATOR 
			. $this->url . self::SEPARATOR 
			. $this->uaccount . self::SEPARATOR 
			. $this->timestamp . self::SEPARATOR 
			. $ciphertext;
		return hash_hmac(self::MAC, $to_sign, $this->mac_key, TRUE);
	}

	/**
	 * @param  string $ciphertext Binary string
	 * @param  string $signature  Binary string
	 * @return bool               Is valid
	 */
	protected function verify($ciphertext, $signature)
	{
		$hash = $this->sign($ciphertext);
		return $hash === $signature;
	}

	/**
	 * @param  string $plaintext Data to encrypt in binary string
	 * @return string            Ciphertext and signature of $plaintext in binary string
	 */
	public function encrypt_request($plaintext)
	{
		$ciphertext = $this->cryptor->encrypt($this->encrypt_key, $plaintext);
		$signature = $this->sign($ciphertext);
		return $ciphertext . $signature;
	}

	/**
	 * @param  string $ciphertext_signature Ciphertext and signature in binary string
	 * @return string                       Plaintext in binary string
	 * @throws Kaspay_api_cryptor_exception If signature or ciphertext is invalid
	 */
	public function decrypt_request($ciphertext_signature)
	{
		$length = strlen($ciphertext_signature); // binary-safe
		$signature = substr($ciphertext_signature, $length - self::SIGNATURE_SIZE);
		$ciphertext = substr($ciphertext_signature, 0, $length - self::SIGNATURE_SIZE);

		// To prevent timing attack, we must perform decryption and validation regardless 
		$decrypted = $this->cryptor->decrypt($this->encrypt_key, $ciphertext);
		$is_valid = $this->verify($ciphertext, $signature);

		if ( ! $is_valid)
		{
			throw new Kaspay_api_cryptor_exception('Invalid signature', Kaspay_api_cryptor_exception::INVALID_SIGNATURE);
		}
		else if ($decrypted === FALSE)
		{
			throw new Kaspay_api_cryptor_exception('Decryption failed', Kaspay_api_cryptor_exception::FAILED_DECRYPTION);
		}
		else 
		{
			return $decrypted;
		}
	}

	/**
	 * @param  int    $status    HTTP response code
	 * @param  string $plaintext Data to encrypt in binary string
	 * @return string            Only encrypt if $status is 200 OK, otherwise $plaintext
	 */
	public function encrypt_response($status, $plaintext)
	{
		if ($status == self::STATUS_OK)
		{
			return $this->encrypt_request($plaintext);
		}
		else
		{
			return $plaintext;
		}
	}

	/**
	 * @param  int    $status               HTTP response code
	 * @param  string $ciphertext_signature Data received as response in binary string
	 * @return string                       Only decrypt if $status is 200 OK
	 */
	public function decrypt_response($status, $ciphertext_signature)
	{
		if ($status == self::STATUS_OK)
		{
			return $this->decrypt_request($ciphertext_signature);
		}
		else
		{
			return $ciphertext_signature;
		}
	}
}

/**
 * Interface for encrypt, decrypt, and key generator
 */
abstract class Kaspay_api_cryptor {

	const KEY_SIZE = 32;

	const BLOCK_SIZE = 16;
	
	const IV_SIZE = 16;

	public static function get_cryptor()
	{
		if (function_exists('openssl_encrypt'))
		{
			return new Kaspay_api_cryptor_openssl();
		} 
		else if (function_exists('mcrypt_encrypt'))
		{
			return new Kaspay_api_cryptor_mcrypt();
		}
		else
		{
			throw new Exception('OpenSSL or Mcrypt is required');
		}
	}

	abstract public function encrypt($key, $plaintext);

	abstract public function decrypt($key, $ciphertext);

	/**
	 * Generates keys for both encryption key and mac key
	 * @return string 256-bit long random bytes
	 */
	abstract public function generate_key();
}


class Kaspay_api_cryptor_exception extends Exception {
	
	const INVALID_SIGNATURE = 1;

	const FAILED_DECRYPTION = 2;
	
}

/**
 * Encrypt/decrypt with Mcrypt
 */
class Kaspay_api_cryptor_mcrypt extends Kaspay_api_cryptor {

	private function filter_key($key)
	{
		// if too short
		$key = str_pad($key, self::KEY_SIZE, "\0", STR_PAD_RIGHT);
		// if too long
		$key = substr($key, 0, self::KEY_SIZE);
		return $key;
	}

	public function encrypt($key, $plaintext)
	{
		$key = $this->filter_key($key);
		$iv = mcrypt_create_iv(self::IV_SIZE, MCRYPT_DEV_URANDOM);
		
		$padding = self::BLOCK_SIZE - (strlen($plaintext) % self::BLOCK_SIZE);
		$plaintext .= str_repeat(chr($padding), $padding);
		$ciphertext_raw = mcrypt_encrypt(MCRYPT_RIJNDAEL_128, $key, $plaintext, MCRYPT_MODE_CBC, $iv);
		return $iv . $ciphertext_raw;
	}

	public function decrypt($key, $ciphertext)
	{
		$key = $this->filter_key($key);
		$iv = substr($ciphertext, 0, self::IV_SIZE);
		$ciphertext_raw = substr($ciphertext, self::IV_SIZE);

		$data = mcrypt_decrypt(MCRYPT_RIJNDAEL_128, $key, $ciphertext_raw, MCRYPT_MODE_CBC, $iv);
		$padding = ord($data[strlen($data) - 1]); 
		return substr($data, 0, -$padding);
	}

	public function generate_key()
	{
		return mcrypt_create_iv(self::KEY_SIZE, MCRYPT_DEV_URANDOM);
	}
}

/**
 * Encrypt/decrypt with OpenSSL
 */
class Kaspay_api_cryptor_openssl extends Kaspay_api_cryptor {

	const CIPHER = "aes-256-cbc";

	public function encrypt($key, $plaintext)
	{
		$iv = openssl_random_pseudo_bytes(self::IV_SIZE);
		$ciphertext_raw = openssl_encrypt($plaintext, self::CIPHER, $key, TRUE, $iv); // OPENSSL_RAW_DATA
		return $iv . $ciphertext_raw;
	}

	public function decrypt($key, $ciphertext)
	{
		$iv = substr($ciphertext, 0, self::IV_SIZE);
		$ciphertext_raw = substr($ciphertext, self::IV_SIZE);
		return openssl_decrypt($ciphertext_raw, self::CIPHER, $key, TRUE, $iv); // OPENSSL_RAW_DATA
	}

	public function generate_key()
	{
		return openssl_random_pseudo_bytes(self::KEY_SIZE);
	}
}


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
	 * @var string User's identifier
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
