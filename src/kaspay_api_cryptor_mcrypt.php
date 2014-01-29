<?php
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