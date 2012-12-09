<?php

class PassHolderException extends Exception {
	
}

class PassHolder {
	
	protected $serviceURL = null;
	
	protected $connectTimeout;
	protected $connectionMaxRetryTimes = 3;
	protected $connectionRetryInterval = 1000000;
	
	protected $socket;
	protected $socketSelectTimeout = 1000000;
	
	protected $rootCertificationAuthorityFile;
	protected $providerCertificateFile;

	public function __construct($serviceUrl, $providerCertificateFile) {
		$this->serviceURL = $serviceUrl;

		if (!is_readable($providerCertificateFile))
			throw new PassHolderException("Unable to read certificate file '{$providerCertificateFile}'");

		$this->providerCertificateFile = $providerCertificateFile;
		$this->connectTimeout = ini_get("default_socket_timeout");
	}

	public function connect() {
		$connected = false;
		$retries = 0;
		while (!$connected) {
			try {
				$connected = $this->_connect();
			} catch (PassHolderException $e) {
				$this->_log('ERROR: ' . $e->getMessage());
				if ($retries >= $this->connectionMaxRetryTimes)
					throw $e;

				$this->_log("INFO: Retry to connect (" . ($retries + 1) . "/{$this->connectionMaxRetryTimes})...");
				usleep($this->connectionRetryInterval);
			}
			$retries++;
		}
	}

	public function disconnect() {
		if (is_resource($this->socket)) {
			$this->_log('INFO: Disconnected.');
			return fclose($this->socket);
		}
		return false;
	}

	public function setRootCertificationAuthority($rootCertificationAuthorityFile) {
		if (!is_readable($rootCertificationAuthorityFile))
			throw new PassHolderException("Unable to read Certificate Authority file '{$rootCertificationAuthorityFile}'");

		$this->rootCertificationAuthorityFile = $rootCertificationAuthorityFile;
	}
	
	public function hold($pass) {
		return $this->dispatchResponse($this->send('h:'.$pass));
	}
	
	public function unhold($hash) {
		return $this->dispatchResponse($this->send('u:'.$hash));
	}
	
	public function remove($hash) {
		return $this->dispatchResponse($this->send('r:'.$hash));
	}
	
	protected function dispatchResponse($response) {
		list($status, $data) = explode(':', $response, 2);
		
		if ($status=='s')
			return $data;
		
		list($errno, $errmsg) = explode(':', $data, 2);
			throw new PassHolderException($errmsg, $errno);
	}
	
	protected function send($data) {
		if (empty($data))
			throw new PassHolderException('Invalid payload');
		
		if (!$this->socket)
			throw new PassHolderException('Not connected to PassHolder Service');

		$dataLen = strlen($data);
		if ($dataLen !== ($written = (int) @fwrite($this->socket, $data)))
			throw new PassHolderException(sprintf('Socket write error (%d bytes written instead of %d bytes)', $written, $dataLen));

		$read = array($this->socket);
		$null = NULL;
		$changed = @stream_select($read, $null, $null, 0, $this->socketSelectTimeout);
		if ($changed === false)
			throw new PassHolderException('Unable to wait for a stream availability');
		if ($changed < 0)
			throw new PassHolderException('No response received');

		$readBuffer = stream_get_contents($this->socket);
		
		return $readBuffer;
	}

	protected function _connect() {
		$this->_log("INFO: Trying {$this->serviceURL}...");
		$streamContext = stream_context_create(array('ssl' => array(
				'verify_peer' => isset($this->rootCertificationAuthorityFile),
				'cafile' => $this->rootCertificationAuthorityFile,
				'local_cert' => $this->providerCertificateFile
				)));

		$this->socket = @stream_socket_client($this->serviceURL, $nError, $sError, $this->connectTimeout, STREAM_CLIENT_CONNECT, $streamContext);
		if (!$this->socket)
			throw new PassHolderException("Unable to connect to '{$this->serviceURL}': {$sError} ({$nError})");

		stream_set_blocking($this->socket, 0);
		stream_set_write_buffer($this->socket, 0);
		$this->_log("INFO: Connected to {$this->serviceURL}.");
		return true;
	}

	protected function _log($message) {
		echo $message . "\n";
	}

}
