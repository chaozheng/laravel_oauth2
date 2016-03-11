<?php
namespace Library;
use App\Models\AccessTokens;
use App\Models\AuthorizationCodes;
use App\Models\Clients;
use App\Models\Jti;
use App\Models\PublicKeys;
use App\Models\RefreshTokens;
use App\Models\Scopes;
use App\User;
use OAuth2\OpenID\Storage\AuthorizationCodeInterface as OpenIDAuthorizationCodeInterface;
use OAuth2\ResponseType\AccessToken;

class MySqlStore implements
    \OAuth2\Storage\AccessTokenInterface,
    \OAuth2\Storage\ClientCredentialsInterface,
    \OAuth2\Storage\RefreshTokenInterface,
    \OAuth2\Storage\JwtBearerInterface,
    \OAuth2\Storage\ScopeInterface,
    \OAuth2\Storage\PublicKeyInterface,
    \OAuth2\OpenID\Storage\UserClaimsInterface,
    OpenIDAuthorizationCodeInterface
{
    public function __construct(){}

    public function checkClientCredentials($client_id, $client_secret = null)
    {

        $client = Clients::where('client_id','=',$client_id)->first();
        if (empty($client)) {
            return false;
        }

        // make this extensible
        return $client && $client->client_secret == $client_secret;
    }

    public function isPublicClient($client_id)
    {
        $client = Clients::where('client_id','=',$client_id)->first();

        if (empty($client)) {
            return false;
        }

        return empty($client->client_secret);
    }

    /* OAuth2\Storage\ClientInterface */
    public function getClientDetails($client_id, $toArray = true)
    {
        $client = Clients::where('client_id','=',$client_id)->first();

        if (empty($client)) {
            return false;
        }

        return $toArray ? $client->toArray() : $client;
    }

    public function setClientDetails($client_id, $client_secret = null, $redirect_uri = null, $grant_types = null, $scope = null, $user_id = null)
    {

        $client = $this->getClientDetails($client_id, false);
        if (empty($client)) {
            $client = new Clients();
        }

        $client->client_secret = $client_secret;
        $client->redirect_uri = $redirect_uri;
        $client->grant_types = $grant_types;
        $client->scope = $scope;
        $client->user_id = $user_id;

        return $client->save();
    }

    public function checkRestrictedGrantType($client_id, $grant_type)
    {
        $details = $this->getClientDetails($client_id);
        if (isset($details['grant_types'])) {
            $grant_types = explode(' ', $details['grant_types']);

            return in_array($grant_type, (array)$grant_types);
        }

        // if grant_types are not defined, then none are restricted
        return true;
    }

    /* OAuth2\Storage\AccessTokenInterface */
    public function getAccessToken($access_token, $toArray = true)
    {
        $token = null;

        $accessToken = AccessTokens::where('access_token', '=', $access_token)->first();


        if (!empty($accessToken)) {
            $token = $accessToken->toArray();
            $token['expires'] = strtotime($token['expires']);
        }

        return $toArray ? $token : $accessToken;
    }

    public function setAccessToken($access_token, $client_id, $user_id, $expires, $scope = null)
    {
        // convert expires to datestring
        $expires = date('Y-m-d H:i:s', $expires);

        $accessToken = $this->getAccessToken($access_token, false);
        // if it exists, update it.
        if (empty($accessToken)) {
            $accessToken = new AccessTokens();
        }
        $accessToken->access_token = $access_token;
        $accessToken->client_id = $client_id;
        $accessToken->expires = $expires;
        $accessToken->user_id = $user_id;
        $accessToken->scope = $scope;

        return $accessToken->save();
    }

    public function unsetAccessToken($access_token)
    {
        $accessToken = $this->getAccessToken($access_token, false);

        return $accessToken->delete();
    }

    /* OAuth2\Storage\AuthorizationCodeInterface */
    public function getAuthorizationCode($code, $toArray = true)
    {

        $codeData = null;

        $authorizationCode = AuthorizationCodes::where("authorization_code",'=',$code)->first();

        if (!empty($authorizationCode)) {
            $codeData = $authorizationCode->toArray();
            $codeData['expires'] = strtotime($codeData['expires']);
        }

        return $toArray ? $codeData : $authorizationCode;
    }

    public function setAuthorizationCode($code, $client_id, $user_id, $redirect_uri, $expires, $scope = null, $id_token = null)
    {
        // convert expires to datestring
        $expires = date('Y-m-d H:i:s', $expires);

        $authorizationCode = $this->getAuthorizationCode($code, false);

        if (empty($authorizationCode)) {
            $authorizationCode = new AuthorizationCodes();
        }

        if (!empty($id_token)) {
            $authorizationCode->id_token = $id_token;
        }

        $authorizationCode->authorization_code = $code;
        $authorizationCode->client_id = $client_id;
        $authorizationCode->user_id = $user_id;
        $authorizationCode->redirect_uri = $redirect_uri;
        $authorizationCode->expires = $expires;
        $authorizationCode->scope = $scope;

        return $authorizationCode->save();
    }

    public function expireAuthorizationCode($code)
    {
        $authorizationCode = $this->getAuthorizationCode($code, false);

        return $authorizationCode->delete();
    }

    /* OAuth2\Storage\UserCredentialsInterface */
    public function getUserByName($username,$password)
    {

        $user = User::where('username','=',$username)->first();

        if( !empty( $user ) && sha1($user->password.$user->salt) == $password ) {
            return $user->toArray();
        }
        return false;
    }

    public function getUserById($user_id)
    {
        $user = Users::find($user_id);

        return empty($user) ? : $user->toArray();
    }

    /* UserClaimsInterface */
    public function getUserClaims($user_id, $claims)
    {
        if (!$userDetails = $this->getUserById($user_id)) {
            return false;
        }

        $claims = explode(' ', trim($claims));
        $userClaims = array();

        // for each requested claim, if the user has the claim, set it in the response
        $validClaims = explode(' ', self::VALID_CLAIMS);
        foreach ($validClaims as $validClaim) {
            if (in_array($validClaim, $claims)) {
                if ($validClaim == 'address') {
                    // address is an object with subfields
                    $userClaims['address'] = $this->getUserClaim($validClaim, $userDetails['address'] ?: $userDetails);
                } else {
                    $userClaims = array_merge($userClaims, $this->getUserClaim($validClaim, $userDetails));
                }
            }
        }

        return $userClaims;
    }

    protected function getUserClaim($claim, $userDetails)
    {
        $userClaims = array();
        $claimValuesString = constant(sprintf('self::%s_CLAIM_VALUES', strtoupper($claim)));
        $claimValues = explode(' ', $claimValuesString);

        foreach ($claimValues as $value) {
            $userClaims[$value] = isset($userDetails[$value]) ? $userDetails[$value] : null;
        }

        return $userClaims;
    }

    /* OAuth2\Storage\RefreshTokenInterface */
    public function getRefreshToken($refresh_token, $toArray = true)
    {
        $token = null;

        $refreshToken = RefreshTokens::where('refresh_token','=',$refresh_token)->first();

        if (!empty($refreshToken)) {
            $token = $refreshToken->toArray();
            $token['expires'] = strtotime($token['expires']);
        }

        return $toArray ? $token : $refreshToken;
    }

    public function setRefreshToken($refresh_token, $client_id, $user_id, $expires, $scope = null)
    {
        // convert expires to datestring
        $expires = date('Y-m-d H:i:s', $expires);

        $refreshToken = new RefreshTokens();
        $refreshToken->refresh_token = $refresh_token;
        $refreshToken->client_id = $client_id;
        $refreshToken->user_id = $user_id;
        $refreshToken->expires = $expires;
        $refreshToken->scope = $scope;

        return $refreshToken->save();
    }

    public function unsetRefreshToken($refresh_token)
    {
        $refreshToken = $this->getRefreshToken($refresh_token, false);

        return $refreshToken->delete();
    }

    /* ScopeInterface */
    public function scopeExists($scope)
    {
        $scope = explode(' ', $scope);
        $whereIn = implode(',', $scope);
        $scopeTotal = Scopes::whereIn('scope',$whereIn)->count();

        return $scopeTotal == count($scope);
    }

    public function getDefaultScope($client_id = null)
    {
        $scopes = Scopes::where('is_default','=',1)->get();

        $defaultScope = null;

        if( !empty($scopes) ) {
            foreach ($scopes as $row) {
                $defaultScope[] = $row->scope;
            }
            return implode(' ', $defaultScope);
        }

        return null;
    }

    /* JWTBearerInterface */
    public function getClientKey($client_id, $subject)
    {
        $publicKey = PublicKeys::where('client_id','=',$client_id)
            ->where('subject','=',$subject)
            ->first();

        return empty($publicKey) ? false : $publicKey->toArray();
    }

    public function getClientScope($client_id)
    {
        if (!$clientDetails = $this->getClientDetails($client_id)) {
            return false;
        }

        if (isset($clientDetails['scope'])) {
            return $clientDetails['scope'];
        }

        return null;
    }

    public function getJti($client_id, $subject, $audience, $expires, $jti)
    {

        $jti = Jti::where('issuer','=',$client_id)
            ->where('subject','=',$subject)
            ->where('expires','=',$expires)
            ->where('jti','=',$jti)
            ->first();

        if( !empty($jti) ) {
            return array(
                'issuer' => $jti->issuer,
                'subject' => $jti->subject,
                'audience' => $jti->audience,
                'expires' => $jti->expires,
                'jti' => $jti->jti,
            );
        }

        return null;
    }

    public function setJti($client_id, $subject, $audience, $expires, $jti)
    {
        $jti = new Jti();
        $jti->issuer = $client_id;
        $jti->subject = $subject;
        $jti->audience = $audience;
        $jti->expires = $expires;
        $jti->jti = $jti;

        return $jti->save();
    }

    /* PublicKeyInterface */
    public function getPublicKey($client_id = null)
    {
        $publicKey = PublicKeys::where('client_id','=',$client_id)
            ->whereOr('client_id','IS NULL')
            ->orderBy('client_id IS NOT NULL','DESC')
            ->first();

        return empty($publicKey) ? : $publicKey->public_key;
    }

    public function getPrivateKey($client_id = null)
    {

        $publicKey = PublicKeys::where('client_id','=',$client_id)
            ->whereOr('client_id','IS NULL')
            ->orderBy('client_id IS NOT NULL','DESC')
            ->first();

        return empty($publicKey) ? : $publicKey->private_key;
    }

    public function getEncryptionAlgorithm($client_id = null)
    {
        $publicKey = PublicKeys::where('client_id','=',$client_id)
            ->whereOr('client_id','IS NULL')
            ->orderBy('client_id IS NOT NULL','DESC')
            ->first();

        return empty($publicKey) ? 'RS256' : $publicKey->encryption_algorithm;
    }
}
