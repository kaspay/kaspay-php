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
	 * @var string Merchant's identifier
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