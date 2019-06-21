<?php

namespace KuMex\SDK;

use KuMex\SDK\Http\GuzzleHttp;
use KuMex\SDK\Http\IHttp;
use KuMex\SDK\Http\Request;
use KuMex\SDK\Http\Response;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

abstract class Api
{
    /**
     * @var string SDK Version
     */
    const VERSION = '1.0.1';

    /**
     * @var string
     */
    protected static $baseUri = 'https://openapi-v2.kucoin.com';

    /**
     * @var bool
     */
    protected static $skipVerifyTls = false;

    /**
     * @var bool
     */
    protected static $debugMode = false;

    /**
     * @var string
     */
    protected static $logPath = '/tmp';

    /**
     * @var LoggerInterface $logger
     */
    protected static $logger;

    /**
     * @var int
     */
    protected static $logLevel = Logger::DEBUG;

    /**
     * @var IAuth $auth
     */
    protected $auth;

    /**
     * @var IHttp $http
     */
    protected $http;

    public function __construct(IAuth $auth = null, IHttp $http = null)
    {
        if ($http === null) {
            $http = new GuzzleHttp(['skipVerifyTls' => &self::$skipVerifyTls]);
        }
        $this->auth = $auth;
        $this->http = $http;
    }

    /**
     * @return string
     */
    public static function getBaseUri()
    {
        return static::$baseUri;
    }

    /**
     * @param string $baseUri
     */
    public static function setBaseUri($baseUri)
    {
        static::$baseUri = $baseUri;
    }

    /**
     * @return bool
     */
    public static function isSkipVerifyTls()
    {
        return static::$skipVerifyTls;
    }

    /**
     * @param bool $skipVerifyTls
     */
    public static function setSkipVerifyTls($skipVerifyTls)
    {
        static::$skipVerifyTls = $skipVerifyTls;
    }

    /**
     * @return bool
     */
    public static function isDebugMode()
    {
        return self::$debugMode;
    }

    /**
     * @param bool $debugMode
     */
    public static function setDebugMode($debugMode)
    {
        self::$debugMode = $debugMode;
    }

    /**
     * @param LoggerInterface $logger
     */
    public static function setLogger(LoggerInterface $logger)
    {
        self::$logger = $logger;
    }

    /**
     * @return Logger|LoggerInterface
     * @throws \Exception
     */
    public static function getLogger()
    {
        if (self::$logger === null) {
            self::$logger = new Logger('kumex-sdk');
            $handler = new RotatingFileHandler(static::getLogPath() . '/kumex-sdk.log', 0, static::$logLevel);
            $formatter = new LineFormatter(null, null, false, true);
            $handler->setFormatter($formatter);
            self::$logger->pushHandler($handler);
        }
        return self::$logger;
    }

    /**
     * @return string
     */
    public static function getLogPath()
    {
        return self::$logPath;
    }

    /**
     * @param string $logPath
     */
    public static function setLogPath($logPath)
    {
        self::$logPath = $logPath;
    }

    /**
     * @return int
     */
    public static function getLogLevel()
    {
        return self::$logLevel;
    }

    /**
     * @param int $logLevel
     */
    public static function setLogLevel($logLevel)
    {
        self::$logLevel = $logLevel;
    }

    /**
     * @param string $method
     * @param string $uri
     * @param array $params
     * @param array $headers
     * @param int $timeout
     * @return Response
     * @throws Exceptions\HttpException
     * @throws Exceptions\InvalidApiUriException
     */
    public function call($method, $uri, array $params = [], array $headers = [], $timeout = 30)
    {
        $request = new Request();
        $request->setMethod($method);
        $request->setBaseUri(static::getBaseUri());
        $request->setUri($uri);
        $request->setParams($params);

        if ($this->auth) {
            $authHeaders = $this->auth->getHeaders(
                $request->getMethod(),
                $request->getRequestUri(),
                $request->getBodyParams()
            );
            $headers = array_merge($headers, $authHeaders);
        }
        $headers['User-Agent'] = 'KuMex-PHP-SDK/' . static::VERSION;
        $request->setHeaders($headers);

        $requestId = uniqid();

        if (self::isDebugMode()) {
            static::getLogger()->debug(sprintf('Sent a HTTP request#%s: %s', $requestId, $request));
        }
        $response = $this->http->request($request, $timeout);
        if (self::isDebugMode()) {
            static::getLogger()->debug(sprintf('Received a HTTP response#%s: %s', $requestId, $response));
        }

        return $response;
    }
}