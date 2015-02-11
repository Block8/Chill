Chill - PHP Library for CouchDb
===============================

Chill is a simple and efficient CouchDb client library for PHP. Released under the BSD 2 Clause Licence and made available via [Composer/Packagist](https://packagist.org/packages/chill/chill).

**Current Build Status:**

[![Build Status](http://phpci.block8.net/build-status/image/4?branch=master)](http://phpci.block8.net/build-status/view/4?branch=master)

Example usage
-------------

**Retrieve a single document by ID:**

    $chill = new Chill\Client('localhost', 'my_database');
    $doc = $chill->get('8128173972d50affdb6724ecbd00d9fc');
    print $doc['_id'];


**Retrieve the results of a view as Chill Document objects:**

    $chill = new Chill\Client('localhost', 'my_database');
    $docs = $chill->asDocuments()->getView('mydesign', 'myview', array('key1', 'key2'));
    foreach ($docs as $doc) {
        print $doc->_id . PHP_EOL;
    } 

**Retrieve and update a document**

    $chill = new Chill\Client('localhost', 'my_database');
    $doc = $chill->get('8128173972d50affdb6724ecbd00d9fc');
    $doc->title = 'Changing my doc.';
    $doc->save();

With thanks to
-------------- 
* [Sylvain Filteau](https://github.com/sylvainfilteau) for contributing various bug fixes.
* [Luke Plaster](https://github.com/notatestuser) for contributing support for arrays as view parameters.
* [Ryan Hughes](https://github.com/ryanhughes) for fixing a bug related to PUT requests.
