<?php
    function Uninstall()
    {
        $db = MysqliDb::getInstance();

        Permissions::Remove('settings_addstreamer');

        $db->rawQuery('DROP TABLE `gex_twitch`');

        $rootfolder = __DIR__;
        $finalroot = implode('/', array_slice(explode('/', $rootfolder, -2),0)).'/';

        copy($finalroot.'addons/twitch/backup/index_backup.php', $finalroot.'index.php'); 
        Redirect('index.php');
        return true;
    }
?>