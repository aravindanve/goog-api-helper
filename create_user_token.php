<?php 

# google sheets api implementation
# by @aravindanve
# on 2015-06-25

error_reporting(E_ALL);
ini_set('display_errors', 1);

date_default_timezone_set('Asia/Kolkata');

include './helper.php';

$setup_helper = new goog_api_helper\SetupHelper();

# must be called from CLI only

$setup_helper->create_new_user_token();

# eof