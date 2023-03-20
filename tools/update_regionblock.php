<?php

require dirname(__FILE__) . '/inc/cli.php';

query('ALTER TABLE ``whitelist_region`` ADD `token` varchar(12) NOT NULL AFTER `ip_hash`;');
