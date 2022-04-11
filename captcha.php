<?php
require_once('inc/bootstrap.php');
$expires_in = 120;

function rand_string($length, $charset) {
	$ret = "";
	while ($length--) {
		$ret .= mb_substr($charset, rand(0, mb_strlen($charset, 'utf-8')-1), 1, 'utf-8');
	}
	return $ret;
}

function cleanup() {
	global $expires_in;
	prepare("DELETE FROM `captchas` WHERE `created_at` < ?")->execute([time() - $expires_in]);
}
function generate_captcha($extra) {
	global $expires_in, $config;

		$cookie = rand_string(20, "abcdefghijklmnopqrstuvwxyz");
		$i = new Securimage($config['securimage_options']);
		$i->createCode();
		ob_start();
		$i->show();
		$rawimg = ob_get_contents();
		$b64img = 'data:image/png;base64,'.base64_encode($rawimg);
		$html = '<img src="'.$b64img.'">';
		ob_end_clean();
		$cdata = $i->getCode();
		$query = prepare("INSERT INTO `captchas` (`cookie`, `extra`, `text`, `created_at`) VALUES (?, ?, ?, ?)");
		$query->execute([$cookie, $extra, $cdata->code_display, $cdata->creationTime]);
		return ['cookie' => $cookie, 'html' => $html, 'rawimg' => $rawimg];
}

$mode = isset($_GET['mode']) ? $_GET['mode'] : null;

if(is_null($mode))
	header('location: /') and exit;

switch ($mode) {
	case 'get':
		if (!isset ($_GET['extra'])) {
			$_GET['extra'] = $config['captcha']['extra'];
		}

		$captcha = generate_captcha($_GET['extra']);

		header("Content-type: application/json");
		$extra = $_GET['extra'];
			if (isset($_GET['raw'])) {
				$_SESSION['captcha_cookie'] = $captcha['cookie'];
				header('Content-Type: image/png');
				echo $captcha['rawimg'];
		} else {
			echo json_encode(["cookie" => $captcha['cookie'], "captchahtml" => $captcha['html'], "expires_in" => $expires_in]);
		}
		break;
	case 'check':
		cleanup();
		if (!isset ($_GET['mode']) || !isset ($_GET['cookie']) || !isset ($_GET['extra']) || !isset ($_GET['text'])) {
			die();
		}

		$query = prepare("SELECT * FROM `captchas` WHERE `cookie` = ? AND `extra` = ?");
		$query->execute([$_GET['cookie'], $_GET['extra']]);

		$ary = $query->fetchAll();

		if (!$ary) {
			echo "0";
		} else {
			$query = prepare("DELETE FROM `captchas` WHERE `cookie` = ? AND `extra` = ?");
			$query->execute([$_GET['cookie'], $_GET['extra']]);
		}

		if (array_key_exists(0, $ary)){
			if (strtolower($ary[0]['text']) !== strtolower($_GET['text']))
				echo "0";
			else 
				echo "1";
		} else 
			echo "0";
		break;
}
