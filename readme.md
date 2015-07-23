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

        if (goog_api_helper\load())
        {
            $drive = new goog_api_helper\Drive();

            $drive->upload('path/to/file', [
                    'title' => 'filename',
                    'destination' => 'path/to/folder/on/drive',
            ]);
        }
        else
        {
            echo "error loading api helper";
        }

### Saving Form Data `currently unavailable`

* create user token

        # see above

* use namespace goog_api_helper

        if (goog_api_helper\load()) 
        {
            $form = new goog_api_helper\Form('sheet_name');
            $form->clear();
            $form->data(['fieldname' => 'value']);
            $form->save();
        }
