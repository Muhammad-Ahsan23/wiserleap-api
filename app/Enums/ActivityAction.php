<?php

namespace App\Enums;

enum ActivityAction: string
{
    case Created = 'created';
    case Updated = 'updated';
    case Published = 'published';
    case Unpublished = 'unpublished';
    case Deleted = 'deleted';
}
