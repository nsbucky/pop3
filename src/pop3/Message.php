<?php namespace Pop3;

class Message
{

    /**
     * @var resource
     */
    protected $connection;

    /**
     * @var integer
     */
    protected $messageNumber;

    /**
     * @var object
     */
    protected $data;

    /**
     * @var object
     */
    protected $headers;

    /**
     * @param Connection $connection
     * @param $messageNumber
     * @param array|object $data
     */
    public function __construct( Connection $connection, $messageNumber, $data )
    {
        $this->connection    = $connection->connect();
        $this->messageNumber = $messageNumber;
        $this->data          = $data;

        foreach( $data as $key=>$value ) {
            $this->$key = $value;
        }

        // get headers for this message
        $this->headers = $this->fetchHeaders();
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getHeader( $key )
    {
        if( is_object( $this->headers )
            && isset( $this->headers->$key ) ) {
            return $this->headers->$key;
        }

        $this->headers = $this->fetchHeaders();

        if( isset( $this->headers->$key ) ) {
            return $this->headers->$key;
        }

        return null;
    }

    /**
     * marks a message for deletion. The message will not actually be deleted
     * until the connection is closed with the appropriate flag, which is done
     * in the class’ destructor.
     *
     * @return boolean
     */
    public function delete()
    {
        return imap_delete( $this->connection, $this->messageNumber );
    }

    /**
     * get rfc822 parsed headers into an array of arrays/objects
     * @return array
     */
    public function fetchHeaders()
    {
        return imap_rfc822_parse_headers( imap_fetchheader( $this->connection, $this->messageNumber ) );
    }

    /**
     * uses regular expressions to parse the email headers which appear in the
     * email as a single block of text, and returns the information as an array
     * for easy access.
     *
     * @param string $headers
     * @return array $result
     */
    public function parseHeaders( $headers )
    {
        $headers = preg_replace( '/\r\n\s+/m', "", $headers );

        preg_match_all( '/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)?\r\n/m', $headers, $matches );

        foreach( $matches[1] as $key => $value ) {
            $result[$value] = $matches[2][$key];
        }

        return $result;
    }

    /**
     * separates each MIME type found and returns the information as an array.
     * If multiple MIME types are found, then it’s a good indication that there
     * are attachments.
     *
     * @param boolean $parseHeaders
     * @return array $mail
     */
    public function mimeToArray( $parseHeaders = false )
    {
        $mail = imap_fetchstructure( $this->connection, $this->messageNumber );
        $mail = $this->getParts( $this->messageNumber, $mail, 0 );

        if( $parseHeaders ) {
            $mail[0]["parsed"] = $this->parseHeaders( $mail[0]["data"] );
        }

        return $mail;
    }

    /**
     * extracts the different parts of the email such as the body and attachment
     * information and returns the information as an array.
     *
     * @param string $part
     * @param string $prefix
     * @return array $attachments
     */
    public function getParts( $part, $prefix )
    {
        $attachments          = array();
        $attachments[$prefix] = $this->decodePart( $this->messageNumber, $part, $prefix );

        // multi-part
        if( isset( $part->parts ) ) {
            $prefix = ( $prefix ) ? $prefix . "." : "";
            foreach( $part->parts as $number => $subpart ) {
                $attachments = array_merge( $attachments, $this->getParts( $this->messageNumber, $subpart, $prefix . ( $number + 1 ) ) );
            }
        }

        return $attachments;
    }

    /**
     *  returns the body of an e-mail that has only one part, or no attachments.
     * @return string
     */
    public function fetchBody()
    {
        $message = $this->mimeToArray( true );

        if( count( $message ) > 1 ) {
            return isset( $msg["1.1"] ) ? $message["1.1"]["data"] : $message[1]["data"];
        }

        $body = imap_fetchbody( $this->connection, $this->messageNumber, '1.1' );

        if( $body == "" ) {
            $body = imap_fetchbody( $this->connection, $this->messageNumber, "1" );
        }

        return $body;
    }

    /**
     * method takes the base64 or quoted-printable encoded attachment data,
     * decodes it, and returns it so you can do something useful with it.
     * @param string $part
     * @param string $prefix
     * @return array $attachment
     */
    public function decodePart( $part, $prefix )
    {
        $attachment = array();

        if( $part->ifdparameters ) {
            foreach( $part->dparameters as $obj ) {
                $attachment[strtolower( $obj->attribute )] = $obj->value;
                if( strtolower( $obj->attribute ) == "filename" ) {
                    $attachment["is_attachment"] = true;
                    $attachment["filename"]      = $obj->value;
                }
            }
        }

        if( $part->ifparameters ) {
            foreach( $part->parameters as $obj ) {
                $attachment[strtolower( $obj->attribute )] = $obj->value;
                if( strtolower( $obj->attribute ) == "name" ) {
                    $attachment["is_attachment"] = true;
                    $attachment["name"]          = $obj->value;
                }
            }
        }

        $attachment["data"] = imap_fetchbody( $this->connection, $this->messageNumber, $prefix );

        // 3 is base64
        if( $part->encoding == 3 ) {
            $attachment["data"] = base64_decode( $attachment["data"] );

            return $attachment;
        }

        // 4 is quoted-printable
        if( $part->encoding == 4 ) {
            $attachment["data"] = quoted_printable_decode( $attachment["data"] );

            return $attachment;
        }

        return $attachment;
    }

}