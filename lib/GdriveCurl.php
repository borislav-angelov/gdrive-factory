<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * GdriveCurl class minimal wrapper around a cURL handle
 *
 * PHP version 5
 *
 * LICENSE: Permission is hereby granted, free of charge, to any person obtaining
 * a copy of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
 * of the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 * FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 * COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 * IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 * CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @category  FileSystem
 * @package   GdriveFactory
 * @author    Yani Iliev <yani@iliev.me>
 * @author    Bobby Angelov <bobby@servmask.com>
 * @copyright 2014 Yani Iliev, Bobby Angelov
 * @license   https://raw.github.com/borislav-angelov/gdrive-factory/master/LICENSE The MIT License (MIT)
 * @version   GIT: 1.0.0
 * @link      https://github.com/borislav-angelov/gdrive-factory/
 */

/**
 * GdriveCurl class
 *
 * @category  FileSystem
 * @package   GdriveFactory
 * @author    Yani Iliev <yani@iliev.me>
 * @author    Bobby Angelov <bobby@servmask.com>
 * @copyright 2014 Yani Iliev, Bobby Angelov
 * @license   https://raw.github.com/borislav-angelov/gdrive-factory/master/LICENSE The MIT License (MIT)
 * @version   GIT: 1.0.0
 * @link      https://github.com/borislav-angelov/gdrive-factory/
 */

class GdriveCurl
{
    protected $baseURL = null;

    protected $path    = null;

    protected $handler = null;

    protected $options = array();

    protected $headers = array(
        'User-Agent'   => 'ServMask',
        'Content-Type' => 'application/json',
    );

    public function __construct() {
        // Check the cURL extension is loaded
        if (!extension_loaded('curl')) {
            throw new Exception('Google Drive Factory requires cURL extension');
        }

        // Default configuration
        $this->setOption(CURLOPT_RETURNTRANSFER, true);
        $this->setOption(CURLOPT_CONNECTTIMEOUT, 10);
        $this->setOption(CURLOPT_LOW_SPEED_LIMIT, 1024);
        $this->setOption(CURLOPT_LOW_SPEED_TIME, 10);

        // Enable SSL support
        $this->setOption(CURLOPT_SSL_VERIFYPEER, true);
        $this->setOption(CURLOPT_SSL_VERIFYHOST, 2);
        $this->setOption(CURLOPT_SSLVERSION, 3);
        $this->setOption(CURLOPT_CAINFO, __DIR__ . '/../certs/cacerts.pem');
        $this->setOption(CURLOPT_CAPATH, __DIR__ . '/../certs/');

        // Limit vulnerability surface area.  Supported in cURL 7.19.4+
        if (defined('CURLOPT_PROTOCOLS')) {
            $this->setOption(CURLOPT_PROTOCOLS, CURLPROTO_HTTPS);
        }

        if (defined('CURLOPT_REDIR_PROTOCOLS')) {
            $this->setOption(CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTPS);
        }
    }

    /**
     * Set access token
     *
     * @param  string      $value Resouse path
     * @return GdriveCurl
     */
    public function setAccessToken($value) {
        $this->setHeader('Authorization', "Bearer $value");
        return $this;
    }

    /**
     * Get access token
     *
     * @return string
     */
    public function getAccessToken() {
        return $this->getHeader('Authorization');
    }

    /**
     * Set cURL base URL
     *
     * @param  string      $value Base URL
     * @return GdriveCurl
     */
    public function setBaseURL($value) {
        $this->baseURL = $value;
        return $this;
    }

    /**
     * Get cURL base URL
     *
     * @return string
     */
    public function getBaseURL() {
        return $this->baseURL;
    }

    /**
     * Set cURL path
     *
     * @param  string      $value Resource path
     * @return GdriveCurl
     */
    public function setPath($value) {
        $this->path = $value;
        return $this;
    }

    /**
     * Get cURL path
     *
     * @return string
     */
    public function getPath() {
        return $this->path;
    }

    /**
     * Set cURL option
     *
     * @param  int         $name  cURL option name
     * @param  mixed       $value cURL option value
     * @return GdriveCurl
     */
    public function setOption($name, $value) {
        $this->options[$name] = $value;
        return $this;
    }

    /**
     * Get cURL option
     *
     * @param  int   $name cURL option name
     * @return mixed
     */
    public function getOption($name) {
        return $this->options[$name];
    }

    /**
     * Set cURL header
     *
     * @param  string      $name  cURL header name
     * @param  string      $value cURL header value
     * @return GdriveCurl
     */
    public function setHeader($name, $value) {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Get cURL header
     *
     * @param  string $name cURL header name
     * @return string
     */
    public function getHeader($name) {
        return $this->headers[$name];
    }

    /**
     * Make cURL request
     *
     * @return array
     */
    public function makeRequest() {
        // cURL handler
        $this->handler = curl_init($this->getBaseURL() . $this->getPath());

        // Apply cURL headers
        $httpHeaders = array();
        foreach ($this->headers as $name => $value) {
            $httpHeaders[] = "$name: $value";
        }
        $this->setOption(CURLOPT_HTTPHEADER, $httpHeaders);

        // Apply cURL options
        foreach ($this->options as $name => $value) {
            curl_setopt($this->handler, $name, $value);
        }

        // HTTP request
        $body = curl_exec($this->handler);
        if ($body === false) {
            throw new Exception('Error executing HTTP request: ' . curl_error($this->handler));
        }

        return json_decode($body, true);
    }

    /**
     * Destroy cURL handler
     *
     * @return void
     */
    public function __destruct()
    {
        if ($this->handler !== null) {
            curl_close($this->handler);
        }
    }
}
