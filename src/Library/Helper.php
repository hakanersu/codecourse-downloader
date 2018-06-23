<?php

use App\Colors;

if (!function_exists('output')) {
    function output() {
        return new Colors();
    }
}

if (!function_exists('error')) {
    function error($msg) {
        echo output()->getColoredString($msg, "red");
        echo "\n";
    }
}

if (!function_exists('success')) {
    function success($msg) {
        echo output()->getColoredString($msg, "green");
        echo "\n";
    }
}