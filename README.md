Nicochan
========================================================

There is a handful of themes I didn't tested bc we don't use them.
This includes: categories, index, js_frameset, recent, basic, frameset

Requirements
------------
1.	PHP >= 7.3 (PHP 8.0 is working quite well)
2.	MySQL >= 5.6 or MariaDB
3.	[mbstring](http://www.php.net/manual/en/mbstring.installation.php)
4.	[PHP GD](http://www.php.net/manual/en/intro.image.php)
5.	[PHP PDO](http://www.php.net/manual/en/intro.pdo.php)
6.	A Unix-like OS, preferrably FreeBSD or Linux

We try to make sure vichan is compatible with all major web servers. vichan does not include an Apache ```.htaccess``` file nor does it need one.

### Recommended
2.	ImageMagick (command-line ImageMagick or GraphicsMagick preferred).
3.	[APC (Alternative PHP Cache)](http://php.net/manual/en/book.apc.php),
	[XCache](http://xcache.lighttpd.net/) or
	[Memcached](http://www.php.net/manual/en/intro.memcached.php)

Installation
-------------
1.	Download and extract Tinyboard to your web directory or get the latest
	development version with:

        git clone git://github.com/perdedora/nicochan.git

2.	run ```composer install``` inside the directory
3.	Navigate to ```install.php``` in your web browser and follow the
	prompts.
4.	Nicochan should now be installed. Log in to ```mod.php``` with the
	default username and password combination: **admin / password**.

Please remember to **change** the account password.

See also: [Configuration Basics](https://github.com/fallenPineapple/NPFchan/wiki/config).

Upgrade
-------
To upgrade from any version of Tinyboard or vichan or NFPchan or Nicochan:

Either run ```git pull``` to update your files if you use git, or replace all
your files in place (don't remove boards etc.) and then run ```install.php```.

IF YOU'RE UPGRADING FROM ANOTHER VICHAN/NPFCHAN INSTANCE, YOU HAVE TO RUN THE UPDATE SCRIPTS IN THE FOLDER "tools"

To migrate from a Kusaba X board, use http://github.com/vichan-devel/Tinyboard-Migration

CLI tools
-----------------
There are a few command line interface tools, based on Tinyboard-Tools. These need
to be launched from a Unix shell account (SSH, or something). They are located in a ```tools/```
directory.

You actually don't need these tools for your imageboard functioning, they are aimed
at the power users. You won't be able to run these from shared hosting accounts
(i.e. all free web servers).

WebM support
------------
Read `inc/lib/webm/README.md` for information about enabling webm.

License
--------
See [LICENSE.md](http://github.com/perdedora/nicochan/blob/master/LICENSE.md).
