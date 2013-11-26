<?php namespace Pop3;

class Pop3
{

    /**
     * @var resource $connection
     */
    protected $connection;

    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $user;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $folder;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var bool
     */
    protected $useSSL = false;

    /**
     *
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $folder
     * @param integer $port
     * @param boolean $useSSL
     */
    public function __construct( $host, $user, $password, $folder = "INBOX", $port = 110, $useSSL = false )
    {
        $this->host     = $host;
        $this->user     = $user;
        $this->password = $password;
        $this->folder   = $folder;
        $this->port     = $port;
        $this->useSSL   = $useSSL;
    }

    public function connect()
    {
        if( isset( $this->connection ) ) {
            return $this->connection;
        }

        $ssl        = $this->useSSL ? null : "/novalidate-cert";
        $mailbox    = sprintf( "{%s:%d/pop3%s}%s", $this->host, $this->port, $this->ssl, $this->folder );
        $connection = imap_open( $mailbox, $this->user, $this->password );

        if( $connection === false ) {
            throw new \RuntimeException( 'Failed to connection to mailbox: ' . $mailbox );
        }

        $this->connection = $connection;

        return $this->connection;
    }

    /**
     * close connection
     */
    public function __destruct()
    {
        imap_close( $this->connection, CL_EXPUNGE );
    }

    /**
     *  returns an array containing a list of all messages currently in the inbox,
     * or you can optionally request information for one specific message by
     * providing a message ID as an argument. The list returned doesn’t contain
     * the actual contents of the email, but rather the from address, to address,
     * subject line, and date.
     *
     * @param integer $msgNum
     * @return array $msgList
     */
    public function listMessages( $msgNum = 0 )
    {
        $msgList = array();

        if( $msgNum ) {
            $range = $msgNum;
        } else {
            $info  = imap_check( $this->connection );
            $range = "1:" . $info->Nmsgs;
        }

        $response = imap_fetch_overview( $this->connection, $range );

        foreach( $response as $msg ) {
            $msgList[$msg->msgno] = (array) $msg;
        }

        return $msgList;
    }

    /**
     * marks a message for deletion. The message will not actually be deleted
     * until the connection is closed with the appropriate flag, which is done
     * in the class’ destructor.
     *
     * @param integer $msgNum
     * @return boolean
     */
    public function deleteMessage( $msgNum )
    {
        return imap_delete( $this->connection, $msgNum );
    }

    /**
     * get rfc822 parsed headers into an array of arrays/objects
     * @param type $msgNum
     * @return array
     */
    public function fetchHeaders( $msgNum )
    {
        return imap_rfc822_parse_headers( imap_fetchheader( $this->connection, $msgNum ) );
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
        preg_match_all( '/([^: ]+): (.+?(?:\r\n\s(?:.+?))*)?\r\n/m',
            $headers, $matches );
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
     * @param integer $msgNum
     * @param boolean $parseHeaders
     * @return array $mail
     */
    public function mimeToArray( $msgNum, $parseHeaders = false )
    {
        $mail = imap_fetchstructure( $this->connection, $msgNum );
        $mail = $this->getParts( $msgNum, $mail, 0 );
        if( $parseHeaders ) {
            $mail[0]["parsed"] = $this->parseHeaders( $mail[0]["data"] );
        }

        return $mail;
    }

    /**
     * extracts the different parts of the email such as the body and attachment
     * information and returns the information as an array.
     *
     * @param integer $msgNum
     * @param string $part
     * @param string $prefix
     * @return array $attachments
     */
    public function getParts( $msgNum, $part, $prefix )
    {
        $attachments          = array();
        $attachments[$prefix] = $this->decodePart( $msgNum, $part, $prefix );

        // multi-part
        if( isset( $part->parts ) ) {
            $prefix = ( $prefix ) ? $prefix . "." : "";
            foreach( $part->parts as $number => $subpart ) {
                $attachments = array_merge( $attachments, $this->getParts( $msgNum, $subpart, $prefix . ( $number + 1 ) ) );
            }
        }

        return $attachments;
    }

    /**
     *  returns the body of an e-mail that has only one part, or no attachments.
     * @param integer $msgNum
     * @return string
     */
    public function fetchBody( $msgNum )
    {
        $body = imap_fetchbody( $this->connection, $msgNum, '1.1' );

        if( $body == "" ) {
            $body = imap_fetchbody( $this->connection, $msgNum, "1" );
        }

        return $body;
    }


    /**
     * method takes the base64 or quoted-printable encoded attachment data,
     * decodes it, and returns it so you can do something useful with it.
     * @param integer $msgNum
     * @param string $part
     * @param string $prefix
     * @return array $attachment
     */
    public function decodePart( $msgNum, $part, $prefix )
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

        $attachment["data"] = imap_fetchbody( $this->connection, $msgNum, $prefix );

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