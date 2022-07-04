<?php
    $rootfolder = __DIR__;
    $finalroot = implode('/', array_slice(explode('/', $rootfolder, -3),0)).'/';
    copy($finalroot.'addons/twitch/installation/installed.txt', $finalroot.'addons/twitch/installed.txt');

    unlink($finalroot.'addons/twitch/main/fix.php')
?>