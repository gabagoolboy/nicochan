<?php
	require 'info.php';

	function catalog_build($action, $settings, $board) {
		global $config;

		// Possible values for $action:
		//	- all (rebuild everything, initialization)
		//	- news (news has been updated)
		//	- boards (board list changed)
		//	- post (a reply has been made)
		//	- post-thread (a thread has been made)

		$b = new Catalog($settings);
		$boards = explode(' ', $settings['boards']);

		if ($action == 'all') {
			foreach ($boards as $board) {
				if (in_array($board, $boards)) {
				$b = new Catalog($settings);
				$b->build($settings, $board);
				}
			}
		} elseif ($action == 'post-thread' || ($settings['update_on_posts'] && $action == 'post') || ($settings['update_on_posts'] && $action == 'post-delete') && in_array($board, $boards)) {
			$b = new Catalog($settings);
			$b->build($settings, $board);
			}
		if ($settings['enable_ukko2'] && (
			$action === 'all' || $action === 'post' ||
			$action === 'post-thread' || $action === 'post-delete' || $action === 'rebuild'))
		{
			$b->buildUkko2();
	}

}


	// Wrap functions in a class so they don't interfere with normal Tinyboard operations
	class Catalog {
		private $settings;
		private $threadsCache = array();

		public function __construct($settings) {
			$this->settings = $settings;
		}

		public function buildUkko2($mod = false) {
			global $config;
			$ukkoSettings = themeSettings('ukko2');
 			$queries = array();
			$threads = array();

			if(isset($ukkoSettings['exclude']))
				$exclusions = explode(' ', $ukkoSettings['exclude']);
			else
				$exclusions = [];

			$boards = array_diff(listBoards(true), $exclusions);

			foreach ($boards as $b) {
				if (array_key_exists($b, $this->threadsCache)) {
					$threads = array_merge($threads, $this->threadsCache[$b]);
				} else {
					$queries[] = $this->buildThreadsQuery($b);
				}
			}

			// Fetch threads from boards that haven't beenp processed yet
			if (!empty($queries)) {
				$sql = implode(' UNION ALL ', $queries);
				$res = query($sql) or error(db_error());
				$threads = array_merge($threads, $res->fetchAll(PDO::FETCH_ASSOC));
			}

			// Sort in bump order
			usort($threads, function($a, $b) {
				return strcmp($b['bump'], $a['bump']);
			});
			// Generate data for the template
			$recent_posts = $this->generateRecentPosts($threads, $mod);

			$this->saveForBoard($ukkoSettings, $recent_posts, true, $mod);

			if ($config['api']['enabled'] && !$mod) {
				$api = new Api();

				// Separate the threads into pages
				$pages = array(array());
				$totalThreads = count($recent_posts);
				$page = 0;
				for ($i = 1; $i <= $totalThreads; $i++) {
					$pages[$page][] = new Thread($recent_posts[$i-1]);

					// If we have not yet visited all threads,
					// and we hit the limit on the current page,
					// skip to the next page
					if ($i < $totalThreads && ($i % $config['threads_per_page'] == 0)) {
						$page++;
						$pages[$page] = array();
					}
				}

				$json = json_encode($api->translateCatalog($pages));
				file_write($ukkoSettings['uri'] . '/catalog.json', $json);

				$json = json_encode($api->translateCatalog($pages, true));
				file_write($ukkoSettings['uri'] . '/threads.json', $json);
			}

		}


		public function build($settings, $board_name, $mod = false) {
			global $config, $board;

			if (!isset($board) || $board['uri'] != $board_name) {
				if (!openBoard($board_name)) {
					error(sprintf(_("Board %s doesn't exist"), $board_name));
				}
			}

			if (array_key_exists($board_name, $this->threadsCache)) {
				$threads = $this->threadsCache[$board_name];
			} else {
				$sql = $this->buildThreadsQuery($board_name);
				$query = query($sql . ' ORDER BY `bump` DESC') or error(db_error());
				$threads = $query->fetchAll(PDO::FETCH_ASSOC);
				// Save for posterity
				$this->threadsCache[$board_name] = $threads;
			}

			// Generate data for the template
			$recent_posts = $this->generateRecentPosts($threads, $mod);

			$this->saveForBoard($board_name, $recent_posts, false, $mod);
		}

		private function buildThreadsQuery($board) {

			$sql = "SELECT *, `id` AS `thread_id`, " .
				"(SELECT COUNT(`id`) FROM ``posts_$board`` WHERE `thread` = `thread_id`) AS `reply_count`, " .
				"(SELECT SUM(`num_files`) FROM ``posts_$board`` WHERE `thread` = `thread_id` AND `num_files` IS NOT NULL) AS `image_count`, " .
				"'$board' AS `board` FROM ``posts_$board`` WHERE `thread` IS NULL";

			return $sql;
		}

		private function generateRecentPosts($threads, $mod = false) {
			global $config, $board;

			$posts = array();
			foreach ($threads as $post) {
				if (!isset($board) || $board['uri'] !== $post['board']) {
					openBoard($post['board']);
				}

				if($post['reply_count'] >= $config['noko50_min']){
					if (!$mod) {
						$post['noko'] = '<a href="' . $config['root'] . $board['dir'] . $config['dir']['res'] . link_for($post, true) . '">' .
						'['.$config['noko50_count'].']'. '</a>';
					} else {
						$post['noko'] = '<a href="' . $config['root'] . $config['file_mod'] . '?/' . $board['dir'] . $config['dir']['res'] . link_for($post, true) . '">' .
						'['.$config['noko50_count'].']'. '</a>';
					}
				}

				if (!$mod)
					$post['link'] = $config['root'] . $board['dir'] . $config['dir']['res'] . link_for($post);
				else
					$post['link'] = $config['root'] . $config['file_mod'] . '?/' . $board['dir'] . $config['dir']['res'] . link_for($post);


				if ($post['embed'] && preg_match('/^https?:\/\/(\w+\.)?(?:youtu\.be\/|youtube\.com\/(?:shorts\/|embed\/|watch\?v=))([a-zA-Z0-9\-_]{10,11}.+|\?t\=)$/i', $post['embed'], $matches)) {
					$post['youtube'] = $matches[2];
				}

				if (isset($post['files']) && $post['files']) {
					$files = json_decode($post['files']);

					if ($files[0]) {
						if ($files[0]->file == 'deleted') {
							if (count($files) > 1) {
								foreach ($files as $file) {
									if (($file == $files[0]) || ($file->file == 'deleted')) continue;
									$post['file'] = $config['uri_thumb'] . $file->thumb;
								}

								if (empty($post['file'])) $post['file'] = $config['root'] . $config['image_deleted'];
							}
							else {
								$post['file'] = $config['root'] . $config['image_deleted'];
							}
						}
						else if($files[0]->thumb == 'spoiler') {
							$post['file'] = $config['root'] . $config['spoiler_image'];
						}
						else {
							$post['file'] = $config['uri_thumb'] . $files[0]->thumb;
						}
					}
				} else {
					$post['file'] = $config['root'] . $config['image_deleted'];
				}

				if (empty($post['image_count'])) $post['image_count'] = 0;
				$post['pubdate'] = date('r', $post['time']);
				$posts[] = $post;
			}
		return $posts;
	}

	private function saveForBoard($board_name, $recent_posts, $isOverboard = false, $mod = false) {
		global $board, $config;


			if (is_array($board_name) && $isOverboard) {
				$boards = listBoards();
				$boardsforukko2 = [];
				foreach($boards as &$_board){
					if (isset($board_name['exclude'])){
						if(in_array($_board['uri'], explode(' ', $board_name['exclude'])))
							continue;
					}
					array_push($boardsforukko2, $_board);
				}

				$board['title'] = $board_name['title'];
				$board['uri'] = $board_name['uri'];
				$board['subtitle'] = $board_name['subtitle'];
				$board_name = $board_name['uri'];
			}

			// Antibot
			$antibot = create_antibot($board_name);
			$antibot->reset();


			$element = Element('themes/catalog/catalog.html', Array(
				'settings' => $this->settings,
				'config' => $config,
				'boardlist' => createBoardlist($mod),
				'recent_posts' => $recent_posts,
				'board' => $board,
				'antibot' => $antibot,
				'isukko' => $isOverboard,
				'boards' => isset($boardsforukko2) ? $boardsforukko2 : '',
				'mod' => $mod
			));


			if ($mod)
				echo $element;
			else {
			file_write($config['dir']['home'] . $board_name . '/catalog.html', $element);

			file_write($config['dir']['home'] . $board_name . '/index.rss', Element('themes/catalog/index.rss', Array(
				'config' => $config,
				'recent_posts' => $recent_posts,
				'board' => $board
			)));
		}}
	};
