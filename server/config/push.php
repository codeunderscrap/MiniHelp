<?php
// config/push.php — Web Push (VAPID) keys and sending.
// The key pair comes from MINIHELP_VAPID_PUBLIC_KEY / MINIHELP_VAPID_PRIVATE_KEY when both are set.
// Otherwise one pair is generated on first use and kept in the app_secrets table, so the private
// key never lives in the repository.

require_once __DIR__ . '/../vendor/autoload.php';

use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

function vapid_keys(PDO $db): array {
    static $keys = null;
    if ($keys !== null) return $keys;

    $pub = getenv('MINIHELP_VAPID_PUBLIC_KEY');
    $priv = getenv('MINIHELP_VAPID_PRIVATE_KEY');
    if ($pub && $priv) {
        return $keys = ['publicKey' => $pub, 'privateKey' => $priv];
    }

    $db->exec("CREATE TABLE IF NOT EXISTS app_secrets (
        name VARCHAR(64) PRIMARY KEY,
        value VARCHAR(255) NOT NULL
    )");

    $stored = $db->query("SELECT value FROM app_secrets WHERE name = 'vapid'")->fetchColumn();
    if (!$stored) {
        $new = VAPID::createVapidKeys();
        $stmt = $db->prepare("INSERT IGNORE INTO app_secrets (name, value) VALUES ('vapid', :v)");
        $stmt->execute([':v' => $new['publicKey'] . '.' . $new['privateKey']]);
        if ($stmt->rowCount() === 1) {
            // Existing subscriptions were made against the previous public key and can no longer
            // be delivered to; browsers re-subscribe with the new key on their next visit.
            $db->exec("DELETE FROM push_subscriptions");
        }
        $stored = $db->query("SELECT value FROM app_secrets WHERE name = 'vapid'")->fetchColumn();
    }

    [$pub, $priv] = explode('.', $stored, 2);
    return $keys = ['publicKey' => $pub, 'privateKey' => $priv];
}

// Best effort: sends $payload to each push_subscriptions row and drops subscriptions the push
// service reports as gone. Never throws.
function send_push(PDO $db, array $subs, string $payload): void {
    if (!$subs) return;
    try {
        $webPush = new WebPush(['VAPID' => ['subject' => 'mailto:admin@minimines.com'] + vapid_keys($db)]);
        foreach ($subs as $sub) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $sub['endpoint'],
                'keys' => ['p256dh' => $sub['p256dh'], 'auth' => $sub['auth']],
            ]), $payload);
        }
        $gone = $db->prepare("DELETE FROM push_subscriptions WHERE endpoint = :e");
        foreach ($webPush->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                $gone->execute([':e' => $report->getEndpoint()]);
            }
        }
    } catch (\Throwable $e) {
        error_log("Push Notification Error: " . $e->getMessage());
    }
}
