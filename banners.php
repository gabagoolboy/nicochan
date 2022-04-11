<?php
// This script assumes there is at least one normal (non-priority)
// banner!
$board = (string)htmlspecialchars($_GET['board']);
// Get the files in a directory, returns null if the directory does
// not exist.
function getFilesInDirectory($dir) {
    if (! is_dir($dir)) {
        return null;
    }

    return array_diff(scandir($dir), array('.', '..'));
}

// Serve a random banner and exit.
function serveRandomBanner($dir, $files) {
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

// Get all the banners
$bannerDir = "static/banners/".$board.'/';
$priorityDir = "static/banners_priority/";

$banners = getFilesInDirectory($bannerDir);
$priority = getFilesInDirectory($priorityDir);

/* If there is any file in priority dir, serve them. This is used because the file is being accessed thru direct access.
The if is rather dumb to be honest because i couldnt make to work with the last if
 */
if($priority !== null && count($priority) !== 0 && (!isset($_SERVER['HTTP_REFERER']))){
	serveRandomBanner($priorityDir, $priority);
}elseif(!isset($_SERVER['HTTP_REFERER'])){
    		header('location: /');
    		exit;
}

// If there are priority banners, serve 1/3rd of the time.
if($priority !== null && count($priority) !== 0 && rand(0,2) === 0 || (!isset($board)) || $board === 'overboard')
    serveRandomBanner($priorityDir, $priority);

if(isset($board))
	serveRandomBanner($bannerDir, $banners);

?>
