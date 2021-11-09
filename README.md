# OS Terrain 50 elevation data in PHP

This repo contains a minimal demo site to accompany the PHP class `OSTerrain50Reader.php` which returns elevation data for Great Britain. It reads a custom binary data file containing elevation data compiled from the freely-downloadable [OS Terrain 50 ASCII](https://www.ordnancesurvey.co.uk/business-government/products/terrain-50) data set.

The binary data file is produced from a free data-compiling application which is available to download from a sister repo [OSTerrain50-SimpleBinary](https://github.com/bobosola/OSTerrain50-SimpleBinary) - more details there. Direct download links are also available below.

## What problem does this solve?

OS Terrain 50 data is almost certainly the optimal free data set to use for the generation of elevation data for Great Britain. The reasons why are explained in more detail on the [author' site](https://osola.org.uk/osterrain50). The reader code produces thousand of elevations in millseconds and is an easy way to obtain elevation data without using specialist GIS software.

## Running the demo

You can see a [working version of this demo](https://www.osola.org.uk/PHP-OSTerrain50-PHP) on the author's site. 

In order to run it yourself you will need a webserver with PHP 7.4 or higher. Here's how:

* git clone (or download) this repo's files to your webserver
* download the ``ASCII Grid & GML (Grid)`` [OS Terrain 50 ASCII data](https://osdatahub.os.uk/downloads/open/Terrain50) zip file
* download the command-line data compiler application ``osterrain50``, available as:
    * a signed & notarized [Mac Universal Binary 64 bit DMG file](https://github.com/bobosola/OSTerrain50-SimpleBinary/tree/main/binaries/Mac)
    * a [Windows 64 bit zip file](https://github.com/bobosola/OSTerrain50-SimpleBinary/tree/main/binaries/Windows)
    * a [Ubuntu 64 bit tar.gz file](https://github.com/bobosola/OSTerrain50-SimpleBinary/tree/main/binaries/Ubuntu)
* use the application to unzip and convert the downloaded OS data zip file into a binary data file thus:
`$ ./osterrain50 path/to/downloaded_OS_data_zip_file`                

* place the generated binary data file `OSTerrrain50.bin` in the same directory as the site files (the OS data zip and data files can now be discarded)
* browse to the site

The reader class is heavily commented to assist with translation to other languages. The sister Rust repo also contains working demo code to read the binary data file to produce elevation data.