<?php
/**
* Chill - CouchDb Client Library
*
* Copyright (c) 2011, Dan Cryer
* All rights reserved.
*
* Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
*
* Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
* Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer 
* in the documentation and/or other materials provided with the distribution.
* Neither the name of the <ORGANIZATION> nor the names of its contributors may be used to endorse or promote products derived from this software 
* without specific prior written permission.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, 
* THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR 
* CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, 
* PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY,
* WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF
* ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*
* @author	Dan Cryer <dan@dancryer.com>
* @link		http://www.dancryer.com/
* @package	Chill
*/

/**
* Chill - CouchDb Client Library
* 
* Usage - Get one document as array:
* <code>
* $chill	= new Chill('localhost', 'my_database');
* $doc		= $chill->get('8128173972d50affdb6724ecbd00d9fc');
* print $doc['_id'];
* </code>
* 
* Usage - Get view results as ChillDoc objects:
* <code>
* $chill	= new Chill('localhost', 'my_database');
* $docs		= $chill->asDocuments()->getView('mydesign', 'myview', array('key1', 'key2'));
* foreach($docs as $doc)
* {
* 	print $doc->_id . PHP_EOL;
* }
* </code>
*
* @package	Chill
*/
class Chill
{
	/**
	* @var string	Database URL
	* @see Chill::__construct()
	*/
	protected $url		= null;
	
	/**
	* @var array	Object cache
	* @see Chill::getCache()
	* @see Chill::setCache()
	*/
	protected $cache	= array();
	
	/**
	* @var bool		Get documents as arrays (false) or ChillDoc objects (true)
	* @see Chill::asDocuments()
	* @see Chill::toDocument()
	* @see Chill::toDocuments()
	*/
	protected $asDocs	= false;
	
	/**
	* Constructor - Create a new Chill object. 
	*
	* @param string		$host	Hostname of the CouchDb server.
	* @param string		$db		Database name.
	* @param integer	$port	Port number.
	* @param string		$scheme	http or https.
	*/
	public function __construct($host, $db, $port = 5984, $scheme = 'http')
	{
		$this->url = $scheme . '://' . $host . ':' . $port . '/' . $db . '/';
	}
	
	/**
	* Get the results of a CouchDb view as an array of arrays, or ChillDoc objects.
	* 
	* @param string	$design	Design name.
	* @param string	$view	View name.
	* @param mixed	$key	(Optional) String key, or array of keys, to pass to the view.
	* @param array	$params	(Optional) Array of query string parameters to pass to the view.
	*/
	public function getView($design, $view, $key = null, $params = array())
	{
		$query = array();
		
		foreach($params as $k => $v)
		{
			$v = is_string($v) ? '"'.$v.'"' : $v;
			$v = is_bool($v) ? $v ? 'true' : 'false' : $v;
			$query[] = $k . '=' . $v;
		}
		
		$url = '_design/'.$design.'/_view/'.$view.'?'.implode('&', $query);
		
		if(is_array($key))
		{
			$rtn = $this->getViewByPost($url, $key);
		}
		else
		{
			if(!is_null($key))
			{
				$key = is_string($key) ? '"'.$key.'"' : $key;
				$key = is_bool($key) ? $key ? 'true' : 'false' : $key;
				
				$url .= '&key='.$key;
			}
									
			$rtn = $this->getViewByGet($url);
		}
		
		return $rtn;
	}
	
	/**
	* Sub-function of getView() - Makes requests by GET.
	*
	* @param string	$url	Full URL of the view, including parameters.
	*/
	protected function getViewByGet($url)
	{
		$rtn = $this->getCache($url);
		
		if(!$rtn)
		{
			list($status, $response) = $this->sendRequest($url);
									
			if($status == 200)
			{
				$response = $this->setCache($url, $response);
			}
			else
			{
				$response = array();
			}
		}
		
		return $this->asDocs ? $this->toDocuments($response) : $response;
	}
	
	/**
	* Sub-function of getView() - Makes requests by POST.
	*
	* @param string	$url	Full URL of the view, including parameters.
	* @param array	$keys	Array of acceptable keys.
	* @see Chill::getView()
	*/
	protected function getViewByPost($url, array $keys)
	{
		$context = array('http' => array());
		
		$context['http']['method']	= 'POST';
		$context['http']['header']	= 'Content-Type: application/json';
		$context['http']['content']	= json_encode(array('keys' => $keys));
		
		list($status, $response) = $this->sendRequest($url, $context);
				
		if($status != 200)
		{
			throw new Chill_Response_Exception('POST View - Unknown response status.');
		}
		
		return $this->asDocs ? $this->toDocuments($response) : $response;
	}
	
