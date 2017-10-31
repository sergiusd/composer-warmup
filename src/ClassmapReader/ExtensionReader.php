<?php

declare (strict_types = 1);

namespace Jderusse\Warmup\ClassmapReader;

use Composer\Config;
use Composer\Util\Filesystem;
use Symfony\Component\Finder\Finder;

class ExtensionReader implements ReaderInterface
{
    /** @var array */
    private $directory;
    /** @var string[] */
    private $extensions;
    /** @var Filesystem */
    private $filesystem;
    /** @var string */
    private $basePath;

    public function __construct(string $directory, array $extensions)
    {
        $this->directory = $directory;
        $this->extensions = $extensions;

        $this->filesystem = new Filesystem();
        $this->basePath = $this->filesystem->normalizePath(realpath(getcwd()));
    }

    public function getClassmap() : \Traversable
    {
        /** @var  $files */
        $files = Finder::create()->files()->followLinks()->name('/\.('.join('|', $this->extensions).')$/')->in($this->directory);
        foreach ($files as $n => $file) {
            yield $n => $this->normalize($file->getPathname());
        }
    }

    private function normalize(string $path) : string
    {
        if (!$this->filesystem->isAbsolutePath($path)) {
            $path = $this->basePath.'/'.$path;
        }

        return $this->filesystem->normalizePath($path);
    }
}