<?php

namespace App\Services;

use App\Helpers\Helper;
use Exception;
use Illuminate\Support\Collection;
use RuntimeException;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\Objects\Update;
use Telegram\Bot\Objects\User;

class TelegramService
{
    private Api $telegram;

    private Update $update;

    private Collection $message;

    private $chat;

    private $text;

    private string $state;

    private string $address;

    /**
     * TelegramService constructor.
     *
     * @constructor
     * @param string $telegramToken - Telegram API token
     * @param GoogleService $googleService - Google service
     */
    public function __construct(
        private readonly string        $telegramToken,
        private readonly GoogleService $googleService,
    )
    {
        try {
            $this->telegram = new Api($telegramToken);
        } catch (TelegramSDKException $e) {
            throw new RuntimeException(
                'Error while creating Telegram API instance: ' // TODO - сделать экспешн класс
                . $e->getMessage()
            );
        }

        $this->subscribeForUpdates();
        $this->setMessage();
        $this->setChat();
        $this->setText();
    }

    /**
     * Operate incoming messages TODO - отрефакторить, так как я уже никакой, устал, если есть желание - вперед.
     *
     * @throws TelegramSDKException
     * @return void
     */
    public function operateMessages(): void
    {
        $data = $this->getStateFromFile($this->chat->getId());

        if (empty($data['state'])) {
            $this->state = 'init';
        } else {
            $this->state = $data['state'];
        }

        $telegramFileUrl = getenv('TELEGRAM_FILE_API_URL');

        // Define your keyboard buttons
        $keyboard = [
            'resize_keyboard' => true,  // Makes the keyboard smaller
            'one_time_keyboard' => true,  // Hide the keyboard after a button is pressed
        ];

        //TODO переделать на switch или на match
        if ($this->text === '/start' || $this->state === 'start') {
            $this->start($keyboard);
        } elseif ($this->state === 'action_1') {
            $this->setState('action_2');

            if ($this->text === 'Жоқ') {
                // TODO - сделать конфиг для текстовых сообщений
                $this->sendMessage(
                    'Деректемелер, мөрлер мен мөртаңбалар,бланкілер, ұйымдардың маңдайшалары,хабарландырулар, жарнама, прейскуранттар, баға көрсеткiштерi, ас мәзірлері, нұсқағыштар, тауар туралы ақпарат, сондай-ақ тауарлардың арнайы мәлiметтер көрсетiлген тауарлық жапсырмалар (этикеткалар), таңбаламалар, нұсқаулықтар, сатушы (дайындаушы, орындаушы) туралы ақпарат және сонымен қатынасқа түсуге алып келетін басқа да ақпарат. (Тіл туралы заң, 21-бап).',
                );
            }

            $keyboard['keyboard'] = [
                [
                    ['text' => 'Ия', 'data' => 'action_2_button_1'],
                    ['text' => 'Жоқ', 'data' => 'action_2_button_2'],
                ],
            ];

            $this->sendMessage(
                'Сіз ҚР Тіл туралы заңына сәйкес көрнекі ақпараттың қазақ тілінде берілуін міндеттейтінің білесіз бе?',
                $keyboard,
            );
        } elseif ($this->state === 'action_2') {
            $this->setState('action_3');

            if ($this->text === 'Жоқ') {
                // TODO - сделать конфиг для текстовых сообщений
                $this->sendMessage(
                    'ҚР Тіл туралы заң, 21 бабына сәйкес деректемелер мен көрнекі ақпараттың барлық мәтiнi мынадай ретпен: мемлекеттiк тiлде - сол жағына немесе жоғарғы жағына, орыс тiлiнде он жағына немесе төменгi жағына орналасады, бiрдей өлшемдегi әрiптермен жазылады. Қажеттiгiне қарай деректемелер мен көрнекі ақпараттың мәтiндерi қосымша басқа да тiлдерге аударылуы мүмкiн. Бұл жағдайда қарiп өлшемi нормативтiк құқықтық актiлерде белгiленген талаптардан аспауға тиiс. Ауызша ақпарат, хабарландыру, жарнама мемлекеттiк тiлде, орыс және қажет болған жағдайда, басқа да тiлдерде берiледi.',
                );
            }

            $keyboard['keyboard'] = [
                [
                    ['text' => 'Ия', 'data' => 'action_3_button_1'],
                    ['text' => 'Жоқ', 'data' => 'action_3_button_2'],
                ],
            ];

            $this->sendMessage(
                'Атырау, Құлсары қалаларында заңға қайшы келген кәсіпкерлік нысанды байқадыңыз ба?',
                $keyboard,
            );
        } elseif ($this->state === 'action_3' && $this->text === 'Жоқ') {
            // TODO - сделать конфиг для текстовых сообщений
            $this->setState('start');
            $this->saveToDataToFile(
                $this->chat->getId(),
                [
                    'state' => $this->state,
                ],
            );
            $this->start($keyboard);
        } elseif ($this->state === 'action_3' && $this->text === 'Ия') {
            // TODO - сделать конфиг для текстовых сообщений
            $this->setState('waiting_for_address');
            $this->sendMessage(
                'Осы ботқа нысанның мекен-жайы мен заңға қайшы келетін көрнекі ақпараттың фотосын жіберіңіз!Нысан мекен-жайы:',
            );
        } elseif ($this->state === 'waiting_for_address') {
            $this->setState('waiting_for_photo');
            $this->sendMessage('Көрнекі қате ақпараттың фотосы:');
            $this->saveToDataToFile($this->chat->getId(), [
                'state' => $this->state,
                'address' => $this->text,
            ]);
        } elseif ($this->state === 'waiting_for_photo') {
            if ($this->message->has('photo') || $this->message->has('document')) {
                // Обработка загрузки фото в Google Drive
                $photo = $this->message->has('photo')
                    ? $this->message->photo
                    : $this->message->document;

                $photoFileId = $this->message->has('photo')
                    ? $photo[count($photo) - 1]->getFileId()
                    : $photo->getFileId();

                if ($this->message->has('document')) {
                    $mimeType = $photo->getMimeType();

                    if (!str_contains($mimeType, 'image/')) {
                        $this->state = 'waiting_for_photo';
                        $this->sendMessage(
                            'Сіз фото жіберген жоқсыз. Тағы да қайталап көріңіз.',
                        );
                    }
                }

                $photoFilePath = $this->telegram->getFile(
                    [
                        'file_id' => $photoFileId,
                    ],
                )['file_path'];

                $photoUrl = $telegramFileUrl . $this->telegramToken . '/' . $photoFilePath;

                try {
                    $this->googleService->photoToDrive($photoUrl);
                } catch (Exception $e) {
                    Helper::logReport(
                        'Error while uploading photo to Google Drive: ' // TODO - сделать экспешн класс
                        . $e->getMessage(),
                    );
                }

                try {
                    $address = $this->getStateFromFile($this->chat->getId())['address'] ?? 'empty address';
                    $this->googleService->recordToSheet($photoFilePath, $this->message, $address);
                } catch (Exception $e) {
                    Helper::logReport(
                        'Google Sheets-ке жазба қосу кезіндегі қате: ' // TODO - сделать экспешн класс
                        . $e->getMessage(),
                    );

                    $this->sendMessage(
                        'Google Sheets қолданбасына жазба қосу кезінде қате орын алды. Тағы да қайталап көріңіз.',
                    );
                }

                $this->setState('init');
                $this->sendMessage('Белсенділік танытып, қазақ тіліне жанашыр болғаныңыз үшін рақмет!');
            } else {
                $this->sendMessage(
                    'Сіз фото жіберген жоқсыз. Тағы да қайталап көріңіз.',
                );
            }
        } elseif ($this->text === '/hello') {
            $this->sendMessage(
                'Саған да сәлем!',
            );
        } else {
            $this->sendMessage(
                'Менен не қалайтыныңды түсінбеймін.',
            );
        }
    }

