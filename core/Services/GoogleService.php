<?php

namespace App\Services;

use App\Helpers\Helper;
use Google\Exception;
use Google_Client;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;
use Illuminate\Support\Collection;

class GoogleService
{
    private Google_Service_Sheets $sheetService;

    private string $spreadsheetId;

    private Google_Service_Drive $driveService;

    private string $imageName;

    /**
     * @constructor
     * @param array $googleAccountFilePath - путь к файлу с учетными данными Google
     * @param string $spreadsheetId - ID Google таблицы
     */
    public function __construct(
        array $googleAccountFilePath,
        string $spreadsheetId,
    ) {
        // Инициализация Google Sheets
        $sheetClient = $this->initSheetService($googleAccountFilePath['sheet']);

        $this->sheetService = new Google_Service_Sheets($sheetClient);
        $this->spreadsheetId = $spreadsheetId;

        // Инициализация Google Drive
        $driveClient = $this->initDriveService($googleAccountFilePath['drive']);

        $this->driveService = new Google_Service_Drive($driveClient);
    }

    /**
     * Загрузка фото в Google Drive
     *
     * @param $photoUrl - URL фото
     * @return void
     */
    public function photoToDrive($photoUrl): void
    {
        $folder = getenv('GOOGLE_DRIVE_FOLDER_ID');
        $drive_export_link = getenv('GOOGLE_DRIVE_EXPORT_FILE_LINK');
        $this->imageName = Helper::generatePhotoName();

        $fileMetadata = new Google_Service_Drive_DriveFile([
            'name' => $this->imageName,
            'parents' => [$folder],
        ]);

        $response = $this->driveService->files->create(
            $fileMetadata,
            [
                'data' => file_get_contents($photoUrl),
                'mimeType' => 'image/jpeg', // TODO - сделать определение MIME-типа
            ],
        );

        $this->imageName = $drive_export_link . $response->getId();
    }

    /**
     * Запись в Google Sheets
     *
     * @param string $photoFilePath - путь к файлу на Google Drive
     * @param Collection $message
     * @return void
     */
    public function recordToSheet(string $photoFilePath, Collection $message, ?string $address = 'empty'): void
    {
        // Запись в Google Sheets
        $values = [
            [
                $message->from?->first_name,
                $address,
                $this->imageName ?? 'uploaded image',
                date('d.m.Y H:i:s'),
            ],
        ];

        $body = new Google_Service_Sheets_ValueRange([
            'values' => $values,
        ]);

        $this->sheetService->spreadsheets_values->append(
            $this->spreadsheetId,
            'A1', // TODO - сделать константу
            $body,
            [
                'valueInputOption' => 'RAW', // TODO - сделать константу
            ],
        );
    }

    /**
     * Инициализация Google Sheets
     *
     * @param string $googleAccountFilePath - путь к файлу с учетными данными Google
     * @return Google_Client
     */
    private function initSheetService(string $googleAccountFilePath): Google_Client
    {
        $client = new Google_Client();

        try {
            $client->setAuthConfig($googleAccountFilePath);
        } catch (Exception $e) {
            throw new \RuntimeException(
                'Error while creating Google Client: ' // TODO - сделать экспешн класс
                . $e->getMessage()
            );
        }

        $client->setApplicationName('Google Sheets and PHP');
        $client->setAccessType('offline');

        $client->setScopes(
            [
                Google_Service_Sheets::SPREADSHEETS,
            ],
        );

        return $client;
    }

    /**
     * Инициализация Google Drive
     *
     * @param string $googleAccountFile - путь к файлу с учетными данными Google
     * @return Google_Client
     */
    private function initDriveService(string $googleAccountFile): Google_Client
    {
        $client = new Google_Client();
        try {
            $client->setAuthConfig($googleAccountFile);
        } catch (Exception $e) {
            throw new \RuntimeException(
                'Error while creating Google Client: ' // TODO - сделать экспешн класс
                . $e->getMessage()
            );
        }

        $client->setScopes(
            [
                Google_Service_Drive::DRIVE,
            ],
        );

        return $client;
    }
}
