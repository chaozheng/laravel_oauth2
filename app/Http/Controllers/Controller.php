<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController,
    Library\MySqlStore,
    OAuth2\Server as OAuthServer,
    OAuth2\Request as OAuthRequest,
    OAuth2\Response as OAuthResponse;

class Controller extends BaseController
{
    protected $storage;
    protected $server;
    protected $oauthRequest;
    protected $oauthResponse;

    public function __construct()
    {
        $this->storage = new MySqlStore();
        $this->server = new OAuthServer($this->storage);
        $this->oauthRequest = OAuthRequest::createFromGlobals();
        $this->oauthResponse = new OAuthResponse();
    }


}
