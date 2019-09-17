<?php

declare(strict_types=1);

/*
 * Contao Package Indexer
 *
 * @copyright  Copyright (c) 2019, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    MIT
 */

namespace App\Command;

class IndexCommand extends AbstractIndexCommand
{
    protected function getCommandName(): string
    {
        return 'package-indexer:index';
    }

    protected function getCommandDescription(): string
    {
        return 'Starts the indexing process with basic information (designed to run more often)';
    }
}
