#!/usr/bin/env php
<?php

namespace Protec\Pot;

use \PDO;
use \PDOException;

class Pot {
    
    const CONFIGFILE = 'pot.json';
    const VERSION = '0.0.1';
    
    /**
     * Array of arguments passed on command line
     *
     * @var array
     */
    protected $args = array();
    
    /**
     * Location of configuration file
     *
     * @var string
     */
    protected $config;
    
    /**
     * Array of values from json config
     *
     * @var array
     */
    protected $json;
    
    /**
     * Database host
     *
     * @var string
     */
    protected $host;
    
    /**
     * Database name
     *
     * @var string
     */
    protected $database;
    
    /**
     * Database user
     *
     * @var string
     */
    protected $user;
    
    /**
     * Database password
     *
     * @var string
     */
    protected $password;
    
    /**
     * Database table
     *
     * @var string
     */
    protected $table;
    
    /**
     * Location of files to run command against
     *
     * @var string
     */
    protected $files;
    
    /**
     * Command to run
     *
     * @var string
     */
    protected $command;
    
    /**
     * @var \PDO
     */
    protected $conn;
    
    /**
     * Set commandline args
     * Basically pass in $argv
     * 
     * @param array $args
     * @return \Protec\Pot\Pot
     */
    public function setArgs($args = array())
    {
        $this->args = $args;

        return $this;
    }
    
    /**
     * Run
     * Runs the pot application
     */
    public function run()
    {
        // specific changes to run
        if ($this->isHelp()) {
            $this->help();
            exit(2);
        }
        if ($this->isVersion()) {
            $this->logo();
            $this->version();
            exit(2);
        }
        
        // setup pot
        $this->parseArgs();
        $this->getConfig();
        
        // check arguments
        $this->checkHost();
        $this->checkDB();
        $this->checkUser();
        $this->checkPass();
        $this->checkTable();
        $this->checkFiles();
        $this->checkCommand();
        
        // check connection
        $this->checkConn();
        
        $latest_statement = $this->conn->prepare("SELECT transformation FROM {$this->table} ORDER BY transformation DESC LIMIT 1");
        $latest_statement->execute();
        $last_transformation = $latest_statement->fetchColumn();
        
        // list files in directory above the last run file
        $to_run = array();
        $handle = opendir($this->files);
        while (false !== ($file = readdir($handle))) {
            if (pathinfo($file, PATHINFO_EXTENSION) == 'ktr') {
                $number = pathinfo($file, PATHINFO_FILENAME);
                
                if (intval($number) > intval($last_transformation)) {
                    $to_run[intval($number)] = $file;
                }
            }
        }
        closedir($handle);
        ksort($to_run);
        
        // loop through each file and fun command
        foreach ($to_run as $file) {
            $this->conn->beginTransaction();
            exec(
                sprintf(
                    $this->command,
                    $this->files . DIRECTORY_SEPARATOR . $file
                )
            );
            
            $number = intval(pathinfo($file, PATHINFO_FILENAME));
            $insert = $this->conn->prepare("INSERT INTO {$this->table} (transformation) VALUES ({$number})");
            $insert->execute();
            $insert->closeCursor();
            
            $this->conn->commit();
        }
    }
    
    /**
     * parse command line arguments and store values
     */
    protected function parseArgs()
    {
        $options = array(
            'config' => array(
                '-c',
                '--config'
            ),
            'host' => array(
                '--host'
            ),
            'database' => array(
                '-d',
                '--database'
            ),
            'user' => array(
                '-u',
                '--user'
            ),
            'password' => array(
                '-p',
                '--password'
            ),
            'table' => array(
                '-t',
                '--table'
            ),
            'files' => array(
                '-f',
                '--files'
            ),
            'command' => array(
                '--command'
            )
        );
        
        for ($i = 0; $i < count($this->args); $i++) {
            $arg = $this->args[$i];
            
            if (substr($arg, 0, 1) != '-') {
                continue;
            }
            
            foreach ($options as $option => $vals) {
                if (in_array($arg, $vals)) {
                    $this->$option = $this->args[$i+1];
                    continue;
                }
            }
        }
    }
    
