<?php

class ShadowDelete
{
    /**
    * Query to update column status of table antispam.
    *
    * @param string $board board name a.k.a uri.
    * @param int $id id of post.
    * @param int $status new shadow status.
    * @return void
    */
    private static function dbUpdateAntispam(string $board, int $id, int $status): void
    {

        $query = prepare('UPDATE ``antispam`` SET `shadow` = :status WHERE `board` = :board AND `thread` = :thread');
        $query->bindValue(':board', $board);
        $query->bindValue(':thread', $id, PDO::PARAM_INT);
        $query->bindValue(':status', $status, PDO::PARAM_INT);
        $query->execute() or error(db_error($query));

    }

    /**
    * Query to establish if we are running on a post or thread.
    *
    * @param string $board board name a.k.a uri.
    * @param int $id id of post.
    * @param int $status current shadow status.
    * @return int|null [int] if it's a post. [null] if it's a thread.
    */
    public static function dbSelectThread(string $board, int $id, int $status = 0): int|null
    {

        $query = prepare(sprintf('SELECT `thread` FROM ``posts_%s`` WHERE `id` = :id AND `shadow` = :status', $board));
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->bindValue(':status', $status, PDO::PARAM_INT);
        $query->execute() or error(db_error($query));

        return $query->fetchColumn();

    }