	/**
	* Get all documents in the database.
	* 
	* @see Chill::getViewByGet()
	*/
	public function getAllDocuments()
	{
		$response = $this->getViewByGet('_all_docs');
		return $this->asDocs ? $this->toDocuments($response) : $response;
	}
		
	/**
	* Get document by ID, optionally pull from cache if previously queried.
	* 
	* @param string	$id		Document ID.
	* @param bool	$cache	Whether or not to use the cache.
	* @link http://wiki.apache.org/couchdb/HTTP_Document_API#GET
	*/
	public function get($id, $cache = true)
	{
		$rtn = $this->getCache($id);
		
		if(!$cache || !$rtn)
		{
			list($status, $doc) = $this->sendRequest($id);
						
			if($status == 200)
			{
				$rtn = $this->setCache($id, $doc);
			}
			else
			{
				$rtn = null;
			}
		}
		
		return $this->asDocs ? $this->toDocument($rtn) : $rtn;
	}
	
	/**
	* Update or create a document by ID. CouchDb recommends using PUT rather than POST where possible to avoid proxy issues.
	*
	* @param string	$id		ID to update or create.
	* @param array	$doc	Document to store.
	* @link http://wiki.apache.org/couchdb/HTTP_Document_API#PUT
	*/
	public function put($id, array $doc)
	{
		$context = array('http' => array());
		
		$context['http']['method']	= 'PUT';
		$context['http']['header']	= 'Content-Type: application/json';
		$context['http']['content']	= json_encode($doc);
		
		list($status, $response) = $this->sendRequest($id . '?rev=' . $doc['_rev'], $context);
		
		if($status == 409)
		{
			throw new Chill_Conflict_Exception('PUT /' . $id . ' failed.');
		}
		elseif($status != 201)
		{
			throw new Chill_Response_Exception('PUT /' . $id . ' - Unknown response status.');
		}
				
		if(isset($response['id']))
		{
			return array('_id' => $response['id'], '_rev' => $response['rev']);
		}
		else
		{
			return $response;
		}
	}
	
	/**
	* Create a new document by POST. CouchDb recommends using PUT rather than POST where possible to avoid proxy issues.
	*
	* @param array	$doc	Document to store.
	* @link http://wiki.apache.org/couchdb/HTTP_Document_API#POST
	*/
	public function post(array $doc)
	{
		$context = array('http' => array());
		
		$context['http']['method']	= 'POST';
		$context['http']['header']	= 'Content-Type: application/json';
		$context['http']['content']	= json_encode($doc);
		
		list($status, $response) = $this->sendRequest('', $context);
		
		if($status != 201)
		{
			throw new Chill_Response_Exception('POST - Unknown response status.');
		}
				
		if(isset($response['id']))
		{
			return array('_id' => $response['id'], '_rev' => $response['rev']);
		}
		else
		{
			return $response;
		}
	}
	
	/**
	* Delete document by ID.
	* 
	* @param string	$id		Document ID.
	* @param string	$rev	Document revision ID.
	* @link http://wiki.apache.org/couchdb/HTTP_Document_API#DELETE
	*/
	public function delete($id, $rev)
	{
		$context = array('http' => array());
		$context['http']['method']	= 'DELETE';	
			
		list($status, $response) = $this->sendRequest($id . '?rev=' . $rev, $context);
				
		if($status != 200)
		{
			throw new Chill_Response_Exception('DELETE - Unknown response status.');
		}
		
		if($this->getCache($id))
		{
			unset($this->cache[$id]);
		}
		
		return true;
	}
	
	/**
	* Get a document from this class' internal cache.
	* 
	* @param string	$id	ID to get from cache.
	*/
	protected function getCache($id)
	{
		if(isset($this->cache[$id]))
		{
			return $this->cache[$id];
		}
		
		return null;
	}
	
	/**
	* Put a document into this class' internal cache.
	* 
	* @param string	$id		ID to get from cache.
	* @param mixed	$value	Object to store.
	*/
	protected function setCache($id, $value)
	{
		$this->cache[$id] = $value;
		
		return $this->cache[$id];
	}
	
	/**
	* Define whether or not to convert documents to ChillDocs on return.
	* 
	* @param bool	$docs	Convert, or not?
	* @return ChillDoc	This class. (Chainable)
	*/
	public function asDocuments($docs = true)
	{
		$this->asDocs = $docs;
		return $this;
	}
	
	/**
	* Convert one CouchDb document result to a ChillDoc object.
	*
	* @param array $doc	Document to convert.
	*/
	protected function toDocument($doc = array())
	{
		if($doc && isset($doc['_id']))
		{
			// Single document:
			return new ChillDoc($this, $doc);
		}
		else
		{
			return null;
		}
	}
	
