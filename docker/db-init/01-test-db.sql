-- Samostatná databáze pro PHPUnit (WP test suite ji při každém běhu přeinstaluje).
CREATE DATABASE IF NOT EXISTS wordpress_test;
GRANT ALL PRIVILEGES ON wordpress_test.* TO 'wordpress'@'%';