    /**
     * Отправка сообщения в Telegram
     *
     * @param string $message - message to send
     * @param $keyboard - keyboard
     * @return void
     */
    private function sendMessage(string $message, $keyboard = null): void
    {
        $chatId = $this->chat->getId();

        $body = [
            'chat_id' => $chatId,
            'text' => $message,
        ];

        if ($keyboard) {
            $body['reply_markup'] = json_encode($keyboard);
        }


        if ($this->state) {
            $address = $this->getStateFromFile($chatId)['address'] ?? 'empty address';
            $this->saveToDataToFile($chatId, [
                'state' => $this->state,
                'address' => $address,
            ]);
        }

        try {
            $this->telegram->sendMessage(
                $body
            );
        } catch (TelegramSDKException $e) {
            throw new RuntimeException(
                'Error while sending Telegram message: ' // TODO - сделать экспешн класс
                . $e->getMessage()
            );
        }
    }

    /**
     * Установка вебхука
     *
     * @return void
     * @throws TelegramSDKException
     */
    public function setWebhook(): void
    {
        $webhookUrl = getenv('TELEGRAM_WEBHOOK_URL');

        if ($this->isWebhookSet($webhookUrl)) {
            return;
        }

        try {
            $this->telegram->setWebhook(
                [
                    'url' => $webhookUrl,
                ],
            );
        } catch (TelegramSDKException $e) {
            throw new RuntimeException(
                'Error while setting Telegram webhook: ' // TODO - сделать экспешн класс
                . $e->getMessage()
            );
        }
    }

