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

## Usage

### Transcription

1. Set up credentials for audio transcriber you want to use for transcription in your `config/transcription.php` configuration file.
2. Use `Transcription` facade to start a transcription.

```php
Transcription::make('https://www.example.com/audio/test.wav', 'en-US');
```

The `Transcription` facade's `make` method is used to start a asynchronous transcription process. The first argument is the URL that can located the audio file you want to transcribe, and the second argument is the language of the supplied audio as a [BCP-47](https://www.rfc-editor.org/rfc/bcp/bcp47.txt) language tag.

### Speaker Identification

Sometimes you might want to identify the speaker in your audio file, you can pass the maximum of speaker count as third argument of `Transcription` facade's `make` method

```php
Transcription::make('https://www.example.com/audio/test.wav', 'en-US', 3);
```

After transcription of audio file completed, you can check out the `speaker_label` attribute of each `TranscriptSegment` models related with the `Transcript` model. There would be identifiable information of the speaker for each transcript segments. For example, you might see `speaker_1` in that column to represent the first speaker. 

### Redaction

For security propose, you might need to hide PII (Personal Identifiable Information) data in transcript. Then you should set up credentials for detector you want to use for PII entity detection in your `config/transcription.php` configuration file.

After setting up configuration file, you can use the fourth argument of `Transcription` facade's make method to decide whether the transcript of audio file should be redacted. 

```php
Transcription::make('https://www.example.com/audio/test.wav', 'en-US', shouldRedact: true);
```

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
