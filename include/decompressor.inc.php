<?php
/**
 * Define function to test for presense of compressed files and
 * uncompress in given directory.
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
 * Tests for the presence of compressed files in the given directory.  
 *
 * If there are no compressed files, this function returns the original path.  
 * 
 * If there are compressed files present, this function copies all of the files
 * in the given directory to a temporary directory and uncompresses the 
 * compressed files.  Any files with the same base name will be overwritten
 * during file decompression.  The function then returns the full system path 
 * of this temporary directory.
 *
 * @param string $path    Full system path to directory of interest
 * @param string $tempdir The temporary directory into which compressed files
 *                         will be copied and then uncompressed
 * 
 * @return string Returns original path or path to temporary directory
 *                 where files are uncompressed.
 */
function decompressor($path, $tempdir) 
{
    if ($handle = opendir("$path")) {

        // Assume no compressed files until proven otherwise:
        $has_compressed_files = false;
        
        // Loop over filenames in directory and stop if a known file extension for a 
        // compressed file is found:
        while (false !== ($file = readdir($handle)) 
            && $has_compressed_files == false
        ) {
            
            // Don't include hidden files (start with '.'):
            $isRegularFile = ($file != "." && $file != ".." 
                && !preg_match("/^\./", $file));
            if ($isRegularFile) {
                
                $fileextension = pathinfo($file, PATHINFO_EXTENSION);
                
                switch ($fileextension) {
                    
                case "zip":  // compression by zip
                case "ZIP":
                case "Z":    // compression by compress
                case "z":
                case "bz2":  // compression by bzip2
                case "BZ2":
                case "gz":   // compression by gzip
                case "GZ":
                    $has_compressed_files = true;
                    break;
                    
                default:
                    break;
                    
                } // end switch ($fileextension)
                
            } // end if file is not "." nor ".." 
            
        } // end loop over files in dir
        
        if ($has_compressed_files) {
            
            echo "\n";
            echo "Detected compressed files in $path.\n";
            
            // If temporary directory does not already exist, create it:
            if (is_dir($tempdir) || @mkdir($tempdir, 0755, true)) {
                echo "Moving and uncompressing data to temporary directory "
                    . "$tempdir ...\n";
            } else {
                echo "decompressor(): Cannot create temporary path: $tempdir\n";
                exit(1);
            }
            
            rewinddir($handle);
            
            // Copy all files to a temporary directory first before attempting
            // to decompress:
            while (false !== ($file = readdir($handle))) {
                
                // Don't include hidden files (start with '.'):
                $isRegularFile = ($file != "." && $file != ".." 
                    && !preg_match("/^\./", $file));
                if ($isRegularFile) {
                    
                    if (false === (copy(
                        $path . "/" . $file, $tempdir . "/" . $file
                    ))) {
                        echo "decompressor(): Could not copy file $file from "
                            . "$path to $tempdir.\n";
                        exit(1);
                    } else {
                        echo "Copied file $file to $tempdir.\n";
                    }
                    
                } // end if ($isRegularFile)
                
            } // end loop over files in directory
            
            if ($temphandle = opendir("$tempdir")) {
                
                // Save current working directory, and change to temporary directory:
                $cwd = getcwd();
                chdir("$tempdir");
                
                // Loop over files in temporary directory, decompressing those 
                // that need it:
                while (false !== ($file = readdir($temphandle))) {
                    
                    // Don't include hidden files (start with '.'):
                    $isRegularFile = ($file != "." && $file != ".." 
                        && !preg_match("/^\./", $file));
                    if ($isRegularFile) {
                        
                        $fileextension = pathinfo($file, PATHINFO_EXTENSION);
                        
                        switch ($fileextension) {
                            
                        case "ZIP":
                        case "zip":  // compression by zip
                            echo "Uncompressing file: $file\n";
                            $cmd_str = "unzip -fo $file";
                            break;
                            
                        case "Z":    // compression by compress
                        case "z":
                            echo "Uncompressing file: $file\n";
                            //$cmd_str = "uncompress -f $file";
                            $cmd_str = "gunzip -f $file";
                            break;
                            
                        case "BZ2":
                        case "bz2":  // compression by bzip2
                            echo "Uncompressing file: $file\n";
                            $cmd_str = "bunzip2 -f $file";
                            break;
                            
                        case "GZ":
                        case "gz":   // compression by gzip
                            echo "Uncompressing file: $file\n";
                            $cmd_str = "gunzip -f $file";
                            break;
                            
                        default:
                            $cmd_str = "";
                            break;
                            
                        } // end switch ($fileextension)
                        
                        if (!empty($cmd_str)) {
                            exec($cmd_str, $result, $ret_status);
                            if ($ret_status != 0) {
                                echo "decompressor(): Error encountered: $cmd_str\n";
                                exit(1);
                            }
                        }
                        
                    } // end if file is not "." nor ".."
                    
                } // end loop over files in directory
                
                // Switch back to original working directory:
                chdir($cwd);
                closedir($handle);
                closedir($temphandle);
                
            } else {
                
                echo "decompressor(): Could not access path: $tempdir\n";
                exit(1);
                
            }
            
            echo "decompressor(): Done.\n";
            return $tempdir;
            
        } else { // if no compressed files, return original path:
            
            closedir($handle);
            return $path;
            
        }
        
    } else { // if don't have dir handle
        
        echo "decompressor(): Could not access path: $path\n";
        exit(1);

    }
    
}  // end function decompressor()

?>
