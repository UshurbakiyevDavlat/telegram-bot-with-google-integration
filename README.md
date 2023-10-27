## Telegram bot for saving data to google sheets and google drive
    
### Requirements
- PHP ^8.1
- Composer ^2.1
- Telegram bot, and telegram bot token
- Google service account credentials
  - Google drive api enabled
  - Google sheets api enabled

### Installation

1. Clone repository
2. Install requirements
3. Copy .env file from .env.example
4. Fill .env file
5. Run `composer install`
6. Configure web server, nginx for example, root should be set to core directory

### Usage

Just send message to bot, if it is image, it will be saved to google drive and record within Google Sheets.

