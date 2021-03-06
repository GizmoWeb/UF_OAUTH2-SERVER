<?php

namespace UserFrosting\Sprinkle\OAuth2Server\ServicesProvider;


use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\PasswordGrant;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Grant\ImplicitGrant;
use UserFrosting\Sprinkle\OAuth2Server\OAuth2\UserEntity;
use UserFrosting\Sprinkle\OAuth2Server\OAuth2\AccessTokenRepository;
use UserFrosting\Sprinkle\OAuth2Server\OAuth2\ClientRepository;
use UserFrosting\Sprinkle\OAuth2Server\OAuth2\ScopeRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use UserFrosting\Sprinkle\OAuth2Server\OAuth2\RefreshTokenRepository;
use UserFrosting\Sprinkle\OAuth2Server\OAuth2\UserRepository;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Middleware\AuthorizationServerMiddleware;
use UserFrosting\Sprinkle\OAuth2Server\OAuth2\AuthCodeRepository;
use League\OAuth2\Server\ResourceServer;
use UserFrosting\Sprinkle\OAuth2Server\Database\Models\OauthClients;
use UserFrosting\Sprinkle\OAuth2Server\Database\Models\Scopes;

class ServicesProvider
{
    public function register($container)
    {
		$container['OAuth2'] = function ($c) {
			$all_scopes = Scopes::where('created_at', '>', 2)->get()->toArray();

			// Init our repositories
			$clientRepository = new ClientRepository($_SESSION["CLIENT"]);
			$scopeRepository = new ScopeRepository($all_scopes);
			$accessTokenRepository = new AccessTokenRepository();
			$authCodeRepository = new AuthCodeRepository();
			$refreshTokenRepository = new RefreshTokenRepository();
            
            if($c->config['oauth2server.private_key_path'] === ""){
			     $privateKeyPath = 'file://' . __DIR__ . '/../OAuth2/private.key';
            } else {
                $privateKeyPath = $c->config['oauth2server.private_key_path'];
            }

            if($c->config['oauth2server.public_key_path'] === ""){
			     $publicKeyPath = 'file://' . __DIR__ . '/../OAuth2/public.key';
            } else {
                 $publicKeyPath = $c->config['oauth2server.public_key_path'];
            }

			// Setup the authorization server
			$OAuth2 = new AuthorizationServer(
				$clientRepository,
				$accessTokenRepository,
				$scopeRepository,
				$privateKeyPath,
                $c->config['oauth2server.EncryptionKey']
			);


			// Enable the implicit grant on the server with a token TTL of 1 hour
			// You can change P1W to P3M (months) to allow access tokens that last three months
			// With this grant type you don't have to deal with anything else. It is the easiest to get startet.
            // But it is only for Browser apps that are not able to store a token in the long term.
			// Read more on the league/oauth2 documentation: https://oauth2.thephpleague.com/authorization-server/implicit-grant/
            $OAuth2->enableGrantType(
				new ImplicitGrant(new \DateInterval('P1W')),
				new \DateInterval('P1W') // access tokens will expire after 1 hour
			);


			// This is the AuthCode Grant server, it is recomended if you have a mobile application.
			// Read more on league/oauth2 docs: https://oauth2.thephpleague.com/authorization-server/auth-code-grant/
			// authorization codes will expire after 10 minutes
			$grant = new \League\OAuth2\Server\Grant\AuthCodeGrant(
				$authCodeRepository,
				$refreshTokenRepository,
				new \DateInterval($c->config['oauth2server.auth_code_time'])
			);

			$grant->setRefreshTokenTTL(new \DateInterval($c->config['oauth2server.refresh_token_time']));

			// Enable the authentication code grant on the server
			$OAuth2->enableGrantType(
				$grant,
				new \DateInterval($c->config['oauth2server.access_token_time'])
			);


            // Activate the refresh token Grant type
            // It is needed to get a new access token.
            $refreshGrant = new \League\OAuth2\Server\Grant\RefreshTokenGrant($refreshTokenRepository);
            $refreshGrant->setRefreshTokenTTL(new \DateInterval($c->config['oauth2server.refresh_token_time'])); // new refresh tokens will expire after 1 month

            // Enable the refresh token grant on the server
            $OAuth2->enableGrantType(
            $refreshGrant,
            new \DateInterval($c->config['oauth2server.access_token_time']));
            	return $OAuth2;
        };


        // Create the ResourceServer Service, you can call this on your route to protect it
        // You could also use this on another userfrosting installation with the same keys.
        $container['ResourceServer'] = function ($c) {
            if($c->config['oauth2server.public_key_path'] === ""){
                $publicKeyPath = 'file://' . __DIR__ . '/../OAuth2/public.key';
            } else {
                $publicKeyPath = $c->config['oauth2server.public_key_path'];
            }
			$server = new ResourceServer(
				new AccessTokenRepository(),
				$publicKeyPath
			);
			return $server;
		    };

        $container->extend('classMapper', function ($classMapper, $c) {
            $classMapper->setClassMapping('oauth_clients', 'UserFrosting\Sprinkle\OAuth2Server\Database\Models\OauthClients');
            return $classMapper;
        });
    }
}
