<?php namespace web\session;

use com\mongodb\{Collection, Document, ObjectId};
use web\session\mongo\Session;

/**
 * Session factory connecting to a MongoDB server
 *
 * @see   https://www.mongodb.com/
 * @see   https://www.mongodb.com/docs/manual/tutorial/expire-data/
 * @test  web.session.mongdo.unittest.MongoTest
 */
class InMongoDB extends Sessions {
  private $collection;

  /** Creates a new MongoDB session */
  public function __construct(Collection $collection) {
    $this->collection= $collection;
  }

  /** @return int */
  public function gc() {
    return $this->collection->delete(['_created' => ['$lt' => time() - $this->duration]])->deleted();
  }

  /**
   * Creates a session
   *
   * @return web.session.ISession
   */
  public function create() {
    $now= time();
    $this->collection->insert($values= new Document(['_created' => $now]));

    // Clean up expired sessions while we're here.
    $this->gc();

    return new Session($this, $this->collection, $values, $now + $this->duration, true);
  }

  /**
   * Opens an existing and valid session. 
   *
   * @param  string $id
   * @return ?web.session.ISession
   */
  public function open($id) {
    $oid= new ObjectId($id);
    if ($values= $this->collection->find($oid)->first()) {

      // Check for expired sessions not already taken care by TTL...
      $timeout= $values['_created'] + $this->duration;
      if ($timeout > time()) return new Session($this, $this->collection, $values, $timeout, false);

      // ...and delete them
      $this->collection->delete($oid);
    }
    return null;
  }
}