    /**
     * Remove webhook
     *
     * @return void
     */
    public function removeWebhook(): void
    {
        try {
            $this->telegram->removeWebhook();
        } catch (TelegramSDKException $e) {
            throw new RuntimeException(
                'Error while removing Telegram webhook: ' // TODO - сделать экспешн класс
                . $e->getMessage()
            );
        }
    }

    /**
     * Проверка, установлен ли вебхук
     *
     * @param string $webhookUrl - webhook URL
     * @return bool
     * @throws TelegramSDKException
     */
    private function isWebhookSet(string $webhookUrl): bool
    {
        // Get the current webhook information
        $webhookInfo = $this->telegram->getWebhookInfo();

        return $webhookInfo['url'] === $webhookUrl;
    }

    /**
     * Subscribe for webhook updates
     *
     * @return void
     */
    private function subscribeForUpdates(): void
    {
        try {
            $this->update = $this->telegram->getWebhookUpdate();
        } catch (Exception $e) {
            Helper::logReport(
                'Error occurred',
                [
                    'trace' => $e->getTraceAsString(),
                    'message' => $e->getMessage(),
                    'data' => $this->update,
                ],
            );
            throw new RuntimeException(
                'Error while getting webhook update: ' // TODO - сделать экспешн класс
                . $e->getMessage()
            );
        }
    }

    /**
     * Set message
     *
     * @return void
     */
    private function setMessage(): void
    {
        try {
            $this->message = $this->update->getMessage();
        } catch (Exception $e) {
            Helper::logReport(
                'Error occurred',
                [
                    'trace' => $e->getTraceAsString(),
                    'message' => $e->getMessage(),
                    'data' => $this->message,
                ],
            );
            throw new RuntimeException(
                'Error while getting webhook update: ' // TODO - сделать экспешн класс
                . $e->getMessage()
            );
        }
    }

    /**
     * Set chat ID
     *
     * @return void
     */
    private function setChat(): void
    {
        try {
            $this->chat = $this->update->getChat();
        } catch (Exception $e) {
            Helper::logReport(
                'Error occurred',
                [
                    'trace' => $e->getTraceAsString(),
                    'message' => $e->getMessage(),
                    'data' => $this->chat,
                ],
            );
            throw new RuntimeException(
                'Error while getting webhook update: ' // TODO - сделать экспешн класс
                . $e->getMessage()
            );
        }
    }

    /**
     * Set text
     *
     * @return void
     */
    private function setText(): void
    {
        try {
            $this->text = $this->message->get('text', 'empty text');
        } catch (Exception $e) {
            Helper::logReport(
                'Error occurred',
                [
                    'trace' => $e->getTraceAsString(),
                    'message' => $e->getMessage(),
                    'data' => $this->text,
                ],
            );
            throw new RuntimeException(
                'Error while getting webhook update: ' // TODO - сделать экспешн класс
                . $e->getMessage()
            );
        }
    }

    /**
     * Show the bot information.
     *
     * @throws TelegramSDKException
     */
    public function show(): User
    {
        return $this->telegram->getMe();
    }

    /**
     *  Get updates from Telegram, if webhook is not set
     *  Long polling
     *
     * @return array
     * @throws TelegramSDKException
     */
    private function getTelegramUpdates(): array
    {
        return $this->telegram->getUpdates();
    }

    /**
     * Set state
     *
     * @param string $state - state
     * @return void
     */
    private function setState(string $state): void
    {
        $this->state = $state;
    }

    private function getStateFromFile($chatId)
    {
        $dataFile = "user_data/$chatId.json";
        if (file_exists($dataFile)) {
            return json_decode(file_get_contents($dataFile), true);
        }

        return [];
    }

    // Function to save user data to external storage
    private function saveToDataToFile($chatId, $data): void
    {
        $dataFile = "user_data/$chatId.json";
        file_put_contents($dataFile, json_encode($data));
    }

    private function start($keyboard): void
    {
        $this->setState('action_1');

        $keyboard['keyboard'] = [
            [
                ['text' => 'Ия'],
                ['text' => 'Жоқ'],
            ],
        ];
        // TODO - сделать конфиг для текстовых сообщений
        $this->sendMessage(
            'Сәлеметсіз бе! Көрнекі ботқа қош келдіңіз! Сіз көрнекі ақпаратқа нелер жататының білесіз бе?',
            $keyboard,
        );
    }
}
