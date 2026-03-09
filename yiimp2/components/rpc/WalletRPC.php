<?php

namespace app\components\rpc;

use app\components\rpc\iRPCConnector;
use app\components\rpc\cBitcoinRPC;

class WalletRPC {
	public $type = 'Bitcoin';
	protected iRPCConnector $connector;
	protected $hasGetInfo = false;

	public function __construct($coin) {
		if ($coin->rpcencoding) {
			$this->type = 'Bitcoin';
			$this->connector = new cBitcoinRPC($coin->rpcuser, $coin->rpcpasswd, $coin->rpchost, $coin->rpcport);
			$this->hasGetInfo = $coin->hasgetinfo;
		}
	}

	public function __call($name, $arguments) {
        if (isset($this->connector) && (method_exists($this->connector, $name) || method_exists($this->connector, '__call'))) {
            return call_user_func_array([$this->connector, $name], $arguments);
        } else {
            error_log("undefined method $name in wallet-rpc");
        }
    }
}
