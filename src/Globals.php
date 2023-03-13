<?php

class Globals
{
    static private $instance = null;

    public $calls = [];

    static public function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new self();
        }

        return self::$instance;
    }
}
