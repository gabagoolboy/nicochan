<?php

require dirname(__FILE__) . '/inc/cli.php';

$query = prepare("SELECT DISTINCT `text` FROM ``modlogs`` WHERE `text` REGEXP '\\\\?\\\\/IP\\\\/[a-zA-Z0-9]+\"'");
$query->execute() or $sql_errors .= "posts_*\n" . db_error();
while($entry = $query->fetchColumn()) {
	$update_query = prepare("UPDATE ``modlogs`` SET `text` = :text WHERE `text` = :text_org");
	$update_query->bindValue(':text', preg_replace('/\?\/IP\/([a-zA-Z0-9]+)"/', '?/IP/$1/page/1"', $entry));
	$update_query->bindValue(':text_org', $entry);
	$update_query->execute() or $sql_errors .= "Alter posts_*\n" . db_error();
}
