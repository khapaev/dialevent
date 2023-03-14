<?php

class Globals
{
    static private $instance = null;

    public $calls = [];

    public $uniqueIDs = [];

    public $fullFnameUrls = [];

    public $callerIDNums = [];

    public $durations = [];

    public $dispositions = [];

    public $extensions = [];

    static public function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
