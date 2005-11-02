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
// | Author: David Grant <david@grant.org.uk>                  |
// +----------------------------------------------------------------------+

/**
 * @author David Grant <david@grant.org.uk>
 * @package File_Ogg
 */

require_once('PEAR.php');
require_once('File/Ogg/Stream.php');

define("OGG_STREAM_VORBIS",		1);
define("OGG_STREAM_THEORA", 	2);
define("OGG_STREAM_SPEEX",		3);
define("OGG_STREAM_FLAC", 		4);

/**
 * Capture pattern to determine if a file is an Ogg physical stream.
 */
define("OGG_CAPTURE_PATTERN", "OggS");
/**
 * Maximum size of an Ogg stream page plus four.  This value is specified to allow
 * efficient parsing of the physical stream.  The extra four is a paranoid measure
 * to make sure a capture pattern is not split into two parts accidentally.
 */
define("OGG_MAXIMUM_PAGE_SIZE", 65311);
/**
 * Capture pattern for an Ogg Vorbis logical stream.
 */
define("OGG_STREAM_CAPTURE_VORBIS", "vorbis");
/**
 * Capture pattern for an Ogg Speex logical stream.
 */
define("OGG_STREAM_CAPTURE_SPEEX", 	"Speex   ");
/**
 * Capture pattern for an Ogg FLAC logical stream.
 */
define("OGG_STREAM_CAPTURE_FLAC", 	"fLaC");
/**
 * Capture pattern for an Ogg Theora logical stream.
 */
define("OGG_STREAM_CAPTURE_THEORA", "theora");
/**
 * Error thrown if the file location passed is nonexistant or unreadable.
 */
define("OGG_ERROR_INVALID_FILE", 1);
/**
 * Error thrown if the user attempts to extract an unsupported logical stream.
 */
define("OGG_ERROR_UNSUPPORTED",	2);
/**
 * Error thrown if the user attempts to extract an logical stream with no
 * corresponding serial number.
 */
