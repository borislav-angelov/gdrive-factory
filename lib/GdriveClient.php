<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */

/**
 * GdriveClient class main file
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

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'GdriveCurl.php';

/**
 * GdriveClient Main class
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
class GdriveClient
{
    const API_URL              = 'https://www.googleapis.com/drive/v2/';

    const CHUNK_THRESHOLD_SIZE = 9863168; // 8 MB

    const CHUNK_SIZE           = 4194304; // 4 MB

    /**
     * OAuth Access Token
     *
     * @var string
     */
    protected $accessToken = null;

    public function __construct($accessToken) {
        $this->accessToken = $accessToken;
    }

    /**
     * Creates a file on Google Drive
     *
     * @param  string   $path     The Google Drive path to save the file to (UTF-8).
     * @param  resource $inStream The data to use for the file contents.
     * @param  int|null $numBytes Provide file size in bytes for more efficient upload or leave it as null
     * @return mixed
     */
    public function uploadFile($path, $inStream, $numBytes = null) {
        if ($numBytes === null || $numBytes > self::CHUNK_THRESHOLD_SIZE) {
            return $this->_uploadFileChunked($path, $inStream);
        }

        return $this->_uploadFile($path, $inStream, $numBytes);
    }

    /**
     * Upload file in chunks
     *
     * @param  string   $path     Google Drive file path
     * @param  resource $inStream File stream
     * @return mixed
     */
    protected function _uploadFileChunked($path, $inStream) {
        $params = array();

        // New chunk upload
        $api = new GdriveCurl;
        $api->setAccessToken($this->accessToken);
        $api->setBaseURL(self::API_URL);
        $api->setOption(CURLOPT_CUSTOMREQUEST, 'PUT');
        $api->setHeader('Content-Type', 'application/octet-stream');

        while ($data = fread($inStream, self::CHUNK_SIZE)) {
            // Upload chunk
            $api->setPath('/chunked_upload/?' . http_build_query($params));
            $api->setOption(CURLOPT_POSTFIELDS, $data);

            $response = $api->makeRequest();

            // Set upload ID
            if (isset($response['upload_id'])) {
                $params['upload_id'] = $response['upload_id'];
            }

            // Set data offset
            if (isset($response['offset'])) {
                $params['offset'] = $response['offset'];
            }
        }

        // Commit chunked upload
        $api = new GdriveCurl;
        $api->setAccessToken($this->accessToken);
        $api->setBaseURL(self::API_URL);
        $api->setPath("/commit_chunked_upload/auto/$path");
        $api->setOption(CURLOPT_POST, true);
        $api->setOption(CURLOPT_POSTFIELDS, $params);

        return $api->makeRequest();
    }

    /**
     * Upload file
     *
     * @param  string   $path     Google Drive file path
     * @param  resource $inStream File stream
     * @param  int      $numBytes File size
     * @return mixed
     */
    protected function _uploadFile($path, $inStream, $numBytes) {
        $api = new GdriveCurl;
        $api->setAccessToken($this->accessToken);
        $api->setBaseURL(self::API_URL);
        $api->setPath("/files_put/auto/$path");
        $api->setOption(CURLOPT_PUT, true);
        $api->setOption(CURLOPT_INFILE, $inStream);
        $api->setOption(CURLOPT_INFILESIZE, $numBytes);

        return $api->makeRequest();
    }

    /**
     * Downloads a file from Google Drive
     *
     * @param  string   $path      The path to the file on Google Drive (UTF-8).
     * @param  resource $outStream If the file exists, the file contents will be written to this stream.
     * @return mixed
     */
    public function getFile($path, $outStream) {
        $api = new GdriveCurl;
        $api->setAccessToken($this->accessToken);
        $api->setBaseURL(self::API_URL);
        $api->setPath("/files/auto/$path");
        $api->setOption(CURLOPT_WRITEFUNCTION, function($ch, $data) use ($outStream) {
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($status !== 200 && ($response = json_decode($data, true))) {
                throw new Exception($response['error'], $status);
            }

            // Write data to stream
            fwrite($outStream, $data);

            return strlen($data);
        });

        return $api->makeRequest();
    }

    /**
     * Creates a folder
     *
     * @param  string $path The Google Drive path at which to create the folder (UTF-8).
     * @return mixed
     */
    public function createFolder($path) {
        $api = new GdriveCurl;
        $api->setAccessToken($this->accessToken);
        $api->setBaseURL(self::API_URL);
        $api->setPath('/files');
        $api->setOption(CURLOPT_POST, true);
        $api->setOption(CURLOPT_POSTFIELDS, array(
            'title': $path,
            'mimeType': 'application/vnd.google-apps.folder',
        ));

        return $api->makeRequest();
    }

    /**
     * Retrieves file and folder metadata
     *
     * @param  string $path The Google Drive path at which to create the folder (UTF-8).
     * @return mixed
     */
    public function metadata($path) {
        $api = new GdriveCurl;
        $api->setAccessToken($this->accessToken);
        $api->setBaseURL(self::API_URL);
        $api->setPath("/metadata/auto/$path");

        return $api->makeRequest();
    }

    /**
     * Deletes a file or folder
     *
     * @param  string $path The Google Drive path of the file or folder to delete (UTF-8).
     * @return mixed
     */
    public function delete($path) {
        $api = new GdriveCurl;
        $api->setAccessToken($this->accessToken);
        $api->setBaseURL(self::API_URL);
        $api->setPath('/fileops/delete');
        $api->setOption(CURLOPT_POST, true);
        $api->setOption(CURLOPT_POSTFIELDS, array(
            'root' => 'auto',
            'path' => $path,
        ));

        return $api->makeRequest();
    }

}
