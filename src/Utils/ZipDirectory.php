<?php

namespace YDTBWP\Utils;

use Diversen\Spinner;

class ZipDirectory
{
    public function __construct(
        private string $sourcePath,
        private ?string $outputPath = null,
    ) {
        $this->sourcePath = realpath($this->sourcePath);

        if (!is_dir($this->sourcePath)) {
            throw new \InvalidArgumentException($this->sourcePath . ' is not a directory!');
        }

        if (is_null($this->outputPath)) {
            $this->outputPath = $this->sourcePath . '.zip';
        }

        return $this;
    }

    /**
     * @return string Output zip file path
     */
    public function make($spinner = true): string
    {
        $spinner = new Spinner(spinner: 'aesthetic', message: "Zipping up files...");

        $fn = function () {

            $zip = new \ZipArchive();

            if ($zip->open($this->outputPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {

                $files = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($this->sourcePath),
                    \RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $name => $file) {
                    // Skip directories (they would be added automatically)
                    if (!$file->isDir()) {
                        // Get real and relative path for current file
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($this->sourcePath) + 1);

                        // Add current file to archive
                        $zip->addFile($filePath, $relativePath);
                    }
                }

                // Zip archive will be created only after closing object
                if ($zip->close()) {
                    return $this->outputPath;
                }

                throw new \RuntimeException("Failed to make zip file at ({$this->outputPath}).");
            } else {
                throw new \RuntimeException("Could not open zip file for writing.");
            }
        };

        if ($spinner) {
            $res = $spinner->callback($fn);
        } else {
            $res = $fn();
        }
        return $res;
    }
}
