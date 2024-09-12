<?php
require_once 'info.php';

function index_build($action, $settings) {
    $builder = new IndexBuilder();
    $builder->build($action, $settings);
}

class IndexBuilder {
    private $stats = [];
    private $settings;

    public function build($action, $settings) {
        $this->settings = $settings;

        if ($action === 'all') {
            $this->copyBaseCSS();
        }

        if (in_array($action, ['all', 'news', 'post', 'post-thread', 'post-delete'])) {
            $this->generateHomepage();
        }
    }

    private function copyBaseCSS() {
        global $config;
        copy('templates/themes/index/' . $this->settings['basecss'], $config['dir']['home'] . $this->settings['css']);
    }

    private function generateHomepage() {
        global $config;
        $homepageContent = $this->homepage();
        file_write($config['dir']['home'] . $this->settings['html'], $homepageContent);
    }

    private function homepage() {
        global $config;

		$this->populateStats();
        $news = $this->getNews();

        return Element('themes/index/index.html', [
            'settings' => $this->settings,
            'config' => $config,
            'boardlist' => createBoardlist(),
            'stats' => $this->stats,
            'news' => $news,
        ]);
    }

    private function populateStats() {
        global $config;

        $boards = listBoards();

        if ($config['cache']['enabled']) {
            $this->stats = Cache::get('stats_homepage') ?: [];
        }

        if (empty($this->stats)) {
            $this->stats['total_posts'] = $this->getTotalPosts($boards);
            $this->stats['unique_posters'] = $this->getUniquePosters($boards);
            $this->stats['active_content'] = $this->getActiveContent($boards);
            $this->stats['total_bans'] = $this->getTotalBans();
            $this->stats['boards'] = $this->getBoardsList($boards);
            $this->stats['update'] = twig_strftime_filter(time(), 'dd/MM/yyyy HH:mm:ss');

            Cache::set('stats_homepage', $this->stats, 3600);
        }
    }

    private function getTotalPosts($boards) {
        $query = 'SELECT SUM(`top`) FROM (';
        foreach ($boards as $_board) {
            $query .= sprintf("SELECT MAX(`id`) AS `top` FROM ``posts_%s`` WHERE `shadow` = 0 UNION ALL ", $_board['uri']);
        }
        $query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
        $result = query($query) or error(db_error());
        return number_format($result->fetchColumn());
    }

    private function getUniquePosters($boards) {
        $query = 'SELECT COUNT(DISTINCT(`ip`)) FROM (';
        foreach ($boards as $_board) {
            $query .= sprintf("SELECT `ip` FROM ``posts_%s`` WHERE `shadow` = 0 UNION ALL ", $_board['uri']);
        }
        $query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
        $result = query($query) or error(db_error());
        return number_format($result->fetchColumn());
    }

    private function getActiveContent($boards) {
        $query = 'SELECT DISTINCT(`files`) FROM (';
        foreach ($boards as $_board) {
            $query .= sprintf("SELECT `files` FROM ``posts_%s`` WHERE `num_files` > 0 AND `shadow` = 0 UNION ALL ", $_board['uri']);
        }
        $query = preg_replace('/UNION ALL $/', ') AS `posts_all`', $query);
        $result = query($query) or error(db_error());

        $files = $result->fetchAll();
        $totalFiles = 0;
        $activeContent = 0;

    	foreach ($files as $file) {
        	if (isset($file[0]) && is_string($file[0])) {
         		preg_match_all('/"size":([\d]*)/', $file[0], $matches);
            	preg_match_all('/"file":("[\d]+)/', $file[0], $matchFile);

            	if (!empty($matchFile[0])) {
             		$totalFiles += count($matchFile[0]);
            	}

            	$activeContent += array_sum($matches[1]);
        	}
    	}

        $this->stats['total_files'] = number_format($totalFiles);
        return $activeContent;
    }

    private function getTotalBans() {
        $query = query("SELECT COUNT(1) FROM ``bans`` WHERE `expires` > UNIX_TIMESTAMP() OR `expires` IS NULL");
        return $query->fetchColumn();
    }

	private function getBoardsList($boards) {
    	$boardList = [];
    
    	foreach ($boards as $board) {
        	if (isset($board['uri'])) {
            	$boardInfo = getBoardInfo($board['uri']);
            
            	if ($boardInfo) {
                	$boardList[] = [
                    	'title' => $boardInfo['title'],
                    	'uri' => $boardInfo['uri'],
                	];
            	}
        	}
    	}

    	$boardList[] = ['title' => 'Overboard', 'uri' => 'overboard'];

    	return $boardList;
	}

    private function getNews() {
        $limit = $this->settings['no_recent'] ? ' LIMIT ' . (int)$this->settings['no_recent'] : '';
        $query = query("SELECT * FROM ``news`` ORDER BY `time` DESC $limit") or error(db_error());
        return $query->fetchAll(PDO::FETCH_ASSOC);
    }
}

