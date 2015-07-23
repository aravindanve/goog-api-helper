<?php 

# goog-api-helper implementation
# by @aravindanve
# on 2015-06-25

error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../helper.php';

/*

# helper with custom config

$config = ['client_type' => goog_api_helper\ClientType::WEB_APPLICATION,];
$helper = new goog_api_helper\Helper($config);

*/

# new helper

$helper = new goog_api_helper\Helper();

if ($helper->load())
{
    # drive helper

    $drive = $helper->new_drive();

    $drive->upload(
        'target-files/file_to_upload.txt',
        'samplefile.txt',
        'Sample Upload Test/files/');
}
else
{
    throw new Exception("error loading api helper");
}


# eof
