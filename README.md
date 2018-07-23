# Contao Package Indexer

The *Contao Package Indexer* is a Symfony console application that
pushes information about Composer packages to [Algolia]. Algolia is then
used by the [Contao Manager] to search for packages.

**This application is for internal use only and should not**
**be used by anyone but the Contao core team.**


## Setup & Run

The application requires two environment variables to run:

 1. `ALGOLIA_APP_ID` is the name of your Algolia application ID
 2. `ALGOLIA_API_KEY` is your Algolia API key

You can either setup these variables in your environment, or add a
`.env` file to your project root which will automatically be loaded
by the [Symfony Dotenv Component][Dotenv].

Next you can simply run the `index` file in your command line.


## Multilingual package data

To enable indexing of multilingual package data, clone the GIT
repository from https://github.com/contao/package-metadata to the
folder "metadata" in the root of this project.


[Algolia]: https://www.algolia.com
[Contao Manager]: https://github.com/contao/contao-manager
[Dotenv]: https://symfony.com/doc/current/components/dotenv.html
