#!/usr/bin/env php 
<?php 
/**
 * Set globals for NavManager
 *
 * PHP version 5
 *
 * @author   Chris Olson <cjolson@ucsd.edu>
 * @license  http://opensource.org/licenses/GPL-3.0 GNU General Public License
 * @link     http://www.rvdata.us
 */
define('INCLUDE_PATH', dirname(__FILE__) . '/../include');
define('TEMPLATE_PATH', dirname(__FILE__) . '/../include/Templates');
define('DATAPATH', '/data/r2r');
define('GEOEXTENT_TEMPLATE_JSON', TEMPLATE_PATH . '/geoextent_template.json');  
define('GEOEXTENT_TEMPLATE_XML',  TEMPLATE_PATH . '/geoextent_template.xml');
define('QA_TEMPLATE_XML', TEMPLATE_PATH . "/" . 'nav_qa_template_ver1.0.xml');  
define('SPLIT_ON_DATELINE', '1');
define('SRID', '4326'); // WGS-84
// http://www.postgis.org/documentation/manual-1.4/ch04.html
define('GEOM_TYPE', 'MULTILINESTRING');a

?>
