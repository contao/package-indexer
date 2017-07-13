# Contao Package Indexer

The *Contao Package Indexer* is a Symfony console application that
pushes information about Composer packages to [Algolia]. Algolia is then
used by the [Contao Manager] to search for packages.

**This application is for internal use only and should not**
**be used by anyone but the Contao core team.**


## Setup & Run

The application requires three environment variables to run:

 1. `ALGOLIA_APP` is the name of your Algolia application ID
 2. `ALGOLIA_KEY` is your Algolia API key
 3. `ALGOLIA_INDEX` is the name of the index to push packages to

You can either setup these variables in your environment, or add a
`.env` file to your project root which will automatically be loaded
by the [Symfony Dotenv Component][Dotenv].

Next you can simply run the `console` file in your command line. The
only command currently available is `console index` which searches and
uploads all relevant package information.



[Algolia]: https://www.algolia.com
[Contao Manager]: https://github.com/contao/contao-manager
[Dotenv]: https://symfony.com/doc/current/components/dotenv.html
