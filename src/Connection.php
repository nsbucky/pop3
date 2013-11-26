<?php namespace Pop3;

class Connection
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

    /**
     * @return resource
     * @throws \RuntimeException
     */
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
     * @return array
     */
    public function getMessages()
    {
        $messages = array();

        $info = imap_check( $this->connect() );

        $range     = "1:" . $info->Nmsgs;
        $responses = imap_fetch_overview( $this->connect(), $range );

        foreach( $responses as $message ) {
            $messages[$message->msgno] = new Message( $this, $message->msgno, $message );
        }

        return $messages;
    }

    /**
     * @param integer $messageNumber
     * @return array
     */
    public function getMessage( $messageNumber )
    {
        $messages  = array();
        $responses = imap_fetch_overview( $this->connect(), $messageNumber );

        foreach( $responses as $message ) {
            $messages[$message->msgno] = new Message( $this, $message->msgno, $message );
        }

        return $messages;
    }
}