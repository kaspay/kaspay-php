<?php
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
