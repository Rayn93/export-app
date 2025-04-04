<?php

namespace App\Config\Enum;

enum Protocol: string
{
    case FTP = 'FTP';
    case SFTP = 'SFTP';
}