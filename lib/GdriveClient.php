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
 * @version   GIT: 1.2.0
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
 * @version   GIT: 1.2.0
 * @link      https://github.com/borislav-angelov/gdrive-factory/
 */
class GdriveClient
{
    const API_URL              = 'https://www.googleapis.com/drive/v2/';

    const API_UPLOAD_URL       = 'https://www.googleapis.com/upload/drive/v2/';

    const API_ACCOUNT_URL      = 'https://accounts.google.com/o/oauth2/';

    const API_TOKEN_URL        = 'https://www.servmask.com/redirect/gdrive/';

    const CHUNK_THRESHOLD_SIZE = 9863168; // 8 MB

    const CHUNK_SIZE           = 4194304; // 4 MB

    /**
     * OAuth Refresh Token
     *
     * @var string
     */
    protected $refreshToken = null;

    public function __construct($refreshToken) {
        $this->refreshToken = $refreshToken;
    }

    /**
     * Creates a file on Google Drive
     *
     * @param  array    $params   The Google Drive query params.
     * @param  resource $inStream The data to use for the file contents.
     * @param  int|null $numBytes Provide file size in bytes for more efficient upload or leave it as null.
     * @return mixed
     */
    public function uploadFile(array $params, $inStream, $numBytes = null) {
        if ($numBytes === null || $numBytes > self::CHUNK_THRESHOLD_SIZE) {
            return $this->uploadFileChunk($params, $inStream, $numBytes);
        }

        return $this->_uploadFile($params, $inStream, $numBytes);
    }

    /**
     * Upload file chunk
     *
     * @param  array    $params   The Google Drive query params.
     * @param  resource $inStream File stream.
     * @param  int      $numBytes File size.
     * @return mixed
     */
    public function uploadFileChunk(array $params, $inStream, $numBytes) {
        $api = new GdriveCurl;
        $api->setHeader('X-Upload-Content-Type', 'application/octet-stream');
        $api->setHeader('X-Upload-Content-Length', $numBytes);
        $api->setAccessToken($this->getAccessToken());
        $api->setBaseURL(self::API_UPLOAD_URL);
        $api->setPath('files/?uploadType=resumable');
        $api->setOption(CURLOPT_POST, true);
        $api->setOption(CURLOPT_POSTFIELDS, json_encode($params));
        $api->setOption(CURLOPT_HEADER, true);

        $response = array();
        if (($response = $api->makeRequest())) {
            if (isset($response['Location']) && ($uploadUrl = $response['Location'])) {

                // New chunk upload
                $upload = new GdriveCurl;
                $upload->setAccessToken($this->getAccessToken());
                $upload->setBaseURL($uploadUrl);
                $upload->setOption(CURLOPT_CUSTOMREQUEST, 'PUT');
                $upload->setHeader('Content-Type', 'application/octet-stream');

                $startBytes = 0;
                while (($data = fread($inStream, self::CHUNK_SIZE))) {
                    $chunkInBytes = strlen($data);

                    // Set end range
                    $endBytes = $startBytes + $chunkInBytes - 1;

                    // Upload chunk
                    $upload->setHeader('Content-Length', $chunkInBytes);
                    $upload->setHeader('Content-Range', "bytes $startBytes-$endBytes/$numBytes");
                    $upload->setOption(CURLOPT_POSTFIELDS, $data);
                    $upload->setOption(CURLOPT_HEADER, true);

                    $info = $upload->makeRequest();

                    // Set start range
                    if (isset($info['Range']) && ($range = explode('-', $info['Range']))) {
                        $startBytes = $range[1] + 1;
                    }
                }
            }
        }

        return $response;
    }

    /**
     * Upload file
     *
     * @param  array    $params   The Google Drive query params.
     * @param  resource $inStream File stream.
     * @param  int      $numBytes File size.
     * @return mixed
     */
    protected function _uploadFile(array $params, $inStream, $numBytes) {
        // Set boundary
        $boundary = mt_rand();

        // Set post data
        $post = null;
        $post .= "--$boundary\r\n";
        $post .= "Content-Type: application/json\r\n\r\n";
        $post .= json_encode($params) . "\r\n";
        $post .= "--$boundary\r\n";
        $post .= "Content-Type: application/octet-stream\r\n\r\n";
        $post .= fread($inStream, $numBytes) . "\r\n";
        $post .= "--$boundary--";

        // Multipart request
        $api = new GdriveCurl;
        $api->setHeader('Content-Type', "multipart/related; boundary=$boundary");
        $api->setAccessToken($this->getAccessToken());
        $api->setBaseURL(self::API_UPLOAD_URL);
        $api->setPath('files/?uploadType=multipart');
        $api->setOption(CURLOPT_POST, true);
        $api->setOption(CURLOPT_POSTFIELDS, $post);

        return $api->makeRequest();
    }

