# OS Terrain 50 elevation data in PHP

This repo contains a minimal demo site to accompany the PHP class `OSTerrain50Reader.php` which returns elevation data for Great Britain. It reads a custom binary data file containing elevation data compiled from the freely-downloadable [OS Terrain 50 ASCII](https://www.ordnancesurvey.co.uk/business-government/products/terrain-50) data set. 

Compiled binaries of the data-compiling application are available as detailed below.

A sister repo [OSTerrain50-SimpleBinary](https://github.com/bobosola/OSTerrain50-SimpleBinary) contains the Rust code for building the data-compling application. More details are there if you wish to build the data-compiling application yourself.


## What problem does this solve?

OS Terrain 50 data is almost certainly the optimal free data set to use for the generation of elevation data for Great Britain. The reasons why are explained in more detail on the [author' site](https://osola.org.uk/osterrain50). The reader code produces thousand of elevations in millseconds and is an easy way to obtain elevation data without using specialist GIS software.

## Running the demo

You can see a working version of this demo on the [author's site](https://www.osola.org.uk/PHP-OST50). 

In order to run it yourself you will need a webserver with PHP 7.4 or higher. Here's how:

* git clone (or download) this repo's files to your webserver
* download the [OS Terrain 50 ASCII data]() zip file
* download the command-line data compiler application ``osterrain50``, available as:
    * a [Mac universal 64 bit DMG file](https://osola.org.uk/osterrain50/binaries/osterrain50.dmg)
    * a [Windows 64 bit ZIP file](https://osola.org.uk/osterrain50/binaries/osterrain50.zip)
    * a [Ubuntu 64 bit GZ file](https://osola.org.uk/osterrain50/binaries/osterrain50.tar.gz)
* use the application to unzip and convert the downloaded OS data zip file into a binary data file thus: `$ osterrain50 path/to/downloaded_OS_data_zip_file`                

* place the generated binary data file `OSTerrrain50.bin` in the same directory as the site files
* browse to the site and check the demo

The reader class is heavily commented to assist with translation to other languages. The Rust repo also contains working demo code to read the binary data file to produce elevation data.