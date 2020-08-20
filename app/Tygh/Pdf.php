<?php
/**********************************************************************************************************************
 * Any components or design related choices are copyright protected under international law. They are proprietary     *
 * code from Harm Smits and shall not be obtained, used or distributed without explicit permission from Harm Smits.   *
 * I grant you a non-commercial license via github when you download the product. Commercial licenses can be obtained *
 * by contacting me. For any legal inquiries, please contact me at <harmsmitsdev@gmail.com>                           *
 **********************************************************************************************************************/

/***************************************************************************
 *                                                                          *
 *   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
 *                                                                          *
 * This  is  commercial  software,  only  users  who have purchased a valid *
 * license  and  accept  to the terms of the  License Agreement can install *
 * and use this program.                                                    *
 *                                                                          *
 ****************************************************************************
 * PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
 * "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
 ****************************************************************************/

namespace Tygh;

use Tygh\Http;
use Tygh\Addons\LocalPdf\Pdf as PdfGenerator;

class Pdf
{
    protected static $pdf_instance;
    protected static $url = 'http://converter.cart-services.com';

    /**
     * Pushes HTML code to batch to render PDF later
     *
     * @param string $html HTML code
     *
     * @return boolean true if transaction created, false - otherwise
     */
    public static function batchAdd($html)
    {
        $html = self::convertImages($html);

        if (!is_object(self::$pdf_instance)) {
            $default_params = array(
                'no-outline',
                'margin-top' => 0,
                'margin-right' => 0,
                'margin-bottom' => 0,
                'margin-left' => 0,
                'page-size' => 'A4',
                'encoding' => 'utf8'
            );

            self::$pdf_instance = new PdfGenerator($default_params);
        }

        self::$pdf_instance->addPage($html);

        return true;
    }

    /**
     * Renders PDF document by transaction ID
     *
     * @param string  $filename filename to save PDF or name of attachment to download
     * @param boolean $save     saves to file if true, outputs if not
     * @param array   $params   params to post along with request
     *
     * @return mixed   true if document saved, false on failure or outputs document
     */
    public static function batchRender($filename = '', $save = false, $params = array())
    {
        $file = fn_create_temp_file();

        if (!self::$pdf_instance->saveAs($file)) {
            $error = self::$pdf_instance->getError();
            error_log($error);
        }

        self::$pdf_instance = null;

        return self::output($file, $filename, $save);
    }

    /**
     * Render PDF document from HTML code
     *
     * @param string  $html     HTML code
     * @param string  $filename filename to save PDF or name of attachment to download
     * @param boolean $save     saves to file if true, outputs if not
     * @param array   $params   params to post along with request
     *
     * @return mixed   true if document saved, false on failure or outputs document
     */
    public static function render($html, $filename = '', $save = false, $params = array())
    {
        if (is_array($html)) {
            $html = implode("<div style='page-break-before: always;'>&nbsp;</div>", $html);
        }

        if (self::isLocalIP(gethostbyname($_SERVER['HTTP_HOST']))) {
            $html = self::convertImages($html);
        }

        $default_params = array(
            'no-outline',
            'margin-top' => 0,
            'margin-right' => 0,
            'margin-bottom' => 0,
            'margin-left' => 0,
            'page-size' => 'A4',
            'encoding' => 'utf8'
        );

        $params = array_merge($default_params, $params);
        $file = fn_create_temp_file();

        $pdf = new PdfGenerator($params);
        $pdf->addPage($html);

        if (!$pdf->saveAs($file)) {
            $error = $pdf->getError();
            error_log($error);
        }

        return self::output($file, $filename, $save);
    }

    /**
     * Generates service URL
     *
     * @param string $action action
     *
     * @return string formed URL
     */
    protected static function action($action)
    {
        return self::$url . $action;
    }

