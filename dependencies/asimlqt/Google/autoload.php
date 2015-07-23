<?php

# autoloader
# by @aravindanve

function php_google_spreadsheet_client_autoload($class_name)
{
    $class_path = explode('\\', $class_name);

    if ($class_path[0] != 'Google') return;

    $class_path = array_slice($class_path, 1, 2);

    $file_path = dirname(__FILE__).'/'.implode('/', $class_path).'.php';

    if (file_exists($file_path)) 
    {
        require_once($file_path);
    }
}

spl_autoload_register('php_google_spreadsheet_client_autoload');