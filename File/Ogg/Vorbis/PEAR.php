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
 *  Check number for the first header in a Vorbis stream.
 */
define("OGG_VORBIS_IDENTIFICATION_HEADER",  1);
/**
 *  Check number for the second header in a Vorbis stream.
 */
define("OGG_VORBIS_COMMENTS_HEADER",        3);
/**
 *  Check number for the third header in a Vorbis stream.
 */
define("OGG_VORBIS_SETUP_HEADER",           5);
/**
 *  Error thrown if the stream appears to be corrupted.
 */
define("OGG_VORBIS_ERROR_UNDECODABLE",      1);
/**
 *  Error thrown if the user attempts to extract a comment using a comment key
 *  that does not exist.
 */
define("OGG_VORBIS_ERROR_INVALID_COMMENT",  2);

/**
 * Extract the contents of a Vorbis logical stream.
 *
 * This class provides an interface to a Vorbis logical stream found within
 * a Ogg stream.  A variety of information may be extracted, including comment
 * tags, running time, and bitrate.  For more information, please see the following
 * links.
 *
 * @link    http://www.xiph.org/ogg/vorbis/docs.html
 * @link    http://www.xiph.org/ogg/vorbis/doc/vorbis-ogg.html
 *
 * @see     File_Ogg_Pear
 * @access  public
 * @package File_Ogg
 */
class File_Ogg_Vorbis_Pear
{
    /**
     * Array to hold each of the comments.
     *
     * @var     array
     * @access  private
     */
    var $_comments = array();

    /**
     * Version of vorbis specification used.
     *
     * @var     int
     * @access  private
     */
    var $_version;

    /**
     * Number of channels in the vorbis stream.
     *
     * @var     int
     * @access  private
     */
    var $_channels;

    /**
     * Number of samples per second in the vorbis stream.
     *
     * @var     int
     * @access  private
     */
    var $_sampleRate;

    /**
     * Minimum bitrate for the vorbis stream.
     *
     * @var     int
     * @access  private
     */
    var $_minBitrate;

    /**
     * Maximum bitrate for the vorbis stream.
     *
     * @var     int
     * @access  private
     */
    var $_maxBitrate;

    /**
     * Nominal bitrate for the vorbis stream.
     *
     * @var     int
     * @access  private
     */
    var $_nomBitrate;

    /**
     * Average bitrate for the vorbis stream.
     *
     * @var     float
     * @access  private
     */
    var $_avgBitrate;

    /**
     * Vendor string for the vorbis stream.
     *
     * @var     string
     * @access  private
     */
    var $_vendor;

    /**
     * The serial number of this logical stream.
     *
     * @var     int
     * @access  private
     */
    var $_streamSerial;

    /**
     * The length of this stream in seconds.
     *
     * @var     int
     * @access  private
     */
    var $_streamLength;

    /**
     * The number of bits used in this stream.
     *
     * @var     int
     * @access  private
     */
    var $_streamSize;


    /**
     * Constructor for accessing a Vorbis logical stream.
     *
     * This method is the constructor for the native-PHP interface to a Vorbis logical
     * stream, embedded within an Ogg physical stream.
     *
     * @param   int     $streamSerial   Serial number of the logical stream.
     * @param   array   $streamData     Data for the requested logical stream.
     * @param   string  $filePath       Location of a file on the filesystem.
     * @param   pointer $filePointer    File pointer for the current physical stream.
     * @access  public
     */
    function File_Ogg_Vorbis_Pear($streamSerial, $streamData, $filePointer)
    {
        $this->_streamSerial = $streamSerial;
        $this->_streamList = $streamData;
        $this->_filePointer = $filePointer;
        $this->_parseIdentificationHeader();
        $this->_parseCommentsHeader();
        $this->_streamLength = $streamData['stream_page'][count($streamData['stream_page']) - 1]['abs_granual_pos'] / $this->_sampleRate;
        // This gives an accuracy of approximately 99.7% to the streamsize of ogginfo.
        for ($i = 0; $i < count($streamData['stream_page']); ++$i)
        {
            $this->_streamSize += $streamData['stream_page'][$i]['data_length'];
        }
        $this->_avgBitrate = ($this->_streamSize * 8) / $this->_streamLength;
    }

