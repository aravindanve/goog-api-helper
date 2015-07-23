<?php 

# goog-api-helper implementation
# by @aravindanve
# on 2015-06-25

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Kolkata');

include '../helper.php';

# form data

$form_data = [

    'Name'          => 'Sarah Connor',
    'Email'         => 'sarah@skynet.org',
    'Date of Birth' => '1992-06-21',
    'Value-1'       => 2324,
    'tag_id'        => 'FCHSD88dzv8VD',
];

/*

# helper with custom config

$config = ['client_type' => goog_api_helper\ClientType::WEB_APPLICATION,];
$helper = new goog_api_helper\Helper($config);

*/

# new helper

$helper = new goog_api_helper\Helper();

if ($helper->load())
{
    # form helper

    $form = $helper->new_form(
        'Test Sheet 1', 'Worksheet 1');

    $form->clear();
    $form->data($form_data);
    /* 

    # or you could do

    foreach ($form_data as $fieldname => $fieldvalue) 
    {
        $form->data($fieldname, $fieldvalue);
    }
    */
    $form->save();
}
else
{
    throw new Exception("error loading api helper");
}


# eof
