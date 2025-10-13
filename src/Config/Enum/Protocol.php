<?php

declare(strict_types=1);

namespace App\Config\Enum;

enum Protocol: string
{
    case FTP = 'FTP';
    case SFTP = 'SFTP';
}