    /**
    * Query to update column shadow of table cites.
    *
    * @param string $board board name a.k.a uri.
    * @param array $ids ids of posts.
    * @param int $status new shadow status.
    * @return void
    */
    private static function dbUpdateCiteStatus(string $board, array $ids, int $status): void
    {
        if (empty($ids)) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $query = prepare("
        	UPDATE 
				`cites`
        	SET 
				`shadow` = ?
        	WHERE (
            	(`target_board` = ? AND `target` IN ($placeholders)) 
            OR 
            	(`board` = ? AND `post` IN ($placeholders))
        	)");

        $params = array_merge([$status, $board], $ids, [$board], $ids);
        $query->execute($params) or error(db_error($query));
    }

    /**
    * Query to delete post shadow table entries
    *
    * @param string $board board name a.k.a uri.
    * @param int $id id of post.
    * @return void
    */
    private static function dbDeleteShadowPost(string $board, int $id): void
    {

        $delete_query = prepare(sprintf("DELETE FROM ``posts_%s`` WHERE `shadow` = 1 AND (`id` = :id OR `thread` = :id)", $board));
        $delete_query->bindValue(':id', $id, PDO::PARAM_INT);
        $delete_query->execute() or error(db_error($delete_query));

    }

    /**
    * Query to delete filehash entries for thread from filehash table
    *
    * @param string $board board name a.k.a uri.
    * @param int $id id of post.
    * @return void
    */
    private static function dbDeleteFilehash(string $board, int $id): void
    {

        $delete_query = prepare("DELETE FROM ``filehashes`` WHERE `shadow` = 1 AND (`thread` = :id OR `post` = :id) AND `board` = :board");
        $delete_query->bindValue(':id', $id, PDO::PARAM_INT);
        $delete_query->bindValue(':board', $board, PDO::PARAM_STR);
        $delete_query->execute() or error(db_error($delete_query));

    }

    /**
    * Query to delete delete temp antispam entry
    *
    * @param string $board board name a.k.a uri.
    * @param int $id id of post.
    * @return void
    */
    private static function dbDeleteAntispam(string $board, int $id): void
    {

        $delete_query = prepare('DELETE FROM ``antispam`` WHERE `shadow` = 1 AND `board` = :board AND `thread` = :thread');
        $delete_query->bindValue(':board', $board);
        $delete_query->bindValue(':thread', $id);
        $delete_query->execute() or error(db_error($delete_query));

    }

    /**
    * Query to delete delete temp cites entry
    *
    * @param string $board board name a.k.a uri.
    * @param array $ids ids of posts.
    * @return void
    */
    private static function dbDeleteCites(string $board, array $ids): void
    {

        $delete_query = prepare("DELETE FROM ``cites`` WHERE (`target_board` = :board AND (`target` = " . implode(' OR `target` = ', $ids) . ")) OR (`board` = :board AND (`post` = " . implode(' OR `post` = ', $ids) . "))");
        $delete_query->bindValue(':board', $board);
        $delete_query->execute() or error(db_error($delete_query));

    }

    /**
    * Hash filenames to obscure filenames
    *
    * @param string $filename original filename.
    * @param string $seed seed for hashing.
    * @return string hashed filename.
    */
    private static function hashShadowDelFilename(string $filename, string $seed): string
    {
        if (in_array($filename, ['deleted', 'spoiler', 'file'], true)) {
            return $filename;
        }

        $file = pathinfo($filename);
        return sha1($file['filename'] . $seed) . "." . ($file['extension'] ?? '');

    }

    /**
    * Hash filenames of a json string
    *
    * @param string $filename original json of filenames.
    * @param string $seed seed for hashing.
    * @return string modified json with hashed filenames.
    */
    public static function hashShadowDelFilenamesDBJSON(string $files_db_json, string $seed): string
    {

        $files_new = [];
        foreach (json_decode($files_db_json) as $f) {
            $f->file = self::hashShadowDelFilename($f->file, $seed);
            $f->thumb = self::hashShadowDelFilename($f->thumb, $seed);
            $files_new[] = $f;
        }
        return json_encode($files_new);

    }

    /**
    * Renames files and thumbnails unless marked as spoiler.
    *
    * @param bool $spoiler Indicates if the thumbnail is a spoiler.
    * @param string $originFile The original file path.
    * @param string $destinationFile The destination file path after rename.
    * @param string $originThumb The original thumbnail path.
    * @param string $destinationThumb The destination thumbnail path after rename.
    * @return void
    */
    private static function handleRename(bool $spoiler, string $originFile, string $destinationFile, string $originThumb, string $destinationThumb): void
    {
        if (file_exists($originFile)) {
            rename($originFile, $destinationFile);
        }

        if (!$spoiler && file_exists($originThumb)) {
            rename($originThumb, $destinationThumb);
        }

    }

    /**
    * Deletes files and thumbnails unless marked as spoiler.
    *
    * @param bool $spoiler Indicates if the thumbnail is a spoiler.
    * @param string $file The file path to delete.
    * @param string $thumb The thumbnail path to delete if not a spoiler.
    * @return void
    */
    private static function handleUnlink(bool $spoiler, string $file, string $thumb): void
    {
        if (file_exists($file)) {
            unlink($file);
        }

        if (!$spoiler && file_exists($thumb)) {
            unlink($thumb);
        }
    }

    /**
    * Handles file operations based on the operation type.
    *
    * Processes a JSON-encoded string of files to either delete, restore, or purge based on the operation.
    * It handles the renaming or unlinking of files and their respective thumbnails.
    *
    * @param string $files JSON-encoded string containing file data.
    * @param string $operation The type of operation to perform: 'delete', 'restore', or 'purge'.
    * @param array $config Configuration settings.
    * @param array $board Board-specific information.
    * @return void
    */
    private static function handleFiles(string $files, string $operation, array $config, array $board): void
    {

        foreach (json_decode($files) as $f) {
            if ($f->file !== 'deleted') {
                $originalFile = $board['dir'] . $config['dir']['img'] . $f->file;
                $originalThumb = $board['dir'] . $config['dir']['thumb'] . $f->thumb;

                $shadowFile = $board['dir'] . $config['dir']['shadow_del'] . $config['dir']['img'] . self::hashShadowDelFilename($f->file, $config['shadow_del']['filename_seed']);
                $shadowThumb = $board['dir'] . $config['dir']['shadow_del'] . $config['dir']['thumb'] . self::hashShadowDelFilename($f->thumb, $config['shadow_del']['filename_seed']);

                $spoilerThumb = $f->thumb === 'spoiler';

                switch ($operation) {
                    case 'delete':
                        self::handleRename($spoilerThumb, $originalFile, $shadowFile, $originalThumb, $shadowThumb);
                        break;
                    case 'restore':
                        self::handleRename($spoilerThumb, $shadowFile, $originalFile, $shadowThumb, $originalThumb);
                        break;
                    case 'purge':
                        self::handleUnlink($spoilerThumb, $shadowFile, $shadowThumb);
                        break;
                    default:
                        break;
                }
            }
        }
    }

    // Delete a post (reply or thread)
    public static function deletePost(int $id, bool $error_if_doesnt_exist = true, bool $rebuild_after = true)
    {
        global $board, $config;

        // Select post and replies (if thread) in one query
        $query = prepare(sprintf("SELECT `id`,`thread`,`files`,`slug` FROM ``posts_%s`` WHERE `shadow` = 0 AND (`id` = :id OR `thread` = :id)", $board['uri']));
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->execute() or error(db_error($query));

        if ($query->rowCount() < 1) {
            if ($error_if_doesnt_exist) {
                error($config['error']['invalidpost']);
            } else {
                return false;
            }
        }

        $ids = [];
        $files = [];

        // Temporarly Delete posts and maybe replies
        while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
            event('shadow-delete', $post);

            // If thread
            if (!$post['thread']) {
                deleteThread($board['dir'], $config['dir']['res'], $post);

                self::dbUpdateAntispam($board['uri'], $post['id'], 1);

            } elseif ($query->rowCount() == 1) {
                // Rebuild thread
                $rebuild = &$post['thread'];
            }

            if ($post['files']) {
                // Move files to temp storage
                self::handleFiles($post['files'], 'delete', $config, $board);
            }

            $ids[] = (int)$post['id'];
        }

        $thread_id = self::dbSelectThread($board['uri'], $id, 0);

        // Insert data into temp table
        $insert_query = prepare("INSERT INTO ``shadow_deleted`` (`board`, `post_id`, `del_time`, `files`, `cite_ids`) VALUES (:board, :post_id, :del_time, :files, :cite_ids)");
        $insert_query->bindValue(':board', $board['uri'], PDO::PARAM_STR);
        $insert_query->bindValue(':post_id', $id, PDO::PARAM_INT);
        $insert_query->bindValue(':del_time', time(), PDO::PARAM_INT);
        $insert_query->bindValue(':files', json_encode($files));
        $insert_query->bindValue(':cite_ids', json_encode($ids));
        $insert_query->execute() or error(db_error($insert_query));

        // Update post table entries
        $query = prepare(sprintf("UPDATE ``posts_%s`` SET `shadow` = 1 WHERE `id` = :id OR `thread` = :id", $board['uri']));
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->execute() or error(db_error($query));

        // Delete reports
        $query = prepare('DELETE FROM ``reports`` WHERE `post` = :post AND `board` = :board');
        $query->bindValue(':post', $id, PDO::PARAM_INT);
        $query->bindValue(':board', $board['uri'], PDO::PARAM_STR);
        $query->execute() or error(db_error($query));

        // Update filehash entries for thread from filehash table
        $query = prepare(sprintf("UPDATE ``filehashes`` SET `shadow` = 1 WHERE ( `thread` = :id OR `post` = :id ) AND `board` = '%s'", $board['uri']));
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->execute() or error(db_error($query));

        if (isset($thread_id)) {
            dbUpdateBumpOrder($board['uri'], $thread_id, $config['reply_limit']);
        }

        dbUpdateCiteLinks($board['uri'], $ids, true);

        self::dbUpdateCiteStatus($board['uri'], $ids, 1);

        if ($rebuild_after) {
            // Rebuild thread if post is deleted
            if (isset($rebuild)) {
                buildThread($rebuild);
            } else {
                buildIndex();
                rebuildThemes('post-delete', $board['uri']);
            }

            // Flush cache
            Cache::flush();
        }

        // If Thread ID is set return it (deleted post within thread) this will pe a positive number and thus viewed as true for legacy purposes
        if (isset($thread_id)) {
            return $thread_id;
        }

        return true;
    }

