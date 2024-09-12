<?php

/*
 *  Copyright (c) 2010-2020 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

class Cache {
	private static $cache;
	public static function init() {
		global $config;

		switch ($config['cache']['enabled']) {
			case 'memcached':
				self::$cache = new Memcached();
				self::$cache->addServers($config['cache']['memcached']);
				break;
			case 'redis':
				self::$cache = new Redis();
				self::$cache->connect($config['cache']['redis'][0], $config['cache']['redis'][1]);
				if ($config['cache']['redis'][2]) {
					self::$cache->auth($config['cache']['redis'][2]);
				}
				self::$cache->select($config['cache']['redis'][3]) or die('cache select failure');
				break;
			case 'php':
				self::$cache = array();
				break;
			default:
				break;
		}
	}
	public static function get($key) {
		global $config, $debug;

		$key = $config['cache']['prefix'] . $key;

		$data = false;
		switch ($config['cache']['enabled']) {
			case 'memcached':
				if (!self::$cache)
					self::init();
				$data = self::$cache->get($key);
				break;
			case 'apcu':
				$data = apcu_fetch($key);
				break;
			case 'php':
				$data = isset(self::$cache[$key]) ? self::$cache[$key] : false;
				break;
			case 'fs':
				$key = str_replace('/', '::', $key);
				$key = str_replace("\0", '', $key);
				$path = 'tmp/cache/'.$key;
				if (!file_exists($path)) {
					$data = false;
				}
				else {
					$data = file_get_contents($path);
					$data = json_decode($data, true);
				}
				break;
			case 'redis':
				if (!self::$cache)
					self::init();
				$data = json_decode(self::$cache->get($key), true);
				break;
			default:
				break;
		}

		if ($config['debug'])
			$debug['cached'][] = $key . ($data === false ? ' (miss)' : ' (hit)');

		return $data;
	}
	public static function set($key, $value, $expires = false) {
		global $config, $debug;

		$key = $config['cache']['prefix'] . $key;

		if (!$expires)
			$expires = $config['cache']['timeout'];

		switch ($config['cache']['enabled']) {
			case 'memcached':
				if (!self::$cache)
					self::init();
				self::$cache->set($key, $value, $expires);
				break;
			case 'redis':
				if (!self::$cache)
					self::init();
				self::$cache->setex($key, $expires, json_encode($value));
				break;
			case 'apcu':
				apcu_store($key, $value, $expires);
				break;
			case 'fs':
				$key = str_replace('/', '::', $key);
				$key = str_replace("\0", '', $key);
				file_put_contents('tmp/cache/'.$key, json_encode($value));
				break;
			case 'php':
				self::$cache[$key] = $value;
				break;
			default:
				break;
		}

		if ($config['debug'])
			$debug['cached'][] = $key . ' (set)';
	}
	public static function delete($key) {
		global $config, $debug;

		$key = $config['cache']['prefix'] . $key;

		switch ($config['cache']['enabled']) {
			case 'memcached':
				if (!self::$cache)
					self::init();
				self::$cache->delete($key);
				break;
			case 'redis':
				if (!self::$cache)
					self::init();
				self::$cache->del($key);
				break;
			case 'apcu':
				apcu_delete($key);
				break;
			case 'fs':
				$key = str_replace('/', '::', $key);
				$key = str_replace("\0", '', $key);
				$path = 'tmp/cache/'.$key;
				if (file_exists($path))
					unlink($path);
				break;
			case 'php':
				unset(self::$cache[$key]);
				break;
			default:
				break;
		}

		if ($config['debug'])
			$debug['cached'][] = $key . ' (deleted)';
	}
	public static function flush() {
		global $config;

		switch ($config['cache']['enabled']) {
			case 'memcached':
				if (!self::$cache)
					self::init();
				return self::$cache->flush();
			case 'apcu':
				return apcu_clear_cache();
			case 'php':
				self::$cache = array();
				break;
			case 'fs':
				$files = glob('tmp/cache/*');
				foreach ($files as $file) {
					unlink($file);
				}
				break;
			case 'redis':
				if (!self::$cache)
					self::init();
				return self::$cache->flushDB();
			default:
				break;
		}

		return false;
	}
}

class TwigCacheTinyboardFilesystem extends Twig\Cache\FilesystemCache
{
	private $directory;
	private $options;

	/**
	 * {@inheritdoc}
	 */
	public function __construct($directory, $options = 0)
	{
		parent::__construct($directory, $options);

		$this->directory = $directory;
	}

	/**
	 * This function was removed in Twig 2.x due to developer views on the Twig library. Who says we can't keep it for ourselves though?
	 */
	public function clear()
	{
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory), RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
			if ($file->isFile()) {
				@unlink($file->getPathname());
			}
		}
	}
}
