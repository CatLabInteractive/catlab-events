<?php

namespace App\Tools\RocketChat;

use Httpful\Request;
use RocketChat\User;

/**
 * Class RocketChatUser
 * @package App\Tools\RocketChat
 */
class RocketChatUser extends User
{
    protected $password;

    public function __construct($username, $password, $fields = array()){
        parent::__construct($username, $password, $fields);
        $this->username = $username;
        $this->password = $password;
        if( isset($fields['nickname']) ) {
            $this->nickname = $fields['nickname'];
        }
        if( isset($fields['email']) ) {
            $this->email = $fields['email'];
        }
    }

    /**
     * Authenticate with the REST API.
     */
    public function login($save_auth = true) {
        $response = Request::post( $this->api . 'login' )
            ->body(array( 'user' => $this->username, 'password' => $this->password ))
            ->send();

        if( $response->code == 200 && isset($response->body->status) && $response->body->status == 'success' ) {
            if( $save_auth) {
                // save auth token for future requests
                $tmp = Request::init()
                    ->addHeader('X-Auth-Token', $response->body->data->authToken)
                    ->addHeader('X-User-Id', $response->body->data->userId);
                Request::ini( $tmp );
            }
            $this->id = $response->body->data->userId;
            return $response->body->data;
        } else {
            return false;
        }
    }

    /**
     * @param string $channel
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    public function joinChannel(string $channel)
    {
        $response = Request::post( $this->api . 'channels.join' )
            ->body(array( 'user' => $this->username, 'password' => $this->password, 'roomId' => $channel ))
            ->send();
    }

    /**
     * @param $nickname
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    public function updateNickname($nickname)
    {
        $response = Request::post( $this->api . 'users.updateOwnBasicInfo' )
            ->body([ 'data' => [ 'name' => $nickname ]])
            ->send();
    }

    /**
     * Create a new user.
     */
    public function create() {
        $response = Request::post( $this->api . 'users.create' )
            ->body(array(
                'name' => $this->nickname,
                'email' => $this->email,
                'username' => $this->username,
                'password' => $this->password,
            ))
            ->send();

        if( $response->code == 200 && isset($response->body->success) && $response->body->success == true ) {
            $this->id = $response->body->user->_id;
            return $response->body->user;
        } else {
            return false;
        }
    }
}
