<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */

/*
 * Uses the autoloader of a standalone checkout (vendor/ inside this package) or, when the package is
 * installed in a Muckidrive project (custom/muckiware/core), the autoloader of that project.
 * MUCKIDRIVE_PROJECT_DIR can point to the project explicitly.
 */
$projectDir = getenv('MUCKIDRIVE_PROJECT_DIR');

$candidates = array_filter([
    \is_string($projectDir) && $projectDir !== '' ? $projectDir . '/vendor/autoload.php' : null,
    dirname(__DIR__) . '/vendor/autoload.php',
    dirname(__DIR__, 4) . '/vendor/autoload.php',
]);

foreach ($candidates as $autoloadFile) {
    if (is_file($autoloadFile)) {
        require_once $autoloadFile;

        return;
    }
}

throw new RuntimeException('No Composer autoloader found. Set MUCKIDRIVE_PROJECT_DIR to the Muckidrive project root.');
