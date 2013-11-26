## Pop3
This class aims to be a simple way to read a POP3 mailbox.

Most of this code was pulled from the set of functions found in this php.net comment: http://www.php.net/manual/en/book.imap.php#96414

### Required setup
In the `require` key of `composer.json` file add the following

    "nsbucky/pop3": "dev-master"

## Example
    $pop3 = new \Pop3\Connection($host, $user, $password);

    try {

        $messages = $pop3->listMessages();

        // each message will be instance of Pop3\Message
        foreach( $messages as $messageNumber => $message ) {
            echo "<pre>" . print_r( get_object_vars($message), true) . "</pre>";

            /*
            prints an array that looks something like this:
            Array
            (
                [subject] => sample message
                [from] => Your Mom <yourmom@herdomain.com>
                [to] => mrpickles@hardbears.com
                [date] => Thu, 20 Sep 2012 09:01:51 -0700
                [message_id] => <CABieW=+W2Xvb6M+mkpDn8JU-R_6c0jkJAe3==AQLDvR7C8z1Ug@mail.gm
            ail.com>
                [size] => 2500
                [uid] => 1
                [msgno] => 1
                [recent] => 1
                [flagged] => 0
                [answered] => 0
                [deleted] => 0
                [seen] => 0
                [draft] => 0
                [udate] => 1348156911
            )
            */

            // fetch body of message
            $body = $message->fetchBody();
        }

    } catch( \RuntimeException $e) {
        echo $e->getMessage(); // will say it can't connect to $mailbox
    }

