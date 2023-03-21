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

    public $caller_id = [];

    public $destination_number = [];

    public $call_start_time = [];

    static public function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
