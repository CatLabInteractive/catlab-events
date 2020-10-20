<?php

namespace App\Tools;

use App\Tools\RocketChat\RocketChatUser;

/**
 * Class RocketChatClient
 * @package App\Tools
 */
class RocketChatClient
{
    /**
     * @var string
     */
    private $adminUsername;

    /**
     * @var string
     */
    private $adminPassword;

    /**
     * RocketChatClient constructor.
     * @param $apiUrl
     * @param $adminUsername
     * @param $adminPassword
     */
    public function __construct(
        $apiUrl,
        $adminUsername,
        $adminPassword
    ) {
        if (!defined('REST_API_ROOT')) {
            define('REST_API_ROOT', '/api/v1/');
        }

        if (!defined('ROCKET_CHAT_INSTANCE')) {
            define('ROCKET_CHAT_INSTANCE', $apiUrl);
        }

        $this->adminUsername = $adminUsername;
        $this->adminPassword = $adminPassword;
    }

    /**
     * @param string $rocketUsername
     * @param string $rocketPassword
     * @param string $email
     * @param string $nickname
     * @param string|null $channel
     * @return mixed
     * @throws \Httpful\Exception\ConnectionErrorException
     */
    public function getAuthToken(
        string $rocketUsername,
        string $rocketPassword,
        string $email,
        string $nickname,
        string $channel = null
    ) {
        ob_start();

        if (\App::environment([ 'local', 'staging' ])) {
            $rocketUsername = 'qwtest_' . $rocketUsername;
            $email = 'qwtest_' . $email;
        } else {
            $rocketUsername = 'qw_' . $rocketUsername;
        }

        $admin = new RocketChatUser($this->adminUsername, $this->adminPassword);
        $admin->login();

        // create a new user
        $newuser = new RocketChatUser($rocketUsername, $rocketPassword, array(
            'nickname' => $nickname,
            'email' => $email
        ));

        // stupid library outputs content.
        $login = $newuser->login(false);

        if( !$login ) {
            // actually create the user if it does not exist yet
            $newuser->create();
            $login = $newuser->login(true);
        } else {
            $login = $newuser->login(true);
        }

        /*
        if ($channel) {
            $newuser->joinChannel($channel);
        }*/

        $newuser->updateNickname($nickname);

        ob_end_clean();

        if (is_object($login) && $login->authToken) {
            return $login->authToken;
        }

        return false;
    }
}
