<?php
require_once 'inc/bootstrap.php';
class Banners
{
	public $bannerDir = 'static/banners/%s/';
	public $priorityDir = 'static/banners_priority/';
	public $board;
	public $ukko = 'overboard';

	public function __construct(string $board) {
		$this->board = $board;
	}

	private function getFilesInDirectory(string $dir): array | null {

		if (!is_dir($dir))
			return null;

		if (!$listFiles = Cache::get("files_{$dir}")) {
			$listFiles = array_diff(scandir($dir, SCANDIR_SORT_NONE), array('.', '..'));
			Cache::set("files_{$dir}", $listFiles, 10800);
		}

		return $listFiles;
	}

	private function serveRandomBanner(string $dir, array $files): void {

		$name = $files[array_rand($files)];

		// snags the extension
		$ext = pathinfo($name, PATHINFO_EXTENSION);

		// send the right headers
		header("Content-type: image/" . $ext);
		header("Content-Length" . filesize($dir.$name));

		// readfile displays the image, passthru seems to spits stream.
		readfile($dir.$name);
		exit;
	}

	public function serve() {

		$priority = $this->getFilesInDirectory($this->priorityDir);
		$this->bannerDir = sprintf($this->bannerDir, $this->board);
		$banners = $this->getFilesInDirectory($this->bannerDir);

		if (!is_null($priority) && (is_countable($priority) ? count($priority) : 0) !== 0 && mt_rand(0,3) === 0 || is_null($banners))
			$this->serveRandomBanner($this->priorityDir, $priority);

		$this->serveRandomBanner($this->bannerDir, $banners);

	}
}
	$b = new Banners((string)htmlspecialchars($_GET['board'] ?? 'overboard'));
	$b->serve();
