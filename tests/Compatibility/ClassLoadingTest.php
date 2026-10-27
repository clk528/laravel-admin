<?php

namespace Encore\Admin\Tests\Compatibility;

class ClassLoadingTest extends TestCase
{
    public function test_all_package_classes_interfaces_and_traits_load(): void
    {
        $source = realpath(__DIR__.'/../../src');
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php' || $file->getFilename() === 'helpers.php') {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($source) + 1, -4);
            $symbol = 'Encore\\Admin\\'.str_replace('/', '\\', $relative);
            $this->assertTrue(class_exists($symbol) || interface_exists($symbol) || trait_exists($symbol), $symbol);
        }
    }
}
