<?php
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