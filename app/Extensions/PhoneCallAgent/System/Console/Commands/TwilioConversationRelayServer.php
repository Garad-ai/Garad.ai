<?php

declare(strict_types=1);

namespace App\Extensions\PhoneCallAgent\System\Console\Commands;

use App\Extensions\PhoneCallAgent\System\WebSocket\ConversationRelayHandler;
use Illuminate\Console\Command;
use Ratchet\Http\HttpServer;
use Ratchet\Http\Router;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use React\EventLoop\Loop;
use React\Socket\SocketServer;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

class TwilioConversationRelayServer extends Command
{
    protected $signature = 'phone-call-agent:relay-server {--port=8090} {--host=0.0.0.0}';

    protected $description = 'Start the Twilio ConversationRelay WebSocket server for Phone Call Agent';

    public function handle(): void
    {
        $host = (string) $this->option('host');
        $port = (int) $this->option('port');

        $this->info("Starting ConversationRelay WebSocket server on {$host}:{$port}");
        $this->line('Add the following nginx proxy rule to route wss:// traffic to this server:');
        $this->line('');
        $this->line('    location /api/phone-call-agent/ws/twilio/ {');
        $this->line('        proxy_pass http://127.0.0.1:' . $port . ';');
        $this->line('        proxy_http_version 1.1;');
        $this->line('        proxy_set_header Upgrade $http_upgrade;');
        $this->line('        proxy_set_header Connection "Upgrade";');
        $this->line('        proxy_set_header Host $host;');
        $this->line('        proxy_read_timeout 3600s;');
        $this->line('    }');
        $this->line('');

        // Build the server stack manually instead of using Ratchet\App. Ratchet\App always
        // starts a legacy Flash policy server on a hard-coded port (843 when listening on 80,
        // otherwise 8843), which collides with other services (e.g. CyberPanel's SSL admin on
        // 8843) and cannot be disabled. We only need the WebSocket route, so we wire up the
        // IoServer/HttpServer/Router stack directly.
        //
        // The route host is left empty so Ratchet does not pin the route to a single HTTP Host
        // header — requests arrive with the public/proxy domain (ngrok, nginx `Host $host`),
        // which would otherwise 404. Allowed origins are unrestricted (no OriginCheck wrapper).
        $loop = Loop::get();

        $handler = new WsServer(new ConversationRelayHandler);
        $handler->enableKeepAlive($loop);

        $routes = new RouteCollection;
        $routes->add('pca-twilio-relay', new Route(
            '/api/phone-call-agent/ws/twilio/{uuid}',
            ['_controller' => $handler],
            [],
            [],
            '',
            [],
            ['GET']
        ));

        $router = new Router(new UrlMatcher($routes, new RequestContext));
        $socket = new SocketServer($host . ':' . $port, [], $loop);

        $server = new IoServer(new HttpServer($router), $socket, $loop);
        $server->run();
    }
}
