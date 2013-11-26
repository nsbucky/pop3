## Pop3
This class aims to be a simple way to read a POP3 mailbox.

### Required setup
In the `require` key of `composer.json` file add the following

    "nsbucky/pop3": "dev-master"

## Example
    $pop3 = new \Pop3\Pop3($host, $user, $password);

    try {
        $pop3->connect();

        $messages = $pop3->listMessages();

        foreach( $messages as $messageNumber => $message ) {
            echo "<pre>" . print_r($message, true) . "</pre>";

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
            $msg = $pop3->mimeToArray($msgNum, true);
            if(sizeof($msg) > 1) {
                $body = (isset($msg["1.1"])) ? $msg["1.1"]["data"] : $msg[1]["data"];
            } else {
                $body = $pop3->fetchBody($msgNum);
            }
        }

    } catch( \RuntimeException $e) {
        echo $e->getMessage(); // will say it can't connect to $mailbox
    }

