<?php
require 'vendor/autoload.php';
use Minishlink\WebPush\VAPID;
$keys = VAPID::createVapidKeys();
echo json_encode($keys);
?>