    // Delete a post (reply or thread)
    public static function restorePost(int $id, bool $error_if_doesnt_exist = true, bool $rebuild_after = true)
    {
        global $board, $config;

        // Select post and replies (if thread) in one query
        $query = prepare(sprintf("SELECT `id`,`thread`,`files`,`slug` FROM ``posts_%s`` WHERE `id` = :id OR `thread` = :id AND `shadow` = 1", $board['uri']));
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->execute() or error(db_error($query));

        if ($query->rowCount() < 1) {
            if ($error_if_doesnt_exist) {
                error($config['error']['invalidpost']);
            } else {
                return false;
            }
        }

        $ids = [];

        // Restore posts and maybe replies
        while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
            event('shadow-restore', $post);

            if (!$post['thread']) {
                self::dbUpdateAntispam($board['uri'], $post['id'], 0);
            }

            // Restore Files
            if ($post['files']) {
                // Move files from temp storage
                self::handleFiles($post['files'], 'restore', $config, $board);
            }

            $ids[] = (int)$post['id'];
        }

        $thread_id = self::dbSelectThread($board['uri'], $id, 1);

        // Delete data from temp table
        $insert_query = prepare("DELETE FROM ``shadow_deleted`` WHERE `board` = :board AND `post_id` = :id");
        $insert_query->bindValue(':board', $board['uri'], PDO::PARAM_STR);
        $insert_query->bindValue(':id', isset($post['id']) ? $post['id'] : $ids[0], PDO::PARAM_INT);
        $insert_query->execute() or error(db_error($insert_query));

        // Update temp post table into post table
        $insert_query = prepare(sprintf("UPDATE ``posts_%s`` SET `shadow` = 0 WHERE `id` = " . implode(' OR `id` = ', $ids), $board['uri'], $board['uri']));
        $insert_query->execute() or error(db_error($insert_query));

