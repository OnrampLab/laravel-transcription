# laravel-transcription

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)
[![CircleCI](https://circleci.com/gh/OnrampLab/laravel-transcription.svg?style=shield)](https://circleci.com/gh/OnrampLab/laravel-transcription)
[![Total Downloads](https://img.shields.io/packagist/dt/onramplab/laravel-transcription.svg?style=flat-square)](https://packagist.org/packages/onramplab/laravel-transcription)

A Laravel package providing transcription feature for audio file

## Requirements

- PHP >= 8.1;
- composer.

## Features

- Support multiple types of third-party transcription service
  - AWS Transcribe

## Installation

```bash
composer require onramplab/laravel-transcription
```

Publish migration files and run command to build tables needed in package

```bash
php artisan vendor:publish --tag="transcription-migrations"
php artisan migrate
```

Also, you can choose to publish the configuration file

```bash
php artisan vendor:publish --tag="transcription-config"
```

## Configuration

1. Set up credentials for provider you want to use for transcription in your `config/transcription.php` configuration file.

## Usage

### Create transcription for audio file

```php
Transcription::make('https://www.example.com/audio/test.wav', 'en-US');
```

The `Transcription` facade's `make` method is used to start a asynchronous transcription process. The first argument is the URL that can located the audio file you want to transcribe, and second argument is the language of the supplied audio as a [BCP-47](https://www.rfc-editor.org/rfc/bcp/bcp47.txt) language tag.


## Useful Tools

## Running Tests:

    php vendor/bin/phpunit

 or

    composer test

## Code Sniffer Tool:

    php vendor/bin/phpcs --standard=PSR2 src/

 or

    composer psr2check

## Code Auto-fixer:

    composer psr2autofix
    composer insights:fix
    rector:fix

## Building Docs:

    php vendor/bin/phpdoc -d "src" -t "docs"

 or

    composer docs

## Changelog

To keep track, please refer to [CHANGELOG.md](https://github.com/onramplab/laravel-transcription/blob/master/CHANGELOG.md).

## Contributing

1. Fork it.
2. Create your feature branch (git checkout -b my-new-feature).
3. Make your changes.
4. Run the tests, adding new ones for your own code if necessary (phpunit).
5. Commit your changes (git commit -am 'Added some feature').
6. Push to the branch (git push origin my-new-feature).
7. Create new pull request.

Also please refer to [CONTRIBUTION.md](https://github.com/onramplab/laravel-transcription/blob/master/CONTRIBUTION.md).

## License

Please refer to [LICENSE](https://github.com/onramplab/laravel-transcription/blob/master/LICENSE).
