<?php

# goog-api-helper/helper.php

# by @aravindanve
# on 2015-06-25

# using 
# https://github.com/asimlqt/php-google-spreadsheet-client
# by Asim Liaquat (https://github.com/asimlqt)
#
# and google-client-api

# note: only unix-style paths supported

namespace goog_api_helper;

# debug mode

$goog_api_helper_debug = true;

class ClientType
{
    const WEB_APPLICATION = 1;
    const SERVICE_ACCOUNT = 2;
}

# start session

(session_status() == PHP_SESSION_ACTIVE) 
    or session_start();

# enable debug mode

if ($goog_api_helper_debug)
{
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

# helper class

class Helper
{
    # config

    private $config = [];

    # client

    public $client = null;
    public $current_token = null;

    # helpers

    public $forms = [];
    public $drives = [];

    public function __construct($config = null)
    {
        # default config

        $def                                = [];

        # paths

        $def['path_dependencies']           = 'dependencies/';
        $def['path_keys']                   = 'keys/';

        # files

        $def['file_service_account_key']    = 'google-service-account.json';
        $def['file_client_secret']          = 'google-client-secret.json';
        $def['file_user_token']             = 'user_token.json';

        # client

        $def['client_app_name']             = 'goog_app';
        $def['client_type']                 = ClientType::WEB_APPLICATION;
        $def['client_scopes']               = [

            'https://www.googleapis.com/auth/drive',
            'https://spreadsheets.google.com/feeds',
        ];

        # load base paths

        $this->pathinfo = pathinfo(__FILE__);
        $this->basepath = rtrim($this->pathinfo['dirname'], '/').'/'; 
        $this->basename = $this->pathinfo['basename']; 

        # load custom config

        is_array($config) or $config = [];

        foreach ($config as $name => $value) 
        {
            $def[$name] = $value;
        }

        # prepend basepath and append forward slash to paths

        foreach ($def as $name => $value) 
        {
            if (substr($name, 0, 5) == 'path_')
            {
                $def[$name] = $this->basepath.rtrim($value, '/').'/';
            }
        }

        # load config

        foreach ($def as $name => $value) 
        {
            $conf_name = '_'.$name;
            $this->$conf_name = $value;
        }
    }

    public function get_version()
    {
        if (file_exists($this->basepath.'.version'))
        {
            return trim(file_get_contents(
                $this->basepath.'.version'));
        }
        else
        {
            return 'unknown';
        }
    }

    public function is_loaded()
    {
        if ($this->client instanceof \Google_Client)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    # load() must be called before loading helpers
    # if no_refresh is true, refreshing of token will not be attempted

    public function load($no_refresh = false)
    {
        # include dependencies

        require_once $this->_path_dependencies
            .'google/Google/autoload.php';

        require_once $this->_path_dependencies
            .'asimlqt/Google/autoload.php';

        # load client

        $_loader = new _loader($this);

        # ignore refreshing token if partial

        $this->client = $_loader->get_client($no_refresh);

        # is loaded

        return $this->is_loaded();
    }

    public function new_drive()
    {
        $drive = new Drive($this);

        $this->drives[] = $drive;

        return $drive;
    }

    public function new_form(
        $sheet_name = null, 
        $worksheet_name = null)
    {
        $form = new Form(
            $this, 
            $sheet_name, 
            $worksheet_name);

        $this->forms[] = $form;

        return $form;
    }
}

# set up class

class SetupHelper
{
    # create new user token
    # CLI access only

    static function create_new_user_token()
    {
        # run only in CLI mode

        if (php_sapi_name() !== 'cli') 
            throw new HelperException('allowed in CLI mode only.');

        # check if client is loaded

        $helper = new Helper();

        if ($helper->load(true))
        {
            # configure client to get refresh token

            $helper->client->setAccessType('offline');

            # create auth url

            $auth_url = $helper->client->createAuthUrl();

            # request authorization

            print "\nplease visit:\n$auth_url\n\n";
            print "copy and paste the auth code here:\n";

            $auth_code = trim(fgets(STDIN));

            # exchange auth code for access token

            $access_token = $helper->client->authenticate($auth_code);

            # write access token to file

            if (file_exists($helper->_path_keys.$helper->_file_user_token))
            {
                rename(
                    $helper->_path_keys.$helper->_file_user_token,
                    $helper->_path_keys.time().'_'.$helper->_file_user_token
                );
            }

            file_put_contents(
                $helper->_path_keys.$helper->_file_user_token, 
                $access_token);

            print "\nuser token saved\n\n";
        }
        else
        {
            throw new HelperException('error loading helper.');
        }
    }
}

# helper exception class

class HelperException extends \Exception
{
    public function __construct(
        $message = null, 
        $code = 0, 
        Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
    public function __toString() 
    {
        // return __CLASS__ . ": [{$this->code}]: {$this->message}\n";

        $xstr = "code: {$this->code}\n"
            ."message: {$this->message}\n"
            ."line: {$this->line} | {$this->file}\n"
            ."Stack Trace:\n".parent::getTraceAsString();

        return 'exception \''.__CLASS__.'\', '.$xstr;
    }
}

# classes for internal use

class _loader
{
    var $client = null;
    var $_assertion_credentials = null;

    function __construct(Helper $helper_instance) 
    {
        $this->h = $helper_instance;
    }

    function _get_file($file)
    {
        if (!file_exists($file))
            throw new HelperException(
                'file not found: '.$file);

        $json = file_get_contents($file);
        $content_object = json_decode($json);

        if (is_object($content_object))
        {
            return $content_object;
        }
        else
        {
            return null;
        }
    }

    function _new_client($from_session = true, $enforce_type = 0)
    {
        # new client 

        $client = new \Google_Client();
        $client->setApplicationName(
            $this->h->_client_app_name);

        if ($from_session and isset(
            $_SESSION, $_SESSION['access_token']))
        {
            if (!$enforce_type) 
            {
                $client->setAccessToken(
                    $_SESSION['access_token']);

                # set current access token in helper

                $this->h->_current_token = 
                    $_SESSION['access_token'];
            }
            elseif (isset($_SESSION['account_type'])
                and ($enforce_type == $_SESSION['account_type']))
            {
                $client->setAccessToken(
                    $_SESSION['access_token']);

                # set current access token in helper

                $this->h->_current_token = 
                    $_SESSION['access_token'];
            }
        }

        return $client;
    }

    function _configure_client(&$client, $type = null)
    {
        isset($type) or $type = ClientType::WEB_APPLICATION;

        if ($type == ClientType::WEB_APPLICATION)
        {
            # load web application client id & secret
            
            if (!$this->_get_file(
                $this->h->_path_keys
                .$this->h->_file_client_secret))
                throw new HelperException(
                    'invalid client_secret');

            $client->setAuthConfigFile(

                $this->h->_path_keys
                    .$this->h->_file_client_secret
            );

            foreach ($this->h->_client_scopes as $scope) 
            {
                $client->addScope($scope);
            }
        }
        elseif ($type == ClientType::SERVICE_ACCOUNT)
        {
            # load service account key
            
            $service_account_key = $this->_get_file(

                $this->h->_path_keys
                    .$this->h->_file_service_account_key
            );

            if (!$service_account_key)
                throw new HelperException('invalid json key');

            if (!isset(
                $service_account_key->client_email, 
                $service_account_key->private_key))
                throw new HelperException(
                    'email or private key missing');

            $credentials = new \Google_Auth_AssertionCredentials(

                $service_account_key->client_email,
                $this->h->_client_scopes,
                $service_account_key->private_key
            );

            $client->setAssertionCredentials($credentials);
            $this->_assertion_credentials = $credentials;
        }
        else
        {
            throw new HelperException('invalid client type'.' '
                .'when calling '.__FUNCTION__);
        }
    }

    function _refresh_token(&$client, $type = null)
    {
        isset($type) or $type = ClientType::WEB_APPLICATION;

        if ($type == ClientType::WEB_APPLICATION)
        {
            $user_token_file = $this->h->_path_keys
                                .$this->h->_file_user_token;

            if (file_exists($user_token_file))
            {
                $user_token = file_get_contents($user_token_file);

                $client->setAccessToken($user_token);
                $client->refreshToken($client->getRefreshToken());
            }
            else
            {
                throw new HelperException('no valid user token found');
            }
        }
        elseif ($type == ClientType::SERVICE_ACCOUNT)
        {
            if ($this->_assertion_credentials)
            {
                # set credentials  & refresh
                $client->getAuth()->refreshTokenWithAssertion(
                    $this->_assertion_credentials);
            }
            else
            {
                throw new HelperException('client not configured');
            }
        }
        else
        {
            throw new HelperException('invalid client type'.' '
                .'when calling '.__FUNCTION__);
        }

        # get & save token to session

        $_SESSION['account_type'] = $type;
        $_SESSION['access_token'] = $client->getAccessToken();


        # set current access token in helper

        $this->h->_current_token = 
            $_SESSION['access_token'];
    }

    function get_client($ignore_refresh_token = false)
    {
        $type = $this->h->_client_type;
        isset($type) or $type = ClientType::WEB_APPLICATION;

        $client = $this->_new_client(true, $type);
        $this->_configure_client($client, $type);

        if ($client->getAuth()->isAccessTokenExpired()
            and !$ignore_refresh_token)
        {
            $this->_refresh_token($client, $type);
        }

        # set current token object

        $this->h->current_token = 
            json_decode($this->h->_current_token);

        if (json_last_error() != JSON_ERROR_NONE)
        {
            throw new HelperException('invalid json token');
        }


        return $client;
    }

}

class _filetree
{

    static function create_tree($root, &$list, &$titles, $depth = 0)
    {
        if (isset($titles[$depth + 1]))
        {
            foreach ($list as $item) 
            {
                foreach ($item['parents'] as $parent) 
                {
                    if ($parent['id'] == $root['id'])
                    {
                        if ($titles[$depth + 1] == $item['title'])
                        {
                            isset($root['children'])
                                or $root['children'] = [];

                            $root['children'][] = 
                                call_user_func_array(
                                    'self::'.__FUNCTION__, 
                                    [
                                        $item, 
                                        $list, 
                                        $titles, 
                                        $depth + 1,
                                    ]);
                        } 
                    }
                }
            }
        }

        return $root;
    }

    static function deepest_leaf($root, $depth = 0)
    {
        $deepest = [
            'id' => $root['id'],
            'depth' => $depth,
        ];

        if (isset($root['children']))
        {
            foreach ($root['children'] as $child) 
            {
                $temp = call_user_func_array(
                            'self::'.__FUNCTION__, [
                                $child, 
                                $depth + 1
                            ]);
                if ($temp['depth'] > $deepest['depth'])
                {
                    $deepest = $temp;
                }

            }
        }
        return $deepest;
    }

    static function map_destination($root_id, $list, $titles)
    {
        # create tree

        $titles = array_merge(['root'], $titles);

        $tree = self::create_tree(
            ['id' => $root_id], $list, $titles
        );

        # get deepest leaf

        $deepest = self::deepest_leaf($tree);

        $map = [
            'complete' => (count($titles) == ($deepest['depth'] + 1)),
            'deepest_leaf_id' => $deepest['id'],
            'deepest_leaf_depth' => $deepest['depth'] - 1,
        ];

        return $map;
    }
}

class _meta
{
    static function get_file_meta($file_object)
    {
        $file_meta = [

            'id' => $file_object['id'],
            'title' => $file_object['title'],
            'mimeType' => $file_object['mimeType'],
            'kind' => $file_object['kind'],
            'parents' => [],
        ];

        foreach ($file_object['parents'] as $pf) 
        {
            $file_meta['parents'][] = [

                'id' => $pf['id'],
                'kind' => $pf['kind'],
                'isRoot' => $pf['isRoot'],
            ];
        }
        return $file_meta;
    }
}

# helpers

class GenericHelper 
{
    protected $h = null;
    protected $service = null;

    public function __construct(Helper $helper)
    {
        if (!$helper->is_loaded())
        {
            throw new HelperException('client not loaded');
        }

        # save helper ref

        $this->h = $helper;
    }
}

# drive helper

class Drive extends GenericHelper
{
    const UPLOAD_FILE       = 1;
    const NEW_FOLDER        = 2;
    const NEW_SPREADSHEET   = 3;

    protected $root_folder_id = null;

    public function __construct(Helper $helper)
    {
        parent::__construct($helper);

        # init service

        $this->service = 
            new \Google_Service_Drive($this->h->client);

        # init root folder id

        $this->root_folder_id = 
            $this->service->about->get()->getRootFolderId();
    }

    protected function _retrieve_all_files($q = '')
    {
        $result = [];
        $page_token = null;

        do 
        {
            try 
            {
                $params = [];

                if ($page_token)
                {
                    $params['pageToken'] = $page_token;
                }
                if ($q)
                {
                    $params['q'] = $q;
                }
                $files = $this->service->files->listFiles($params);

                $result = array_merge($result, $files->getItems());

                $page_token = $files->getNextPageToken();
            }
            catch (Exception $e)
            {
                throw new HelperException(
                    "Error occurred: ".$e->getMessage());
            }

        } while ($page_token);
        
        return $result;
    }

    public function create_file()
    {
        # unsupported
    }

    # path_to_file is relative to 
    # current working directory
    # options may include
    # destination: destination path
    # title: file title
    # is_private: true or false (true by default)

    public function upload(
        $path_to_file, 
        $title = null, 
        $destination = null,
        $is_private = true)
    {
        # title

        if (!isset($title) or empty($title))
        {
            $title = preg_replace(

                '#.*\/([^\/]*$)#i', 
                '\\1', $path_to_file
            );
        }

        $options = [];

        $options['path_to_file'] = $path_to_file;

        if ($destination) 
            $options['destination'] = $destination;

        if ($is_private) 
            $options['is_private'] = $is_private;

        return $this->_new_file(
            self::UPLOAD_FILE, $title, $options);
    }

    public function new_spreadsheet(
        $title, 
        $destination = null, 
        $is_private = true)
    {
        if (!isset($title) or empty($title))
        {
            throw new HelperException(
                "new spreadsheet title is required");
            
        }

        $options = [];

        if ($destination) 
            $options['destination'] = $destination;

        if ($is_private) 
            $options['is_private'] = $is_private;

        return $this->_new_file(
            self::NEW_SPREADSHEET, $title, $options);
    }

    public function _new_file($type, $title, $options = [])
    {
        if (!in_array($type, [
            self::UPLOAD_FILE,
            self::NEW_FOLDER,
            self::NEW_SPREADSHEET,
            ]))
        {
            throw new HelperException(
                'invalid file type specified');
        }

        if (!is_string($title) or empty($title))
        {
            throw new HelperException(
                "new file title is required");
            
        }

        if ($type == self::UPLOAD_FILE)
        {
            if (!isset($options['path_to_file']))
            {
                throw new HelperException(
                    'path_to_file option must be'
                    .' set for uploading files');
            }

            if (!file_exists($options['path_to_file']))
            {
                throw new HelperException(
                    'file not found');
            }
        }

        is_array($options) or $options = [];
        $new_file = new \Google_Service_Drive_DriveFile();

        # title

        $new_file->setTitle($title);

        # destination

        if (isset($options['destination'])
            and !empty($options['destination']))
        {
            $parent_titles = preg_split(
                '#\/#i', $options['destination'], 
                null, PREG_SPLIT_NO_EMPTY);

            # find the last parent id

            if (count($parent_titles))
            {
                $parent_titles_copy = $parent_titles;
                foreach ($parent_titles_copy as &$ti) 
                {
                   $ti = 'title=\''.$ti.'\'';
                }
                unset($ti);
                $titles = implode(' or ', $parent_titles_copy);

                $q_params = 
                    'mimeType=\'application/vnd.google-apps.folder\''
                    .' and trashed=false'
                    .' and ('.$titles.')';

                # get folders from destination path

                $folders = $this->_retrieve_all_files($q_params);

                # prepare list

                $folder_list = [];
                $folder_list_count = 0;

                foreach ($folders as $fl) 
                {
                    # get relevant file meta data 
                    # from fileobj

                    $folder_list[$folder_list_count] = 
                        _meta::get_file_meta($fl);

                    $folder_list_count++;
                }

                # build folder tree

                $map = _filetree::map_destination(
                                    $this->root_folder_id, 
                                    $folder_list, 
                                    $parent_titles);

                # set last parent id

                $last_parent_id = $map['deepest_leaf_id'];

                # if map incomplete 
                # create remaining folders

                if (!$map['complete'])
                {
                    for($i = ($map['deepest_leaf_depth'] + 1);
                        $i < count($parent_titles); $i++)
                    {
                        # create $parent_titles[$i]
                        # set new parent id to last_parent_id

                        $new_folder =
                            new \Google_Service_Drive_DriveFile(); 

                        # set new folder title

                        $new_folder->setTitle($parent_titles[$i]);

                        $new_folder->setMimeType(
                            'application/vnd.google-apps.folder');

                        # configure parent

                        $new_folder_parent_ref =
                            new \Google_Service_Drive_ParentReference();

                        $new_folder_parent_ref->setId($last_parent_id);

                        $new_folder->setParents([$new_folder_parent_ref]);

                        # create folder

                        $created_folder = $this->service->files->insert(
                            $new_folder);

                        # set last parent id

                        $last_parent_id = $created_folder->id;
                    }
                }

                # get parent reference for file to be uploaded

                $parent_reference = 
                    new \Google_Service_Drive_ParentReference();

                $parent_reference->setId($last_parent_id);

                # set new file's parent

                $new_file->setParents([$parent_reference]);
            }
            else
            {
                # set destination to root folder

                $parent_reference = 
                    new \Google_Service_Drive_ParentReference();
                $parent_reference->setId($this->root_folder_id);

                $new_file->setParents([$parent_reference]);
            }
        }

        # is_private

        if (isset($options['is_private'])
            and !$options['is_private'])
        {
            # public

            $file_visibility = 'DEFAULT';
        }
        else 
        {
            # private
            
            $file_visibility = 'PRIVATE';
        }

        # file type

        $_file_insert_options = [

            'uploadType' => 'multipart',
            'visibility' => $file_visibility,
        ];

        if ($type == self::UPLOAD_FILE)
        {
            $new_file->setMimeType(
                'application/octet-stream');

            $_file_insert_options['data'] = 
                file_get_contents($options['path_to_file']);
        }
        elseif ($type == self::NEW_FOLDER)
        {
            $new_file->setMimeType(
                'application/vnd.google-apps.folder');
        }
        elseif ($type == self::NEW_SPREADSHEET)
        {
            $new_file->setMimeType(
                'application/vnd.google-apps.spreadsheet');
        }

        # insert new file into drive

        $result = $this->service->files->insert(
            $new_file, $_file_insert_options);

        /*
        echo '<pre>';
        echo 'root_id: '.$this->root_folder_id."\n";
        echo 'last_parent_id: '
            .(isset($last_parent_id)?$last_parent_id:'--')."\n";
        var_dump(_meta::get_file_meta($result));
        */

        return $result;
    }

    public function resumable_file_upload()
    {
        # unsupported
    }
}

# form helper
# uses sheets api

class Form extends GenericHelper
{
    protected $sheet_name = null;
    protected $worksheet_name = null;
    protected $sheet_destination = null;
    protected $fields = [];

    public function __construct(
        Helper $helper, 
        $sheet_name = null,
        $worksheet_name = null,
        $sheet_destination = null)
    {
        parent::__construct($helper);

        # init sheet name

        isset($sheet_name) 
            or $sheet_name = $this->h->_client_app_name;

        $this->sheet_name = $sheet_name;

        # init worksheet name

        isset($worksheet_name)
            or $worksheet_name = 'Sheet1';

        $this->worksheet_name = $worksheet_name;

        # init spreadsheet service 

        $service_request = 
            new \Google\Spreadsheet\DefaultServiceRequest(
                $this->h->current_token->access_token, 'Bearer');

        \Google\Spreadsheet\ServiceRequestFactory::setInstance(
            $service_request);

        $this->service = 
            new \Google\Spreadsheet\SpreadsheetService();
    }

    public function clear()
    {
        # clear fields

        $this->fields = [];
    }

    # format
    # to set:
    # $form_helper->data(array('fieldname' => 'value'));
    # or
    # $form_helper->data('fieldname', 'value')
    # to get:
    # $form_helper->data('fieldname');

    public function data($data, $value = null)
    {
        # append field data

        if (is_array($data))
        {
            foreach ($data as $name => $val) 
            {
                $this->fields[$name] = $val;
            }
        }
        elseif ($value)
        {
            $this->fields[$data] = $value;
        }
        else
        {
            return (isset($this->fields[$data])? 
                $this->fields[$data] : null);
        }
    }

    protected function _get_new_spreadsheet($title,
        \Google\Spreadsheet\SpreadsheetService &$service,
        \Google\Spreadsheet\SpreadsheetFeed &$spreadsheet_feed)
    {       
            $drive = $this->h->new_drive();

            $_nws_result = $drive->new_spreadsheet(
                $title,
                $this->sheet_destination);

            $spreadsheet_feed = 
                $this->service->getSpreadsheets();

            $spreadsheet = $spreadsheet_feed->getByTitle(
                $title);

            if (!$spreadsheet) 
            {
                throw new HelperException(
                    'error creating spreadsheet');
            }

            return $spreadsheet;
    }

    protected function _get_new_worksheet($title,
        \Google\Spreadsheet\Spreadsheet &$spreadsheet,
        \Google\Spreadsheet\WorksheetFeed &$worksheet_feed)
    {
            $spreadsheet->addWorksheet($title);

            $worksheet_feed = 
                $spreadsheet->getWorksheets();

            # delete auto generated worksheets

            $_auto_worksheet_names = [

                'Sheet1', 'Sheet2', 'Sheet3', 'Sheet4'
            ];

            $auto_worksheet_names = [];

            foreach ($_auto_worksheet_names as $_auto_worksheet_name) 
            {
                if ($title != $_auto_worksheet_name)
                {
                    $auto_worksheet_names[] = $_auto_worksheet_name;
                }
            }

            foreach ($auto_worksheet_names as $auto_worksheet_name) 
            {
                $temp_worksheet = $worksheet_feed->getByTitle(
                    $auto_worksheet_name);

                if ($temp_worksheet)
                {
                    $temp_worksheet->delete();
                }
            }

            $worksheet = $worksheet_feed->getByTitle($title);

            if (!$worksheet) 
            {
                throw new HelperException(
                    'error creating worksheet');
            }

            return $worksheet;
    }

    public function save()
    {
        if (!$this->sheet_name)
            throw new HelperException(
                "no sheet name defined");

        if (!$this->worksheet_name)
            throw new HelperException(
                "no worksheet name defined");

        # fields & field names

        $_fields = [];
        $_field_names = [];

        $_fstcolmkr = 'DO NOT MODIFY ->';

        $_fields[$_fstcolmkr] = 
            substr(md5(uniqid(rand(), true)), 0, 6);

        $_field_names[] = $_fstcolmkr;

        foreach ($this->fields as $fname => $fvalue) 
        {
            $fname = preg_replace(
                '#[^a-z0-9\-\_\s]#i', '', $fname);

            $_fields[$fname] = $fvalue;
            $_field_names[] = $fname;
        }

        # spreadsheet feed

        $spreadsheet_feed = 
            $this->service->getSpreadsheets();

        $spreadsheet = 
            $spreadsheet_feed->getByTitle(
                $this->sheet_name);

        if (!$spreadsheet)
        {
            # create new spreadsheet
            
            $spreadsheet = $this->_get_new_spreadsheet(
                $this->sheet_name, 
                $this->service, 
                $spreadsheet_feed);
        }

        # worksheet feed

        $worksheet_feed = 
            $spreadsheet->getWorksheets();

        $worksheet = 
            $worksheet_feed->getByTitle(
                $this->worksheet_name);

        if (!$worksheet)
        {
            # create worksheet

            $worksheet = $this->_get_new_worksheet(
                $this->worksheet_name,
                $spreadsheet,
                $worksheet_feed);
        }

        # cell feed

        $cell_feed = $worksheet->getCellFeed();

        # check if worksheet fieldnames match

        $_cell_count = 0;
        $_fields_error = false;

        foreach ($_field_names as $_cell_col => $_cell_content) 
        {
            $_cell = $cell_feed->getCell(1, $_cell_col + 1);

            if (!$_cell)
            {
                $_fields_error = true;
                break;
            }

            $_cell_value = $_cell->getContent();
            
            $_cell_content = strtolower(preg_replace(
                '#[^a-z0-9\-]#i', '', $_cell_content));

            $_cell_value = strtolower(preg_replace(
                '#[^a-z0-9\-]#i', '', $_cell_value));

            if ($_cell_value != $_cell_content)
            {
                $_fields_error = true;
                break;
            }
        }

        if (!$_fields_error)
        {
            # insert

            $this->_save_row($worksheet, $_fields);
        }
        else
        {
            # check if sheet is empty

            if (empty($cell_feed->getEntries()))
            {
                # add headers

                foreach ($_field_names as $_cell_col => $_cell_content) 
                {
                    $cell_feed->editCell(
                        1, $_cell_col + 1, $_cell_content);
                }
            }
            else
            {
                # rename worksheet and create new

                $worksheet->update(
                    time().'_'.$worksheet->getTitle());

                $worksheet = $this->_get_new_worksheet(
                    $this->worksheet_name,
                    $spreadsheet,
                    $worksheet_feed);

                # refresh cell feed

                $cell_feed = $worksheet->getCellFeed();

                # add headers

                foreach ($_field_names as $_cell_col => $_cell_content) 
                {
                    $cell_feed->editCell(
                        1, $_cell_col + 1, $_cell_content);
                }
            }

            # refresh cell feed

            $cell_feed = $worksheet->getCellFeed();

            # insert data

            $this->_save_row($worksheet, $_fields);
        }

        return true;
    }

    protected function _save_row(
        \Google\Spreadsheet\Worksheet $worksheet,
        Array $row)
    {
        echo "<pre>"; var_dump($row);
        $list_feed = $worksheet->getListFeed();
        $list_feed->insert($row);
    }

    public function test()
    {
        $spreadsheet_feed = 
            $this->service->getSpreadsheets();

        echo "<pre>";
        /*
        foreach ($spreadsheet_feed as $spreadsheet) 
        {
            $s_info = []; 
            $s_info['title'] = $spreadsheet->getTitle();
            $s_info['id'] = $spreadsheet->getId();
            $s_info['worksheets'] = [];

            $s_worksheets = $spreadsheet->getWorksheets();
            foreach ($s_worksheets as $s_worksheet) 
            {
                $s_info['worksheets'][] = $s_worksheet;
            }
            var_dump($s_info);
            echo "\n\n";
        } */

        $spreadsheet = $spreadsheet_feed->getByTitle('testsheet');

        $worksheet_feed = $spreadsheet->getWorksheets();

        $worksheet = $worksheet_feed->getByTitle('asdfg');

        $cell_feed = $worksheet->getCellFeed();

        $field_names = [
            'DO NOT MODIFY -->', 'Header-2', // 'Name', 'Date of Birth', 'val-1', 'val_2'
        ];



        /*
        $cell_feed->editCell(1,1, "Row1Col1Header");
        $cell_feed->editCell(1,2, "Row1Col2Header");
        $cell_feed->editCell(1,3, "Row1Col3Header");
        $cell_feed->editCell(1,4, "Row1Col4Header"); */

        $c_count = 0;
        $fields_error = false;

        foreach ($field_names as $col => $content) 
        {
            $cell = $cell_feed->getCell(1, $col + 1);

            if (!$cell)
            {
                $fields_error = true;
                break;
            }

            $cell_value = $cell->getContent();
            
            $content = strtolower(preg_replace(
                '#[^a-z0-9\-]#i', '', $content));

            $cell_value = strtolower(preg_replace(
                '#[^a-z0-9\-]#i', '', $cell_value));

            if ($cell_value != $content)
            {
                $fields_error = true;
                break;
            }
        }

        if (!$fields_error)
        {
            $list_feed = $worksheet->getListFeed();

            $row = array($field_names[0]=>'John', $field_names[1]=>25);
            $list_feed->insert($row);
        }
        else
        {
            var_dump("Error");
        }
        
    }
}

