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
 * @author  David Grant <david@davidjonathangrant.info>
 * @package File_Ogg
 */

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
define("OGG_STREAM_CAPTURE_SPEEX", "Speex   ");
/**
 * Capture pattern for an Ogg FLAC logical stream.
 */
define("OGG_STREAM_CAPTURE_FLAC", "fLaC");
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
define("OGG_ERROR_UNSUPPORTED", 2);
/**
 * Error thrown if the user attempts to extract an logical stream with no
 * corresponding serial number.
 */
define("OGG_ERROR_BAD_SERIAL", 3);

/**
 * Native PHP interface to an Ogg stream.
 *
 * This class provides access to multiple streams inside an Ogg physical stream.
 * At the time of writing, only Vorbis streams are accessible through this class,
 * but will eventually provide an interface to FLAC, Speex and Theora (and anything
 * else wrapped in an Ogg stream).  Streams may be retrieved using the unique serial
 * number provided by the Ogg container.
 *
 * @access  public
 * @package File_Ogg
 */
class File_Ogg_Pear
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
     * Constructor for the native PHP interface to Ogg physical streams.
     *
     * This function provides an interface to Ogg physical streams which may contain
     * a number of different logical streams, such as Vorbis and FLAC.  Whilst it is
     * possible to call this function directly, it is strongly recommended that the user
     * use the factory method of File_Ogg to ensure a future-proof interface to a
     * PECL interface to Ogg physical streams.
     *
     * @see     File_Ogg
     * @param   string  $fileLocation   The path of the file to be examined.
     */
    function File_Ogg_Pear($fileLocation)
    {
        clearstatcache();
        if (file_exists($fileLocation)) {
            $this->_filePointer = fopen($fileLocation, "rb");
            if (is_resource($this->_filePointer)) {
                $this->_splitStreams();
            } else {
                PEAR::raiseError("Couldn't Open File.  Check File Permissions.", OGG_ERROR_INVALID_FILE);
            }
        }
        else {
            PEAR::raiseError("Couldn't Open File.  Check File Path.", OGG_ERROR_INVALID_FILE);
        }
    }

    /**
     * Decode an Ogg page header.
     *
     * This function extracts various bits and pieces from the various packets for a
     * bitstream page header.  For more information, please take a look the following
     * links.
     *
     * @link    http://www.xiph.org/ogg/vorbis/doc/framing.html
     * @link    http://www.xiph.org/ogg/vorbis/doc/oggstream.html
     *
     * @param   array   $pageData
     * @param   int     $pageOffset
     * @param   int     $pageFinish
     * @access  private
     */
    function _decodePageHeader($pageData, $pageOffset, $pageFinish)
    {
        // Extract the various bits and pieces found in each packet header.
        if (substr($pageData, 0, 4) != "OggS") {
            return (FALSE);
        }
        $stream_version = unpack("c1data", substr($pageData, 4, 1));
        if ($stream_version['data'] != 0x00) {
            return (FALSE);
        }
        $header_flag = unpack("c1data", substr($pageData, 5, 1));
        $abs_granual_pos = unpack("I1data", substr($pageData, 6, 8));
        // Serial number for the current datastream.
        $stream_serial = unpack("V1data", substr($pageData, 14, 4));
        $page_sequence = unpack("V1data", substr($pageData, 18, 4));
        $checksum = unpack("V1data", substr($pageData, 22, 4));
        $page_segments = unpack("c1data", substr($pageData, 26, 1));
        $segments_total = 0;
        for ($i = 0; $i < $page_segments['data']; ++$i) {
            $segments = unpack("C1data", substr($pageData, 26 + ($i + 1), 1));
            $segments_total += $segments['data'];
        }
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['stream_version'] = $stream_version['data'];
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['header_flag'] = $header_flag['data'];
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['abs_granual_pos'] = $abs_granual_pos['data'];
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['checksum'] = $checksum['data'];
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['segments'] = $segments_total;
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['head_offset'] = $pageOffset;
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['body_offset'] = $pageOffset + 26 + $page_segments['data'] + 1;
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['body_finish'] = $pageFinish;
        $this->_streamList[$stream_serial['data']]['stream_page'][$page_sequence['data']]['data_length'] = $pageFinish - $pageOffset;
    }

    /**
     * Splits the physical stream into its consituent pages.
     *
     * This function detects the various logical streams contained within the
     * current Ogg physical stream and places them into a directory for easier
     * future access.
     *
     * @access  private
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
            if ($stream_data = fread($this->_filePointer, OGG_MAXIMUM_PAGE_SIZE)) {
                // Split the data into various pages.
                $stream_pages = explode(OGG_CAPTURE_PATTERN, $stream_data);
                // If the maximum data has been read, it is likely that this is an
                // intermediate page.  Since the split adds an empty element at the
                // start of the array, we must account for that by substracting one
                // iteration from the loop.  This argument also follows if the data
                // includes an incomplete page at the end, in which case we substract
                // two iterations from the loop.
                $number_pages = (strlen($stream_data) == OGG_MAXIMUM_PAGE_SIZE) ? count($stream_pages) - 2 : count($stream_pages) - 1;
                if (count($stream_pages)) {
                    for ($i = 1; $i <= $number_pages; ++$i) {
                        $stream_pages[$i] = "OggS" . $stream_pages[$i];
                        // Set the current page offset to the next page offset of the
                        // previous loop iteration.
                        $this_page_offset = $next_page_offset;
                        // Set the next page offset to the current page offset plus the
                        // length of the current page.
                        $next_page_offset += strlen($stream_pages[$i]);
                        $this->_decodePageHeader($stream_pages[$i], $this_page_offset, $next_page_offset - 1);
                    }
                    fseek($this->_filePointer, $next_page_offset, SEEK_SET);
                } else {
                    break;
                }
            } else {
                break;
            }
        }
        // Loop through the streams, and find out what type of stream is available.
        foreach ($this->_streamList as $stream_serial => $pages) {
            fseek($this->_filePointer, $pages['stream_page'][0]['body_offset'], SEEK_SET);
            $pattern = fread($this->_filePointer, 8);
            if (preg_match("/" . OGG_STREAM_CAPTURE_VORBIS . "/", $pattern)) {
                $this->_streamList[$stream_serial]['stream_type'] = "vorbis";
            } elseif (preg_match("/" . OGG_STREAM_CAPTURE_SPEEX . "/", $pattern)) {
                $this->_streamList[$stream_serial]['stream_type'] = "speex";
            } elseif (preg_match("/" . OGG_STREAM_CAPTURE_FLAC . "/", $pattern)) {
                $this->_streamList[$stream_serial]['stream_type'] = "flac";
            } elseif (preg_match("/" . OGG_STREAM_CAPTURE_THEORA . "/", $pattern)) {
                $this->_streamList[$stream_serial]['stream_type'] = "theora";
            } else {
                $this->_streamList[$stream_serial]['stream_type'] = "unknown";
            }
        }
    }

    /**
     * List all available logical streams.
     *
     * This function returns an list of the available logical streams in the
     * supplied physical stream.  The list uses the logical stream serial number
     * as the array key, with the type of logical stream (e.g. Vorbis) as the array
     * value.
     *
     * @return  array
     * @access  public
     */
    function listStreams()
    {
        $streams = array();
        foreach ($this->_streamList as $stream_serial => $stream) {
            $streams[$stream_serial] = $stream['stream_type'];
        }
        return ($streams);
    }

    /**
     * Extract a specific logical stream.
     *
     * This method is a factory method for returning an interfaces to various logical
     * streams, such as Vorbis or FLAC.  At the time of writing, only the interface
     * to Vorbis is complete, but it is anticipated that other streams will be added
     * in the not-too-distant future.
     *
     * @see     File_Ogg_Vorbis_Pear
     * @param   int     $streamSerial   The serial number of the requested stream.
     * @return  object
     * @access  public
     */
    function getStream($streamSerial)
    {
        if (array_key_exists($streamSerial, $this->_streamList)) {
            switch ($this->_streamList[$streamSerial]['stream_type']) {
                case ("vorbis"):
                    require_once("File/Ogg/Vorbis/PEAR.php");
                    return (new File_Ogg_Vorbis_PEAR($streamSerial, $this->_streamList[$streamSerial], $this->_filePointer));
                    break;
                case ("speex"):
                    PEAR::raiseError("Speex streams are not currently supported.", OGG_ERROR_UNSUPPORTED);
                    break;
                case ("flac"):
                    PEAR::raiseError("FLAC streams are not currently supported.", OGG_ERROR_UNSUPPORTED);
                    break;
                case ("theora"):
                    PEAR::raiseError("Theora streams are not currently supported.", OGG_ERROR_UNSUPPORTED);
                    break;
                default:
                    PEAR::raiseError("This stream could not be identified.", OGG_ERROR_UNSUPPORTED);
            }
        } else {
            PEAR::raiseError("The stream number is invalid.", OGG_ERROR_BAD_SERIAL);
        }
    }

    /**
     * Determines whether a certain type of logical stream exists.
     *
     * This function loops through the logical streams in the physical Ogg stream
     * in an attempt to match the stream type (e.g. Vorbis).  It is strongly
     * recommended that users take advantage of the provided stream constants with
     * this method, to avoid typographical errors.
     *
     * @param    string   $streamType    The textual name of the stream required.
     * @return   boolean
     * @access   public
     */
    function hasStream($streamType)
    {
        foreach ($this->_streamList as $stream_serial => $stream) {
            if ($stream['stream_type'] == $streamType) {
                return (true);
            }
        }
        return (false);
    }
}
?>