        // Update filehash table into temp filehash table
        $insert_query = prepare("UPDATE ``filehashes`` SET `shadow` = 0 WHERE `board` = :board AND (`post` = " . implode(' OR `post` = ', $ids) . ")");
        $insert_query->bindValue(':board', $board['uri'], PDO::PARAM_STR);
        $insert_query->execute() or error(db_error($insert_query));

        if (isset($thread_id)) {
            dbUpdateBumpOrder($board['uri'], $thread_id, $config['reply_limit']);
        }

        dbUpdateCiteLinks($board['uri'], $ids);

        if (isset($tmp_board)) {
            openBoard($tmp_board);
        }

        self::dbUpdateCiteStatus($board['uri'], $ids, 0);

        if ($rebuild_after) {
            // Rebuild thread (if post get thread id from `thread` field if OP rebuild ID)
            buildThread(isset($thread_id) ? $thread_id : $id);
            // If OP rebuild Catalog
            if (!isset($thread_id)) {
                buildIndex();
                rebuildThemes('post-thread', $board['uri']);
            }
        }

        buildIndex();

        // If Thread ID is set return it (deleted post within thread) this will pe a positive number and thus viewed as true for legacy purposes
        if (isset($thread_id)) {
            return $thread_id;
        }

        return true;
    }

    // Delete a post (reply or thread)
    public static function purgePost(int $id, bool $error_if_doesnt_exist = true)
    {
        global $board, $config;

        // Select post and replies (if thread) in one query
        $query = prepare(sprintf("SELECT `id`,`thread`,`files`,`slug` FROM ``posts_%s`` WHERE `shadow` = 1 AND (`id` = :id OR `thread` = :id)", $board['uri']));
        $query->bindValue(':id', $id, PDO::PARAM_INT);
        $query->execute() or error(db_error($query));

        if ($query->rowCount() < 1) {
            if ($error_if_doesnt_exist) {
                error($config['error']['invalidpost']);
            } else {
                return false;
            }
        }

        $ids = [];

        // Delete files
        while ($post = $query->fetch(PDO::FETCH_ASSOC)) {
            event('shadow-perm-delete', $post);
            if ($post['files']) {
                self::handleFiles($post['files'], 'purge', $config, $board);
            }

            $ids[] = (int)$post['id'];
        }

        // Delete data from temp table
        $insert_query = prepare("DELETE FROM ``shadow_deleted`` WHERE `board` = :board AND `post_id` = :id");
        $insert_query->bindValue(':board', $board['uri'], PDO::PARAM_STR);
        $insert_query->bindValue(':id', isset($post['id']) ? $post['id'] : $ids[0], PDO::PARAM_INT);

        $insert_query->execute() or error(db_error($insert_query));

        $thread_id = self::dbSelectThread($board['uri'], $id, 1);

        self::dbDeleteShadowPost($board['uri'], $id);

        self::dbDeleteFilehash($board['uri'], $id);

        self::dbDeleteAntispam($board['uri'], $id);

        self::dbDeleteCites($board['uri'], $ids);

        // If Thread ID is set return it (deleted post within thread) this will pe a positive number and thus viewed as true for legacy purposes
        if (isset($thread_id)) {
            return $thread_id;
        }

        return true;
    }

    // Delete a post (reply or thread)
    public static function purge()
    {
        global $config;

        // Delete data from temp table
        $query = prepare("SELECT * FROM ``shadow_deleted`` WHERE `del_time` < :del_time");
        $query->bindValue(':del_time', strtotime("-" . $config['shadow_del']['lifetime']), PDO::PARAM_INT);
        $query->execute() or error(db_error($query));

        // Temporarly Delete posts and maybe replies
        while ($shadow_post = $query->fetch(PDO::FETCH_ASSOC)) {
            event('shadow-perm-delete', $shadow_post);

            // Set Board Dir for Deletion
            $board['dir'] = sprintf($config['board_path'], $shadow_post['board']);

            // Delete files from temp storage
            self::handleFiles($shadow_post['files'], 'purge', $config, $board);

            self::dbDeleteShadowPost($shadow_post['board'], $shadow_post['post_id']);

            self::dbDeleteFilehash($shadow_post['board'], $shadow_post['post_id']);

            self::dbDeleteAntispam($shadow_post['board'], $shadow_post['post_id']);

            $ids = [];
            foreach (json_decode($shadow_post['cite_ids']) as $c) {
                $ids[] = $c;
            }

            self::dbDeleteCites($shadow_post['board'], $ids);
        }

        // Delete data from temp table
        $query = prepare("DELETE FROM ``shadow_deleted`` WHERE `del_time` < :del_time");
        $query->bindValue(':del_time', strtotime("-" . $config['shadow_del']['lifetime']), PDO::PARAM_INT);
        $query->execute() or error(db_error($query));

        return true;
    }

}
