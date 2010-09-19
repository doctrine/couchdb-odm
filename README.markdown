# CouchDB ODM

# Current Status:

* find() by id implemented
* metadata mapping needs to be registered manually (no annotation, xml, yml support yet)
* proxy classes implemented
* relation view defined
* still missing logic for lazy loading
* check out the tests for how to use everything
* consider moving the HTTP classes out of the CouchDB namespace
* plenty of TODO's noted through out the source code

# Use cases

* load document (done)
* insert document (done)
* update document
* delete document
* lazy load related document
* annotation/xml/yml metadata definition support

# Hackaton Discussions: CouchDB + Doctrine

##Problem 1: Query API

* Views
    * including ability to define limit, sort direction, include docs
* CouchDB-Lucene hidden transparently in the Background
    * Allows a dynamic query language to be implemented
    * Depending on the search backend (query language translator)

##Problem 2: Lazy Loading

How to implement object-graph traversal transparently?

* two views required, because bi-directional relationships?
* `emit([doc.type, doc.field, doc._id], 0);` (triple)

We need some matadata to be stored in doctrine couchdb odm documents: "type", "relations"

## Problem 3: Joins

2 possibilities: embedded, with ids

"Foreign Keys":

* one-to-many: save one key-reference in each "many"-document
* many-to-many: save ids in the owning-side document
* one-to-one: maybe good use-case for embedded documents

## Problem 4: Embedded Documents Use-Case?

Value objects (Color example)

## Problem 5: Computed Values from View

* By default we dump everything that isnt mapped to a property into a "values" array
* Optionally provide a way to map these values to a key so that we can provide an associative array

## Problem 6: `@DynamicFields`

Just have mapping type "array".

    class Address
    {
        /** @var array
        public $additional = array();
    }

## Problem 7: "Eventual Migration" / Liberal Reads

MongoODM has solution for that
http://www.doctrine-project.org/projects/mongodb_odm/1.0/docs/reference/migrating-schemas/en#migrating-schemas

## Problem 8: Write/Flushing changes

* Conflict Management throws an Exception into the users face :)

## Problem 9: Attachments

Easily lazyloaded by resource handle or "transparent" proxy

## Problem 10: HTTP Client

* Should be interfaced
* Different implementations: Socket, Stream Wrapper, pecl/http

## Problem 11: Objects without "Doctrine Metadata"

* Eventual migration possibilities for this case should be possible

## Problem 12: ID Generation

* Assigned IDs (Username for the User)
* Unique Constraints
* UUID (Generate IDs upfront possible)

# Requirements

1. type of the document in each couchdb "doc"
2. Expose revision to the user!!!
3. metadata field in each "doctrine handled" document

## Struct

    {
        "_id": "asbaklsjdfksjddf",
        "__doctrine": {
            "type": "foo",
            "relations" : {
                "bar": [ "id1", "id2" ], // M:N
             }
        },
        "fieldA": "foobar"
        "embeddedA": [{...}, {...}]
    }

## Views for relation retrieval

Related objects (works for 1:1, 1:n, n:1 and n:m)

	function (doc)
	{
		if (doc.doctrine_metadata &&
			doc.doctrine_metadata.relations)
		{
			var relations = doc.doctrine_metadata.relations;
			for ( type in relations )
			{
				for ( var i = 0; i < relations[type].length; ++i )
				{
					emit([doc._id, type, relations[type][i]], {"_id": relations[type][i]} );
				}
			}
		}
	}

Reverse relations objects (works for 1:1, 1:n, n:1 and n:m)

	function (doc)
	{
		if (doc.doctrine_metadata &&
			doc.doctrine_metadata.relations)
		{
			var relations = doc.doctrine_metadata.relations;
			for ( type in relations )
			{
				for ( var i = 0; i < relations[type].length; ++i )
				{
					emit([relations[type][i], type, doc._id], {"_id": relations[type][i]} );
				}
			}
		}
	}

## Also Natural Key Support
    class User
    {
        /** @Id @Field */
        public $username;
    }
