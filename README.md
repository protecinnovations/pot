pot
===
        ______     _
        | ___ \   | |
        | |_/ /__ | |_
        |  __/ _ \| __|
        | | | (_) | |_
        \_|  \___/ \__|

Tool to manage iterating through files in a folder and running a command against them.
Records the filename of each file and the timestamp of when run in database.

Can be run by passing command line flags, or will read configuration from a json config file.

Usage
=====

php pot.php [options]

Alternatively, add pot.php you your PATH and just run

pot [options]

Options
=======

```
  --help          -h            Display help message
  --config <file> -c <file>     Use configuration from <file> instead of pot.json
  --db <database> -d <database> Override configuration file and use <database>
  --user <user>   -u <user>     Override configuration file and use database user <user>
  --pass <pass>   -p <pass>     Override configuration file and use database password <pass>
  --table <table> -t <table>    Override configuration file and use database password <pass>
  --files <dir>   -f <dir>      Override configuration file and use directory <dir> for transformations
  --kettle <dir>  -k <dir>      Override configuration file and use directory <dir> for kettle
  --version       -v            Display application version
```

Configuration
=============

Provided is an example pot.json file (example.pot.json). Copy this file to your project directory and adjust to your needs.
The table pot uses will be created if it does not exist.
The command to run must be as complete as possible, missing only the file to run against (represented by %s), eg:
 * echo %s
 * chmod 775 %s
 * /usr/bin/kettle/pan.sh -file=%s
All files in the directory listed will have the command run against. It is recommended to give these files a numeric filename, as pot will calculate the integer value of the filename to determine what order the files should be run