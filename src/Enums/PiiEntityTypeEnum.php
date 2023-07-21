<?php

namespace OnrampLab\Transcription\Enums;

enum PiiEntityTypeEnum: string
{
    case PHONE_NUMBER = 'phone_number';
    case EMAIL = 'email';
    case NAME = 'name';
    case ZIP_CODE = 'zip_code';
    case ADDRESS = 'address';
}
