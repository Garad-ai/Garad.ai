<?php

declare(strict_types=1);

namespace App\Extensions\AIAgent\System\Formatters\Contracts;

interface FormatterInterface
{
    public function format(string $text): string;
}
