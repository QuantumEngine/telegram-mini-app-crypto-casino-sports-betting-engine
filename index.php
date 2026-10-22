<?php
/**
 * Mint Scripts • Telegram Mini App Crypto Casino & Sportsbook Engine
 *
 * @package     MintScripts\iGaming
 * @author      Mint Scripts Technology Lab <dev@mintscripts.net>
 * @copyright   (c) 2026 Mint Scripts. All rights reserved.
 * @license     Proprietary / B2B Commercial License
 * @link        https://mintscripts.net
 * @version     2.4.0-HYBRID
 */

declare(strict_types=1);

namespace MintScripts\Engine;

// Define System Constants
define('MS_START_TIME', microtime(true));
define('MS_ROOT_DIR', __DIR__);
define('MS_REQUIRED_PHP', '8.2.0');

// Environment & PHP Compatibility Check
if (version_compare(PHP_VERSION, MS_REQUIRED_PHP, '<')) {
    http_response_code(500);
    exit(sprintf('Mint Scripts Engine requires PHP %s or higher. Current version: %s', MS_REQUIRED_PHP, PHP_VERSION));
}

// Security & Telegram WebApp Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: ALLOW-FROM https://web.telegram.org');
header('X-XSS-Protection: 1; mode=block');
header('Content-Type: application/json; charset=utf-8');

/**
 * Telegram WebApp Native Authentication Controller
 */
class TelegramAuthHandler
{
    private string $botToken;

    public function __construct(string $botToken)
    {
        $this->botToken = $botToken;
    }

    /**
     * Authenticate and Register User via Telegram WebApp initData
     */
    public function handleLogin(string $initData): array
    {
        if (!$this->validateInitData($initData)) {
            http_response_code(401);
            return [
                'status'  => 'error',
                'message' => 'Invalid Telegram WebApp authentication signature.'
            ];
        }

        // Parse query string data
        parse_str($initData, $parsedData);
        $userData = json_decode($parsedData['user'] ?? '{}', true);

        if (empty($userData['id'])) {
            http_response_code(400);
            return [
                'status'  => 'error',
                'message' => 'User payload data is missing or corrupted.'
            ];
        }

        // Register or sync user in DB (Mocked Object for Engine Sample)
        $user = $this->syncUser([
            'telegram_id' => $userData['id'],
            'username'    => $userData['username'] ?? null,
            'first_name'  => $userData['first_name'] ?? 'Player',
            'last_name'   => $userData['last_name'] ?? null,
            'photo_url'   => $userData['photo_url'] ?? null,
            'language'    => $userData['language_code'] ?? 'en'
        ]);

        return [
            'status' => 'success',
            'data'   => [
                'user'            => $user,
                'access_token'    => bin2hex(random_bytes(32)),
                'auth_type'       => 'telegram_initdata_native',
                'execution_time'  => round((microtime(true) - MS_START_TIME) * 1000, 2) . 'ms'
            ]
        ];
    }

    /**
     * HMAC-SHA256 Signature Validation for Telegram initData
     */
    private function validateInitData(string $initData): bool
    {
        parse_str($initData, $data);
        if (!isset($data['hash'])) {
            return false;
        }

        $hash = $data['hash'];
        unset($data['hash']);

        // Sort parameters alphabetically
        ksort($data);

        $dataCheckArr = [];
        foreach ($data as $key => $value) {
            $dataCheckArr[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $dataCheckArr);

        // Compute HMAC Secret Key and Data Hash
        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($calculatedHash, $hash);
    }

    /**
     * User Synchronization Logic Placeholder
     */
    private function syncUser(array $tgUser): array
    {
        return [
            'id'           => 1024,
            'telegram_id'  => $tgUser['telegram_id'],
            'username'     => $tgUser['username'] ?? 'player_' . $tgUser['telegram_id'],
            'first_name'   => $tgUser['first_name'],
            'avatar'       => $tgUser['photo_url'],
            'balance'      => [
                'USDT'     => '0.000000',
                'TON'      => '0.000000'
            ],
            'vip_tier'     => 'Bronze',
            'created_at'   => date('Y-m-d H:i:s')
        ];
    }
}

// Dummy Bot Token for Repository Demonstration
$botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '7777777777:AAEE_MintScriptsDemoEngineSecretKey';
$handler = new TelegramAuthHandler($botToken);

// Process incoming WebApp request
$rawInitData = $_POST['initData'] ?? $_GET['initData'] ?? '';

if (empty($rawInitData)) {
    echo json_encode([
        'engine'       => 'Mint Scripts iGaming Core',
        'status'       => 'active',
        'version'      => '2.4.0-HYBRID',
        'auth_endpoint' => '/index.php?initData={TELEGRAM_INIT_DATA}'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode($handler->handleLogin($rawInitData), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