    /**
     * Load configuration file, defaults to pot.json in current working directory
     * 
     * @return void
     */
    protected function getConfig()
    {
        if (is_null($this->config)) {
            $this->config = getcwd() . DIRECTORY_SEPARATOR . self::CONFIGFILE;
        }
        if (!is_null($this->config)) {
            if (file_exists($this->config)) {
                $this->json = json_decode(file_get_contents($this->config), true);
                return;
            }
            $filename = getcwd() . DIRECTORY_SEPARATOR . $this->config;
            if (file_exists($filename)) {
                $this->json = json_decode(file_get_contents($filename), true);
                return;
            }
            echo "\033[0;31mERROR:\n";
            echo sprintf("Unable to find file %s\n", $this->config);
            echo "Aborting\033[0m\n";
            exit(1);
        }
    }
    
    /**
     * Check a database host has been set by cli or config
     */
    protected function checkHost()
    {
        if (is_null($this->host)) {
            if (!is_null($this->json)) {
                if (array_key_exists('host', $this->json) && !empty($this->json['host'])) {
                    $this->host = $this->json['host'];
                }
            }
        }
        
        if (is_null($this->host)) {
            echo "\033[0;31mERROR:\n";
            echo "No database host provided\n";
            echo "Aborting\033[0m\n";
            exit(1);
        }
    }
    
    /**
     * Check database name has been set by cli or config
     */
    protected function checkDB()
    {
        if (is_null($this->database)) {
            if (!is_null($this->json)) {
                if (array_key_exists('database', $this->json) && !empty($this->json['database'])) {
                    $this->database = $this->json['database'];
                }
            }
        }
        
        if (is_null($this->database)) {
            echo "\033[0;31mERROR:\n";
            echo "No database name provided\n";
            echo "Aborting\033[0m\n";
            exit(1);
        }
    }
    
    /**
     * Check database user has been set by cli or config
     */
    protected function checkUser()
    {
        if (is_null($this->user)) {
            if (!is_null($this->json)) {
                if (array_key_exists('username', $this->json) && !empty($this->json['username'])) {
                    $this->user = $this->json['username'];
                }
            }
        }
        
        if (is_null($this->user)) {
            echo "\033[0;31mERROR:\n";
            echo "No database username provided\n";
            echo "Aborting\033[0m\n";
            exit(1);
        }
    }
    
    /**
     * Check database password has been set by cli or config
     */
    protected function checkPass()
    {
        if (is_null($this->password)) {
            if (!is_null($this->json)) {
                if (array_key_exists('password', $this->json) && !empty($this->json['password'])) {
                    $this->password = $this->json['password'];
                }
            }
        }
        
        if (is_null($this->password)) {
            echo "\033[0;31mERROR:\n";
            echo "No database password provided\n";
            echo "Aborting\033[0m\n";
            exit(1);
        }
    }
    
    /**
     * Check database table has been set by cli or config
     */
    protected function checkTable()
    {
        if (is_null($this->table)) {
            if (!is_null($this->json)) {
                if (array_key_exists('table', $this->json) && !empty($this->json['table'])) {
                    $this->table = $this->json['table'];
                }
            }
        }
        
        if (is_null($this->table)) {
            echo "\033[0;31mERROR:\n";
            echo "No database table provided\n";
            echo "Aborting\033[0m\n";
            exit(1);
        }
        
        $this->table = preg_replace("/[^A-Za-z0-9_]/", '', $this->table);
    }
    
    /**
     * Check files directory has been set by cli or config
     */
    protected function checkFiles()
    {
        if (is_null($this->files)) {
            if (!is_null($this->json)) {
                if (array_key_exists('files', $this->json) && !empty($this->json['files'])) {
                    $this->files = $this->json['files'];
                }
            }
        }
        
        if (is_null($this->files)) {
            echo "\033[0;31mERROR:\n";
            echo "No transformation directory provided\n";
            echo "Aborting\033[0m\n";
            exit(1);
        }
        
        if (!file_exists($this->files)) {
            $files = getcwd() . DIRECTORY_SEPARATOR . $this->files;
            if (!file_exists($files)) {
                echo "\033[0;31mERROR:\n";
                echo "Transformation directory not found\n";
                echo "Aborting\033[0m\n";
                exit(1);
            }
            $this->files = $files;
        }
        
        $handle = opendir($this->files);
        
        $transformations_found = false;
        
        while (false !== ($file = readdir($handle))) {
            if ($file != '.' && $file != '..') {
                $transformations_found = true;
                continue;
            }
        }
        closedir($handle);
        
        if (!$transformations_found) {
            echo "\033[0;31mERROR:\n";
            echo "Directory contains no transformation (.ktr) files\n";
            echo "Aborting\033[0m\n";
            exit(1);
        }
    }
    
