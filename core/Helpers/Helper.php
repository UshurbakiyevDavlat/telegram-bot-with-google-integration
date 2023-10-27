<?php

namespace App\Helpers;

use JetBrains\PhpStorm\NoReturn;

class Helper
{
    /**
     * Dump and die
     *
     * @param mixed $data - data to dump
     * @return void
     */
    #[NoReturn] public static function dd(mixed $data): void
    {
        if (is_array($data)) {
            echo '<pre>';
            print_r($data);
            echo '</pre>';
            die;
        }

        echo '<pre>';
        var_dump($data);
        echo '</pre>';
        die;
    }

    /**
     * Helper function to log reports
     *
     * @param $message
     * @param array $context
     * @return void
     */
    public static function logReport($message, array $context = []): void
    {
        $logFile = __DIR__ . env('LOG_FILE') ?? '/../../log.txt';
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message"
            . (!empty($context)
                ? ' - ' . json_encode($context)
                : '') . "\n";

        // Open the log file in append mode and write the log entry
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }

    /**
     * Helper function to generate random string
     *
     * @return string
     */
    public static function generatePhotoName(): string
    {
        $randomString = substr(
            str_shuffle(
                str_repeat(
                    $x = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
                    ceil(10 / strlen($x)),
                ),
            ),
            1,
            10,
        );

        return 'uploaded_photo' . $randomString . '.jpg';
    }
}
