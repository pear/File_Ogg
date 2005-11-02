<?php
class File_Ogg_Stream
{
    /**
     * The serial number of this logical stream.
     *
     * @var     int
     * @access  private
     */
    var $_streamSerial;
	var $_streamList;
	var $_filePointer;
	
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
}
?>