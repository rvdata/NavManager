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
define('TEMPLATE_PATH', dirname(__FILE__) . '/../include/Templates');
define('DATAPATH', '/data/r2r');
define('GEOEXTENT_TEMPLATE_JSON', TEMPLATE_PATH . '/geoextent_template.json');  
define('GEOEXTENT_TEMPLATE_XML',  TEMPLATE_PATH . '/geoextent_template.xml');
define('QA_TEMPLATE_XML', TEMPLATE_PATH . '/nav_qa_template_ver1.0.xml');  
define('SPLIT_ON_DATELINE', '1');
define('SRID', '4326'); // WGS-84
define('GEOM_TYPE', 'MULTILINESTRING');
define('GPS_START_DATE', '1994-03-01T00:00:00Z');
define('GPS_END_DATE', '2034-12-31T00:00:00Z');
define('MAX_SPEED', '8.7');
define('MAX_ACCEL', '1');
define('MAX_GAP', '300');
// http://www.postgis.org/documentation/manual-1.4/ch04.html
?>
