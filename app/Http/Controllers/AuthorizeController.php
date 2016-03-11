<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\DB;

class AuthorizeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function index()
    {
        if (!$this->server->validateAuthorizeRequest($this->oauthRequest, $this->oauthResponse)) {
            $this->oauthResponse->send();
        }

        $this->server->handleAuthorizeRequest($this->oauthRequest, $this->oauthResponse, true);

        $this->oauthResponse->send();
    }
}
