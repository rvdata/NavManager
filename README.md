# NavManager

__Contact:__ R2R Program, info@rvdata.us

## Description

This code is designed to produce three navigation standard products from the primary GPS receiver onboard research vessels in the UNOLS fleet. Tools exist for finding basic quality parameters, flag bad points, and plot the data.  For a full description of capabilities, please check out the cookbook provided.

## Requirements

You will need a version of PHP that is at least 5.2 in order to use the built\-in JSON functions: json\_encode\(\) and json\_decode\(\).

You will need a version of Java that is at least 1.5 in order to use the NavControl Java code.

If you wish to make basic maps of r2rnav products using the experimental navplot.php function, you must have [GMT](http://gmt.soest.hawaii.edu/) installed.

## Installation

Clone the repository into a directory from which it can be run:

    git clone git@github.com:rvdata/NavManager.git

You should add the absolute path name of where you placed these programs to your path environment variable.

For Mac users running bash:

    cat 'PATH=$PATH:/path/to/r2r/NavManager/bin' >> ~/.bash_profile

For linux users running bash:

    cat 'PATH=$PATH:/path/to/r2r/NavManager/bin' >> ~/.bashrc

For linux users running csh:

    cat 'setenv PATH $PATH:/path/to/r2r/NavManager/bin' >> ~/.cshrc

Make sure to change the path in these commands to where your NavManager code actually resides.

Now you can type the name of the executable scripts on the command line  and see a general description of what the code does and what arguments to specify.

## Products

* __NavBestRes__ - GPS position sampled at the highest rate in time (typ. once-per-second) with "bad" positions flagged.
* __Nav1Min__ - NavBestRes sampled at once-per-minute
* __NavControl__ - Nav1Min sampled using the Douglas Peucker algorithm to specify the minimum number of points to capture the general shape of a curve

## Code Manifest

#### Executable scripts:

* __bin/navcopy.php__ - Convert raw navigation data into the r2r standard format
* __bin/navformat.php__ - Display supported input navigation formats.
* __bin/navinfo.php__ - Get basic info on r2rnav files
* __bin/navplot.php__ - Make a basic plot of r2rnav file (experimental; requires GMT 4+)
* __bin/navqa.php__ - Performa a quality assesment report on r2rnav files
* __bin/navqc.php__ - Flag bad values in r2rnav files
* __bin/navsample.php__ - Downsample r2rnav files
* __bin/navsogcog.php__ - Compute speed over ground and course over ground for r2rnav files


#### Documentation:
* __doc/fileformat/format-nav[N].txt__ - Plain text files describing known fileformats (and content)
* __doc/guide/NavManager_Cookbook.pdf__ - PDF file containing a cookbook for someone starting out with NavManager
* __doc/bestpractices/Recommended-Best-Practices-for-Navigation-Data-Collection.pdf__ - PDF file describing recommendations to operators on best practices for navigation data collection

#### Libraries:

* __include/decompressor.inc.php__ - Test for the presence of compressed files and uncompress them in your temporary work space.
* __include/flags.inc.php__ - Defines the character used for flagging during quality control.
* __include/getopts.php__ - Function to process command-line arguments.
* __include/globals.inc.php__ - Contains global parameters.
* __include/jsontools.inc.php__ - Function to pretty-print JSON given a PHP object.
* __include/navbounds.inc.php__ - Calculate the geographic extent of the cruise (north, south, west, east).
* __include/navconvert.inc.php__ - Convert R2R Navigation Standard format into MB-System nav format 1 (experimental)
* __include/navcopy.inc.php__ - Read through all files in the datalist and translate them into R2R navigation standard format.
* __include/navdatalist.inc.php__ - Create a date/time ordered list of raw navigation files.
* __include/navheader.inc.php__ - Define product headers
* __include/navqa.inc.php__ - Assess the quality of the navigation in standard format.
* __include/navqc.inc.php__ - Flag "bad" navigation data.  Output: NavBestRes
* __include/navsample.inc.php__ - Sample the navigation data at a lower rate than originally sampled.  No interpolation or filtering is performed
* __include/navsogcog.inc.php__ - Calculate instantaneous speed over ground and course over ground and append columns to NavBestRes file.
* __include/navtools.inc.php__ - Functions for calculating distance, speed, acceleration, azimuth, and geographic extent.
* __include/nmeatools.inc.php__ - Classes for various NMEA messages.
* __include/xmltools.inc.php__ - Function to write QA Report in XML format.
* __include/xmltools2.inc.php__ - Function to write QA Report in the QA 2.0 XML format.
 
 
* __include/NavControl/__ - Contains Java code for creating control point navigation
* __include/Templates/__ - Contains XML templates