    /**
     * Parse the identification header (the first of three headers) in a Vorbis stream.
     *
     * This function parses the identification header.  The identification header
     * contains simple audio characteristics, such as sample rate and number of
     * channels.  There are a number of error-checking provisions laid down in the Vorbis
     * specification to ensure the stream is pure.
     *
     * @access  private
     */
    function _parseIdentificationHeader()
    {
        fseek($this->_filePointer, $this->_streamList['stream_page'][0]['body_offset'], SEEK_SET);
        // Check if this is the correct header.
        $packet = unpack("C1data", fread($this->_filePointer, 1));
        if ($packet['data'] != OGG_VORBIS_IDENTIFICATION_HEADER) {
            PEAR::raiseError("Stream Undecodable", OGG_VORBIS_ERROR_UNDECODABLE);
        }

        // Check that this stream is a Vorbis stream.
        if (fread($this->_filePointer, 6) != "vorbis") {
            PEAR::raiseError("Stream Undecodable", OGG_VORBIS_ERROR_UNDECODABLE);
        }

        $version = unpack("V1data", fread($this->_filePointer, 4));
        // The Vorbis stream version must be 0.
        if ($version['data'] != 0) {
            PEAR::raiseError("Stream Undecodable", OGG_VORBIS_ERROR_UNDECODABLE);
        } else {
            $this->_version = $version['data'];
        }

        $channels = unpack("C1data", fread($this->_filePointer, 1));
        // The number of channels MUST be greater than 0.
        if ($channels['data'] <= 0) {
            PEAR::raiseError("Stream Undecodable", OGG_VORBIS_ERROR_UNDECODABLE);
        } else {
            $this->_channels = $channels['data'];
        }

        $sample_rate = unpack("V1data", fread($this->_filePointer, 4));
        // The sample rate MUST be greater than 0.
        if ($sample_rate['data'] <= 0) {
            PEAR::raiseError("Stream Undecodable", OGG_VORBIS_ERROR_UNDECODABLE);
        } else {
            $this->_sampleRate = $sample_rate['data'];
        }

        // Extract the various bitrates from the vorbis stream.
        $bitrate['max'] = unpack("V1data", fread($this->_filePointer, 4));
        $this->_maxBitrate = $bitrate['max']['data'];
        $bitrate['nom'] = unpack("V1data", fread($this->_filePointer, 4));
        $this->_nomBitrate = $bitrate['nom']['data'];
        $bitrate['min'] = unpack("V1data", fread($this->_filePointer, 4));
        $this->_minBitrate = $bitrate['min']['data'];

        $blocksize = unpack("C1data", fread($this->_filePointer, 1));

        $valid_block_sizes = array(64, 128, 256, 512, 1024, 2048, 4096, 8192);

        // blocksize_0 MUST be a valid blocksize.
        $blocksize[0] = pow(2, ($blocksize['data'] & 0x0F));
        if (FALSE == in_array($blocksize[0], $valid_block_sizes)) {
            PEAR::raiseError("Stream Undecodable", OGG_VORBIS_ERROR_UNDECODABLE);
        }
        // blocksize_1 MUST be a valid blocksize.
        $blocksize[1] = pow(2, ($blocksize['data'] & 0xF0) >> 4);
        if (FALSE == in_array($blocksize[1], $valid_block_sizes)) {
            PEAR::raiseError("Stream Undecodable", OGG_VORBIS_ERROR_UNDECODABLE);
        }
        // blocksize_0 MUST be less than or equal to blocksize_1.
        if ($blocksize[1] < $blocksize[0]) {
            PEAR::raiseError("Stream Undecodable", OGG_VORBIS_ERROR_UNDECODABLE);
        }

        // The framing bit MUST be set to mark the end of the identification header.
        $framing_bit = unpack("C1data", fread($this->_filePointer, 1));
        if ($framing_bit['data'] != 1) {
            PEAR::raiseError("Stream Undecodable", OGG_VORBIS_ERROR_UNDECODABLE);
        }
    }

    /**
     * Parse the comments header (the second of three headers) of a Vorbis stream.
     *
     * This function parses the comments header.  The comments header contains a series of
     * UTF-8 comments related to the audio encoded in the stream.  This header also contains
     * a string to identify the encoding software.  More details on the comments header can
     * be found at the following location.
     *
     * @link    http://www.xiph.org/ogg/vorbis/doc/v-comment.html
     * @access  private
     */
    function _parseCommentsHeader()
    {
        fseek($this->_filePointer, $this->_streamList['stream_page'][1]['body_offset'], SEEK_SET);
        // Check if this is the correct header.
        $packet = unpack("C1data", fread($this->_filePointer, 1));
        if ($packet['data'] != OGG_VORBIS_COMMENTS_HEADER) {
            PEAR::raiseError("Stream Undecodable", OGG_VORBIS_ERROR_UNDECODABLE);
            exit ("ERROR");
        }

        // Check that this stream is a Vorbis stream.
        if (fread($this->_filePointer, 6) != "vorbis") {
            PEAR::raiseError("Stream Undecodable", OGG_VORBIS_ERROR_UNDECODABLE);
        }

        // Decode the vendor string length.
        $vendor_len = unpack("V1data", fread($this->_filePointer, 4));
        $this->_vendor = fread($this->_filePointer, $vendor_len['data']);
        // Decode the size of the comments list.
        $comment_list_length = unpack("V1data", fread($this->_filePointer, 4));
        for ($i = 0; $i < $comment_list_length['data']; $i++) {
            $comment_length = unpack("V1data", fread($this->_filePointer, 4));
            // Comments are in the format 'ARTIST=Super Furry Animals', so split it on the equals character.
            // NOTE: Equals characters are strictly prohibited in either the COMMENT or DATA parts.
            $comment = explode("=", fread($this->_filePointer, $comment_length['data']));
            $comment_title = (string) $comment[0];
            $comment_value = (string) utf8_decode($comment[1]);

            // Check if the comment type (e.g. ARTIST) already exists.  If it does,
            // take the new value, and the existing value (or array) and insert it
            // into a new array.  This is important, since each comment type may have
            // multiple instances (e.g. ARTIST for a collaboration) and we should not
            // overwrite the previous value.
            if (isset($this->_comments[$comment_title])) {
                if (is_array($this->_comments[$comment_title])) {
                    $this->_comments[$comment_title][] = $comment_value;
                } else {
                    $this->_comments[$comment_title] = array($this->_comments[$comment_title], $comment_value);
                }
            } else {
                $this->_comments[$comment_title] = $comment_value;
            }
        }

        // The framing bit MUST be set to mark the end of the comments header.
        $framing_bit = unpack("C1data", fread($this->_filePointer, 1));
        if ($framing_bit['data'] != 1) {
            PEAR::raiseError("Stream Undecodable", OGG_VORBIS_ERROR_UNDECODABLE);
        }
    }

