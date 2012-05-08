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
* Neither the name of the Chill nor the names of its contributors may be used to endorse or promote products derived from this software 
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
* @link		https://github.com/dancryer/Chill
* @package	Chill
*/

namespace Chill;

/**
* ChillDoc object - Representation of a CouchDb document.
* 
* Usage:
* <code>
* $chill		= new Chill\Client('localhost', 'my_database');
* $doc			= $chill->get('8128173972d50affdb6724ecbd00d9fc');
* $doc->title	= 'Changing my doc.';
* $doc->save();
* </code>
*
* @package	Chill
*/
class Document
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
			if(isset($this->data['_id']))
			{
				$this->data = array_merge($this->data, $this->chill->put($this->_id, $this->data));
			}
			else
			{
				$this->data = array_merge($this->data, $this->chill->post($this->data));
			}
			
			return true;
		}
		catch(Chill\Exception $ex)
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
