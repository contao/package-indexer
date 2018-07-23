<?php

declare(strict_types=1);

/*
 * Contao Package Indexer
 *
 * @copyright  Copyright (c) 2018, terminal42 gmbh
 * @author     terminal42 gmbh <info@terminal42.ch>
 * @license    MIT
 */

namespace App\Command;

class IndexMetaCommand extends AbstractIndexCommand
{
    protected function getCommandName(): string
    {
        return 'package-indexer:index-meta';
    }

    protected function getCommandDescription(): string
    {
        return 'Starts the indexing process with extended information (designed to run once or twice a month)';
    }
}
