<?php 
/**
 * Define functions for converting PHP object to JSON string
 *
 * PHP version 5
 *
 * @category R2R_Products
 * @package  R2R_Nav
 * @author   Aaron Sweeney <asweeney@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */

/**
 * Serialize an object as a JSON string
 *
 * Serialize an object as a JSON string using json_encode(), 
 * un-escape forward-slashes, and re-format with appropriate 
 * indentation and newline characters to make it more human-readable
 * (and, hence, easier for a human to modify).
 *
 * @param object $json_obj PHP Object
 *
 * @return string Returns pretty-print JSON string
 */
function json_format($json_obj) 
{
    $tab = "  ";
    $new_json = "";
    $indent_level = 0;
    $in_string = false;
    
    $json = json_encode($json_obj);
    // json_encode() escapes forward-slashes!  Un-escape them:
    $json = preg_replace("/\\\\\//", '/', $json);
    $len = strlen($json);
    
    for ($c = 0; $c < $len; $c++) {
        $char = $json[$c];
        switch($char) {
        case '{':
        case '[':
            if (!$in_string) {
                $new_json .= $char . "\n" . str_repeat($tab, $indent_level+1);
                $indent_level++;
            } else {
                $new_json .= $char;
            }
            break;
        case '}':
        case ']':
            if (!$in_string) {
                $indent_level--;
                $new_json .= "\n" . str_repeat($tab, $indent_level) . $char;
            } else {
                $new_json .= $char;
            }
            break;
        case ',':
            if (!$in_string) {
                $new_json .= ",\n" . str_repeat($tab, $indent_level);
            } else {
                $new_json .= $char;
            }
            break;
        case ':':
            if (!$in_string) {
                $new_json .= ": ";
            } else {
                $new_json .= $char;
            }
            break;
        case '"':
            if ($c > 0 && $json[$c-1] != '\\') {
                $in_string = !$in_string;
            }
        default:
            $new_json .= $char;
            break;                   
        }
    }
    
    return $new_json;
    
} // end function json_format()

?>
