<?php
// +----------------------------------------------------------------------+
// | PHP version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available through the world-wide-web at                              |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: David Grant <david@davidjonathangrant.info>                  |
// +----------------------------------------------------------------------+

/**
 * @author David Grant <david@davidjonathangrant.info>
 * @package File_Ogg
 */

/**
 * Factory class for providing either a PEAR or PECL interface to an Ogg stream.
 *
 * Due to the widespread availability of libraries from Xiph.Org, it is anticipated
 * that a PECL version of this package will become available in the not-too-distant
 * future. This class will provide a switch between the two modes if the PECL version
 * is installed.  Naturally, the PECL interface should offer speed benefits over the
 * native PHP version (or PEAR as it is called throughout this package), and will be
 * used as the preferred choice.
 *
 * @package File_Ogg
 */
class File_Ogg
{
    /**
     * Returns an interface to an Ogg physical stream.
     *
     * This method acts as an interface to switch between the PECL and native-PHP
     * versions of this package.  At the time of writing, there is no PECL version
     * so this class simply passes onto File_Ogg_PEAR.  The aforementioned file
     * can be called directly, but the author strongly recommends against this.
     *
     * @see     File_Ogg_Pear
     * @access  public
     * @param   string  $fileLocation   The path of the file to be examined.
     * @return  object  File_Ogg_Pear
     */
    function openFile($fileLocation)
    {
        require_once("File/Ogg/PEAR.php");
        return (new File_Ogg_PEAR($fileLocation));
    }
}
?>
