<?php

namespace App\Http\Controllers;


class TokenController extends Controller
{
    public function index()
    {
        //curl -u testclient:testpass http://127.0.0.1:8002/oauth2/token -d 'grant_type=authorization_code&code=853aa0728362'
        //curl -u testclient:testpass http://127.0.0.1:8002/oauth2/token -d 'grant_type=client_credentials'
        $this->server->handleTokenRequest($this->oauthRequest,$this->oauthResponse)->send();
    }
}