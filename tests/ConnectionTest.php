<?php

require __DIR__ . '/../src/Pop3/Connection.php';
require __DIR__ . '/../src/Pop3/Message.php';

class ConnectionTest extends PHPUnit_Framework_Testcase {

    public $host = '';
    public $user = '';
    public $pass = '';

    public function testMakeConnection()
    {

        try {
            $pop3 = new Pop3\Connection($this->host, $this->user, $this->pass);

            $connection = $pop3->connect();

            $this->assertTrue( is_resource( $connection ) );

        } catch( RuntimeException $e) {
            echo $e->getMessage();
        }

        unset($pop3);
    }

    public function testListMessages()
    {
        // need to launch a test message somehow. hmm.
        try {
            $pop3 = new Pop3\Connection($this->host, $this->user, $this->pass);

            $connection = $pop3->connect();

            $this->assertTrue( is_resource( $connection ) );

        } catch( RuntimeException $e) {
            echo $e->getMessage();
        }

        // list messages
        $messages = $pop3->getMessages();

        $this->assertTrue( is_array( $messages) );

        if( count( $messages) > 0 ) {
            foreach( $messages as $message ) {
                $this->assertInstanceOf("\\Pop3\\Message", $message);
                var_dump( $message->getHeader('to'));
                break;
            }
        }

        unset($pop3);
    }

}