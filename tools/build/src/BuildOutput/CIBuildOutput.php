<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\BuildOutput;

use Bramus\Ansi\Ansi;
use Bramus\Ansi\ControlSequences\EscapeSequences\Enums\SGR;
use function count;
use function explode;
use const PHP_EOL;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\TargetId;
use function str_contains;
use function trim;

final class CIBuildOutput implements BuildOutput
{
    private string $standardOutput = '';
    private string $standardError = '';
    private int $targetCount = 0;
    private int $currentTarget = 0;

    public function __construct(private Ansi $ansi)
    {
    }

    public function pushError(string $data): void
    {
        $this->writeWithColoredPrefix('[E]', [SGR::COLOR_FG_RED], $data);
        $this->standardError .= $data;
    }

    public function pushOutput(string $data): void
    {
        $this->writeWithColoredPrefix('[O]', [SGR::COLOR_FG_CYAN], $data);
        $this->standardOutput .= $data;
    }

    public function setTargetCount(int $count): void
    {
        $this->targetCount = $count;
        $this->currentTarget = 1;
    }

    public function startTarget(TargetId $id): void
    {
        $this
            ->ansi
            ->nostyle()
            ->color([SGR::COLOR_FG_CYAN])
            ->text("($this->currentTarget/$this->targetCount) Running target {$id->toString()} " . PHP_EOL)
            ->nostyle();

        $this->currentTarget++;
    }

    public function getCollectedStandardOutput(): string
    {
        return $this->standardOutput;
    }

    public function getCollectedStandardError(): string
    {
        return $this->standardError;
    }

    public function finalizeTarget(TargetId $targetId, BuildActionResult $result): void
    {
        $color = $result->hasSucceeded() ? [SGR::COLOR_FG_GREEN] : [SGR::COLOR_FG_RED];
        $message = $result->hasSucceeded() ? "succeeded" : "failed: " . ($result->getMessage() ?? '');

        $this
            ->ansi
            ->nostyle()
            ->color($color)
            ->text($message . PHP_EOL)
            ->nostyle();
    }

    /**
     * @param non-empty-list<string> $color
     */
    public function writeWithColoredPrefix(string $prefix, array $color, string $data): void
    {
        if (!str_contains($data, "\n")) {
            $this->ansi->text($data);
            return;
        }

        $lines = explode("\n", $data);

        foreach ($lines as $key => $line) {
            if (trim($line) !== '') {
                $this
                    ->ansi
                    ->nostyle()
                    ->color($color)
                    ->text("$prefix ")
                    ->nostyle();
            }

            $this
                ->ansi
                ->text($line . (($key === count($lines) - 1) ? '' : PHP_EOL));
        }
    }
}