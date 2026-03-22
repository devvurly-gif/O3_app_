<?php

namespace App\Services;

use App\Models\DocumentIncrementor;

class DocumentNumberService
{
    public static function generate(DocumentIncrementor $incrementor): string
    {
        return str_replace(
            ['{YEAR}', '{MONTH}', '{NUM}'],
            [date('Y'), date('m'), str_pad($incrementor->nextTrick, 4, '0', STR_PAD_LEFT)],
            $incrementor->template
        );
    }
}