    /**
     * Saves PDF document or outputs it
     *
     * @param string  $file     file with PDF document
     * @param string  $filename filename to save PDF or name of attachment to download
     * @param boolean $save     saves to file if true, outputs if not
     *
     * @return mixed   true if document saved, false on failure or outputs document
     */
    protected static function output($file, $filename = '', $save = false)
    {
        if (!empty($filename) && strpos($filename, '.pdf') === false) {
            $filename .= '.pdf';
        }

        if (!empty($filename) && $save == true) {
            return fn_rename($file, $filename);

        } else {
            if (!empty($filename)) {
                $filename = fn_basename($filename);
                header("Content-disposition: attachment; filename=\"$filename\"");
            }

            header('Content-type: application/pdf');
            readfile($file);
            fn_rm($file);
            exit;
        }

        return false;
    }

    /**
     * Converts images links to image:data attribute
     *
     * @param string $html html code
     *
     * @return string html code with converted links
     */
    protected static function convertImages($html)
    {
        $http_location = Registry::get('config.http_location');
        $https_location = Registry::get('config.https_location');
        $http_path = Registry::get('config.http_path');
        $https_path = Registry::get('config.https_path');
        $files = array();

        if (preg_match_all("/(?<=\ssrc=|\sbackground=)('|\")(.*)\\1/SsUi", $html, $matches)) {
            $files = fn_array_merge($files, $matches[2], false);
        }

        if (preg_match_all("/(?<=\sstyle=)('|\").*url\(('|\"|\\\\\\1)(.*)\\2\).*\\1/SsUi", $html, $matches)) {
            $files = fn_array_merge($files, $matches[3], false);
        }

        if (empty($files)) {
            return $html;
        } else {
            $files = array_unique($files);

            foreach ($files as $k => $_path) {
                $path = str_replace('&amp;', '&', $_path);

                $real_path = '';
                // Replace url path with filesystem if this url is NOT dynamic
                if (strpos($path, '?') === false && strpos($path, '&') === false) {
                    if (($i = strpos($path, $http_location)) !== false) {
                        $real_path = substr_replace($path, Registry::get('config.dir.root'), $i, strlen($http_location));
                    } else if (($i = strpos($path, $https_location)) !== false) {
                        $real_path = substr_replace($path, Registry::get('config.dir.root'), $i, strlen($https_location));
                    } else if (!empty($http_path) && ($i = strpos($path, $http_path)) !== false) {
                        $real_path = substr_replace($path, Registry::get('config.dir.root'), $i, strlen($http_path));
                    } else if (!empty($https_path) && ($i = strpos($path, $https_path)) !== false) {
                        $real_path = substr_replace($path, Registry::get('config.dir.root'), $i, strlen($https_path));
                    }
                }

                if (empty($real_path)) {
                    $real_path = (strpos($path, '://') === false) ? $http_location . '/' . $path : $path;
                }

                list($width, $height, $mime_type) = fn_get_image_size($real_path);

                if (!empty($width)) {
                    $content = fn_get_contents($real_path);
                    $html = preg_replace("/(['\"])" . str_replace("/", "\/", preg_quote($_path)) . "(['\"])/Ss", "\\1data:$mime_type;base64," . base64_encode($content) . "\\2", $html);
                }
            }
        }

        return $html;
    }

    /**
     * Checks if server IP address is local
     *
     * @param string $ip IP address
     *
     * @return boolean true if IP is local, false - if public
     */
    protected static function isLocalIP($ip)
    {
        $ranges = array(
            '10' => array(
                'min' => ip2long('10.0.0.0'),
                'max' => ip2long('10.255.255.255')
            ),
            '192' => array(
                'min' => ip2long('192.168.0.0'),
                'max' => ip2long('192.168.255.255')
            ),
            '127' => array(
                'min' => ip2long('127.0.0.0'),
                'max' => ip2long('127.255.255.255')
            ),
            '172' => array(
                'min' => ip2long('172.16.0.0'),
                'max' => ip2long('172.31.255.255')
            ),
        );

        $ip = ip2long($ip);

        foreach ($ranges as $range) {
            if ($ip >= $range['min'] && $ip <= $range['max']) {
                return true;
            }
        }

        return false;
    }
}
