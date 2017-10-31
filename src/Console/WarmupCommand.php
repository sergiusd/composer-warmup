<?php

namespace Jderusse\Warmup\Console;

use Composer\Command\BaseCommand;
use Jderusse\Warmup\ClassmapReader\ChainReader;
use Jderusse\Warmup\ClassmapReader\DirectoryReader;
use Jderusse\Warmup\ClassmapReader\ExtensionReader;
use Jderusse\Warmup\ClassmapReader\OptimizedReader;
use Jderusse\Warmup\Compiler\PhpServerCompiler;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WarmupCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('warmup-opcode')
            ->setDescription('Warmup the application\'s OpCode')
            ->addArgument('extra-dir', InputArgument::IS_ARRAY, 'add extra path to compile')
            ->addOption('ext', null, InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY, 'add extension to compile', []);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!extension_loaded('Zend OPcache')) {
            throw new \RuntimeException('You have to enable opcache to use this commande');
        }

        if (!(bool) ini_get('opcache.enable_cli')) {
            throw new \RuntimeException('You have to enable the opcache extension');
        }

        $opcacheDir = ini_get('opcache.file_cache');
        if (empty($opcacheDir)) {
            throw new \RuntimeException('You have to define a file_cache to use');
        }

        if (!is_dir($opcacheDir)) {
            throw new \RuntimeException(sprintf('You have to create the "%s" directory', $opcacheDir));
        }

        if ((int) ini_get('opcache.file_update_protection') > 0) {
            $output->writeln(
                '<warning>You should set the `opcache.file_update_protection` to 0 in order to cache recently updated files</warning>'
            );
        }

        $composer = $this->getComposer();

        $extensions = $input->getOption('ext');
        if ($extensions && $input->getArgument('extra-dir')) {
            $readers = array_map(
                function ($extra) use ($extensions) {
                    return new ExtensionReader($extra, $extensions);
                },
                $input->getArgument('extra-dir')
            );
        } else {
            $readers = array_merge(
                [new OptimizedReader($composer->getConfig())],
                array_map(
                    function ($extra) {
                        return new DirectoryReader($extra);
                    },
                    $input->getArgument('extra-dir')
                )
            );
        }

        $reader = new ChainReader($readers);
        $compiler = new PhpServerCompiler();
        foreach ($reader->getClassmap() as $file) {
            try {
                $compiler->compile($file);
                if ($output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE) {
                    $output->writeln(sprintf('<info>Compiled file <comment>%s</comment></info>', $file));
                }
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<error>Failed to compile file <comment>%s</comment></error>', $file));
            }
            if ($message = $compiler->getProcessOutput()) {
                $output->writeln($message);
            }
        }
    }
}
