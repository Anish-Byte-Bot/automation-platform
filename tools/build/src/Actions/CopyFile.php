<?php

declare(strict_types=1);

namespace Ramona\AutomationPlatformLibBuild\Actions;

use const DIRECTORY_SEPARATOR;
use Ramona\AutomationPlatformLibBuild\BuildActionResult;
use Ramona\AutomationPlatformLibBuild\BuildOutput\TargetOutput;
use Ramona\AutomationPlatformLibBuild\Context;
use function Safe\copy;

/**
 * @api
 */
final class CopyFile implements BuildAction
{
    public function __construct(private string $source, private string $target)
    {
    }

    public function execute(TargetOutput $output, Context $context, string $workingDirectory): BuildActionResult
    {
        copy($workingDirectory . DIRECTORY_SEPARATOR . $this->source, $workingDirectory . DIRECTORY_SEPARATOR . $this->target);

        return BuildActionResult::ok([]);
    }
}
