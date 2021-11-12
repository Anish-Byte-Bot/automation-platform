<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Log;

use function array_map;
use function implode;
use function is_bool;
use function is_float;
use function is_scalar;
use const JSON_PRETTY_PRINT;
use Monolog\Formatter\FormatterInterface;
use const PHP_EOL;
use function Safe\json_encode;
use function Safe\sprintf;
use function str_contains;

final class LogFormatter implements FormatterInterface
{
    public function format(array $record): string
    {
        $dateTime = $record['datetime']->format('Y-m-d H:i:sP');
        $channel = $record['channel'] === '' ? '' : "[{$record['channel']}]";
        $result = "[{$dateTime}][{$record['level_name']}]$channel {$record['message']}" . PHP_EOL;

        /** @psalm-suppress MixedAssignment */
        foreach ($record['context'] as $key => $value) {
            $formattedValue = $this->formatValue($value);

            $result .= "{$key}:" . (str_contains($formattedValue, "\n") ? PHP_EOL : ' ');
            $result .= $formattedValue . PHP_EOL;
        }

        return $result;
    }

    public function formatBatch(array $records): string
    {
        return implode('', array_map([$this, 'format'], $records));
    }

    private function formatValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_float($value)) {
            return sprintf('%.4F', $value); // todo do locale aware formatting?
        }

        if (is_scalar($value)) {
            return (string)$value;
        }

        return json_encode($value, JSON_PRETTY_PRINT);
    }
}