    /**
     * Check command has been set by cli or config
     */
    protected function checkCommand()
    {
        if (is_null($this->command)) {
            if (!is_null($this->json)) {
                if (array_key_exists('command', $this->json) && !empty($this->json['command'])) {
                    $this->command = $this->json['command'];
                }
            }
        }
        
        if (is_null($this->command)) {
            echo "\033[0;31mERROR:\n";
            echo "No command provided\n";
            echo "Aborting\033[0m\n";
            exit(1);
        }
    }
    
    /**
     * Check we can establish a connection to database, and create table if missing
     */
    protected function checkConn()
    {
        $connect_string = sprintf("mysql:host=%s;dbname=%s", $this->host, $this->database);
        try {
            $this->conn = new PDO($connect_string, $this->user, $this->password);
        } catch (PDOException $e) {
            echo "\033[0;31mERROR:\n";
            echo sprintf("Unable to connect to database %s with supplied credentials\n", $this->database);
            echo sprintf("%s\n", $e->getMessage());
            echo "Aborting\033[0m\n";
            exit(1);
        }
        
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $this->conn->exec(
            sprintf("CREATE TABLE IF NOT EXISTS `%s` (transformation INT NOT NULL, last_run TIMESTAMP DEFAULT NOW(), PRIMARY KEY (transformation)) ENGINE = InnoDB DEFAULT CHARSET = utf8", $this->table)
        );
    }
    
    /**
     * Check if current execution should display help
     * 
     * @return boolean
     */
    protected function isHelp()
    {
        $help = array(
            '-h',
            '--help'
        );
        
        foreach ($this->args as $arg) {
            if (in_array($arg, $help)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if current execution should display version
     * 
     * @return boolean
     */
    protected function isVersion()
    {
        $version = array(
            '-v',
            '--version'
        );
        
        foreach ($this->args as $arg) {
            if (in_array($arg, $version)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Display help
     */
    protected function help()
    {
        $this->logo();
        $this->version();
        
        echo "\n\033[1;33mUsage:\033[0m\n";
        echo "  php pot.php [options]\n";
        
        echo "\n\033[1;33mOptions:\033[0m\n";
        echo "\033[0;32m  --help          -h            \033[0mDisplay this help message\n";
        echo "\033[0;32m  --config <file> -c <file>     \033[0mUse configuration from <file> instead of pot.json\n";
        echo "\033[0;32m  --db <database> -d <database> \033[0mOverride configuration file and use <database>\n";
        echo "\033[0;32m  --user <user>   -u <user>     \033[0mOverride configuration file and use database user <user>\n";
        echo "\033[0;32m  --pass <pass>   -p <pass>     \033[0mOverride configuration file and use database password <pass>\n";
        echo "\033[0;32m  --table <table> -t <table>    \033[0mOverride configuration file and use database password <pass>\n";
        echo "\033[0;32m  --files <dir>   -f <dir>      \033[0mOverride configuration file and use directory <dir> for transformations\n";
        echo "\033[0;32m  --kettle <dir>  -k <dir>      \033[0mOverride configuration file and use directory <dir> for kettle\n";
        echo "\033[0;32m  --version       -v            \033[0mDisplay this application version\n";
    }
    
    /**
     * Display logo
     */
    protected function logo()
    {
        echo "\033[0;32m______     _\n";
        echo "| ___ \   | |\n";
        echo "| |_/ /__ | |_\n";
        echo "|  __/ _ \| __|\n";
        echo "| | | (_) | |_\n";
        echo "\_|  \___/ \__|\033[0m\n";
    }
    
    /**
     * Display Version
     */
    protected function version()
    {
        echo sprintf("\n\033[0;32mPot\033[0m version \033[0;31m%s\033[0m\n", self::VERSION);
    }
}

$pot = new Pot();
$pot->setArgs($argv)
    ->run();
