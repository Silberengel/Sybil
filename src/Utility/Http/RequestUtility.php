<?php

namespace Sybil\Utility\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LoggerInterface;
use Sybil\Exception\HttpException;
use Sybil\Utility\Log\LoggerFactory;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\TransferException;

/**
 * Utility class for making HTTP requests
 * 
 * This class provides utility functions for making HTTP requests,
 * including handling responses and errors.
 */
class RequestUtility
{
    private static ?LoggerInterface $logger = null;
    private const MAX_HEADER_LOG_LENGTH = 100;
    private const MAX_BODY_LOG_LENGTH = 1000;
    private const SENSITIVE_HEADERS = ['authorization', 'cookie', 'set-cookie', 'x-api-key'];

    private static function getLogger(): LoggerInterface
    {
        if (self::$logger === null) {
            self::$logger = LoggerFactory::createLogger('request_utility');
        }
        return self::$logger;
    }

    /**
     * Make an HTTP request
     *
     * @param string $method The HTTP method
     * @param string|UriInterface $uri The request URI
     * @param array $options The request options
     * @return ResponseInterface The response
     * @throws HttpException If the request fails
     */
    public static function request(string $method, $uri, array $options = []): ResponseInterface
    {
        try {
            $client = new Client();
            $sanitizedOptions = self::sanitizeOptionsForLogging($options);
            
            self::getLogger()->debug('Making HTTP request', [
                'method' => $method,
                'uri' => (string)$uri,
                'options' => $sanitizedOptions
            ]);

            $response = $client->request($method, $uri, $options);
            
            self::getLogger()->debug('HTTP request successful', [
                'method' => $method,
                'uri' => (string)$uri,
                'status_code' => $response->getStatusCode()
            ]);

            return $response;
        } catch (RequestException $e) {
            self::handleRequestException($e, $method, $uri);
        } catch (ConnectException $e) {
            self::handleConnectException($e, $method, $uri);
        } catch (TransferException $e) {
            self::handleTransferException($e, $method, $uri);
        } catch (GuzzleException $e) {
            self::handleGuzzleException($e, $method, $uri);
        }
    }

    /**
     * Get request headers
     *
     * @param RequestInterface $request The request
     * @return array The headers
     */
    public static function getHeaders(RequestInterface $request): array
    {
        $headers = [];
        foreach ($request->getHeaders() as $name => $values) {
            $headers[$name] = implode(', ', $values);
        }
        return $headers;
    }

    /**
     * Get request body
     *
     * @param RequestInterface $request The request
     * @return string The body
     */
    public static function getBody(RequestInterface $request): string
    {
        return (string)$request->getBody();
    }

    /**
     * Get response body
     *
     * @param ResponseInterface $response The response
     * @return string The body
     */
    public static function getResponseBody(ResponseInterface $response): string
    {
        return (string)$response->getBody();
    }

    /**
     * Check if response is successful
     *
     * @param ResponseInterface $response The response
     * @return bool Whether the response is successful
     */
    public static function isSuccessful(ResponseInterface $response): bool
    {
        return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
    }

    /**
     * Handle request exception
     *
     * @param RequestException $e The exception
     * @param string $method The HTTP method
     * @param string|UriInterface $uri The request URI
     * @throws HttpException
     */
    private static function handleRequestException(RequestException $e, string $method, $uri): void
    {
        $response = $e->hasResponse() ? $e->getResponse() : null;
        $statusCode = $response ? $response->getStatusCode() : null;
        
        self::getLogger()->error('HTTP request failed', [
            'method' => $method,
            'uri' => (string)$uri,
            'status_code' => $statusCode,
            'error' => $e->getMessage()
        ]);

        throw new HttpException(
            'HTTP request failed: ' . $e->getMessage(),
            $statusCode ?? 0,
            $e
        );
    }

    /**
     * Handle connect exception
     *
     * @param ConnectException $e The exception
     * @param string $method The HTTP method
     * @param string|UriInterface $uri The request URI
     * @throws HttpException
     */
    private static function handleConnectException(ConnectException $e, string $method, $uri): void
    {
        self::getLogger()->error('HTTP connection failed', [
            'method' => $method,
            'uri' => (string)$uri,
            'error' => $e->getMessage()
        ]);

        throw new HttpException(
            'HTTP connection failed: ' . $e->getMessage(),
            0,
            $e
        );
    }

    /**
     * Handle transfer exception
     *
     * @param TransferException $e The exception
     * @param string $method The HTTP method
     * @param string|UriInterface $uri The request URI
     * @throws HttpException
     */
    private static function handleTransferException(TransferException $e, string $method, $uri): void
    {
        self::getLogger()->error('HTTP transfer failed', [
            'method' => $method,
            'uri' => (string)$uri,
            'error' => $e->getMessage()
        ]);

        throw new HttpException(
            'HTTP transfer failed: ' . $e->getMessage(),
            0,
            $e
        );
    }

    /**
     * Handle Guzzle exception
     *
     * @param GuzzleException $e The exception
     * @param string $method The HTTP method
     * @param string|UriInterface $uri The request URI
     * @throws HttpException
     */
    private static function handleGuzzleException(GuzzleException $e, string $method, $uri): void
    {
        self::getLogger()->error('HTTP request failed', [
            'method' => $method,
            'uri' => (string)$uri,
            'error' => $e->getMessage()
        ]);

        throw new HttpException(
            'HTTP request failed: ' . $e->getMessage(),
            0,
            $e
        );
    }

    /**
     * Sanitize options for logging
     *
     * @param array $options The options to sanitize
     * @return array The sanitized options
     */
    private static function sanitizeOptionsForLogging(array $options): array
    {
        $sanitized = $options;

        // Sanitize headers
        if (isset($sanitized['headers'])) {
            foreach ($sanitized['headers'] as $name => $value) {
                if (in_array(strtolower($name), self::SENSITIVE_HEADERS)) {
                    $sanitized['headers'][$name] = '***';
                }
            }
        }

        // Sanitize body
        if (isset($sanitized['body'])) {
            if (is_string($sanitized['body'])) {
                $sanitized['body'] = self::sanitizeStringForLogging($sanitized['body']);
            } elseif (is_array($sanitized['body'])) {
                $sanitized['body'] = '***';
            }
        }

        // Sanitize form_params
        if (isset($sanitized['form_params'])) {
            $sanitized['form_params'] = '***';
        }

        // Sanitize multipart
        if (isset($sanitized['multipart'])) {
            $sanitized['multipart'] = '***';
        }

        return $sanitized;
    }

    /**
     * Sanitize string for logging
     *
     * @param string $string The string to sanitize
     * @return string The sanitized string
     */
    private static function sanitizeStringForLogging(string $string): string
    {
        if (strlen($string) > self::MAX_BODY_LOG_LENGTH) {
            return substr($string, 0, self::MAX_BODY_LOG_LENGTH) . '...';
        }
        return $string;
    }
} 