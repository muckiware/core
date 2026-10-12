<?php declare(strict_types=1);
/**
 * @category   Muckidrive Component
 * @package    Core
 * @copyright  Copyright (c) 2026 by Muckiware
 * @license    MIT
 * @author     Muckiware
 *
 */
namespace Muckiware\Core;
class Helper
{
    /**
     * @throws \ReflectionException
     */
    public static function getFolderByClassName(string|null $className=null, string $targetFolder='/vendor'): string
    {
        // PROJECT_ROOT (e.g. exported by bin/build-administration.sh) only applies to the project root
        // lookup; a class name always resolves to the folder of that class.
        if ($className === null && $folder = $_ENV['PROJECT_ROOT'] ?? $_SERVER['PROJECT_ROOT'] ?? false) {
            return $folder;
        }

        if($className) {
            $folder = new \ReflectionClass($className)->getFileName();
        } else {
            $folder = new \ReflectionClass(self::class)->getFileName();
        }

        $folder = $rootDir = \dirname($folder);
        while (!\is_dir($folder.$targetFolder)) {
            if ($folder === \dirname($folder)) {
                return $rootDir;
            }
            $folder = \dirname($folder);
        }

        return $folder;
    }
}