    /**
     * Provides a list of the comments extracted from the Vorbis stream.
     *
     * It is recommended that the user fully inspect the array returned by this function
     * rather than blindly requesting a comment in false belief that it will always
     * be present.  Whilst the Vorbis specification dictates a number of popular
     * comments (e.g. TITLE, ARTIST, etc.) for use in Vorbis streams, they are not
     * guaranteed to appear.
     *
     * @return  array
     * @access  public
     */
    function getCommentList()
    {
      	return (array_keys($this->_comments));
    }

    /**
     * Provides an interface to the numerous comments located with a Vorbis stream.
     *
     * A Vorbis stream may contain one or more instances of each comment, so the user
     * should check the variable type before printing out the result of this method.
     * The situation in which multiple instances of a comment occurring are not as
     * rare as one might think, since they are conceivable at least for ARTIST comments
     * in the situation where a track is a duet.
     *
     * @param   string  $commentTitle   Comment title to search for, e.g. TITLE.
     * @return  mixed
     * @access  public
     */
    function getComment($commentTitle)
    {
        if (isset($this->_comments[$commentTitle]))	{
            if (is_array($this->_comments[$commentTitle])) {
                return (implode(", ", $this->_comments[$commentTitle]));
            } else {
                return ($this->_comments[$commentTitle]);
            }
        }
        else
        {
            // The comment doesn't exist in this file.  The user should've called getCommentList first.
            PEAR::raiseError("Invalid Comment.", OGG_VORBIS_ERROR_INVALID_COMMENT);
        }
    }

    /**
     * Gives the serial number of this stream.
     *
     * The stream serial number is of fairly academic importance, as it makes little
     * difference to the end user.  The serial number is used by the Ogg physical
     * stream to distinguish between concurrent logical streams.
     *
     * @return  int
     * @access  public
     */
    function getSerial()
    {
        return ($this->_streamSerial);
    }

    /**
     * Version of the Vorbis specification referred to in the encoding of this stream.
     *
     * This method returns the version of the Vorbis specification (currently 0 (ZERO))
     * referred to by the encoder of this stream.  The Vorbis specification is well-
     * defined, and thus one does not expect this value to change on a frequent basis.
     *
     * @return  int
     * @access  public
     */
    function getVersion()
    {
        return ($this->_version);
    }

    /**
     * Gives the vendor string for the software used to encode this stream.
     * It is common to find libVorbis here.  A previous version of this package
     * compared this vendor string against a release table, but this has been
     * removed, as encoding software is not limited to libvorbis.
     *
     * @return  string
     * @access  public
     */
    function getVendor()
    {
        return ($this->_vendor);
    }

    /**
     * Gives the number of channels used in this stream.
     *
     * @return  int
     * @access  public
     */
    function getChannels()
    {
        return ($this->_channels);
    }

    /**
     * Gives the number of samples per second used in this stream.
     *
     * @return  int
     * @access  public
     */
    function getSampleRate()
    {
        return ($this->_sampleRate);
    }

    /**
     * Gives an array of the values of four different types of bitrates for this
     * stream. The nominal, maximum and minimum values are found within the file,
     * whereas the average value is computed.
     *
     * @return  array
     * @access  public
     */
    function getBitrates()
    {
        return (array("nom" => $this->_nomBitrate, "max" => $this->_maxBitrate, "min" => $this->_minBitrate, "avg" => $this->_avgBitrate));
    }

    /**
     * Gives the most accurate bitrate measurement from this stream.
     *
     * @return  float
     * @access  public
     */
    function getBitrate()
    {
        if ($this->_avgBitrate != 0) {
            return ($this->_avgBitrate);
        } elseif ($this->_nomBitrate != 0) {
            return ($this->_nomBitrate);
        } else{
            return (($this->_minBitrate + $this->_maxBitrate) / 2);
        }
    }

    /**
     * Gives the size (in bits) of this stream.
     *
     * @return  int
     * @access  public
     */
    function getSize()
    {
        return ($this->_streamSize);
    }

    /**
     * Gives the length (in seconds) of this stream.
     *
     * @return  int
     * @access  public
     */
    function getLength()
    {
        return ($this->_streamLength);
    }
}
?>
