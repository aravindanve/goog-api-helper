<?php
/**
 * Copyright 2013 Asim Liaquat
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace Google\Spreadsheet;

/**
 * ServiceRequestFactory
 *
 * @package    Google
 * @subpackage Spreadsheet
 * @author     Asim Liaquat <asimlqt22@gmail.com>
 */
class ServiceRequestFactory
{
    private static $instance;

    /**
     * [setInstance description]
     * 
     * @param ServiceRequestInterface $instance
     */
    public static function setInstance(ServiceRequestInterface $instance = null)
    {
        self::$instance = $instance;
    }

    /**
     * [getInstance description]
     * 
     * @return ServiceRequestInterface
     * 
     * @throws \Google\Spreadsheet\Exception
     */
    public static function getInstance()
    {
        if(is_null(self::$instance)) {
            throw new Exception();
        }
        return self::$instance;
    }
}