    /**
     * Downloads a file from Google Drive
     *
     * @param  string   $fileId    The Google Drive File ID.
     * @param  resource $outStream If the file exists, the file contents will be written to this stream.
     * @param  array    $params    File parameters.
     * @return mixed
     */
    public function getFile($fileId, $outStream, $params = array()) {
        $api = new GdriveCurl;
        $api->setAccessToken($this->getAccessToken());
        $api->setBaseURL(self::API_URL);
        $api->setPath("files/$fileId");

        // Make request
        $data = array();
        if (($data = $api->makeRequest())) {
            if (isset($data['downloadUrl']) && ($downloadUrl = $data['downloadUrl'])) {
                $download = new GdriveCurl;
                $download->setAccessToken($this->getAccessToken());
                $download->setBaseURL($downloadUrl);
                $download->setOption(CURLOPT_WRITEFUNCTION, function($ch, $data) use ($outStream) {
                    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    if ($status !== 200 && ($response = json_decode($data, true))) {
                        throw new Exception($response['error'], $status);
                    }

                    // Write data to stream
                    fwrite($outStream, $data);

                    return strlen($data);
                });

                // Partial download
                if (isset($params['size']) && isset($params['startBytes']) && isset($params['endBytes'])) {
                    $download->setHeader('Range', "bytes={$params['startBytes']}-{$params['endBytes']}");

                    // Next startBytes
                    if ($params['size'] < ($params['startBytes'] + self::CHUNK_SIZE)) {
                        $params['startBytes'] = $params['size'];
                    } else {
                        $params['startBytes'] = $params['endBytes'] + 1;
                    }

                    // Next endBytes
                    if ($params['size'] < ($params['endBytes'] + self::CHUNK_SIZE)) {
                        $params['endBytes'] = $params['size'];
                    } else {
                        $params['endBytes'] += self::CHUNK_SIZE;
                    }
                }

                return $download->makeRequest();
            }
        }

        return $data;
    }

    /**
     * Creates a folder
     *
     * @param  string $name The Google Drive Folder Name.
     * @return mixed
     */
    public function createFolder($name) {
        $api = new GdriveCurl;
        $api->setAccessToken($this->getAccessToken());
        $api->setBaseURL(self::API_URL);
        $api->setPath('files');
        $api->setOption(CURLOPT_POST, true);
        $api->setOption(CURLOPT_POSTFIELDS, json_encode(array(
            'title'    => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
        )));

        return $api->makeRequest();
    }

    /**
     * Retrieves file and folder metadata
     *
     * @param  array $params The Google Drive query params.
     * @return mixed
     */
    public function listFolder($params = array()) {
        $api = new GdriveCurl;
        $api->setAccessToken($this->getAccessToken());
        $api->setBaseURL(self::API_URL);
        $api->setPath('files/?' . http_build_query($params));

        return $api->makeRequest();
    }

    /**
     * Deletes a file or folder
     *
     * @param  string $fileId The Google Drive File ID.
     * @return mixed
     */
    public function delete($fileId) {
        $api = new GdriveCurl;
        $api->setAccessToken($this->getAccessToken());
        $api->setBaseURL(self::API_URL);
        $api->setPath("files/$fileId");
        $api->setOption(CURLOPT_CUSTOMREQUEST, 'DELETE');

        return $api->makeRequest();
    }

    /**
     * Get account info
     *
     * @return mixed
     */
    public function getAccountInfo() {
        $api = new GdriveCurl;
        $api->setAccessToken($this->getAccessToken());
        $api->setBaseURL(self::API_URL);
        $api->setPath('about');

        return $api->makeRequest();
    }

    /**
     * Revoke token
     *
     * @return mixed
     */
    public function revoke() {
        $api = new GdriveCurl;
        $api->setBaseURL(self::API_ACCOUNT_URL);
        $api->setPath('revoke/?' . http_build_query(array(
            'token' => $this->refreshToken,
        )));

        return $api->makeRequest();
    }

    /**
     * Get access token
     *
     * @return string
     */
    protected function getAccessToken() {
        $api = new GdriveCurl;
        $api->setBaseURL(self::API_TOKEN_URL);
        $api->setPath('refresh');
        $api->setOption(CURLOPT_POST, true);
        $api->setOption(CURLOPT_POSTFIELDS, json_encode(array(
            'token' => $this->refreshToken,
        )));

        // Make request
        if (($data = $api->makeRequest())) {
            if (isset($data['access_token']) && ($accessToken = $data['access_token'])) {
                return $accessToken;
            }
        }

    }
}
