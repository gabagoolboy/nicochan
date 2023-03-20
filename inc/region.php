<?php

class Regionblock {

	private $ip, $token;
	public $charset = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
	public function __construct(?string $ip = null, ?string $token = null) {

			$this->ip = $ip;
			$this->token = $token;
	}

	private function isTokenValid(): bool {

		if (empty($this->token) || strlen($this->token) == 0 || strlen($this->token) > 12)
			return false;

		return true;
	}

	private function isIPValid(): void {

		if (!isset($this->ip) || !filter_var($this->ip, FILTER_VALIDATE_IP))
			error(_('This function requires a valid IP'));
	}

	// this is not to be secure, just a random ass string
	private function generateToken($hash): void {

		
		$random = substr($hash, 0, 4);
		for ($i = 0; $i < 8; $i++) {
			$index = mt_rand(0, strlen($this->charset) - 1);
			$random .= $this->charset[$index];
		}

		$this->token = $random;
	}

	public function userAdd(): void {

		$this->isIPValid();

		$hash = get_ip_hash($this->ip);

		if ($this->token !== 'create' && !$this->isTokenValid())
			error(_('Token must have, at least, 1 character to 12 charaters'));
		else
			$this->generateToken($hash);

		try {
			$query = prepare('INSERT INTO ``whitelist_region`` (`ip`, `ip_hash`, `token`) VALUES (:ip, :ip_hash, :token)');
			$query->bindValue(':ip', trim($this->ip), PDO::PARAM_STR);
			$query->bindValue(':ip_hash', $hash, PDO::PARAM_STR);
			$query->bindValue(':token', $this->token, PDO::PARAM_STR);
			$query->execute();
		} catch(PDOException $e) {
			error(_('This IP already has a token'));
		}
	}

	public function validateToken(): bool {

		$this->isTokenValid();

		$query = prepare('SELECT 1 FROM ``whitelist_region`` WHERE `token` = :token');
		$query->bindValue(':token', $this->token, PDO::PARAM_STR);
		$query->execute() or error(db_error($query));

		if ($query->fetchColumn(0)){
			return true;
		}

		return false;
	}

	public function revokeWhitelist(): void {

		if (str_starts_with($this->ip, '/'))
			$this->ip = substr_replace($this->ip, '', 0, 1);

		$this->isIPValid();

		$query = prepare('DELETE FROM ``whitelist_region`` WHERE `ip` = :ip');
		$query->bindValue(':ip', $this->ip, PDO::PARAM_STR);
		$query->execute() or error(db_error($query));
	}

}
