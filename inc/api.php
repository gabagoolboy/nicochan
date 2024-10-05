<?php
/*
 *  Copyright (c) 2010-2013 Tinyboard Development Group
 */

defined('TINYBOARD') or exit;

/**
 * Class for generating json API compatible with 4chan API
 */
class Api
{
    private array $config;
    private array $postFields;
    private array $threadsPageFields;
    private array $fileFields;

    public function __construct(array $config)
    {
        /**
         * Translation from local fields to fields in 4chan-style API
         */
        $this->config = $config;

        $this->postFields = [
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
            'board' => 'board'
        ];

        $this->threadsPageFields = [
            'id' => 'no',
            'bump' => 'last_modified',
            'board' => 'board'
        ];

        $this->fileFields = [
            'thumbheight' => 'tn_h',
            'thumbwidth' => 'tn_w',
            'height' => 'h',
            'width' => 'w',
            'size' => 'fsize',
        ];

        if (isset($this->config['api']['extra_fields']) && gettype($this->config['api']['extra_fields']) == 'array') {
            $this->postFields = array_merge($this->postFields, $this->config['api']['extra_fields']);
        }
    }

    private static $ints = [
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
    ];

    private function translateFields(array $fields, object $object, array &$apiPost): void
    {
        foreach ($fields as $local => $translated) {
            if (!isset($object->$local)) {
                continue;
            }

            $toInt = isset(self::$ints[$translated]);
            $val = $object->$local;
            if ($val !== null && $val !== '') {
                $apiPost[$translated] = $toInt ? (int) $val : $val;
            }

        }
    }

    private function translateFile(object $file, object $post, array &$apiPost): void
    {
        $this->translateFields($this->fileFields, $file, $apiPost);
        $dotPos = strrpos($file->file, '.');

        if ($this->config['show_filename']) {
            $apiPost['filename'] = @substr($file->name, 0, strrpos($file->name, '.'));
        } else {
            $apiPost['filename'] = @substr($file->file, 0, $dotPos);
        }

        $apiPost['ext'] = substr($file->file, $dotPos);
        $apiPost['tim'] = substr($file->file, 0, $dotPos);
        $apiPost['full_path'] = $this->config['dir']['media'] . $file->file;

        // Add spoiler flag to API data
        if (isset($file->thumb)) {
            if ($file->thumb == 'spoiler') {
                $apiPost['spoiler'] = 1;
            } else {
                $apiPost['thumb_path'] = $this->config['dir']['media'] . $file->thumb; 
                $apiPost['spoiler'] = 0;
            }
        }

        if (isset($file->hash) && $file->hash) {
            $apiPost['md5'] = base64_encode($file->hash);
        } elseif (isset($post->filehash) && $post->filehash) {
            $apiPost['md5'] = base64_encode($post->filehash);
        }
    }

    private function translatePost(object $post, bool $threadsPage = false, bool $hideposterid = false): array
    {
        $apiPost = [];
        $fields = $threadsPage ? $this->threadsPageFields : $this->postFields;
        $this->translateFields($fields, $post, $apiPost);

        if (!$hideposterid && isset($this->config['poster_ids']) && $this->config['poster_ids']) {
            $apiPost['id'] = poster_id($post->ip, $post->thread ?? $post->id);
        }

        if ($threadsPage) {
            return $apiPost;
        }

        if (isset($post->embed)) {
            $apiPost['embed'] = $post->embed_url;
            $apiPost['embed_title'] = $post->embed_title;
        }

        if (isset($post->flag_iso, $post->flag_ext)) {
            $apiPost['country'] = $post->flag_iso;
            $apiPost['country_name'] = $post->flag_ext;
        }

        // Handle ban/warning messages
        if (isset($post->body_nomarkup, $post->modifiers)) {
            if (isset($post->modifiers['warning message'])) {
                $apiPost['warning_msg'] = $post->modifiers['warning message'];
            }
            if (isset($post->modifiers['ban message'])) {
                $apiPost['ban_msg'] = str_replace('<br>', '; ', $post->modifiers['ban message']);
            }
        }

        if ($this->config['slugify'] && !$post->thread) {
            $apiPost['semantic_url'] = $post->slug;
        }

        // Handle files
        // Note: 4chan only supports one file, so only the first file is taken into account for 4chan-compatible API.
        if (isset($post->files) && $post->files && !$threadsPage) {
            $this->handleFiles($post, $apiPost);
        }

        return $apiPost;
    }

    private function handleFiles(object $post, array &$apiPost): void
    {
        $file = $post->files[0];
        $this->translateFile($file, $post, $apiPost);
        if (sizeof($post->files) > 1) {
            $extra_files = [];
            foreach ($post->files as $i => $f) {
                if ($i == 0) {
                    continue;
                }

                $extra_file = [];
                $this->translateFile($f, $post, $extra_file);

                $extra_files[] = $extra_file;
            }
            $apiPost['extra_files'] = $extra_files;
        }
    }

    public function translateThread(Thread $thread, bool $threadsPage = false): array
    {

        $apiPosts = [];
        $op = $this->translatePost($thread, $threadsPage, $thread->hideid);
        if (!$threadsPage) {
            $op['resto'] = 0;
        }
        $apiPosts['posts'][] = $op;

        foreach ($thread->posts as $p) {
            $apiPosts['posts'][] = $this->translatePost($p, $threadsPage, $thread->hideid);
        }
        if (!$thread->hideid) {
            // Count unique IPs
            $ips = [$thread->ip];
            foreach ($thread->posts as $p) {
                $ips[] = $p->ip;
            }
            $apiPosts['posts'][0]['unique_ids'] = count(array_unique($ips));
        }

        return $apiPosts;
    }

    public function translatePage(array $threads): array
    {
        $apiPage = [];
        foreach ($threads as $thread) {
            $apiPage['threads'][] = $this->translateThread($thread);
        }
        return $apiPage;
    }

    public function translateCatalogPage(array $threads, bool $threadsPage = false): array
    {
        $apiPage = [];
        foreach ($threads as $thread) {
            $ts = $this->translateThread($thread, $threadsPage);
            $apiPage['threads'][] = current($ts['posts']);
        }
        return $apiPage;
    }

    public function translateCatalog(array $catalog, bool $threadsPage = false): array
    {
        $apiCatalog = [];
        foreach ($catalog as $page => $threads) {
            $apiPage = $this->translateCatalogPage($threads, $threadsPage);
            $apiPage['page'] = $page;
            $apiCatalog[] = $apiPage;
        }

        return $apiCatalog;
    }
}
