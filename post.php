<?php
/*
 *  Copyright (c) 2010-2014 Tinyboard Development Group
 */

require_once 'inc/bootstrap.php';

function handle_delete() {
	global $config, $board;

	if (!isset($_POST['board'], $_POST['password']))
		error($config['error']['bot']);

	if(empty($_POST['password']))
		error($config['error']['invalidpassword']);

	$password = sha256Salted($_POST['password']);

	$delete = array();
	foreach ($_POST as $post => $value) {
		if (preg_match('/^delete_(\d+)$/', $post, $m)) {
			$delete[] = (int)$m[1];
		}
	}

	// Check if board exists
	if (!openBoard($_POST['board']))
		error($config['error']['noboard']);

	checkDNSBL();

	// Check if banned
	checkBan($board['uri']);

	handle_blocks(); // function to handle region blocks

	if ((!isset($_POST['mod']) || !$_POST['mod']) && $config['board_locked'])
		error(_("Board is locked"));

	// Check if deletion enabled
	if (!$config['allow_delete'])
		error(_('Post deletion is not allowed!'));

	if (empty($delete))
		error($config['error']['nodelete']);

	foreach ($delete as &$id) {
		$query = prepare(sprintf("SELECT `id`,`thread`,`time`,`password`, `num_files` FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$post = $query->fetch(PDO::FETCH_ASSOC);

		if(!$post)
			continue;



		$thread = false;
			if ($config['user_moderation'] && $post['thread']) {
				$thread_query = prepare(sprintf("SELECT `time`,`password` FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
				$thread_query->bindValue(':id', $post['thread'], PDO::PARAM_INT);
				$thread_query->execute() or error(db_error($query));

				$thread = $thread_query->fetch(PDO::FETCH_ASSOC);
			}

		if ((!hash_equals($post['password'], $password) && !$thread) ||
		($thread && !hash_equals($thread['password'], $password)))
			error($config['error']['invalidpassword']);

		if ($post['thread'] == NULL) {
			if ($post['time'] > time() - $config['delete_time'])  {
					error(sprintf($config['error']['delete_too_soon'], until($post['time'] + $config['delete_time'])));
				}
			}
			else {
				if ($post['time'] > time() - $config['delete_time_reply'] && (!$thread || !hash_equals($thread['password'], $password))) {
					error(sprintf($config['error']['delete_too_soon'], until($post['time'] + $config['delete_time'])));
				}
			}

			if (isset($_POST['file'])) {
				// Delete file

				if(isset($_POST['file_single']) && !empty($_POST['file_single'])){
					// Delete spesific file
					if(is_numeric($_POST['file_single'])) {
						deleteFile($id, false, (int)$_POST['file_single'] - 1);
						modLog("User deleted spesific file from his own post #$id");
					} else {
						error(_('Uknown file specified.'));
					}
				} else {
					// Delete all file(s)
					deleteFile($id);
					modLog("User deleted all file(s) from his own post #$id");
				}
			} else {

				// Check if thread and that the delete cutoff post count haven't been reached
				if($config['allow_delete_cutoff'] && $post['thread'] === NULL) {
					$count_query = query(sprintf("SELECT COUNT(1) FROM ``posts_%s`` WHERE `thread` = %d", $board['uri'], (int)$id));
					if($count_query->fetchColumn(0) >= $config['allow_delete_cutoff'])
						error(_('Deletion of this thread is not allowed!'));
				}

				// Delete entire post
				if($config['shadow_del']['user_delete']) {
					deletePostShadow($id);
					modLog("User deleted his own post #$id (shadow deleted)");
				}
				else {
					deletePostPermanent($id);
					modLog("User deleted his own post #$id");
				}
			}

			_syslog(LOG_INFO, 'Deleted post: ' .
				'/' . $board['dir'] . $config['dir']['res'] . link_for($post) . ($post['thread'] ? '#' . $id : '')
			);
	}

	buildIndex();

	$is_mod = isset($_POST['mod']) && $_POST['mod'];
	$root = $is_mod ? $config['root'] . $config['file_mod'] . '?/' : $config['root'];

	if (!isset($_POST['json_response'])) {
		header('Location: ' . $root . $board['dir'] . $config['file_index'], true, $config['redirect_http']);
	} else {
		header('Content-Type: text/json');
		echo json_encode(array('success' => true));
	}

        // We are already done, let's continue our heavy-lifting work in the background (if we run off FastCGI)
        if (function_exists('fastcgi_finish_request'))
                @fastcgi_finish_request();

	rebuildThemes('post-delete', $board['uri']);

}
function handle_report() {
	global $config, $board;

	if (!isset($_POST['board'], $_POST['reason']))
		error($config['error']['bot']);

	$report = array();
	foreach ($_POST as $post => $value) {
		if (preg_match('/^delete_(\d+)$/', $post, $m)) {
			$report[] = (int)$m[1];
		}
	}

	// Check if board exists
	if (!openBoard($_POST['board']))
		error($config['error']['noboard']);

	checkDNSBL();

	// Check if banned
	checkBan($board['uri']);


	handle_blocks(); // function to handle region blocks


	if ((!isset($_POST['mod']) || !$_POST['mod']) && $config['board_locked'])
		error(_("Board is locked"));

	if (empty($report))
		error($config['error']['noreport']);

	if (mb_strlen($_POST['reason']) > 30 || empty($_POST['reason']))
		error($config['error']['invalidreport']);

	if (count($report) > $config['report_limit'])
		error($config['error']['toomanyreports']);

	if ($config['captcha']['report_captcha'] && !isset($_POST['captcha_text'], $_POST['captcha_cookie']))
		error($config['error']['bot']);


	if ($config['captcha']['report_captcha']) {
		$ch = curl_init($config['captcha']['provider_check'] . "?" . http_build_query([
			'mode' => 'check',
			'text' => $_POST['captcha_text'],
			'cookie' => $_POST['captcha_cookie']
		]));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$resp = json_decode(curl_exec($ch), true);

		if (!$resp['success'])
			error($config['error']['captcha']);

	}

	$reason = escape_markup_modifiers($_POST['reason']);
	markup($reason);

	foreach ($report as &$id) {
		$query = prepare(sprintf("SELECT `id`, `thread`, `body_nomarkup` FROM ``posts_%s`` WHERE `id` = :id", $board['uri']));
		$query->bindValue(':id', $id, PDO::PARAM_INT);
		$query->execute() or error(db_error($query));

		$post = $query->fetch(PDO::FETCH_ASSOC);

		if(!$post)
			error(_('Post not found'));

	        $error = event('report', array('ip' => $_SERVER['REMOTE_ADDR'], 'board' => $board['uri'], 'post' => $post, 'reason' => $reason, 'link' => link_for($post)));

	        if ($error)
	                error($error);


		if ($config['syslog'])
			_syslog(LOG_INFO, 'Reported post: ' .
				'/' . $board['dir'] . $config['dir']['res'] . link_for($post) . ($post['thread'] ? '#' . $id : '') .
				' for "' . $reason . '"'
			);
		$ip = get_ip_hash($_SERVER['REMOTE_ADDR']);
		$query = prepare("INSERT INTO ``reports`` (`time`, `ip`, `board`, `post`, `reason`) VALUES (:time, :ip, :board, :post, :reason)");
		$query->bindValue(':time', time(), PDO::PARAM_INT);
		$query->bindValue(':ip', $ip, PDO::PARAM_STR);
		$query->bindValue(':board', $board['uri'], PDO::PARAM_STR);
		$query->bindValue(':post', $id, PDO::PARAM_INT);
		$query->bindValue(':reason', $reason, PDO::PARAM_STR);
		$query->execute() or error(db_error($query));

	// thanks lainchan for the skeleton
	if ($config['discord']['enabled']){

	//$postcontent = mb_substr($post['body_nomarkup'], 0, 150) . '...';

	// regex to remove quotes and strip line breaks
	$postcontent = preg_replace('/>{2,}.+|\\n/', ' ', $post['body_nomarkup']);

	$discordmessage = '***Novo report***' . "\n";
	$discordmessage .= '***Link: *** ' . '<' . $config['domain'] . '/' . $config['file_mod'] . '/' . $board['dir'] . $config['dir']['res'] . link_for($post) . ($post['thread'] ? '#' . $id : '') . '>' . "\n";
	$discordmessage .= '***Board: *** /' . $board['dir'] . "\n";
	$discordmessage .= '***Post: *** ' . $postcontent . "\n";
	$discordmessage .= '***Motivo: *** ' . $reason . "\n";
	$discordmessage .= '***Denunciado por: *** ' . '<' . $config['domain'] . "/mod.php?/IP/" . $ip . '>';

	discord($discordmessage);
	}
}

	$is_mod = isset($_POST['mod']) && $_POST['mod'];
	$root = $is_mod ? $config['root'] . $config['file_mod'] . '?/' : $config['root'];

	if (!isset($_POST['json_response'])) {
		$index = $root . $board['dir'] . $config['file_index'];
		$reported = $root . $board['dir'] . $config['dir']['res'] . link_for($post) . ($post['thread'] ? '#' . $id : '');
		echo Element('page.html', array('config' => $config, 'body' => '<div style="text-align:center; padding-left:"><a href="javascript:window.close()">[ ' . _('Close window') ." ]</a>&nbsp;&nbsp;<a href='$index'>[ " . _('Index') . " ]</a>&nbsp;&nbsp;<a href='$reported'>[ " . _('Go to thread') . ']</a> </div>', 'title' => _('Report submitted!')));

	} else {
		header('Content-Type: text/json');
		echo json_encode(array('success' => true));
	}
}

function handle_post(){
	global $config, $board, $mod, $pdo;

	if (!isset($_POST['body'], $_POST['board']))
		error($config['error']['bot']);

	$post = array('board' => $_POST['board'], 'files' => array());

	// Check if board exists
	if (!openBoard($post['board']))
		error($config['error']['noboard']);

	if ((!isset($_POST['mod']) || !$_POST['mod']) && $config['board_locked'])
		error(_("Board is locked"));


	if (!isset($_POST['name']))
		$_POST['name'] = $config['anonymous'];

	if (!isset($_POST['email']))
		$_POST['email'] = '';

	if (!isset($_POST['subject']))
		$_POST['subject'] = '';

	// QUICK AND DIRTY FIX FOR THOSE EXPLOITING THE ##
	$_POST['subject'] = str_replace("##", "Certamente não é uma moderadora", $_POST['subject']);

	if (!isset($_POST['password']))
		$_POST['password'] = '';

	if (isset($_POST['thread'])) {
		$post['op'] = false;
		$post['thread'] = round($_POST['thread']);
	} else
		$post['op'] = true;


	$post['ip'] = get_ip_hash($_SERVER['REMOTE_ADDR']);
	$cookie = get_uuser_cookie();
	if (!$config['hcaptcha'] && !Whitelist::check($post['ip'], $cookie))
		message(Element('whitelist.html', array('config' => $config)), 'White list captcha', '');

		checkDNSBL();

		// Check if banned, warned or nicenoticed
		checkBan($board['uri']);

		handle_blocks(); // function to handle region blocks

		// Check for CAPTCHA right after opening the board so the "return" link is in there
		if ($config['hcaptcha']) {
			if (!isset($_POST['h-captcha-response']))
				error($config['error']['bot']);

			// Check what hCAPTCHA has to say...
				$resp = json_decode(file_get_contents(sprintf('https://hcaptcha.com/siteverify?secret=%s&response=%s&remoteip=%s',
				$config['hcaptcha_private'],
				urlencode($_POST['h-captcha-response']),
				$_SERVER['REMOTE_ADDR'])), true);

			if (!$resp['success'])
				error($config['error']['captcha']);

		}

		if ($config['captcha']['post_captcha'] || ($post['op'] && $config['captcha']['thread_captcha'])) {
			$ch = curl_init($config['captcha']['provider_check'] . "?" . http_build_query([
				'mode' => 'check',
				'text' => $_POST['captcha_text'],
				'cookie' => $_POST['captcha_cookie']
			]));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			$resp = json_decode(curl_exec($ch), true);

			if (!$resp['success']) {
				error($config['error']['captcha'] .
	'<script>if (actually_load_captcha !== undefined) actually_load_captcha("'.$config['captcha']['provider_get'].'");</script>');
			}
		}

		if (!(($post['op'] && $_POST['post'] == $config['button_newtopic']) ||
			(!$post['op'] && $_POST['post'] == $config['button_reply'])))
			error($config['error']['bot']);

		// Check the referrer
		if ($config['referer_match'] !== false &&
			(!isset($_SERVER['HTTP_REFERER']) || !preg_match($config['referer_match'], rawurldecode($_SERVER['HTTP_REFERER']))))
			error($config['error']['referer']);


		if ($post['mod'] = isset($_POST['mod']) && $_POST['mod']) {
			check_login(false);
			if (!$mod) {
				// Liar. You're not a mod.
				error($config['error']['notamod']);
			}

			$post['sticky'] = $post['op'] && isset($_POST['sticky']);
			$post['locked'] = $post['op'] && isset($_POST['lock']);
			$post['raw'] = isset($_POST['raw']);

			if ($post['sticky'] && !hasPermission($config['mod']['sticky'], $board['uri']))
				error($config['error']['noaccess']);
			if ($post['locked'] && !hasPermission($config['mod']['lock'], $board['uri']))
				error($config['error']['noaccess']);
			if ($post['raw'] && !hasPermission($config['mod']['rawhtml'], $board['uri']))
				error($config['error']['noaccess']);

		if (!$post['mod'] && !$config['turn_off_antispam']) {
			$post['antispam_hash'] = checkSpam(array($board['uri'], isset($post['thread']) ? $post['thread'] : ($config['try_smarter'] && isset($_POST['page']) ? 0 - (int)$_POST['page'] : null)));
			if ($post['antispam_hash'] === true)
				error($config['error']['spam']);
		}

		if ($config['robot_enable'] && $config['robot_mute'])
			checkMute();

	}
	else {
		$mod = $post['mod'] = false;
	}

	//Check if thread exists
	if (!$post['op']) {
		$query = prepare(sprintf("SELECT `sticky`,`locked`,`cycle`,`sage`,`slug` FROM ``posts_%s`` WHERE `id` = :id AND `thread` IS NULL LIMIT 1", $board['uri']));
		$query->bindValue(':id', $post['thread'], PDO::PARAM_INT);
		$query->execute() or error(db_error());

		if (!$thread = $query->fetch(PDO::FETCH_ASSOC)) {
			// Non-existant
			error($config['error']['nonexistant']);
		}
	}
	else {
		$thread = false;
	}

	// Check for an embed field
	if ($config['enable_embedding'] && isset($_POST['embed']) && !empty($_POST['embed'])) {
		// yep; validate it
		$value = $_POST['embed'];
		foreach ($config['embeds'] as &$embed) {
			if (preg_match($embed['regex'], trim($value))) {
				// Valid link
				$post['embed'] = $value;
				// This is bad, lol.
				$post['no_longer_require_an_image_for_op'] = true;

				if ($embed['service'] == 'youtube')
					$post['embed'] = preg_replace('/\bshorts\b\//i', 'watch?v=', $post['embed']);

				if (isset($embed['oembed']) && !empty($embed['oembed'])) {
					$json_str = @file_get_contents(sprintf($embed['oembed'], $post['embed']));
					if (!$json_str) {
						unset($post['embed']); // invalid link
						break;
					}
					$_json = json_decode($json_str);
					$post['embed'] = json_encode(['title' => $_json->title, 'url' => $post['embed']], JSON_UNESCAPED_UNICODE);
				} else {
					$post['embed'] = json_encode(['title' => '', 'url' => $post['embed']]);
				}
				break;
			}
		}
		if (!isset($post['embed']))
			error($config['error']['invalid_embed']);

	}

	if (!hasPermission($config['mod']['bypass_field_disable'], $board['uri'])) {
		if ($config['field_disable_name'])
			$_POST['name'] = $config['anonymous']; // "forced anonymous"

		if ($config['field_disable_email'])
			$_POST['email'] = '';

		if ($config['field_disable_password'])
			$_POST['password'] = '';

		if ($config['field_disable_subject'] || (!$post['op'] && $config['field_disable_reply_subject']))
			$_POST['subject'] = '';
	}


	if ($config['url_upload']['enabled']){
		for ($ui = 0; $ui <= $config['max_images']; $ui++) {
			if (isset($_POST['file_url'][$ui]) && !empty($_POST['file_url'][$ui])) {

				$post['file_url'] = $_POST['file_url'][$ui];
				if (!preg_match('@^https?://@', $post['file_url']))
					error($config['error']['invalidimg']);

				if (mb_strpos($post['file_url'], '?') !== false)
					$url_without_params = mb_substr($post['file_url'], 0, mb_strpos($post['file_url'], '?'));
				else
					$url_without_params = $post['file_url'];

				$post['extension'] = strtolower(mb_substr($url_without_params, mb_strrpos($url_without_params, '.') + 1));

				if ($post['op'] && $config['allowed_ext_op']) {
					if (!in_array($post['extension'], $config['allowed_ext_op']) && !in_array($post['extension'], $config['url_upload']['curl_extensions']))
						error($config['error']['unknownext']);
				}
				else if (!in_array($post['extension'], $config['allowed_ext']) && !in_array($post['extension'], $config['allowed_ext_files']) && !in_array($post['extension'], $config['url_upload']['curl_extensions']))
					error($config['error']['unknownext']);


				$post['file_tmp' . $ui] = @tempnam($config['tmp'] . $ui, 'url');
				register_shutdown_function('unlink_tmp_file', $post['file_tmp' . $ui]);
				$fp = fopen($post['file_tmp' . $ui], 'w');

				$post['file_url'] = preg_replace('/^https?:\/\/(?:\w+\.|)/i', '', $post['file_url']);
				$post['file_url'] = str_replace('%%url%%', urlencode($post['file_url' . $ui]), $config['url_upload']['curl_proxy']);
				$curl = curl_init();
				curl_setopt($curl, CURLOPT_URL, $post['file_url']);
				curl_setopt($curl, CURLOPT_FAILONERROR, true);
				curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
				curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
				curl_setopt($curl, CURLOPT_TIMEOUT, $config['url_upload']['timeout']);
				curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:47.0) Gecko/20100101 Firefox/47.0');
				curl_setopt($curl, CURLOPT_FILE, $fp);
				curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
				if (curl_exec($curl) === false)
					error($config['error']['nomove'] . '<br/>Curl says: ' . curl_error($curl));
				curl_close($curl);
				$_FILES[] = array(
					'name' => basename($url_without_params),
					'tmp_name' => $post['file_tmp' . $ui],
					'file_tmp' => true,
					'error' => 0,
					'size' => filesize($post['file_tmp' . $ui])
				);
			}
		}
	}

	if($config['show_countryballs_single'] && isset($_POST['cbsingle']))
		$post['showcountryball'] = true;

	if($config['hide_poster_id_thread'] && $post['op'])
		$post['hideposterid'] = isset($_POST['hideposterid']);

	$post['name'] = !empty($_POST['name']) ? $_POST['name'] : $config['anonymous'];
	$post['subject'] = $_POST['subject'];
	$post['email'] = str_replace(' ', '%20', htmlspecialchars($_POST['email']));
	$post['body'] = $_POST['body'];
	$post['password'] = sha256Salted($_POST['password']);
	$post['has_file'] = (!isset($post['embed']) && (($post['op'] && !isset($post['no_longer_require_an_image_for_op']) && $config['force_image_op']) || count($_FILES) > 0));
	$post['shadow'] = 0;

	if (isset($_POST['rmexif']) && $post['has_file'] && $config['strip_exif_single'])
		$config['strip_exif'] = true;


		if (!($post['has_file'] || isset($post['embed'])) || (($post['op'] && $config['force_body_op']) || (!$post['op'] && $config['force_body']))) {
			$stripped_whitespace = preg_replace('/^[\pZ\pC]+|[\pZ\pC]+$/u', '', $post['body']);
			if (empty($stripped_whitespace))
				error($config['error']['tooshort_body']);

		}

		if (!$post['op']) {
			// Check if thread is locked
			// but allow mods to post
			if ($thread['locked'] && !hasPermission($config['mod']['postinlocked'], $board['uri']))
				error($config['error']['locked']);

			$numposts = numPosts($post['thread']);

			if ($config['reply_hard_limit'] != 0 && $config['reply_hard_limit'] <= $numposts['replies'])
				error($config['error']['reply_hard_limit']);

			if ($post['has_file'] && $config['image_hard_limit'] != 0 && $config['image_hard_limit'] <= $numposts['images'])
				error($config['error']['image_hard_limit']);
		}

	if ($post['has_file']) {
		// Determine size sanity
		$size = 0;
		if ($config['multiimage_method'] == 'split') {
			foreach ($_FILES as $key => $file) {
				if(is_array($file['size'])) {
					foreach($file['size'] as $fsize)
						$size += $fsize;
				} else {
					$size += $file['size'];
				}
			}
		} elseif ($config['multiimage_method'] == 'each') {
			foreach ($_FILES as $key => $file) {
				if(is_array($file['size'])) {
					foreach($file['size'] as $fsize)
						if ($fsize > $size) {
							$size = $fsize;
						}
				} else {
					if ($file['size'] > $size) {
						$size = $file['size'];
					}
				}
			}
		} else {
			error(_('Unrecognized file size determination method.'));
		}

		if ($size > $config['max_filesize'])
			error(sprintf3($config['error']['filesize'], array(
				'sz' => number_format($size),
				'filesz' => number_format($size),
				'maxsz' => number_format($config['max_filesize'])
			)));
		$post['filesize'] = $size;
	}

	$post['capcode'] = false;

	if ($mod && preg_match('/^((.+) )?## *(.+)$/', $post['name'], $matches)) {
		$name = !empty($matches[2]) ? $matches[2] : $config['anonymous'];
		$cap = $matches[3];

		foreach ($config['mod']['capcode'] as $mod_level => $capcode_group) {
			if ($mod['type'] < $mod_level)
				break;

			foreach ($capcode_group as $capcode) {
				if (strcasecmp($cap, $capcode) == 0) {
					$post['capcode'] = utf8tohtml($capcode);
					$post['name'] = $name;
				}
			}
		}
	}

	$trip = generate_tripcode($post['name']);
	$post['name'] = $trip[0];
	$post['trip'] = isset($trip[1]) ? $trip[1] : ''; // XX: Tripcodes

	$noko = false;
	if (strtolower($post['email']) == 'noko') {
		$noko = true;
		$post['email'] = '';
	} elseif (strtolower($post['email']) == 'nonoko'){
		$noko = false;
		$post['email'] = '';
	} else $noko = $config['always_noko'];

	if ($post['has_file']) {
		$i = 0;
		foreach ($_FILES as $key => $file) {
			if(is_array($file['size'])) {
				// Turn the $_FILES[] -> into a workable array
				$tmp_fi_file = array();
				for($fi = 0; $fi < count($file['size']); $fi++) {
					foreach ($file as $fi_key => $fi_val) {
						$tmp_fi_file[$fi][$fi_key] = $fi_val[$fi];
					}
				}
				// Add all files
				foreach($tmp_fi_file as $fi_key => $fi_file) {
					if ($fi_file['size'] && $fi_file['tmp_name']) {
						$post['files'][] = process_filenames($fi_file, $board['dir'], sizeof($_FILES) > 1 || sizeof($file['size']) > 1, $i);
						$i++;
					}
				}
			} else {
				if ($file['size'] && $file['tmp_name']) {
					$post['files'][] = process_filenames($file, $board['dir'], sizeof($_FILES) > 1, $i);
					$i++;
				}
			}
		}
	}

	if (empty($post['files']))
		$post['has_file'] = false;

		// Check for a file
		if ($post['op'] && !isset($post['no_longer_require_an_image_for_op'])) {
			if (!$post['has_file'] && $config['force_image_op'])
				error($config['error']['noimage']);
		}

		// Check for too many files
		if (sizeof($post['files']) > $config['max_images'])
			error($config['error']['toomanyimages']);

	if ($config['strip_combining_chars']) {
		$post['name'] = strip_combining_chars($post['name']);
		$post['email'] = strip_combining_chars($post['email']);
		$post['subject'] = strip_combining_chars($post['subject']);
		$post['body'] = strip_combining_chars($post['body']);
	}

	// Fix for line count in max chars
	$post['body'] = str_replace(array("\r\n", "\n\r"), "\n", $post['body']);

		// Check string lengths
		if (mb_strlen($post['name']) > 35)
			error(sprintf($config['error']['toolong'], 'name'));
		if (mb_strlen($post['email']) > 40)
			error(sprintf($config['error']['toolong'], 'email'));
		if (mb_strlen($post['subject']) > 100)
			error(sprintf($config['error']['toolong'], 'subject'));
		if (!$mod && mb_strlen($post['body']) > $config['max_body'])
			error($config['error']['toolong_body']);
		if (mb_strlen($post['body']) <= $config['min_body'] && $post['op'])
			error(sprintf(_('OP must be at least %d chars.'), $config['min_body']));
		if (mb_strlen($post['password']) > 64)
			error(sprintf($config['error']['toolong'], 'password'));

	wordfilters($post['body']);

	// Roll Dices
	inlineDiceRoller($post['body']);

	$post['body'] = escape_markup_modifiers($post['body']);

	if ($mod && isset($post['raw']) && $post['raw']) {
		$post['body'] .= "\n<tinyboard raw html>1</tinyboard>";
	}

	if (($config['countryballs'] && !$config['allow_no_country']) ||
		($config['countryballs'] && $config['allow_no_country'] &&
		!isset($_POST['no_country'])) || isset($post['showcountryball'])){

		$arr = forcedIPflags($_SERVER['REMOTE_ADDR']);
		if ($arr)
			$arr['country_name'] = $config['mod']['forcedflag_countries'][$arr['country']];
		else
			$arr = get_maxmind($_SERVER['REMOTE_ADDR']);

		$post['body'] .= insertFlagsBody($arr['country'], $arr['country_name']);
	}


	if ($config['user_flag'] && isset($_POST['user_flag']) && !empty($_POST['user_flag'])){

		$user_flag = $_POST['user_flag'];

		if (!isset($config['user_flags'][$user_flag]))
			error(_('Invalid flag selection!'));

		$flag_alt = isset($user_flag_alt) ? $user_flag_alt : $config['user_flags'][$user_flag];

		$post['body'] .= insertFlagsBody(strtolower($user_flag), $flag_alt);
	}

	$post['body_nomarkup'] = $post['body']; // Assume we're using the utf8mb4 charset

	$post['tracked_cites'] = markup($post['body'], true);

	if ($post['has_file']) {
		$md5cmd = false;
		if ($config['bsd_md5'])  $md5cmd = '/sbin/md5 -r';
		if ($config['gnu_md5'])  $md5cmd = 'md5sum';

		$allhashes = '';

		foreach ($post['files'] as $key => &$file) {
			if ($post['op'] && $config['allowed_ext_op']) {
				if (!in_array($file['extension'], $config['allowed_ext_op']))
					error($config['error']['unknownext']);
			}
			elseif (!in_array($file['extension'], $config['allowed_ext']) && !in_array($file['extension'], $config['allowed_ext_files']))
				error($config['error']['unknownext']);

			$file['is_an_image'] = !in_array($file['extension'], $config['allowed_ext_files']);

			// Truncate filename if it is too long
			$file['filename'] = mb_substr($file['filename'], 0, $config['max_filename_len']);

			$upload = $file['tmp_name'];

			if (!is_readable($upload))
				error($config['error']['nomove']);

			if ($md5cmd) {
				$output = shell_exec_error($md5cmd . " " . escapeshellarg($upload));
				$output = explode(' ', $output);
				$hash = $output[0];
			}
			else {
				$hash = md5_file($upload);
			}

			$file['hash'] = $hash;
			// Add Hashes as an imploded string
			$allhashes .= $hash . ':';

			// Add list of filenames
			$post['allhashes_filenames'][] = $file['filename'];
		}


		// Remove exsessive ":" from imploded list
		$allhashes = substr_replace($allhashes, '', -1);
		$post['allhashes'] = $allhashes;


		if (count ($post['files']) == 1)
			$post['filehash'] = $hash;

		else
			$post['filehash'] = md5($allhashes);

	}

	if (!hasPermission($config['mod']['bypass_filters'], $board['uri'])) {
		require_once 'inc/filters.php';

		do_filters($post);
	}

	if ($post['has_file']) {
		foreach ($post['files'] as $key => &$file) {
		if ($file['is_an_image']) {
			if ($config['ie_mime_type_detection'] !== false) {
				// Check IE MIME type detection XSS exploit
				$buffer = file_get_contents(filename: $upload, length: 255);
				if (preg_match($config['ie_mime_type_detection'], $buffer)) {
					undoImage($post);
					error($config['error']['mime_exploit']);
				}
			}

			require_once 'inc/image.php';

			// find dimensions of an image using GD
			if (!$size = @getimagesize($file['tmp_name']))
				error($config['error']['invalidimg']);

			if (!in_array($size[2], array(IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_BMP, IMAGETYPE_WEBP)))
				error($config['error']['invalidimg']);

			if ($size[0] > $config['max_width'] || $size[1] > $config['max_height'])
				error($config['error']['maxsize']);



			if ($config['convert_auto_orient'] && ($file['extension'] == 'jpg' || $file['extension'] == 'jpeg')) {
				// The following code corrects the image orientation.
				// Currently only works with the 'convert' option selected but it could easily be expanded to work with the rest if you can be bothered.
				if (!($config['redraw_image'] || (($config['strip_exif'] && !$config['use_exiftool']) && ($file['extension'] == 'jpg' || $file['extension'] == 'jpeg')))) {
					if (in_array($config['thumb_method'], array('convert', 'convert+gifsicle', 'gm', 'gm+gifsicle'))) {
						$exif = @exif_read_data($file['tmp_name']);
						$gm = in_array($config['thumb_method'], array('gm', 'gm+gifsicle'));
						if (isset($exif['Orientation']) && $exif['Orientation'] != 1) {
							if ($config['convert_manual_orient']) {
								$error = shell_exec_error(($gm ? 'gm ' : '') . 'convert ' .
									escapeshellarg($file['tmp_name']) . ' ' .
									ImageConvert::jpeg_exif_orientation(false, $exif) . ' ' .
									($config['strip_exif'] ? '+profile "*"' :
										($config['use_exiftool'] ? '' : '+profile "*"')
									) . ' ' .
									escapeshellarg($file['tmp_name']));
								if ($config['use_exiftool'] && !$config['strip_exif']) {
									if ($exiftool_error = shell_exec_error(
										'exiftool -overwrite_original -q -q -orientation=1 -n ' .
											escapeshellarg($file['tmp_name'])))
										error(_('exiftool failed!'), null, $exiftool_error);
								} else {
									// TODO: Find another way to remove the Orientation tag from the EXIF profile
									// without needing `exiftool`.
								}
							} else {
								$error = shell_exec_error(($gm ? 'gm ' : '') . 'convert ' .
										escapeshellarg($file['tmp_name']) . ' -auto-orient ' . escapeshellarg($upload));
							}
							if ($error)
								error(_('Could not auto-orient image!'), null, $error);
							$size = @getimagesize($file['tmp_name']);
							if ($config['strip_exif'])
								$file['exif_stripped'] = true;
						}
					}
				}
			}

			// create image object
			$image = new Image($file['tmp_name'], $file['extension'], $size);
			if ($image->size->width > $config['max_width'] || $image->size->height > $config['max_height']) {
				$image->delete();
				error($config['error']['maxsize']);
			}

			$file['width'] = $image->size->width;
			$file['height'] = $image->size->height;

			if ($config['spoiler_images'] && isset($_POST['spoiler'])) {
				$file['thumb'] = 'spoiler';

				$size = @getimagesize($config['spoiler_image']);
				$file['thumbwidth'] = $size[0];
				$file['thumbheight'] = $size[1];

				if ($file['thumbwidth'] == 0)
					$file['thumbwidth'] = $config['thumb_width'];
				if ($file['thumbheight'] == 0)
					$file['thumbheight'] = $config['thumb_height'];

			} elseif ($config['minimum_copy_resize'] &&
				$image->size->width <= $config['thumb_width'] &&
				$image->size->height <= $config['thumb_height'] &&
				$file['extension'] == ($config['thumb_ext'] ? $config['thumb_ext'] : $file['extension'])) {

				// Copy, because there's nothing to resize
				copy($file['tmp_name'], $file['thumb_path']);

				$file['thumbwidth'] = $image->size->width;
				$file['thumbheight'] = $image->size->height;
			} else {
				$thumb = $image->resize(
					$config['thumb_ext'] ? $config['thumb_ext'] : $file['extension'],
					$post['op'] ? $config['thumb_op_width'] : $config['thumb_width'],
					$post['op'] ? $config['thumb_op_height'] : $config['thumb_height']
				);

				$thumb->to($file['thumb_path']);

				$file['thumbwidth'] = $thumb->width;
				$file['thumbheight'] = $thumb->height;

				$thumb->_destroy();
			}

			if ($config['redraw_image'] || (!array_key_exists('exif_stripped', $file) && $config['strip_exif'] && ($file['extension'] == 'jpg' || $file['extension'] == 'jpeg'))) {
				if (!$config['redraw_image'] && $config['use_exiftool']) {
					if($error = shell_exec_error('exiftool -overwrite_original -ignoreMinorErrors -q -q -all= ' .
						escapeshellarg($file['tmp_name'])))
						error(_('Could not strip EXIF metadata!'), null, $error);
				} else {
					$image->to($file['file_path']);
					$dont_copy_file = true;
				}
			}
			$image->destroy();
		} else {
			// not an image
			$file['thumb'] = 'file';

			$size = @getimagesize(sprintf($config['file_thumb'],
				isset($config['file_icons'][$file['extension']]) ?
					$config['file_icons'][$file['extension']] : $config['file_icons']['default']));
			$file['thumbwidth'] = $size[0];
			$file['thumbheight'] = $size[1];
		}

		if ($config['tesseract_ocr'] && !isset($file['thumb'])) { // Let's OCR it! (Unless thumb is already determined to be a file icon or spoiler)
			$fname = ($file['height'] > 500 || $file['width'] > 500) ? $file['thumb_path'] : $file['tmp_name'];
			$tmpname = "tmp/tesseract/".rand(0,10000000);

			// Preprocess command is an ImageMagick b/w quantization
			$error = shell_exec_error(sprintf($config['tesseract_preprocess_command'], escapeshellarg($fname)) . " | " .
                                                          'tesseract stdin '.escapeshellarg($tmpname).' '.$config['tesseract_params']);
			$tmpname .= ".txt";

			if(file_exists($tmpname)) {
				$value = file_get_contents($tmpname);
				unlink($tmpname);
			}

			if ($value && trim($value)) {
				// This one has an effect, that the body is appended to a post body. So you can write a correct
				// spamfilter.
				$post['body_nomarkup'] .= "<tinyboard ocr image $key>".htmlspecialchars($value)."</tinyboard>";
			}
		}

		if (!isset($dont_copy_file) || !$dont_copy_file) {
			if (isset($file['file_tmp'])) {
				if (!@rename($file['tmp_name'], $file['file_path']))
					error($config['error']['nomove']);
				chmod($file['file_path'], 0644);
			} elseif (!@move_uploaded_file($file['tmp_name'], $file['file_path']))
				error($config['error']['nomove']);
			}
		}


		// Check if multiple images and if same image is added more than once
		$hashArray = explode(":", $post['allhashes']);
		$hashCount = count($hashArray);
		for($i=0; $i<$hashCount; $i++) {
			for($j=0; $j<$hashCount; $j++) {
				if($i != $j && $hashArray[$i] == $hashArray[$j]) {
					error($config['error']['fileduplicate']);
				}
			}
		}


		if ($config['image_reject_repost']) {
			// if ($p = getPostByHash($post['filehash'])) {
			if ($p = getPostByAllHash($post['allhashes'])) {
				undoImage($post);
				error(sprintf($config['error']['fileexists'], $post['allhashes_filenames'][$p['image_number']],
					($post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root']) .
					($board['dir'] . $config['dir']['res'] .
						($p['thread'] ?
							$p['thread'] . '.html#' . $p['id']
						:
							$p['id'] . '.html'
						))
				));
			}
		} else if (!$post['op'] && $config['image_reject_repost_in_thread']) {
			// if ($p = getPostByHashInThread($post['filehash'], $post['thread'])) {
			if ($p = getPostByAllHashInThread($post['allhashes'], $post['thread'])) {
				undoImage($post);
				error(sprintf($config['error']['fileexistsinthread'], $post['allhashes_filenames'][$p['image_number']],
					($post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root']) .
					($board['dir'] . $config['dir']['res'] .
						($p['thread'] ?
							$p['thread'] . '.html#' . $p['id']
						:
							$p['id'] . '.html'
						))
				));
			}
		} else if ($post['op'] && $config['image_reject_repost_in_thread']) {
			// Check all OP images and see if any have been used before
			if ($p = getPostByAllHashInOP($post['allhashes'])) {
				undoImage($post);
				error(sprintf($config['error']['fileexistsinthread'], $post['allhashes_filenames'][$p['image_number']],
					($post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root']) .
					($board['dir'] . $config['dir']['res'] .
						($p['thread'] ?
							$p['thread'] . '.html#' . $p['id']
						:
							$p['id'] . '.html'
						))
				));
			}
		}
	}

	// Do filters again if OCRing
	if ($config['tesseract_ocr'] && !hasPermission($config['mod']['bypass_filters'], $board['uri']))
		do_filters($post);


	if (!hasPermission($config['mod']['postunoriginal'], $board['uri']) && $config['robot_enable'] && checkRobot($post['body_nomarkup'])) {
		undoImage($post);
		if ($config['robot_mute'])
			error(sprintf($config['error']['muted'], mute()));
		else
			error($config['error']['unoriginal']);

	}

	// Remove board directories before inserting them into the database.
	if ($post['has_file']) {
		foreach ($post['files'] as $key => &$file) {
			$file['file'] = mb_substr($file['file_path'], mb_strlen($board['dir'] . $config['dir']['img']));
			if (!isset($file['thumb']))
				$file['thumb'] = mb_substr($file['thumb_path'], mb_strlen($board['dir'] . $config['dir']['thumb']));

		}
	}


	$post = (object)$post;
	$post->files = array_map(function($a) { return (object)$a; }, $post->files);

	$error = event('post', $post);
	$post->files = array_map(function($a) { return (array)$a; }, $post->files);

	if ($error) {
		undoImage((array)$post);
		error($error);
	}
	$post = (array)$post;

	if ($post['files'])
		$post['files'] = $post['files'];
	$post['num_files'] = sizeof($post['files']);

	$post['id'] = $id = post($post);
	$post['slug'] = slugify($post);


	insertFloodPost($post);

	// Handle cyclical threads
	if (!$post['op'] && isset($thread['cycle']) && $thread['cycle']) {
		// Query is a bit weird due to "This version of MariaDB doesn't yet support 'LIMIT & IN/ALL/ANY/SOME subquery'" (MariaDB Ver 15.1 Distrib 10.0.17-MariaDB, for Linux (x86_64))
		$query = prepare(sprintf('DELETE FROM ``posts_%s`` WHERE `thread` = :thread AND `id` NOT IN (SELECT `id` FROM (SELECT `id` FROM ``posts_%s`` WHERE `thread` = :thread ORDER BY `id` DESC LIMIT :limit) i)', $board['uri'], $board['uri']));
		$query->bindValue(':thread', $post['thread']);
		$query->bindValue(':limit', $config['cycle_limit'], PDO::PARAM_INT);
		$query->execute() or error(db_error($query));
	}

	if (isset($post['antispam_hash']))
		incrementSpamHash($post['antispam_hash']);


	if (isset($post['tracked_cites']) && !empty($post['tracked_cites'])) {
		$insert_rows = array();
		foreach ($post['tracked_cites'] as $cite) {
			$insert_rows[] = '(' .
				$pdo->quote($board['uri']) . ', ' . (int)$id . ', ' .
				$pdo->quote($cite[0]) . ', ' . (int)$cite[1] . ')';
		}
		query('INSERT INTO ``cites`` (`board`, `post`, `target_board`, `target`) VALUES ' . implode(', ', $insert_rows)) or error(db_error());
	}

	if (!$post['op'] && strtolower($post['email']) != 'sage' && !$thread['sage'] && ($config['reply_limit'] == 0 || $numposts['replies']+1 < $config['reply_limit']))
		bumpThread($post['thread']);


	if (isset($_SERVER['HTTP_REFERER'])) {
		// Tell Javascript that we posted successfully
		if (isset($_COOKIE[$config['cookies']['js']]))
			$js = json_decode($_COOKIE[$config['cookies']['js']]);
		else
			$js = (object) array();
		// Tell it to delete the cached post for referer
		$js->{$_SERVER['HTTP_REFERER']} = true;
		// Encode and set cookie
		setcookie($config['cookies']['js'], json_encode($js), array(
			'expires' => 0,
			'path' => $config['cookies']['jail'] ? $config['cookies']['path'] : '/',
			'domain' => null,
			'secure' => false,
			'httponly' => false,
			'samesite' => 'Lax'
		));
	}

	$root = $post['mod'] ? $config['root'] . $config['file_mod'] . '?/' : $config['root'];

	if ($noko) {
		$redirect = $root . $board['dir'] . $config['dir']['res'] .
			link_for($post, false, false, $thread) . (!$post['op'] ? '#' . $id : '');

		if (!$post['op'] && isset($_SERVER['HTTP_REFERER'])) {
			$regex = array(
				'board' => str_replace('%s', '(\w{1,8})', preg_quote($config['board_path'], '/')),
				'page' => str_replace('%d', '(\d+)', preg_quote($config['file_page'], '/')),
				'page50' => '(' . str_replace('%d', '(\d+)', preg_quote($config['file_page50'], '/')) . '|' .
						  str_replace(array('%d', '%s'), array('(\d+)', '[a-z0-9-]+'), preg_quote($config['file_page50_slug'], '/')) . ')',
				'res' => preg_quote($config['dir']['res'], '/'),
			);

			if (preg_match('/\/' . $regex['board'] . $regex['res'] . $regex['page50'] . '([?&].*)?$/', $_SERVER['HTTP_REFERER'])) {
				$redirect = $root . $board['dir'] . $config['dir']['res'] .
					link_for($post, true, false, $thread) . (!$post['op'] ? '#' . $id : '');
			}
		}
	} else {
		$redirect = $root . $board['dir'] . $config['file_index'];

	}


	buildThread($post['op'] ? $id : $post['thread']);

	if ($config['syslog'])
		_syslog(LOG_INFO, 'New post: /' . $board['dir'] . $config['dir']['res'] .
			link_for($post) . (!$post['op'] ? '#' . $id : ''));

	if (!$post['mod']) header('X-Associated-Content: "' . $redirect . '"');

	if (!isset($_POST['json_response'])) {
		header('Location: ' . $redirect, true, $config['redirect_http']);
	} else {
		header('Content-Type: text/json; charset=utf-8');
		echo json_encode(array(
			'redirect' => $redirect,
			'noko' => $noko,
			'id' => $id
		));
	}

	if ($config['try_smarter'] && $post['op'])
		$build_pages = range(1, $config['max_pages']);

	if ($post['op'])
		clean($id);

	event('post-after', $post);

	buildIndex();

	// We are already done, let's continue our heavy-lifting work in the background (if we run off FastCGI)
	if (function_exists('fastcgi_finish_request'))
		@fastcgi_finish_request();

	if ($post['op'])
		rebuildThemes('post-thread', $board['uri']);
	else
		rebuildThemes('post', $board['uri']);


}

function handle_appeal(){
	global $config;

	if (!isset($_POST['ban_id']))
		error($config['error']['bot']);

	$ban_id = (int)$_POST['ban_id'];

	$bans = Bans::find($_SERVER['REMOTE_ADDR']);
	foreach ($bans as $_ban) {
		if ($_ban['id'] == $ban_id) {
			$ban = $_ban;
			break;
		}
	}

	if (!isset($ban))
		error(_("That ban doesn't exist or is not for you."));

	if (!(bool)$ban['appealable'])
		error("Apelo desativado para esse ban");


	if ($ban['expires'] && $ban['expires'] - $ban['created'] <= $config['ban_appeals_min_length'])
		error(_("You cannot appeal a ban of this length."));


	$query = query("SELECT `denied` FROM ``ban_appeals`` WHERE `ban_id` = $ban_id") or error(db_error());
	$ban_appeals = $query->fetchAll(PDO::FETCH_COLUMN);

	if (count($ban_appeals) >= $config['ban_appeals_max'])
		error(_("You cannot appeal this ban again."));


	foreach ($ban_appeals as $is_denied) {
		if (!$is_denied)
			error(_("There is already a pending appeal for this ban."));
	}

	$query = prepare("INSERT INTO ``ban_appeals`` (`ban_id`, `time`, `message`) VALUES (:ban_id, :time, :message)");
	$query->bindValue(':ban_id', $ban_id, PDO::PARAM_INT);
	$query->bindValue(':time', time(), PDO::PARAM_INT);
	$query->bindValue(':message', substr($_POST['appeal'], 0, $config['ban_appeals_max_appeal_text_len']));
	$query->execute() or error(db_error($query));

	displayBan($ban);

}

function handle_archive() {
 	global $config, $board;

	if (!isset($_POST['board'], $_POST['thread_id']))
		error($config['error']['bot']);

	// Check if board exists
	if (!openBoard($_POST['board']))
		error($config['error']['noboard']);

	// Add Vote
	Archive::addVote($_POST['board'], $_POST['thread_id']);

	// Return user to archive
	header('Location: ' . $config['root'] . sprintf($config['board_path'], $_POST['board']) . $config['dir']['archive'], true, $config['redirect_http']);


}

function handle_whitelist(){
	global $config;

	$ip = get_ip_hash($_SERVER['REMOTE_ADDR']);
	$cookie = get_uuser_cookie();

	if (Whitelist::check($ip, $cookie))
		error($config['error']['already_whitelisted']);

	$resp = json_decode(file_get_contents(sprintf('https://hcaptcha.com/siteverify?secret=%s&response=%s&remoteip=%s',
	$config['hcaptcha_private'],
	urlencode($_POST['h-captcha-response']),
	$_SERVER['REMOTE_ADDR'])), true);

	if (!$resp['success'])
		error($config['error']['captcha']);



	if ($resp['success']) {
		Whitelist::add_user($ip, $cookie);
		message(sprintf('Success! You\'re now whitelisted and can post unrestricted for %s days.', $config['whitelist']['expires_in']), "Success!", "");
	}
	else
		error($config['error']['captcha']);


}

if ($config['captcha']['post_captcha'] || $config['captcha']['thread_captcha'] || $config['captcha']['report_captcha']) {
	session_start();
	if (!isset($_POST['captcha_cookie']) && isset($_SESSION['captcha_cookie']))
		$_POST['captcha_cookie'] = $_SESSION['captcha_cookie'];
}


if (isset($_POST['delete']))
	handle_delete();
elseif (isset($_POST['report']))
	handle_report();
elseif (isset($_POST['post']))
	handle_post();
elseif (isset($_POST['appeal']))
	handle_appeal();
elseif (isset($_POST['archive_vote']))
	handle_archive();
elseif (isset($_POST['whitelist']))
	handle_whitelist();
else {
	if (!file_exists($config['has_installed']))
		header('Location: install.php', true, $config['redirect_http']);
	else
		// They opened post.php in their browser manually.
		error($config['error']['nopost']);

}
