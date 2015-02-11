<?php
/**
 * Chill - CouchDb Client Library
 *
 * Copyright (c) 2013, Dan Cryer
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * Redistributions of source code must retain the above copyright notice, this list
 * of conditions and the following disclaimer.
 * Redistributions in binary form must reproduce the above copyright notice, this
 * list of conditions and the following disclaimer in the documentation and/or other
 * materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT,
 * INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF
 * LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE
 * OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
 * OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author   Dan Cryer <dan@dancryer.com>
 * @link     https://github.com/dancryer/Chill
 * @package  Chill
 */

namespace Chill;

/**
 * Chill - CouchDb Client Library
 *
 * Usage - Get one document as array:
 * <code>
 * $chill    = new Chill\Client('localhost', 'my_database');
 * $doc        = $chill->get('8128173972d50affdb6724ecbd00d9fc');
 * print $doc['_id'];
 * </code>
 *
 * Usage - Get view results as Chill Document objects:
 * <code>
 * $chill    = new Chill\Client('localhost', 'my_database');
 * $docs        = $chill->asDocuments()->getView('mydesign', 'myview', array('key1', 'key2'));
 * foreach($docs as $doc)
 * {
 *    print $doc->_id . PHP_EOL;
 * }
 * </code>
 *
 * @package    Chill
 */
class Client
{
    /**
     * @var string Database URL
     * @see Chill\Client::__construct()
     */
    protected $url = null;

    /**
     * @var array Object cache
     * @see Chill\Client::getCache()
     * @see Chill\Client::setCache()
     */
    protected $cache = array();

    /**
     * @var bool Get documents as arrays (false) or Chill Document objects (true)
     * @see Chill\Client::asDocuments()
     * @see Chill\Client::toDocument()
     * @see Chill\Client::toDocuments()
     */
    protected $asDocs = false;

    /**
     * Constructor - Create a new Chill object.
     *
     * @param string $host Hostname of the CouchDb server.
     * @param string $database Database name.
     * @param integer $port Port number.
     * @param string $scheme http or https.
     */
    public function __construct($host, $database, $port = 5984, $scheme = 'http')
    {
        $this->url = $scheme . '://' . $host . ':' . $port . '/' . $database . '/';
    }

    /**
     * Get the results of a CouchDb view as an array of arrays, or Chill Document objects.
     *
     * @param string $design Design name.
     * @param string $view View name.
     * @param mixed $key (Optional) String key, or array of keys, to pass to the view.
     * @param array $params (Optional) Array of query string parameters to pass to the view.
     * @return \Chill\Document[]|array
     */
    public function getView($design, $view, $key = null, $params = array())
    {
        $query = $this->processViewParameters($params);

        $url = '_design/' . $design . '/_view/' . $view . '?' . implode('&', $query);

        if (is_array($key)) {
            $rtn = $this->getViewByPost($url, $key);
        } else {
            if (!is_null($key)) {
                if (is_string($key)) {
                    $key = '"' . $key . '"';
                }

                if (is_bool($key)) {
                    $key = $key ? 'true' : 'false';
                }

                $url .= '&key=' . $key;
            }

            $rtn = $this->getViewByGet($url);
        }

        return $rtn;
    }

    /**
     * Sub-function of getView() - Makes requests by GET.
     *
     * @param string $url Full URL of the view, including parameters.
     * @return \Chill\Document[]|array
     */
    protected function getViewByGet($url)
    {
        $response = $this->getCache($url);

        if (!$response) {
            list($status, $response) = $this->sendRequest($url);

            if ($status == 200) {
                $response = $this->setCache($url, $response);
            } else {
                $response = array();
            }
        }

        return $this->asDocs ? $this->toDocuments($response) : $response;
    }

    /**
     * Sub-function of getView() - Makes requests by POST.
     *
     * @param string $url Full URL of the view, including parameters.
     * @param array $keys Array of acceptable keys.
     * @see Chill\Client::getView()
     * @return \Chill\Document[]|array
     * @throws \Chill\Exception\Response
     */
    protected function getViewByPost($url, array $keys)
    {
        $context = array('http' => array());

        $context['http']['method'] = 'POST';
        $context['http']['header'] = 'Content-Type: application/json';
        $context['http']['content'] = json_encode(array('keys' => $keys));

        list($status, $response) = $this->sendRequest($url, $context);

        if ($status != 200) {
            throw new Exception\Response('POST View - Unknown response status.');
        }

        return $this->asDocs ? $this->toDocuments($response) : $response;
    }

    /**
     * Get all documents in the database.
     *
     * @see Chill\Client::getViewByGet()
     */
    public function getAllDocuments()
    {
        $response = $this->getViewByGet('_all_docs');
        return $this->asDocs ? $this->toDocuments($response) : $response;
    }

    /**
     * Get document by ID, optionally pull from cache if previously queried.
     *
     * @param string $documentId Document ID.
     * @param bool $cache Whether or not to use the cache.
     * @link http://wiki.apache.org/couchdb/HTTP_Document_API#GET
     * @return \Chill\Document[]|array
     */
    public function get($documentId, $cache = true)
    {
        $rtn = $this->getCache($documentId);

        if (!$cache || !$rtn) {
            list($status, $doc) = $this->sendRequest(urlencode($documentId));

            if ($status == 200) {
                $rtn = $this->setCache($documentId, $doc);
            } else {
                $rtn = null;
            }
        }

        return $this->asDocs ? $this->toDocument($rtn) : $rtn;
    }

