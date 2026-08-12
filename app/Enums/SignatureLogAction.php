<?php

namespace App\Enums;

enum SignatureLogAction: string
{
    case Uploaded = 'uploaded';
    case Updated = 'updated';
    case Deleted = 'deleted';
}
