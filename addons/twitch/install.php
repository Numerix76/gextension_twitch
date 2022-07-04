<?php
    function Install()
    {
        $db = MysqliDb::getInstance();

        Permissions::Add('settings_addstreamer');

        if (!sizeof($db->rawQuery("SHOW TABLES LIKE 'gex_twitch'"))) {
            $db->rawQuery('
                CREATE TABLE `gex_twitch` (
                  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                  `url` text NOT NULL,
                  `name` text NOT NULL
                ) DEFAULT CHARSET=latin1;
            ');
        }

        $rootfolder = __DIR__;
        $finalroot = implode('/', array_slice(explode('/', $rootfolder, -2),0)).'/';

        if (file_exists ($finalroot.'addons/twitch/backup/index_backup.php')) {
            rename($finalroot.'addons/twitch/backup/index_backup.php', $finalroot.'index.php');
        };

        copy($finalroot.'index.php', $finalroot.'addons/twitch/backup/index_backup.php');
        copy($finalroot.'addons/twitch/installation/index.php', $finalroot.'index.php');
        copy($finalroot.'addons/twitch/installation/live_main.php', $finalroot.'addons/twitch/main/live_main.php');
        copy($finalroot.'addons/twitch/installation/fix.php', $finalroot.'addons/twitch/main/fix.php');
        copy($finalroot.'addons/twitch/installation/fr.php', $finalroot.'addons/twitch/language/fr.php');
        copy($finalroot.'addons/twitch/installation/settings_live.php', $finalroot.'addons/twitch/settings/settings_live.php');
        copy($finalroot.'addons/twitch/installation/live.php', $finalroot.'addons/twitch/pages/live.php');
        
        return true;
    }
?>