    /**
     * Update or create a document by ID.
     * CouchDb recommends using PUT rather than POST where possible to avoid proxy issues.
     *
     * @param string $documentId ID to update or create.
     * @param array $doc Document to store.
     * @link http://wiki.apache.org/couchdb/HTTP_Document_API#PUT
     * @throws \Chill\Exception\Conflict
     * @throws \Chill\Exception\Response
     * @return array
     */
    public function put($documentId, array $doc)
    {
        $context = array('http' => array());

        $context['http']['method'] = 'PUT';
        $context['http']['header'] = 'Content-Type: application/json';
        $context['http']['content'] = json_encode($doc);

        $rev = isset($doc['_rev']) ? '?rev=' . $doc['_rev'] : '';
        list($status, $response) = $this->sendRequest(urlencode($documentId) . $rev, $context);

        if ($status == 409) {
            throw new Exception\Conflict('PUT /' . $documentId . ' failed.');
        } elseif ($status != 201) {
            throw new Exception\Response('PUT /' . $documentId . ' - Unknown response status.');
        }

        if (isset($response['id'])) {
            return array('_id' => $response['id'], '_rev' => $response['rev']);
        } else {
            return $response;
        }
    }

    /**
     * Create a new document by POST.
     * CouchDb recommends using PUT rather than POST where possible to avoid proxy issues.
     *
     * @param array $doc Document to store.
     * @link http://wiki.apache.org/couchdb/HTTP_Document_API#POST
     * @throws \Chill\Exception\Response
     * @return array
     */
    public function post(array $doc)
    {
        $context = array('http' => array());

        $context['http']['method'] = 'POST';
        $context['http']['header'] = 'Content-Type: application/json';
        $context['http']['content'] = json_encode($doc);

        list($status, $response) = $this->sendRequest('', $context);

        if ($status != 201) {
            throw new Exception\Response('POST - Unknown response status.');
        }

        if (isset($response['id'])) {
            return array('_id' => $response['id'], '_rev' => $response['rev']);
        } else {
            return $response;
        }
    }

    /**
     * Delete document by ID.
     *
     * @param string $documentId Document ID.
     * @param string $rev Document revision ID.
     * @link http://wiki.apache.org/couchdb/HTTP_Document_API#DELETE
     * @throws \Chill\Exception\Response
     * @return bool|void
     */
    public function delete($documentId, $rev)
    {
        $context = array('http' => array());
        $context['http']['method'] = 'DELETE';

        list($status, $response) = $this->sendRequest($documentId . '?rev=' . $rev, $context);
        unset($response);

        if ($status != 200) {
            throw new Exception\Response('DELETE - Unknown response status.');
        }

        if ($this->getCache($documentId)) {
            unset($this->cache[$documentId]);
        }

        return true;
    }

    /**
     * Get a document from this class' internal cache.
     *
     * @param string $documentId ID to get from cache.
     * @return \Chill\Document[]|array|null
     */
    protected function getCache($documentId)
    {
        if (isset($this->cache[$documentId])) {
            return $this->cache[$documentId];
        }

        return null;
    }

    /**
     * Put a document into this class' internal cache.
     *
     * @param string $documentId ID to get from cache.
     * @param mixed $value Object to store.
     * @return \Chill\Document[]|array
     */
    protected function setCache($documentId, $value)
    {
        $this->cache[$documentId] = $value;

        return $this->cache[$documentId];
    }

    /**
     * Define whether or not to convert documents to Chill Documents on return.
     *
     * @param bool $docs Convert, or not?
     * @return Chill\Document    This class. (Chainable)
     */
    public function asDocuments($docs = true)
    {
        $this->asDocs = $docs;
        return $this;
    }

    /**
     * Convert one CouchDb document result to a Chill Document object.
     *
     * @param array $doc Document to convert.
     * @return \Chill\Document
     */
    protected function toDocument($doc = array())
    {
        if ($doc && isset($doc['_id'])) {
            // Single document:
            return new Document($this, $doc);
        } else {
            return null;
        }
    }

    /**
     * Convert many CouchDb documents to Chill Document objects.
     *
     * @param array $docs Documents to convert.
     * @return \Chill\Document[]
     */
    protected function toDocuments(array $docs = array())
    {
        if (isset($docs['rows'])) {
            $rtn = array();

            foreach ($docs['rows'] as $row) {
                $rtn[] = $this->toDocument($row['value']);
            }

            return $rtn;
        } else {
            return array();
        }
    }

    /**
     * Send request to CouchDb server. Currently uses file_get_contents() to make requests.
     *
     * @param string $uri Request URI, e.g. _design/mydesign/_view/myview?key="test"
     * @param array @context    Array of stream_context_create options.
     * @throws \Chill\Exception\Connection
     * @return array
     */
    protected function sendRequest($uri, array $context = array())
    {
        $context['http']['timeout'] = 5;
        $context['http']['ignore_errors'] = true;
        $context['http']['user_agent'] = 'Simple Couch/1.0';

        $context = stream_context_create($context);
        $response = @file_get_contents($this->url . $uri, false, $context);

        if ($response === false) {
            throw new Exception\Connection('Could not connect to CouchDb server.');
        }

        $statusParts = explode(' ', $http_response_header[0]);

        return array((int)$statusParts[1], json_decode($response, true));
    }

    /**
     * @param array $params
     * @return string[]
     */
    protected function processViewParameters(array $params)
    {
        $query = array();

        foreach ($params as $k => $v) {
            if (is_string($v)) {
                $v = '"' . $v . '"';
            }

            if (is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }

            if (is_array($v)) {
                $v = json_encode($v);
            }

            $query[] = $k . '=' . urlencode($v);
        }

        return $query;
    }
}
