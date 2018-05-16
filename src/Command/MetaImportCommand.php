<?php

/*
 * This file is part of Contao Package Indexer.
 *
 * Copyright (c) 2017 Contao Association
 *
 * @license LGPL-3.0+
 */

namespace Contao\PackageIndexer\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

class MetaImportCommand extends Command
{
    /**
     * @var name
     */
    private $name;

    /**
     * @var language
     */
    private $language;

    /**
     * @var repoFolder
     */
    private $repoFolder;

    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('meta')
            ->setDescription('Imports meta data from global repository')
            ->addArgument('name', InputArgument::REQUIRED, 'Which package do you want to read? (Example: terminal42/contao-easy_themes')
            ->addArgument('language', InputArgument::OPTIONAL, 'Which language do you want to read? (Use ISO 639-1)')
        ;

        $this->repoFolder = __DIR__.'/../../package-metadata/meta/';
        $this->language = 'en';
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->name = $input->getArgument('name');
        $this->language = $input->getArgument('language') ?? $this->language;

        $this->io = new SymfonyStyle($input, $output);
        $this->io->newLine();

        $this->parseMetaPackage($this->name, $this->language);
    }

    private function parseMetaPackage(string $name, string $language): void
    {
        $this->io->writeln('Parse: ' . $name);
        $this->io->writeln('Language: ' . $language);

        $yml = $this->parseYml($name, $language);

        $this->io->writeln('Result:');
        $this->io->newLine(1);
        $this->io->listing($yml);

        $this->io->newLine(2);
    }

    public function parseYml(string $name, string $language)
    {
        $file = $this->repoFolder . $name . '/' . $language . '.yml';

        $data = Yaml::parse(@file_get_contents($file));

        // empty file
        if (null === $data) {
            $data = array();
        }

        // not an array
        if (!is_array($data)) {
            throw new \InvalidArgumentException(sprintf('The file "%s" must contain a YAML array.', $file));
        }

        return $data;
    }

    public function getLogo($path)
    {
        $image = $this->repoFolder . $path . '/logo.svg';
        if(file_exists($image))
        {
            if(filesize($image) > 5120) # if bigger than 5kb use raw url
            {
                return 'https://raw.githubusercontent.com/contao/package-metadata/master/meta/'.$path.'/logo.svg';
            }

            // return base64
            $mimetype = mime_content_type($image);
            $data = base64_encode(file_get_contents($image));
            return "data:$mimetype;base64,$data";
        }
        return;
    }
}
