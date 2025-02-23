# Cohere Telegram Bot

> [!WARNING]
> This branch of the repository for PHP 7.3.10 is deprecated and will no longer receive updates. It is created solely to run on older Linux distributions.

A Telegram bot that utilizes the [Cohere API](https://cohere.ai/) to deliver intelligent responses and assistance. This bot manages user interactions, including terms acceptance and context retention, to create a seamless experience.

## Features

- Intelligent responses powered by Cohere's AI.
- User context management for personalized interactions.
- Terms of service acceptance flow.
- Multi-language support based on user preferences.

## Getting Started

### Prerequisites

- PHP 7.4 or higher **(or PHP 7.3 for legacy support)**
- Composer
- A Telegram bot token (create one with [BotFather](https://t.me/BotFather))
- A Cohere API key (sign up at [Cohere](https://cohere.ai/))

![Cohere Telegram Bot Interface](https://github.com/user-attachments/assets/5bd480c5-3990-435a-b115-82e158f3a226)
*Example of interaction with the bot in Telegram.*

### Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/ChernegaSergiy/cohere-telegram-bot.git
   cd cohere-telegram-bot
   ```

2. Install dependencies:

   For PHP 7.4 or higher:
   ```bash
   composer require telegram-bot/api
   composer install
   ```

   For PHP 7.3 support (on the `legacy-php-7.3` branch):
   ```bash
   git checkout legacy-php-7.3
   composer require telegram-bot/api
   composer install
   ```

4. Configure your bot token and API key in the `bot.php` file.

5. Run the bot:
   ```bash
   php bot.php
   ```

## Contributing

Contributions are welcome and appreciated! Here's how you can contribute:

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

Please make sure to update tests as appropriate and adhere to the existing coding style.

## License

This project is licensed under the CSSM Unlimited License v2 (CSSM-ULv2). See the [LICENSE](LICENSE) file for details.

## Acknowledgments

- [Cohere](https://cohere.ai/) for providing the API that powers the bot's intelligent responses.
- [Telegram Bot API](https://core.telegram.org/bots/api) for enabling bot functionality on Telegram.
- Special thanks to the open-source community for their contributions and support.
