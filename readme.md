# Google API Helper

by [Aravindan Ve](https://bitbucket.org/aravindanve)

## Usage

pull repository into your project and include 'google-api-helper/helper.php'
in your script. use namespace goog_api_helper

## Examples
    
### Uploading File to Drive

* create user token

        php create_user_token.php

* use namespace goog_api_helper

        include 'goog-api-helper/helper.php';
        
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
                'path/to/file',
                'filetitle',
                'path/to/folder/on/drive');     // will be created if one doesnt exist
        }
        else
        {
            throw new Exception("error loading api helper");
        }
        

### Saving Form Data `very slow`

* create user token

        # see above

* use namespace goog_api_helper

        include 'goog-api-helper/helper.php';
        
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
        
        
### Work in progress ...
