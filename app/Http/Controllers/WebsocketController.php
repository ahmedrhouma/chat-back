<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class WebsocketController extends Controller implements MessageComponentInterface
{
    const ERROR_MESSAGE_STATUS = 201;
    const SUCCESS_MESSAGE_STATUS = 200;


    private $connections = [];
    private $users = [];
    private $groups = [];

    public function __construct()
    {
    }

    function onOpen(ConnectionInterface $conn)
    {
        $this->connections[$conn->resourceId] = $conn;
    }


    function onClose(ConnectionInterface $conn)
    {
        $disconnectedId = $conn->resourceId;
        unset($this->connections[$disconnectedId]);
        if (($key = array_search($conn->resourceId, $this->users)) !== false) {
            foreach ($this->administration as $agent) {
                $this->connections[$agent]->send(json_encode([
                    'action' => 'closedConnection',
                    'id' => $key,
                ]));
            }
            unset($this->users[$key]);
        }
        if (($key = array_search($conn->resourceId, $this->administration)) !== false) {
            unset($this->administration[$key]);
            $this->negotiations = array_filter($this->negotiations, function($value) use($key){
                return $value != $key;
            });
        }
    }


    function onError(ConnectionInterface $conn, \Exception $e)
    {
        echo "An error has occurred with user : {$e->getMessage()}\n";

        if (($key = array_search($conn->resourceId, $this->users)) !== false) {
            unset($this->users[$key]);
        }
        unset($this->connections[$conn->resourceId]);
        $conn->close();
    }

    function onMessage(ConnectionInterface $conn, $msg)
    {
        echo $msg;
        $content = json_decode($msg, true);

        switch ($content['action']) {
            case 'attachAccount':
                $this->users[$content['user_id']] = $conn->resourceId;
                break;
            case 'message':
                /* Store Message  */
                if ($message = Message::create(['sender_id'=>$content['sender_id'],'receiver_id'=>$content['receiver_id'],'content'=>$content['content'],'receiver_type'=>1,'seen'=>0])) {
                    /* Send to receiver if connected  */
                    $content['status'] = self::SUCCESS_MESSAGE_STATUS;
                    $content['message_sent'] = $message->created_at;
                    $conn->send(json_encode($content));
                    echo json_encode($this->users);
                    if (isset($this->users[$content['receiver_id']])) {
                        $this->connections[$this->users[$content['receiver_id']]]->send(json_encode($content));
                    }
                } else {
                    /* return error message when not stored  */
                    $conn->send(json_encode([
                        'action' => 'message',
                        'sender_id' => $content['sender_id'],
                        'status' => self::ERROR_MESSAGE_STATUS
                    ]));
                }
                break;
            case 'seen':
                $this->negotiation->openConversation($content['negotiation_id'], $content['sender_id']);
                if (in_array($content['role'], [self::ADVERTISER_ROLE, self::PUBLISHER_ROLE])) {
                    if (isset($this->administration[$content['receiver_id'] ?? ''])) {
                        $this->connections[$this->administration[$content['receiver_id']]]->send($msg);
                    }
                } else if (isset($this->users[$content['receiver_id'] ?? ''])) {
                    $this->connections[$this->users[$content['receiver_id']]]->send($msg);
                }
                break;
            case 'invitation':
                if (isset($this->users[$content['receiver_id'] ?? ''])) {
                    $this->connections[$this->users[$content['receiver_id']]]->send($msg);
                }
                if (isset($content['content']) && $content['content'] != "") {
                    $message = $this->negotiation->storeMessage($content['sender_id'], (isset($content['receiver_id']) ? $content['receiver_id'] : NULL), $content['content'], $content['negotiation_id']);
                }
                break;
            case 'attachNegotiation':
                $this->negotiation->attachedNegotiation($content['negotiation_id'], $content['sender_id']);
                $this->negotiations[$content['negotiation_id']] = $content['sender_id'];
                foreach ($this->administration as $agent) {
                    $this->connections[$agent]->send($msg);
                }
                if (isset($this->users[$content['receiver_id']])) {
                    $this->connections[$this->users[$content['receiver_id']]]->send($msg);
                }
                break;
            case 'writing':
                if (isset($this->users[$content['receiver_id']])) {
                    $this->connections[$this->users[$content['receiver_id']]]->send($msg);
                } elseif (isset($this->administration[$content['receiver_id']])) {
                    $this->connections[$this->administration[$content['receiver_id']]]->send($msg);
                }
                break;
            case 'connected':
                $conn->send(json_encode(['users'=>array_keys($this->users),'action'=>'connected']));
                break;
        }
    }
}