	/**
	* Convert many CouchDb documents to ChillDoc objects.
	* 
	* @param array $docs	Documents to convert.
	*/
	protected function toDocuments(array $docs = array())
	{		
		if(isset($docs['rows']))
		{
			$rtn = array();
			
			foreach($docs['rows'] as $row)
			{
				$rtn[] = $this->toDocument($row['value']);
			}
			
			return $rtn;
		}
		else
		{
			return array();
		}
	}
	
	/**
	* Send request to ChillDoc server. Currently uses file_get_contents() to make requests.
	*
	* @param string	$uri		Request URI, e.g. _design/mydesign/_view/myview?key="test"
	* @param array	@context	Array of stream_context_create options.
	*/
	protected function sendRequest($uri, array $context = array())
	{
		$context['http']['timeout']			= 5;
		$context['http']['ignore_errors']	= true;
		$context['http']['user_agent']		= 'Simple Couch/1.0';
				
		$context		= stream_context_create($context); 
		$response		= @file_get_contents($this->url . $uri, false, $context);
		
		if($response === false)
		{
			throw new Chill_Connection_Exception('Could not connect to CouchDb server.');
		}
		
		$statusParts	= explode(' ', $http_response_header[0]);
		
		return array((int)$statusParts[1], json_decode($response, true));
	}
}

/**
* ChillDoc object - Representation of a CouchDb document.
* 
* Usage:
* <code>
* $chill		= new Chill('localhost', 'my_database');
* $doc			= $chill->get('8128173972d50affdb6724ecbd00d9fc');
* $doc->title	= 'Changing my doc.';
* $doc->save();
* </code>
*
* @package	Chill
*/
class ChillDoc
{
	/**
	* @var array Document data from CouchDb.
	*/
	protected $data		= array();
	
	/**
	* @var Chill Chill class, for interacting with CouchDb.
	*/
	protected $chill	= null;
	
	/**
	* Constructor - Create a new document, or load an existing one from data.
	*
	* @param Chill $chill Chill class.
	* @param array $doc (Optional) Document data.
	*/
	public function __construct(Chill $chill, array $doc = array())
	{		
		$this->chill	= $chill;
		$this->data		= $doc;
	}
	
	/**
	* Checks whether a key is set on this document.
	*
	* @param string $key The key.
	* @return bool
	*/
	public function __isset($key)
	{
		return isset($this->data[$key]);
	}
	
	/**
	* Get the value of a key in this document.
	* 
	* @param string $key The key.
	* @return mixed
	*/
	public function __get($key)
	{
		if(isset($this->data[$key]))
		{
			return $this->data[$key];
		}
		else
		{
			return null;
		}
	}
	
	/**
	* Set the value of a key in this document.
	* 
	* @param string $key The key.
	* @param string $value The value.
	*/
	public function __set($key, $value)
	{
		$this->data[$key] = $value;
	}
	
	/**
	* Save this document, either by updating the document that already exists, or creating. Based on presence of _id.
	* 
	* @return bool
	*/
	public function save()
	{
		try
		{			
			if($this->data['_id'])
			{
				$this->data = array_merge($this->data, $this->chill->put($this->_id, $this->data));
			}
			else
			{
				$this->data = array_merge($this->data, $this->chill->post($this->data));
			}
			
			return true;
		}
		catch(Chill_Exception $ex)
		{
			return false;
		}
	}
	
	/**
	* Get the internal data array for this object.
	* 
	* @return array
	*/
	public function getArray()
	{
		return $this->data;
	}
}

/**
* Basic Chill exception class.
*/
class Chill_Exception extends Exception {}

/**
* Chill exception thrown when Chill cannot connect to CouchDb.
*/
class Chill_Connection_Exception extends Chill_Exception {}

/**
* Chill exception thrown on save when a conflict arises.
*/
class Chill_Conflict_Exception extends Chill_Exception {}

/**
* Chill exception thrown when a non-expected and/or failure response is received.
*/
class Chill_Response_Exception extends Chill_Exception {}

// Some people might not want to use Chill / ChillDoc as their class names, so where possible, alias as CouchDb and CouchDb_Document
if(!class_exists('CouchDb') && !class_exists('CouchDb_Document'))
{
	/**
	* CouchDb name alias for people who don't want to use Chill
	*/
	class CouchDb extends Chill {}
	
	/**
	* CouchDb_Document name alias for people who don't want to use ChillDoc
	*/
	class CouchDb_Document extends ChillDoc {}
}