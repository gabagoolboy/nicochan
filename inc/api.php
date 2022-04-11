<?php
/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

/**
 * Class for generating json API compatible with 4chan API
 */
class Api {
	function __construct(){
		global $config;
		/**
		 * Translation from local fields to fields in 4chan-style API
		 */
		$this->config = $config;

		$this->postFields = array(
			'id' => 'no',
			'thread' => 'resto',
			'subject' => 'sub',
			'body' => 'com',
			'email' => 'email',
			'name' => 'name',
			'trip' => 'trip',
			'capcode' => 'capcode',
			'time' => 'time',
			'omitted' => 'omitted_posts',
			'omitted_images' => 'omitted_images',
			'replies' => 'replies',
			'images' => 'images',
			'sticky' => 'sticky',
			'locked' => 'locked',
			'cycle' => 'cyclical',
			'bump' => 'last_modified',
			'embed' => 'embed',
			'board' => 'board'
		);

		$this->threadsPageFields = array(
			'id' => 'no',
			'bump' => 'last_modified',
			'board' => 'board'
		);

		$this->fileFields = array(
			'thumbheight' => 'tn_h',
			'thumbwidth' => 'tn_w',
			'height' => 'h',
			'width' => 'w',
			'size' => 'fsize',
		);

		if (isset($config['api']['extra_fields']) && gettype($config['api']['extra_fields']) == 'array'){
			$this->postFields = array_merge($this->postFields, $config['api']['extra_fields']);
		}
	}

	private static $ints = array(
		'no' => 1,
		'resto' => 1,
		'time' => 1,
		'tn_w' => 1,
		'tn_h' => 1,
		'w' => 1,
		'h' => 1,
		'fsize' => 1,
		'omitted_posts' => 1,
		'omitted_images' => 1,
		'replies' => 1,
		'images' => 1,
		'sticky' => 1,
		'locked' => 1,
		'last_modified' => 1
	);

	private function translateFields($fields, $object, &$apiPost) {
		foreach ($fields as $local => $translated) {
			if (!isset($object->$local))
				continue;

			$toInt = isset(self::$ints[$translated]);
			$val = $object->$local;
			if ($val !== null && $val !== '') {
				$apiPost[$translated] = $toInt ? (int) $val : $val;
			}

		}
	}

	private function translateFile($file, $post, &$apiPost) {
		global $config;
		$this->translateFields($this->fileFields, $file, $apiPost);
		$dotPos = strrpos($file->file, '.');
		if($config['show_filename'])
			$apiPost['filename'] = @substr($file->name, 0, strrpos($file->name, '.'));
		else
			$apiPost['filename'] = @substr($file->file, 0, $dotPos);
		$apiPost['ext'] = substr($file->file, $dotPos);
		$apiPost['tim'] = substr($file->file, 0, $dotPos);

		// Add spoiler flag to API data
		if(isset($file->thumb) && $file->thumb == 'spoiler')
			$apiPost['spoiler'] = 1;
		else
			$apiPost['spoiler'] = 0;

		if (isset ($file->hash) && $file->hash) {
			$apiPost['md5'] = base64_encode($file->hash);
		}
		else if (isset ($post->filehash) && $post->filehash) {
			$apiPost['md5'] = base64_encode($post->filehash);
		}
	}

	private function translatePost($post, $threadsPage = false, $hideposterid = false) {
		global $config, $board;
		$apiPost = array();
		$fields = $threadsPage ? $this->threadsPageFields : $this->postFields;
		$this->translateFields($fields, $post, $apiPost);

		if (!$hideposterid && isset($config['poster_ids']) && $config['poster_ids'])
			$apiPost['id'] = poster_id($post->ip, $post->thread, $board['uri']);

		if ($threadsPage) return $apiPost;

		// Handle country field
		if (isset($post->body_nomarkup) && $this->config['country_flags']) {
			$modifiers = extract_modifiers($post->body_nomarkup);
			if (isset($modifiers['flag']) && isset($modifiers['flag alt']) && preg_match('/^\w+/', $modifiers['flag'])) {
					$apiPost['country'] = $modifiers['flag'];
					$apiPost['country_name'] = $modifiers['flag alt'];
			}
			if (isset($modifiers['warning message'])) {
				$apiPost['warning_msg'] = $modifiers['warning message'];
			}
			if (isset($modifiers['ban message'])) {
				$apiPost['ban_msg'] = $modifiers['ban message'];
			}
		}

		if ($config['slugify'] && !$post->thread) {
			$apiPost['semantic_url'] = $post->slug;
		}

		// Handle files
		// Note: 4chan only supports one file, so only the first file is taken into account for 4chan-compatible API.
		if (isset($post->files) && $post->files && !$threadsPage) {
			$file = $post->files[0];
			$this->translateFile($file, $post, $apiPost);
			if (sizeof($post->files) > 1) {
				$extra_files = array();
				foreach ($post->files as $i => $f) {
					if ($i == 0) continue;

					$extra_file = array();
					$this->translateFile($f, $post, $extra_file);

					$extra_files[] = $extra_file;
				}
				$apiPost['extra_files'] = $extra_files;
			}
		}

		return $apiPost;
	}

	function translateThread(Thread $thread, $threadsPage = false) {

		$hideposterid = $thread->hideid;

		$apiPosts = array();
		$op = $this->translatePost($thread, $threadsPage, $hideposterid);
		if (!$threadsPage) $op['resto'] = 0;
		$apiPosts['posts'][] = $op;

		foreach ($thread->posts as $p) {
			$apiPosts['posts'][] = $this->translatePost($p, $threadsPage, $hideposterid);
		}
		if(!$hideposterid){
			// Count unique IPs
			$ips = array($thread->ip);
			foreach ($thread->posts as $p) {
				$ips[] = $p->ip;
			}
		$apiPosts['posts'][0]['unique_ids'] = count(array_unique($ips));
		}

		return $apiPosts;
	}

	function translatePage(array $threads) {
		$apiPage = array();
		foreach ($threads as $thread) {
			$apiPage['threads'][] = $this->translateThread($thread);
		}
		return $apiPage;
	}

	function translateCatalogPage(array $threads, $threadsPage = false) {
		$apiPage = array();
		foreach ($threads as $thread) {
			$ts = $this->translateThread($thread, $threadsPage);
			$apiPage['threads'][] = current($ts['posts']);
		}
		return $apiPage;
	}

	function translateCatalog($catalog, $threadsPage = false) {
		$apiCatalog = array();
		foreach ($catalog as $page => $threads) {
			$apiPage = $this->translateCatalogPage($threads, $threadsPage);
			$apiPage['page'] = $page;
			$apiCatalog[] = $apiPage;
		}

		return $apiCatalog;
	}
}
