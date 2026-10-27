<?php

namespace App\Enums;

/** What kind of site a scout source is. */
enum SourceType: string
{
    case Auction = 'auction';
    case Portal = 'portal';
}
