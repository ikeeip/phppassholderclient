phppassholderclient
===================

For the latest source code, see https://github.com/ikeeip/phppassholderclient

PHP client for passholder

Example
-------

	<?php

	require_once 'passholder.php';

	$serviceUrl = 'ssl://localhost:8123';
	$clientPemFilePath = 'keys/client.pem';
	$caCertFilePath = 'keys/ca-cert.pem';

	$testPass = 'mysecurepass';

	$passHolder = new PassHolder($serviceUrl, $clientPemFilePath);
	$passHolder->setRootCertificationAuthority($caCertFilePath);
	$passHolder->connect();

	$hash = $passHolder->hold($testPass);
	echo ">>> " . $hash . "\n";

	$pass = $passHolder->unhold($hash);
	echo ">>> " . $pass . "\n";

	$ret = $passHolder->remove($hash);
	echo ">>> " . $ret . "\n";

	$pass = $passHolder->unhold($hash); # Exception will be raised here
	echo ">>> " . $pass . "\n";

	$passHolder->disconnect();

