<?php

require_once __DIR__ . '/../bootstrap.php';

use App\Services\GoogleService;
use App\Services\TelegramService;
use Telegram\Bot\Exceptions\TelegramSDKException;

$telegramToken = getenv('TELEGRAM_API_TOKEN');
$googleAccountFilePath = [
    'sheet' => __DIR__ . getenv('GOOGLE_SERVICE_SHEET_ACCOUNT_FILE'),
    'drive' => __DIR__ . getenv('GOOGLE_SERVICE_DRIVE_ACCOUNT_FILE'),
];

$googleSpreadSheetId = getenv('GOOGLE_SHEETS_SPREADSHEET_ID');

$googleService = new GoogleService($googleAccountFilePath, $googleSpreadSheetId);

try {
    $telegramService = new TelegramService($telegramToken, $googleService);
    $telegramService->setWebhook();
} catch (TelegramSDKException $e) {
    throw new \RuntimeException(
        'Error while creating Telegram API instance: ' // TODO - сделать экспешн класс
        . $e->getMessage()
    );
}

try {
    $telegramService->operateMessages();
} catch (TelegramSDKException $e) {
    throw new \RuntimeException(
        'Error while operating Telegram messages: ' // TODO - сделать экспешн класс
        . $e->getMessage()
    );
}

