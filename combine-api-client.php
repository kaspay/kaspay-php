<?php
// HELP MESSAGE
if ($argc == 2 && $argv[1] == 'help') {
	echo <<<EOT

This script will combine the files related to Kaspay client API into one file.

Usage: php combine-api-client.php [-o=OUTPUT_FILE] [client_class] ... [client_class]

Parameters:
-o=OUTPUT_FILE
  
  Put the combined file as this file. The file will be overwritten.
  Default is "kaspay_api.combined.php".

[client_class]

  Part of file name inside the "/client/"" directory to be included. You can add
  as many files as you would like, those files will be appended. Just make sure 
  that the file exists. No need to include "kaspay_api_client.php" because it's 
  already included.

  The script assumes the files use the following format so you don't have to type
  the full file name:

  	kaspay_*_api_client.php

  For example, if the file name is "kaspay_escrow_api_client.php", you can use
  the following value to refer to that file:

    kaspay_escrow_api_client.php
    kaspay_escrow_api_client
    escrow_api_client
    escrow


EOT;
	return;
}

// SIMPLE CONFIG
$api_dir = './src/';
$out_file = 'dist/kaspay_api.combined.php';
$files = array( // default files included
	'kaspay_api_call.php',
	'kaspay_api_cryptor.php',
	'kaspay_api_cryptor_exception.php',
	'kaspay_api_cryptor_mcrypt.php',
	'kaspay_api_cryptor_openssl.php',
	'kaspay_api_parameter.php',
	'client/kaspay_api_client.php',
);

// GRAB ADDITIONAL FILES
$params = $argv;
array_shift($params);
foreach ($params as $chunk) {
	// option -o
	if (strpos($chunk, '-o=') === 0) {
		$out_file = substr($chunk, 3);
		continue;
	}
	if (!preg_match('/^kaspay_/', $chunk)) {
		$chunk = 'kaspay_' . $chunk;
	}
	if (!preg_match('/_api_client\.php$/', $chunk)) {
		$chunk .= '_api_client.php';
	}
	if (!preg_match('/\.php$/', $chunk)) {
		$chunk .= '.php';
	}
	$files[] = 'client/'.$chunk;
}

// COMBINE THEM
$output = '<'.'?php';
foreach ($files as $file) {
	$content = file_get_contents($api_dir.$file);
	$content = preg_replace('/^\<\?php/', '', $content);
	$output .= "\n".$content;
}
file_put_contents($out_file, $output);