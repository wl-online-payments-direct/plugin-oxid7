<?php
require_once dirname(__FILE__) . "/../../../source/bootstrap.php";

function canRunCronjob() {
    if(empty($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['HTTP_USER_AGENT']) && count($_SERVER['argv']) > 0) {
        // is called by php cli
        return true;
    }

    return false;
}

if (canRunCronjob() === false) {
    die('Permission denied');
}

$iShopId = false;
if (isset($argv[1]) && is_numeric($argv[1])) {
    $iShopId = $argv[1];
}

$oScheduler = oxNew(\FC\FCWLOP\Application\Model\Cronjob\FcwlopCronScheduler::class);
$oScheduler->start($iShopId);