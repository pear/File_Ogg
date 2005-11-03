<?php
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +--------------------------------------------------------------------------------+
// | File_Ogg PEAR Package for Accessing Ogg Bitstreams                             |
// | Copyright (c) 2005 David Grant <david@grant.org.uk>                            |
// +--------------------------------------------------------------------------------+
// | This library is free software; you can redistribute it and/or                  |
// | modify it under the terms of the GNU Lesser General Public                     |
// | License as published by the Free Software Foundation; either                   |
// | version 2.1 of the License, or (at your option) any later version.             |
// |                                                                                |
// | This library is distributed in the hope that it will be useful,                |
// | but WITHOUT ANY WARRANTY; without even the implied warranty of                 |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU              |
// | Lesser General Public License for more details.                                |
// |                                                                                |
// | You should have received a copy of the GNU Lesser General Public               |
// | License along with this library; if not, write to the Free Software            |
// | Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA     |
// +--------------------------------------------------------------------------------+

/**
 * @author 		David Grant <david@grant.org.uk>
 * @category	File
 * @copyright 	David Grant <david@grant.org.uk>
 * @license		http://www.gnu.org/copyleft/lesser.html GNU LGPL
 * @link		http://pear.php.net/package/File_Ogg
 * @package 	File_Ogg
 * @version 	CVS: $Id$
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
     * This method takes the path to a local file and examines it for a physical
     * ogg bitsream.  After instantiation, the user should query the object for
     * the logical bitstreams held within the ogg container.
     *
     * @access  public
     * @param   string  $fileLocation   The path of the file to be examined.
     */
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
    
    /**
     * @access 	private
     */
    function _decodePageHeader($pageData, $pageOffset, $pageFinish)
    {
        // Extract the various bits and pieces found in each packet header.
        if (substr($pageData, 0, 4) != OGG_CAPTURE_PATTERN)
            return (FALSE);

        $stream_version = unpack("c1data", substr($pageData, 4, 1));
        if ($stream_version['data'] != 0x00)
            return (FALSE);

        $header_flag 		= unpack("cdata", substr($pageData, 5, 1));
        $abs_granual_pos 	= unpack("Vdata", substr($pageData, 6, 8));
        var_dump($abs_granual_pos);
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
    
    /**
     *	@access 	private
     */
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
     * Returns the appropriate logical bitstream that corresponds to the provided serial.
     *
     * This function returns a logical bitstream contained within the ogg physical
     * stream, corresponding to the serial used as the offset for that bitstream.
     * The returned stream may be Vorbis, Speex, FLAC or Theora, although the only
     * usable bitstream is Vorbis.
     *
     * @return File_Ogg_Bitstream
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
    
    /**
     * This function returns true if a logical bitstream of the requested type can be found.
     *
     * This function checks the contents of this ogg physical bitstream for of logical
     * bitstream corresponding to the supplied type.  If one is found, the function returns
     * true, otherwise it return false.
     *
     * @param 	int		$streamType
     */
    function hasStream($streamType)
    {
        foreach ($this->_streamList as $stream) {
            if ($stream['stream_type'] == $streamType)
                return (true);
        }
        return (false);
    }
    
    /**
     * Returns an array of logical streams inside this physical bitstream.
     *
     * This function returns an array of logical streams found within this physical
     * bitstream.  If a filter is provided, only logical streams of the requested type
     * are returned, as an array of serial numbers.  If no filter is provided, this
     * function returns a two-dimensional array, with the stream type as the primary key,
     * and a value consisting of an array of stream serial numbers.
     */
    function listStreams($filter = null)
    {
        $streams = array();
        foreach ($this->_streamList as $stream_serial => $stream) {
        	if (! isset($streams[$stream['stream_type']]))
        		$streams[$stream['stream_type']] = array();
        		
        	$streams[$stream['stream_type']][] = $stream_serial;
        }

        if (is_null($filter))
        	return ($streams);
        elseif (isset($streams[$filter]))
        	return ($streams[$filter]);
        else
        	return array();
    }
}
?>