define("OGG_ERROR_BAD_SERIAL", 3);

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
     * File pointer to Ogg container.
     *
     * This is the file pointer used for extracting data from the Ogg stream.  It is
     * the result of a standard fopen call.
     *
     * @var     pointer
     * @access  private
     */
    var $_filePointer;

    /**
     * The container for all logical streams.
     *
     * List of all of the unique streams in the Ogg physical stream.  The key
     * used is the unique serial number assigned to the logical stream by the
     * encoding application.
     *
     * @var     array
     * @access  private
     */
    var $_streamList = array();

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
     * @deprecated
     */
    function openFile($fileLocation)
    {
        require_once("File/Ogg/PEAR.php");
        return (new File_Ogg_PEAR($fileLocation));
    }
    
    function File_Ogg($fileLocation)
    {
        clearstatcache();
        if (! file_exists($fileLocation)) {
        	PEAR::raiseError("Couldn't Open File.  Check File Path.", OGG_ERROR_INVALID_FILE);
        	return;
        }

        $this->_filePointer = fopen($fileLocation, "rb");
        if (is_resource($this->_filePointer))
            $this->_splitStreams();
		else
            PEAR::raiseError("Couldn't Open File.  Check File Permissions.", OGG_ERROR_INVALID_FILE);
    }
    
    function _decodePageHeader($pageData, $pageOffset, $pageFinish)
    {
        // Extract the various bits and pieces found in each packet header.
        if (substr($pageData, 0, 4) != OGG_CAPTURE_PATTERN)
            return (FALSE);

        $stream_version = unpack("c1data", substr($pageData, 4, 1));
        if ($stream_version['data'] != 0x00)
            return (FALSE);

        $header_flag 		= unpack("cdata", substr($pageData, 5, 1));
        $abs_granual_pos 	= unpack("Idata", substr($pageData, 6, 8));
        // Serial number for the current datastream.
        $stream_serial 		= unpack("Vdata", substr($pageData, 14, 4));
        $page_sequence 		= unpack("Vdata", substr($pageData, 18, 4));
        $checksum 			= unpack("Vdata", substr($pageData, 22, 4));
        $page_segments 		= unpack("cdata", substr($pageData, 26, 1));
        $segments_total = 0;
        for ($i = 0; $i < $page_segments['data']; ++$i) {
            $segments 		= unpack("Cdata", substr($pageData, 26 + ($i + 1), 1));
            $segments_total += $segments['data'];
        }
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['stream_version'] 	= $stream_version['data'];
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['header_flag'] 		= $header_flag['data'];
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['abs_granual_pos'] 	= $abs_granual_pos['data'];
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['checksum'] 			= $checksum['data'];
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['segments'] 			= $segments_total;
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['head_offset'] 		= $pageOffset;
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['body_offset'] 		= $pageOffset + 26 + $page_segments['data'] + 1;
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['body_finish'] 		= $pageFinish;
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['data_length'] 		= $pageFinish - $pageOffset;
        
        return (TRUE);
    }
    
    function _splitStreams()
    {
        // Loop through the physical stream until there are no more pages to read.
        while (TRUE) {
            $this_page_offset = ftell($this->_filePointer);
            $next_page_offset = $this_page_offset;

            // Read in 65311 bytes from the physical stream.  Ogg documentation
            // states that a page has a maximum size of 65304 bytes.  An extra
            // 4 bytes are added to ensure that the capture pattern of the next
            // pages comes through.
            if (! ($stream_data = fread($this->_filePointer, OGG_MAXIMUM_PAGE_SIZE)))
            	break;

            // Split the data into various pages.
            $stream_pages = explode(OGG_CAPTURE_PATTERN, $stream_data);
            // If the maximum data has been read, it is likely that this is an
            // intermediate page.  Since the split adds an empty element at the
            // start of the array, we must account for that by substracting one
            // iteration from the loop.  This argument also follows if the data
            // includes an incomplete page at the end, in which case we substract
            // two iterations from the loop.
            $number_pages = (strlen($stream_data) == OGG_MAXIMUM_PAGE_SIZE) ? count($stream_pages) - 2 : count($stream_pages) - 1;
            if (! count($stream_pages))
            	break;

            for ($i = 1; $i <= $number_pages; ++$i) {
                $stream_pages[$i] = OGG_CAPTURE_PATTERN . $stream_pages[$i];
                // Set the current page offset to the next page offset of the
                // previous loop iteration.
                $this_page_offset = $next_page_offset;
                // Set the next page offset to the current page offset plus the
                // length of the current page.
                $next_page_offset += strlen($stream_pages[$i]);
                $this->_decodePageHeader($stream_pages[$i], $this_page_offset, $next_page_offset - 1);
            }
            fseek($this->_filePointer, $next_page_offset, SEEK_SET);
            break;
        }
        // Loop through the streams, and find out what type of stream is available.
        foreach ($this->_streamList as $stream_serial => $pages) {
            fseek($this->_filePointer, $pages['stream_page'][0]['body_offset'], SEEK_SET);
            $pattern = fread($this->_filePointer, 8);
            if (preg_match("/" . OGG_STREAM_CAPTURE_VORBIS . "/", $pattern))
                $this->_streamList[$stream_serial]['stream_type'] = OGG_STREAM_VORBIS;
            elseif (preg_match("/" . OGG_STREAM_CAPTURE_SPEEX . "/", $pattern))
                $this->_streamList[$stream_serial]['stream_type'] = OGG_STREAM_SPEEX;
            elseif (preg_match("/" . OGG_STREAM_CAPTURE_FLAC . "/", $pattern))
                $this->_streamList[$stream_serial]['stream_type'] = OGG_STREAM_FLAC;
            elseif (preg_match("/" . OGG_STREAM_CAPTURE_THEORA . "/", $pattern))
                $this->_streamList[$stream_serial]['stream_type'] = OGG_STREAM_THEORA;
            else
                $this->_streamList[$stream_serial]['stream_type'] = "unknown";
        }
    }
    
    /**
     * @return File_Ogg_Stream
     */
    function getStream($streamSerial)
    {
        if (! array_key_exists($streamSerial, $this->_streamList))
        	PEAR::raiseError("The stream number is invalid.", OGG_ERROR_BAD_SERIAL);

        switch ($this->_streamList[$streamSerial]['stream_type']) {
            case (OGG_STREAM_VORBIS):
                require_once("File/Ogg/Vorbis.php");
                return (new File_Ogg_Vorbis($streamSerial, $this->_streamList[$streamSerial], $this->_filePointer));
                break;
            case (OGG_STREAM_SPEEX):
                require_once("File/Ogg/Speex.php");
                return (new File_Ogg_Speex($streamSerial, $this->_streamList[$streamSerial], $this->_filePointer));
                // PEAR::raiseError("Speex streams are not currently supported.", OGG_ERROR_UNSUPPORTED);
                break;
            case (OGG_STREAM_FLAC):
                require_once("File/Ogg/Flac.php");
                return (new File_Ogg_Flac($streamSerial, $this->_streamList[$streamSerial], $this->_filePointer));
                // PEAR::raiseError("FLAC streams are not currently supported.", OGG_ERROR_UNSUPPORTED);
                break;
            case (OGG_STREAM_THEORA):
                require_once("File/Ogg/Theora.php");
                return (new File_Ogg_Theora($streamSerial, $this->_streamList[$streamSerial], $this->_filePointer));
                // PEAR::raiseError("Theora streams are not currently supported.", OGG_ERROR_UNSUPPORTED);
                break;
            default:
                PEAR::raiseError("This stream could not be identified.", OGG_ERROR_UNSUPPORTED);
        }
        return false;
    }
    
    function hasStream($streamType)
    {
        foreach ($this->_streamList as $stream) {
            if ($stream['stream_type'] == $streamType)
                return (true);
        }
        return (false);
    }
    
    function listStreams($type = null)
    {
        $streams = array();
        foreach ($this->_streamList as $stream_serial => $stream) {
        	if (is_null($type) || $stream['stream_type'] == $type)
	            $streams[$stream_serial] = $stream['stream_type'];
        }

        return ($streams);
    }
}
$ogg	= new File_Ogg("C:\Documents and Settings\David Grant\Desktop\Epoq-Lepidoptera.ogg");
if ($ogg->hasStream(OGG_STREAM_VORBIS))
	var_dump($ogg->listStreams());
?>